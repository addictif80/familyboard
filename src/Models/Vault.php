<?php
namespace App\Models;

use App\Core\Database;
use App\Core\OcrHelper;

class Vault
{
    public const CATEGORIES = [
        'document' => ['label' => 'Document',      'icon' => '📄'],
        'compte'   => ['label' => 'Compte',         'icon' => '🔑'],
        'contact'  => ['label' => 'Contact utile',  'icon' => '📇'],
        'souhait'  => ['label' => 'Souhaits',       'icon' => '🕊️'],
        'autre'    => ['label' => 'Autre',          'icon' => '🗃️'],
    ];

    // ---- Entrées du coffre-fort ----

    public static function getEntries(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM vault_entries WHERE family_id=? ORDER BY category, title', [$familyId]);
    }

    public static function getEntryById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM vault_entries WHERE id=?', [$id]);
    }

    public static function createEntry(int $familyId, int $userId, array $d, ?array $file = null): int
    {
        $filePath = $fileOriginal = $fileMime = null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            [$filePath, $fileOriginal, $fileMime] = OcrHelper::saveUploadedFile($file, 'vault', $familyId);
        }
        return Database::insert(
            'INSERT INTO vault_entries (family_id, category, title, content, file_path, file_original, file_mime, created_by) VALUES (?,?,?,?,?,?,?,?)',
            [$familyId, $d['category'], $d['title'], $d['content'], $filePath, $fileOriginal, $fileMime, $userId]
        );
    }

    public static function updateEntry(int $id, int $familyId, array $d, ?array $file = null): void
    {
        $existing = self::getEntryById($id);
        $filePath = $existing['file_path'] ?? null;
        $fileOriginal = $existing['file_original'] ?? null;
        $fileMime = $existing['file_mime'] ?? null;

        if (!empty($d['remove_file'])) {
            if ($filePath) @unlink(BASE_PATH . $filePath);
            $filePath = $fileOriginal = $fileMime = null;
        }
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if ($filePath) @unlink(BASE_PATH . $filePath);
            [$filePath, $fileOriginal, $fileMime] = OcrHelper::saveUploadedFile($file, 'vault', $familyId);
        }

        Database::execute(
            'UPDATE vault_entries SET category=?, title=?, content=?, file_path=?, file_original=?, file_mime=? WHERE id=? AND family_id=?',
            [$d['category'], $d['title'], $d['content'], $filePath, $fileOriginal, $fileMime, $id, $familyId]
        );
    }

    public static function deleteEntry(int $id, int $familyId): void
    {
        $entry = self::getEntryById($id);
        if ($entry && (int)$entry['family_id'] === $familyId && $entry['file_path']) {
            @unlink(BASE_PATH . $entry['file_path']);
        }
        Database::execute('DELETE FROM vault_entries WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    // ---- Personnes de confiance ----

    public static function getTrustees(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM vault_trustees WHERE family_id=? ORDER BY created_at DESC', [$familyId]);
    }

    public static function getTrusteeById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM vault_trustees WHERE id=?', [$id]);
    }

    public static function findTrusteeByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM vault_trustees WHERE access_token=?', [$token]);
    }

    public static function createTrustee(int $familyId, int $userId, array $d): array
    {
        $token = bin2hex(random_bytes(24));
        $id = Database::insert(
            'INSERT INTO vault_trustees (family_id, name, email, relationship, notes, access_token, created_by) VALUES (?,?,?,?,?,?,?)',
            [$familyId, $d['name'], $d['email'], $d['relationship'], $d['notes'], $token, $userId]
        );
        return self::getTrusteeById($id);
    }

    public static function deleteTrustee(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM vault_trustees WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    /** Le titulaire du lien magique déclenche une demande d'accès d'urgence — reste en attente
     *  jusqu'à décision explicite d'un administrateur (jamais d'ouverture automatique). */
    public static function requestAccess(int $id): void
    {
        Database::execute(
            "UPDATE vault_trustees SET status='requested', requested_at=NOW(), decided_at=NULL, decided_by=NULL WHERE id=? AND status IN ('none','denied')",
            [$id]
        );
    }

    public static function decideAccess(int $id, int $familyId, bool $approve, int $adminUserId): void
    {
        Database::execute(
            "UPDATE vault_trustees SET status=?, decided_at=NOW(), decided_by=? WHERE id=? AND family_id=? AND status='requested'",
            [$approve ? 'approved' : 'denied', $adminUserId, $id, $familyId]
        );
    }

    /** Révoque un accès déjà approuvé (l'administrateur peut fermer la porte à tout moment). */
    public static function revokeAccess(int $id, int $familyId): void
    {
        Database::execute(
            "UPDATE vault_trustees SET status='denied', decided_at=NOW() WHERE id=? AND family_id=? AND status='approved'",
            [$id, $familyId]
        );
    }
}

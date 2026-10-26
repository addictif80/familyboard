<?php
namespace App\Models;

use App\Core\Database;
use App\Core\OcrHelper;
use ZipArchive;

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

    public static function createEntry(int $familyId, int $userId, array $d, ?array $file = null, ?array $sourceDocument = null): int
    {
        $filePath = $fileOriginal = $fileMime = null;
        if ($sourceDocument) {
            [$filePath, $fileOriginal, $fileMime] = self::copyFromDocument($sourceDocument, $familyId);
        } elseif ($file && $file['error'] === UPLOAD_ERR_OK) {
            [$filePath, $fileOriginal, $fileMime] = OcrHelper::saveUploadedFile($file, 'vault', $familyId);
        }
        return Database::insert(
            'INSERT INTO vault_entries (family_id, category, title, content, file_path, file_original, file_mime, created_by) VALUES (?,?,?,?,?,?,?,?)',
            [$familyId, $d['category'], $d['title'], $d['content'], $filePath, $fileOriginal, $fileMime, $userId]
        );
    }

    public static function updateEntry(int $id, int $familyId, array $d, ?array $file = null, ?array $sourceDocument = null): void
    {
        $existing = self::getEntryById($id);
        $filePath = $existing['file_path'] ?? null;
        $fileOriginal = $existing['file_original'] ?? null;
        $fileMime = $existing['file_mime'] ?? null;

        if (!empty($d['remove_file'])) {
            if ($filePath) @unlink(BASE_PATH . $filePath);
            $filePath = $fileOriginal = $fileMime = null;
        }
        if ($sourceDocument) {
            if ($filePath) @unlink(BASE_PATH . $filePath);
            [$filePath, $fileOriginal, $fileMime] = self::copyFromDocument($sourceDocument, $familyId);
        } elseif ($file && $file['error'] === UPLOAD_ERR_OK) {
            if ($filePath) @unlink(BASE_PATH . $filePath);
            [$filePath, $fileOriginal, $fileMime] = OcrHelper::saveUploadedFile($file, 'vault', $familyId);
        }

        Database::execute(
            'UPDATE vault_entries SET category=?, title=?, content=?, file_path=?, file_original=?, file_mime=? WHERE id=? AND family_id=?',
            [$d['category'], $d['title'], $d['content'], $filePath, $fileOriginal, $fileMime, $id, $familyId]
        );
    }

    /** Copie physique du fichier (jamais une simple référence au file_path du document source) :
     *  le coffre-fort et le module Documents suppriment chacun leurs fichiers sans savoir si une
     *  autre table les référence (voir Document::delete(), PremiumDataPurge) — partager le même
     *  chemin ferait disparaître le fichier d'un module quand l'autre le supprime. */
    private static function copyFromDocument(array $document, int $familyId): array
    {
        $srcPath = BASE_PATH . $document['file_path'];
        if (!is_file($srcPath)) {
            throw new \RuntimeException('Le fichier du document source est introuvable.');
        }
        $ext = pathinfo($document['file_path'], PATHINFO_EXTENSION);
        $destDir = BASE_PATH . '/storage/vault/' . $familyId;
        if (!is_dir($destDir)) mkdir($destDir, 0755, true);
        $filename = bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
        if (!@copy($srcPath, $destDir . '/' . $filename)) {
            throw new \RuntimeException('Impossible de copier le document dans le coffre-fort.');
        }
        return ['/storage/vault/' . $familyId . '/' . $filename, $document['file_original'], $document['file_mime']];
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

    /**
     * Archive ZIP de toutes les entrées du coffre-fort d'une famille : les pièces jointes
     * (fichiers) sous fichiers/, et le texte (titre + contenu) de chaque entrée dans un
     * recapitulatif.txt — pour que le "tout télécharger" conserve aussi les entrées sans fichier
     * (comptes, contacts, souhaits...). Retourne le chemin absolu du ZIP temporaire (à supprimer
     * après envoi par l'appelant).
     */
    public static function buildZip(int $familyId): string
    {
        $entries = self::getEntries($familyId);
        $tmpZip = tempnam(sys_get_temp_dir(), 'coffrefort_') . '.zip';
        $zip = new ZipArchive();
        $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $recap = '';
        $used = [];
        foreach ($entries as $e) {
            $label = self::CATEGORIES[$e['category']]['label'] ?? 'Autre';
            $recap .= "=== " . $e['title'] . " (" . $label . ") ===\n";
            if ($e['content']) $recap .= $e['content'] . "\n";
            if ($e['file_path']) $recap .= "Pièce jointe : " . $e['file_original'] . "\n";
            $recap .= "\n";

            if ($e['file_path']) {
                $abs = BASE_PATH . $e['file_path'];
                if (is_file($abs)) {
                    $name = self::sanitizeFilename($e['title']) . '_' . basename($abs);
                    if (isset($used[$name])) $name = uniqid() . '_' . $name;
                    $used[$name] = true;
                    $zip->addFile($abs, 'fichiers/' . $name);
                }
            }
        }

        $zip->addFromString('recapitulatif.txt', $recap !== '' ? $recap : "Le coffre-fort est vide.\n");
        $zip->close();
        return $tmpZip;
    }

    private static function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N} _-]+/u', '', $name);
        $name = trim($name);
        return $name !== '' ? mb_strimwidth($name, 0, 60, '') : 'entree';
    }
}

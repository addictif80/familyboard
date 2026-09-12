<?php
namespace App\Models;

use App\Core\Database;
use App\Core\OcrHelper;

class BirthList
{
    public static function getByFamily(int $familyId): ?array
    {
        return Database::fetch('SELECT * FROM birth_lists WHERE family_id=?', [$familyId]);
    }

    public static function findByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM birth_lists WHERE token=?', [$token]);
    }

    /** Créée à la demande (comme Family::ensureReferralCode()) — pas de liste tant que la
     *  famille n'a pas ajouté un premier article ou généré le lien. */
    public static function ensureForFamily(int $familyId, int $userId): array
    {
        $existing = self::getByFamily($familyId);
        if ($existing) return $existing;
        $token = bin2hex(random_bytes(24));
        try {
            Database::insert(
                'INSERT INTO birth_lists (family_id, token, created_by) VALUES (?,?,?)',
                [$familyId, $token, $userId]
            );
        } catch (\Throwable) {
            // Créée entre-temps par une requête concurrente (contrainte uq_family) : relit.
        }
        return self::getByFamily($familyId);
    }

    public static function regenerateToken(int $id, int $familyId): void
    {
        Database::execute('UPDATE birth_lists SET token=? WHERE id=? AND family_id=?', [bin2hex(random_bytes(24)), $id, $familyId]);
    }

    // ── Articles ────────────────────────────────────────────────

    public static function getItems(int $birthListId): array
    {
        return Database::fetchAll('SELECT * FROM birth_list_items WHERE birth_list_id=? ORDER BY created_at', [$birthListId]);
    }

    public static function getItemById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM birth_list_items WHERE id=?', [$id]);
    }

    /** Vue famille : jamais le statut de réservation, pour préserver la surprise (même principe
     *  que WishlistItem::scrubForViewer()). */
    public static function scrubForOwner(array $item): array
    {
        unset($item['reserved_by_name'], $item['reserved_by_user_id'], $item['reservation_token'], $item['reserved_at']);
        return $item;
    }

    public static function createItem(int $birthListId, int $familyId, int $userId, array $d, ?array $file = null): int
    {
        $imagePath = $d['image_path'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            [$uploadedPath] = OcrHelper::saveUploadedFile($file, 'birth-list', $familyId);
            $imagePath = $uploadedPath;
        }
        return Database::insert(
            'INSERT INTO birth_list_items (birth_list_id, title, url, image_path, price, notes, created_by) VALUES (?,?,?,?,?,?,?)',
            [$birthListId, $d['title'], $d['url'], $imagePath, $d['price'], $d['notes'], $userId]
        );
    }

    public static function updateItem(int $id, int $birthListId, int $familyId, array $d, ?array $file = null): void
    {
        $existing = self::getItemById($id);
        $imagePath = $existing['image_path'] ?? null;

        if (!empty($d['remove_image'])) {
            if ($imagePath) @unlink(BASE_PATH . $imagePath);
            $imagePath = null;
        }
        if (!empty($d['image_path'])) $imagePath = $d['image_path'];
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if ($imagePath) @unlink(BASE_PATH . $imagePath);
            [$imagePath] = OcrHelper::saveUploadedFile($file, 'birth-list', $familyId);
        }

        Database::execute(
            'UPDATE birth_list_items SET title=?, url=?, image_path=?, price=?, notes=? WHERE id=? AND birth_list_id=?',
            [$d['title'], $d['url'], $imagePath, $d['price'], $d['notes'], $id, $birthListId]
        );
    }

    public static function deleteItem(int $id, int $birthListId): void
    {
        $item = self::getItemById($id);
        if ($item && (int)$item['birth_list_id'] === $birthListId && $item['image_path']) {
            @unlink(BASE_PATH . $item['image_path']);
        }
        Database::execute('DELETE FROM birth_list_items WHERE id=? AND birth_list_id=?', [$id, $birthListId]);
    }

    // ── Réservation (accès public) ────────────────────────────────

    /** @return string|null Le jeton de réservation à renvoyer au navigateur du réservant
     *  (pour qu'il puisse annuler plus tard), ou null si l'article était déjà réservé
     *  entre-temps (concurrence — la contrainte "WHERE reserved_at IS NULL" fait foi). */
    public static function reserve(int $itemId, int $birthListId, string $name): ?string
    {
        $token = bin2hex(random_bytes(24));
        $affected = Database::execute(
            'UPDATE birth_list_items SET reserved_by_name=?, reservation_token=?, reserved_at=NOW()
             WHERE id=? AND birth_list_id=? AND reserved_at IS NULL',
            [$name, $token, $itemId, $birthListId]
        );
        return $affected > 0 ? $token : null;
    }

    public static function unreserve(int $itemId, int $birthListId, string $reservationToken): bool
    {
        $affected = Database::execute(
            'UPDATE birth_list_items SET reserved_by_name=NULL, reserved_by_user_id=NULL, reservation_token=NULL, reserved_at=NULL
             WHERE id=? AND birth_list_id=? AND reservation_token=?',
            [$itemId, $birthListId, $reservationToken]
        );
        return $affected > 0;
    }
}

<?php
namespace App\Models;

use App\Core\Database;

class KioskLink
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM kiosk_links WHERE family_id=? ORDER BY created_at DESC',
            [$familyId]
        );
    }

    public static function create(int $familyId, int $userId, string $label): array
    {
        $token = bin2hex(random_bytes(24));
        $id = Database::insert(
            'INSERT INTO kiosk_links (family_id, label, token, created_by) VALUES (?,?,?,?)',
            [$familyId, $label, $token, $userId]
        );
        return Database::fetch('SELECT * FROM kiosk_links WHERE id=?', [$id]);
    }

    public static function revoke(int $id): void
    {
        Database::execute('UPDATE kiosk_links SET revoked_at=NOW() WHERE id=?', [$id]);
    }

    public static function findValidByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM kiosk_links WHERE token=? AND revoked_at IS NULL', [$token]);
    }
}

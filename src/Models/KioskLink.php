<?php
namespace App\Models;

use App\Core\Database;

class KioskLink
{
    public static function getByFamily(int $familyId): array
    {
        $links = Database::fetchAll(
            'SELECT * FROM kiosk_links WHERE family_id=? ORDER BY created_at DESC',
            [$familyId]
        );
        foreach ($links as &$link) {
            if (!$link['short_code']) {
                $link['short_code'] = self::ensureShortCode((int)$link['id']);
            }
        }
        return $links;
    }

    public static function create(int $familyId, int $userId, string $label): array
    {
        $token = bin2hex(random_bytes(24));
        $shortCode = self::generateUniqueShortCode();
        $id = Database::insert(
            'INSERT INTO kiosk_links (family_id, label, token, short_code, created_by) VALUES (?,?,?,?,?)',
            [$familyId, $label, $token, $shortCode, $userId]
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

    public static function findValidByShortCode(string $code): ?array
    {
        return Database::fetch('SELECT * FROM kiosk_links WHERE short_code=? AND revoked_at IS NULL', [$code]);
    }

    /** Attribue un code court à un lien créé avant l'ajout de cette fonctionnalité. */
    private static function ensureShortCode(int $id): string
    {
        $code = self::generateUniqueShortCode();
        Database::execute('UPDATE kiosk_links SET short_code=? WHERE id=? AND short_code IS NULL', [$code, $id]);
        $row = Database::fetch('SELECT short_code FROM kiosk_links WHERE id=?', [$id]);
        return $row['short_code'] ?? $code;
    }

    private static function generateUniqueShortCode(): string
    {
        do {
            $code = (string)random_int(100000, 999999);
        } while (Database::fetch('SELECT id FROM kiosk_links WHERE short_code=?', [$code]));
        return $code;
    }
}

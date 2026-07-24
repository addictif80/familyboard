<?php
namespace App\Models;

use App\Core\Database;

class SitterLink
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM sitter_links WHERE family_id=? ORDER BY created_at DESC',
            [$familyId]
        );
    }

    public static function create(int $familyId, int $userId, string $label, string $expiresAt): array
    {
        $token = bin2hex(random_bytes(24));
        $id = Database::insert(
            'INSERT INTO sitter_links (family_id, label, token, created_by, expires_at) VALUES (?,?,?,?,?)',
            [$familyId, $label, $token, $userId, $expiresAt]
        );
        return Database::fetch('SELECT * FROM sitter_links WHERE id=?', [$id]);
    }

    public static function revoke(int $id): void
    {
        Database::execute('UPDATE sitter_links SET revoked_at=NOW() WHERE id=?', [$id]);
    }

    /** A still-valid (not expired, not revoked) link, or null. */
    public static function findValidByToken(string $token): ?array
    {
        return Database::fetch(
            'SELECT * FROM sitter_links WHERE token=? AND revoked_at IS NULL AND expires_at > NOW()',
            [$token]
        );
    }

    /** The most recent currently-active sitter link for a family, or null. */
    public static function getActiveForFamily(int $familyId): ?array
    {
        return Database::fetch(
            'SELECT * FROM sitter_links WHERE family_id=? AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1',
            [$familyId]
        );
    }
}

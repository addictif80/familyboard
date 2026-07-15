<?php
namespace App\Models;

use App\Core\Database;

class ImpersonationLog
{
    public static function create(int $targetUserId, int $targetFamilyId, string $adminUsername, ?string $ip): int
    {
        return Database::insert(
            'INSERT INTO impersonation_log (target_user_id, target_family_id, admin_username, ip) VALUES (?,?,?,?)',
            [$targetUserId, $targetFamilyId, $adminUsername, $ip]
        );
    }

    public static function end(int $id): void
    {
        Database::execute('UPDATE impersonation_log SET ended_at=NOW() WHERE id=? AND ended_at IS NULL', [$id]);
    }

    public static function getRecent(int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT l.*, u.name as target_name, u.email as target_email
             FROM impersonation_log l
             JOIN users u ON u.id = l.target_user_id
             ORDER BY l.started_at DESC
             LIMIT ?',
            [$limit]
        );
    }
}

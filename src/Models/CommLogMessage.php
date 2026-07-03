<?php
namespace App\Models;

use App\Core\Database;

/**
 * Journal de communication parental : messages volontairement immuables —
 * pas de méthode update()/delete() par conception.
 */
class CommLogMessage
{
    public static function create(int $familyId, int $userId, string $content): int
    {
        return Database::insert(
            'INSERT INTO comm_log_messages (family_id, user_id, content) VALUES (?,?,?)',
            [$familyId, $userId, $content]
        );
    }

    public static function getByFamily(int $familyId, int $limit = 200): array
    {
        return Database::fetchAll(
            'SELECT m.*, u.name as user_name, u.color as user_color, u.avatar as user_avatar
             FROM comm_log_messages m
             JOIN users u ON u.id = m.user_id
             WHERE m.family_id=?
             ORDER BY m.created_at ASC
             LIMIT ?',
            [$familyId, $limit]
        );
    }

    public static function getNew(int $familyId, int $afterId): array
    {
        return Database::fetchAll(
            'SELECT m.*, u.name as user_name, u.color as user_color, u.avatar as user_avatar
             FROM comm_log_messages m
             JOIN users u ON u.id = m.user_id
             WHERE m.family_id=? AND m.id > ?
             ORDER BY m.created_at ASC',
            [$familyId, $afterId]
        );
    }

    public static function getLastId(int $familyId): int
    {
        $row = Database::fetch('SELECT MAX(id) as id FROM comm_log_messages WHERE family_id=?', [$familyId]);
        return (int)($row['id'] ?? 0);
    }

    /** Mark every message not authored by $userId as read by them. */
    public static function markAllRead(int $familyId, int $userId): void
    {
        Database::execute(
            'INSERT IGNORE INTO comm_log_reads (message_id, user_id)
             SELECT m.id, ? FROM comm_log_messages m WHERE m.family_id=? AND m.user_id != ?',
            [$userId, $familyId, $userId]
        );
    }

    /** [message_id => [ ['user_name'=>..., 'read_at'=>...], ... ]] */
    public static function getReadsByFamily(int $familyId): array
    {
        $rows = Database::fetchAll(
            'SELECT r.message_id, r.read_at, u.name as user_name
             FROM comm_log_reads r
             JOIN comm_log_messages m ON m.id = r.message_id
             JOIN users u ON u.id = r.user_id
             WHERE m.family_id=?',
            [$familyId]
        );
        $byMessage = [];
        foreach ($rows as $row) {
            $byMessage[$row['message_id']][] = ['user_name' => $row['user_name'], 'read_at' => $row['read_at']];
        }
        return $byMessage;
    }
}

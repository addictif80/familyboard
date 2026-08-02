<?php
namespace App\Models;

use App\Core\Database;

/** Messagerie privée 1-à-1, réservée aux paires de membres qui se suivent mutuellement (voir
 *  Follow::isMutual) — cette règle est appliquée par le contrôleur, jamais ici. */
class DirectMessage
{
    private static function pairIds(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    public static function getOrCreateThread(int $userA, int $userB): int
    {
        [$a, $b] = self::pairIds($userA, $userB);
        $existing = Database::fetch('SELECT id FROM dm_threads WHERE user_a_id=? AND user_b_id=?', [$a, $b]);
        if ($existing) return (int)$existing['id'];
        return Database::insert('INSERT INTO dm_threads (user_a_id, user_b_id) VALUES (?,?)', [$a, $b]);
    }

    public static function findThread(int $userA, int $userB): ?array
    {
        [$a, $b] = self::pairIds($userA, $userB);
        return Database::fetch('SELECT * FROM dm_threads WHERE user_a_id=? AND user_b_id=?', [$a, $b]);
    }

    public static function getThreadById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM dm_threads WHERE id=?', [$id]);
    }

    /** Threads de $userId avec aperçu du dernier message et compteur de non-lus. */
    public static function getThreadsForUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT t.*,
                    IF(t.user_a_id=?, t.user_b_id, t.user_a_id) as other_id,
                    u.name as other_name, u.avatar as other_avatar, u.color as other_color,
                    (SELECT content FROM dm_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) as last_content,
                    (SELECT created_at FROM dm_messages m WHERE m.thread_id=t.id ORDER BY m.id DESC LIMIT 1) as last_at,
                    (SELECT COUNT(*) FROM dm_messages m WHERE m.thread_id=t.id AND m.sender_id!=? AND m.is_read=0) as unread_count
             FROM dm_threads t
             JOIN users u ON u.id = IF(t.user_a_id=?, t.user_b_id, t.user_a_id)
             WHERE t.user_a_id=? OR t.user_b_id=?
             HAVING last_at IS NOT NULL
             ORDER BY last_at DESC",
            [$userId, $userId, $userId, $userId, $userId]
        );
    }

    public static function getMessages(int $threadId, int $limit = 50): array
    {
        return array_reverse(Database::fetchAll(
            'SELECT m.*, u.name as sender_name, u.avatar as sender_avatar, u.color as sender_color
             FROM dm_messages m JOIN users u ON u.id=m.sender_id
             WHERE m.thread_id=? ORDER BY m.id DESC LIMIT ?',
            [$threadId, $limit]
        ));
    }

    public static function getNew(int $threadId, int $afterId): array
    {
        return Database::fetchAll(
            'SELECT m.*, u.name as sender_name, u.avatar as sender_avatar, u.color as sender_color
             FROM dm_messages m JOIN users u ON u.id=m.sender_id
             WHERE m.thread_id=? AND m.id>? ORDER BY m.id ASC',
            [$threadId, $afterId]
        );
    }

    public static function send(int $threadId, int $senderId, string $content): int
    {
        return Database::insert('INSERT INTO dm_messages (thread_id, sender_id, content) VALUES (?,?,?)', [$threadId, $senderId, $content]);
    }

    public static function markThreadRead(int $threadId, int $readerId): void
    {
        Database::execute('UPDATE dm_messages SET is_read=1 WHERE thread_id=? AND sender_id!=? AND is_read=0', [$threadId, $readerId]);
    }

    public static function getUnreadTotal(int $userId): int
    {
        $row = Database::fetch(
            "SELECT COUNT(*) as c FROM dm_messages m
             JOIN dm_threads t ON t.id=m.thread_id
             WHERE (t.user_a_id=? OR t.user_b_id=?) AND m.sender_id!=? AND m.is_read=0",
            [$userId, $userId, $userId]
        );
        return (int)($row['c'] ?? 0);
    }
}

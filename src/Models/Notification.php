<?php
namespace App\Models;

use App\Core\Database;

class Notification
{
    public static function create(int $userId, string $type, string $title, string $message, ?string $link = null): int
    {
        $id = Database::insert(
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?,?,?,?,?)',
            [$userId, $type, $title, $message, $link]
        );
        try {
            \App\Core\Push::sendToUser($userId, $title, $message, $link);
        } catch (\Throwable $e) {
            error_log('Push notification error: ' . $e->getMessage());
        }
        return $id;
    }

    public static function getByUser(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            'SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    public static function markRead(int $id, int $userId): void
    {
        Database::execute('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?', [$id, $userId]);
    }

    public static function markAllRead(int $userId): void
    {
        Database::execute('UPDATE notifications SET is_read=1 WHERE user_id=?', [$userId]);
    }

    public static function getUnreadCount(int $userId): int
    {
        $row = Database::fetch('SELECT COUNT(*) as cnt FROM notifications WHERE user_id=? AND is_read=0', [$userId]);
        return (int)($row['cnt'] ?? 0);
    }

    public static function notifyFamily(int $familyId, int $excludeUserId, string $type, string $title, string $message, ?string $link = null): void
    {
        $users = User::getByFamily($familyId);
        foreach ($users as $user) {
            if ($user['id'] !== $excludeUserId) {
                self::create($user['id'], $type, $title, $message, $link);
            }
        }
    }

    /** Notification système envoyée par un administrateur à tous les utilisateurs, toutes familles confondues. */
    public static function broadcastToAll(string $title, string $message, ?string $link = null): int
    {
        $userIds = Database::fetchAll('SELECT id FROM users');
        foreach ($userIds as $row) {
            self::create((int)$row['id'], 'system', $title, $message, $link);
        }
        return count($userIds);
    }
}

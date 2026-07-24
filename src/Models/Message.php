<?php
namespace App\Models;

use App\Core\Database;

class Message
{
    public static function getByFamily(int $familyId, int $limit = 50, int $beforeId = 0): array
    {
        if ($beforeId > 0) {
            return Database::fetchAll(
                'SELECT m.*, u.name as user_name, u.avatar as user_avatar, u.color as user_color
                 FROM messages m JOIN users u ON u.id=m.user_id
                 WHERE m.family_id=? AND m.id < ? ORDER BY m.created_at DESC LIMIT ?',
                [$familyId, $beforeId, $limit]
            );
        }
        return Database::fetchAll(
            'SELECT m.*, u.name as user_name, u.avatar as user_avatar, u.color as user_color
             FROM messages m JOIN users u ON u.id=m.user_id
             WHERE m.family_id=? ORDER BY m.created_at DESC LIMIT ?',
            [$familyId, $limit]
        );
    }

    public static function getNew(int $familyId, int $afterId): array
    {
        return Database::fetchAll(
            'SELECT m.*, u.name as user_name, u.avatar as user_avatar, u.color as user_color
             FROM messages m JOIN users u ON u.id=m.user_id
             WHERE m.family_id=? AND m.id > ? ORDER BY m.created_at ASC',
            [$familyId, $afterId]
        );
    }

    public static function create(
        int $familyId, int $userId, string $content,
        ?string $audioPath = null, ?string $audioOriginal = null, ?string $audioMime = null, ?int $audioDuration = null
    ): int {
        return Database::insert(
            'INSERT INTO messages (family_id, user_id, content, audio_path, audio_original, audio_mime, audio_duration) VALUES (?,?,?,?,?,?,?)',
            [$familyId, $userId, $content, $audioPath, $audioOriginal, $audioMime, $audioDuration]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM messages WHERE id=?', [$id]);
    }

    public static function findById(int $id, int $familyId): ?array
    {
        return Database::fetch('SELECT * FROM messages WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    public static function getLastId(int $familyId): int
    {
        $row = Database::fetch('SELECT MAX(id) as max_id FROM messages WHERE family_id=?', [$familyId]);
        return (int)($row['max_id'] ?? 0);
    }
}

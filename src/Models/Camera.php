<?php
namespace App\Models;

use App\Core\Database;

class Camera
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT c.*, u.name as user_name FROM cameras c JOIN users u ON u.id=c.user_id
             WHERE c.family_id=? ORDER BY c.sort_order ASC, c.created_at ASC',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT c.*, p.family_id FROM cameras c JOIN families p ON p.id=c.family_id WHERE c.id=?',
            [$id]
        );
    }

    public static function create(int $familyId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO cameras (family_id, user_id, name, host, stream_url, stream_type, username, password, model, notes, sort_order)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $userId,
                $data['name'],
                $data['host'] ?? '',
                $data['stream_url'] ?? null,
                $data['stream_type'] ?? 'other',
                $data['username'] ?? null,
                $data['password'] ?? null,
                $data['model'] ?? null,
                $data['notes'] ?? null,
                (int)($data['sort_order'] ?? 0),
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE cameras SET name=?, host=?, stream_url=?, stream_type=?, username=?, password=?, model=?, notes=?, sort_order=? WHERE id=?',
            [
                $data['name'],
                $data['host'] ?? '',
                $data['stream_url'] ?? null,
                $data['stream_type'] ?? 'other',
                $data['username'] ?? null,
                $data['password'] ?? null,
                $data['model'] ?? null,
                $data['notes'] ?? null,
                (int)($data['sort_order'] ?? 0),
                $id,
            ]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM cameras WHERE id=?', [$id]);
    }
}

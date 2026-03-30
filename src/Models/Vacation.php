<?php
namespace App\Models;

use App\Core\Database;

class Vacation
{
    public static function getByFamily(int $familyId, string $start, string $end): array
    {
        return Database::fetchAll(
            'SELECT v.*, u.name AS user_name, u.color AS user_color
             FROM vacations v
             JOIN users u ON u.id = v.user_id
             WHERE v.family_id = ? AND v.end_date >= ? AND v.start_date <= ?
             ORDER BY v.start_date',
            [$familyId, $start, $end]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM vacations WHERE id = ?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO vacations (family_id, user_id, title, start_date, end_date) VALUES (?,?,?,?,?)',
            [
                $familyId,
                $userId,
                trim($data['title'] ?? '') ?: 'Congé',
                $data['start_date'],
                $data['end_date'],
            ]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM vacations WHERE id = ?', [$id]);
    }
}

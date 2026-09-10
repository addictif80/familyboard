<?php
namespace App\Models;

use App\Core\Database;

class LeaveRequest
{
    public const TYPES = [
        'conges_payes' => ['label' => 'Congés payés', 'icon' => '🏖️'],
        'rtt'          => ['label' => 'RTT',          'icon' => '🗓️'],
        'sans_solde'   => ['label' => 'Sans solde',   'icon' => '📅'],
        'autre'        => ['label' => 'Autre',        'icon' => '📌'],
    ];

    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT lr.*, u.name AS user_name, u.color AS user_color
             FROM leave_requests lr JOIN users u ON u.id=lr.user_id
             WHERE lr.family_id=? ORDER BY lr.start_date DESC',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM leave_requests WHERE id=?', [$id]);
    }

    /** Congés d'autres membres qui chevauchent la période donnée — alerte informative. */
    public static function getOverlapping(int $familyId, string $start, string $end, int $excludeUserId, ?int $excludeId = null): array
    {
        $sql = 'SELECT lr.*, u.name AS user_name
                FROM leave_requests lr JOIN users u ON u.id=lr.user_id
                WHERE lr.family_id=? AND lr.user_id!=? AND lr.start_date<=? AND lr.end_date>=?';
        $params = [$familyId, $excludeUserId, $end, $start];
        if ($excludeId) {
            $sql .= ' AND lr.id!=?';
            $params[] = $excludeId;
        }
        return Database::fetchAll($sql, $params);
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO leave_requests (family_id, user_id, leave_type, title, start_date, end_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?)',
            [$familyId, $d['user_id'], $d['leave_type'], $d['title'], $d['start_date'], $d['end_date'], $d['notes'], $userId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM leave_requests WHERE id=? AND family_id=?', [$id, $familyId]);
    }
}

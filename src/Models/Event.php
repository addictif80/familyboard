<?php
namespace App\Models;

use App\Core\Database;

class Event
{
    public static function getByFamily(int $familyId, string $start, string $end): array
    {
        return Database::fetchAll(
            'SELECT e.*, u.name as user_name, u.color as user_color FROM events e
             JOIN users u ON u.id = e.user_id
             WHERE e.family_id = ? AND e.start_datetime < ? AND e.end_datetime > ?
             ORDER BY e.start_datetime',
            [$familyId, $end, $start]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT e.*, u.name as user_name FROM events e JOIN users u ON u.id=e.user_id WHERE e.id=?', [$id]);
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO events (family_id, user_id, title, description, start_datetime, end_datetime, is_all_day, color, recurrence, recurrence_end, custody_schedule_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$data['family_id'], $data['user_id'], $data['title'], $data['description'] ?? null,
             $data['start_datetime'], $data['end_datetime'], $data['is_all_day'] ?? 0,
             $data['color'] ?? '#4A90D9', $data['recurrence'] ?? null, $data['recurrence_end'] ?? null,
             !empty($data['custody_schedule_id']) ? $data['custody_schedule_id'] : null]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE events SET title=?, description=?, start_datetime=?, end_datetime=?, is_all_day=?, color=?, recurrence=?, recurrence_end=?, custody_schedule_id=? WHERE id=?',
            [$data['title'], $data['description'] ?? null, $data['start_datetime'], $data['end_datetime'],
             $data['is_all_day'] ?? 0, $data['color'] ?? '#4A90D9', $data['recurrence'] ?? null, $data['recurrence_end'] ?? null,
             !empty($data['custody_schedule_id']) ? $data['custody_schedule_id'] : null, $id]
        );
    }

    /** Événements calendrier tagués à l'un des plannings de garde donnés (vue co-parent restreint). */
    public static function getForSchedules(array $scheduleIds, string $start, string $end): array
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        if (empty($scheduleIds)) return [];
        $ph = implode(',', array_fill(0, count($scheduleIds), '?'));
        return Database::fetchAll(
            "SELECT e.*, u.name as user_name, u.color as user_color FROM events e
             JOIN users u ON u.id = e.user_id
             WHERE e.custody_schedule_id IN ($ph) AND e.start_datetime < ? AND e.end_datetime > ?
             ORDER BY e.start_datetime",
            [...$scheduleIds, $end, $start]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM events WHERE id=?', [$id]);
    }

    public static function createFromCalDAV(int $familyId, int $userId, int $sourceId, array $data): int
    {
        return Database::insert(
            'INSERT INTO events (family_id, user_id, title, description, start_datetime, end_datetime, is_all_day, color, caldav_uid, caldav_source_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$familyId, $userId, $data['title'], $data['description'] ?? null,
             $data['start_datetime'], $data['end_datetime'], $data['is_all_day'] ?? 0,
             $data['color'] ?? '#4A90D9', $data['uid'] ?? null, $sourceId]
        );
    }

    public static function deleteBySource(int $sourceId): void
    {
        Database::execute('DELETE FROM events WHERE caldav_source_id=?', [$sourceId]);
    }

    public static function getUpcoming(int $familyId, int $days = 7): array
    {
        return Database::fetchAll(
            'SELECT e.*, u.name as user_name, u.color as user_color FROM events e JOIN users u ON u.id=e.user_id
             WHERE e.family_id=? AND e.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
             ORDER BY e.start_datetime LIMIT 10',
            [$familyId, $days]
        );
    }
}

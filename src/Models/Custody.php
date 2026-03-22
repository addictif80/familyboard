<?php
namespace App\Models;

use App\Core\Database;

class Custody
{
    public static function getSchedules(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM custody_schedules WHERE family_id=? ORDER BY child_name', [$familyId]);
    }

    public static function getScheduleById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM custody_schedules WHERE id=?', [$id]);
    }

    public static function createSchedule(int $familyId, string $childName, string $color = '#E67E22', string $notes = ''): int
    {
        return Database::insert('INSERT INTO custody_schedules (family_id, child_name, color, notes) VALUES (?,?,?,?)', [$familyId, $childName, $color, $notes]);
    }

    public static function updateSchedule(int $id, string $childName, string $color, string $notes): void
    {
        Database::execute('UPDATE custody_schedules SET child_name=?, color=?, notes=? WHERE id=?', [$childName, $color, $notes, $id]);
    }

    public static function deleteSchedule(int $id): void
    {
        Database::execute('DELETE FROM custody_schedules WHERE id=?', [$id]);
    }

    public static function getEvents(int $scheduleId, string $start, string $end): array
    {
        return Database::fetchAll(
            'SELECT ce.*, u.name as parent_name, u.color as parent_color FROM custody_events ce
             JOIN users u ON u.id=ce.parent_user_id
             WHERE ce.schedule_id=? AND ce.end_date >= ? AND ce.start_date <= ?
             ORDER BY ce.start_date',
            [$scheduleId, $start, $end]
        );
    }

    public static function getAllEventsForFamily(int $familyId, string $start, string $end): array
    {
        return Database::fetchAll(
            'SELECT ce.*, cs.child_name, cs.color as schedule_color, u.name as parent_name, u.color as parent_color
             FROM custody_events ce
             JOIN custody_schedules cs ON cs.id=ce.schedule_id
             JOIN users u ON u.id=ce.parent_user_id
             WHERE cs.family_id=? AND ce.end_date >= ? AND ce.start_date <= ?
             ORDER BY ce.start_date',
            [$familyId, $start, $end]
        );
    }

    public static function createEvent(int $scheduleId, int $parentUserId, array $data): int
    {
        return Database::insert(
            'INSERT INTO custody_events (schedule_id, parent_user_id, start_date, end_date, arrival_time, departure_time, notes) VALUES (?,?,?,?,?,?,?)',
            [$scheduleId, $parentUserId, $data['start_date'], $data['end_date'], $data['arrival_time'] ?? null, $data['departure_time'] ?? null, $data['notes'] ?? null]
        );
    }

    public static function updateEvent(int $id, array $data): void
    {
        Database::execute(
            'UPDATE custody_events SET parent_user_id=?, start_date=?, end_date=?, arrival_time=?, departure_time=?, notes=? WHERE id=?',
            [$data['parent_user_id'], $data['start_date'], $data['end_date'], $data['arrival_time'] ?? null, $data['departure_time'] ?? null, $data['notes'] ?? null, $id]
        );
    }

    public static function deleteEvent(int $id): void
    {
        Database::execute('DELETE FROM custody_events WHERE id=?', [$id]);
    }

    public static function getEvent(int $id): ?array
    {
        return Database::fetch('SELECT ce.*, cs.family_id FROM custody_events ce JOIN custody_schedules cs ON cs.id=ce.schedule_id WHERE ce.id=?', [$id]);
    }
}

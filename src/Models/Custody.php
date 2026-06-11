<?php
namespace App\Models;

use App\Core\Database;

class Custody
{
    public static function getSchedules(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT cs.*,
             u1.name as parent1_name, u1.color as parent1_color,
             u2.name as parent2_name, u2.color as parent2_color
             FROM custody_schedules cs
             LEFT JOIN users u1 ON u1.id = cs.recurrence_parent1_id
             LEFT JOIN users u2 ON u2.id = cs.recurrence_parent2_id
             WHERE cs.family_id=? ORDER BY cs.child_name',
            [$familyId]
        );
    }

    public static function getScheduleById(int $id): ?array
    {
        return Database::fetch(
            'SELECT cs.*,
             u1.name as parent1_name, u1.color as parent1_color,
             u2.name as parent2_name, u2.color as parent2_color
             FROM custody_schedules cs
             LEFT JOIN users u1 ON u1.id = cs.recurrence_parent1_id
             LEFT JOIN users u2 ON u2.id = cs.recurrence_parent2_id
             WHERE cs.id=?',
            [$id]
        );
    }

    public static function createSchedule(int $familyId, string $childName, string $color = '#E67E22', string $notes = '', array $recurrence = []): int
    {
        return Database::insert(
            'INSERT INTO custody_schedules
             (family_id, child_name, color, notes, recurrence_type, recurrence_start,
              recurrence_parent1_id, recurrence_parent2_id,
              recurrence_parent1_label, recurrence_parent1_color,
              recurrence_parent2_label, recurrence_parent2_color)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $childName, $color, $notes,
                $recurrence['type'] ?? 'none',
                $recurrence['start'] ?? null,
                $recurrence['parent1_id'] ?: null,
                $recurrence['parent2_id'] ?: null,
                $recurrence['parent1_label'] ?? null,
                $recurrence['parent1_color'] ?? '#4A90D9',
                $recurrence['parent2_label'] ?? null,
                $recurrence['parent2_color'] ?? '#E74C3C',
            ]
        );
    }

    public static function updateSchedule(int $id, string $childName, string $color, string $notes, array $recurrence = []): void
    {
        Database::execute(
            'UPDATE custody_schedules SET child_name=?, color=?, notes=?, recurrence_type=?, recurrence_start=?,
             recurrence_parent1_id=?, recurrence_parent2_id=?,
             recurrence_parent1_label=?, recurrence_parent1_color=?,
             recurrence_parent2_label=?, recurrence_parent2_color=?
             WHERE id=?',
            [
                $childName, $color, $notes,
                $recurrence['type'] ?? 'none',
                $recurrence['start'] ?? null,
                $recurrence['parent1_id'] ?: null,
                $recurrence['parent2_id'] ?: null,
                $recurrence['parent1_label'] ?? null,
                $recurrence['parent1_color'] ?? '#4A90D9',
                $recurrence['parent2_label'] ?? null,
                $recurrence['parent2_color'] ?? '#E74C3C',
                $id,
            ]
        );
    }

    public static function deleteSchedule(int $id): void
    {
        Database::execute('DELETE FROM custody_schedules WHERE id=?', [$id]);
    }

    public static function getAllEventsForFamily(int $familyId, string $start, string $end): array
    {
        // Manual events
        $manual = Database::fetchAll(
            'SELECT ce.*, cs.child_name, cs.color as schedule_color, cs.recurrence_type,
             u.name as parent_name, u.color as parent_color
             FROM custody_events ce
             JOIN custody_schedules cs ON cs.id=ce.schedule_id
             JOIN users u ON u.id=ce.parent_user_id
             WHERE cs.family_id=? AND ce.end_date >= ? AND ce.start_date <= ?
             ORDER BY ce.start_date',
            [$familyId, $start, $end]
        );

        // Generate recurring events from schedules
        $schedules = self::getSchedules($familyId);
        $recurring = [];
        foreach ($schedules as $schedule) {
            if ($schedule['recurrence_type'] !== 'none' && $schedule['recurrence_start']) {
                $recurring = array_merge($recurring, self::generateRecurringEvents($schedule, $start, $end));
            }
        }

        // Merge: recurring first, then manual events are added on top
        return array_merge($recurring, $manual);
    }

    /**
     * Generate recurring custody events for a given date range.
     * Returns array of event-like arrays (not stored in DB).
     */
    public static function generateRecurringEvents(array $schedule, string $rangeStart, string $rangeEnd): array
    {
        $p1ok = $schedule['recurrence_parent1_id'] || !empty($schedule['recurrence_parent1_label']);
        $p2ok = $schedule['recurrence_parent2_id'] || !empty($schedule['recurrence_parent2_label']);
        if (!$p1ok || !$p2ok) return [];

        $recType = $schedule['recurrence_type'];

        if (in_array($recType, ['weekends_every_2', 'weekends_monthly'], true)) {
            return self::generateWeekendEvents($schedule, $rangeStart, $rangeEnd);
        }

        $periodDays = match($recType) {
            'every_other_day'  => 1,
            'every_other_week' => 7,
            'every_2weeks'     => 14,
            'every_month'      => 30,
            default            => 0,
        };
        if ($periodDays === 0) return [];

        $recStart  = new \DateTime($schedule['recurrence_start']);
        $rangeFrom = new \DateTime($rangeStart);
        $rangeTo   = new \DateTime($rangeEnd);

        $parents = [
            1 => [
                'id'    => $schedule['recurrence_parent1_id'],
                'name'  => $schedule['recurrence_parent1_label'] ?: ($schedule['parent1_name'] ?? 'Parent 1'),
                'color' => $schedule['recurrence_parent1_color'] ?: ($schedule['parent1_color'] ?? '#4A90D9'),
            ],
            2 => [
                'id'    => $schedule['recurrence_parent2_id'],
                'name'  => $schedule['recurrence_parent2_label'] ?: ($schedule['parent2_name'] ?? 'Parent 2'),
                'color' => $schedule['recurrence_parent2_color'] ?: ($schedule['parent2_color'] ?? '#E74C3C'),
            ],
        ];

        if ($recStart > $rangeTo) return [];

        $events = [];

        $current = clone $recStart;
        if ($rangeFrom > $recStart) {
            $diff = (int)$recStart->diff($rangeFrom)->days;
            $skip = max(0, (int)floor($diff / $periodDays) - 1);
            if ($skip > 0) {
                $current->modify('+' . ($skip * $periodDays) . ' days');
            }
        }

        $iterations = 0;
        $maxIterations = (int)ceil(($current->diff($rangeTo)->days + $periodDays * 2) / $periodDays) + 4;

        while ($current <= $rangeTo && $iterations < $maxIterations) {
            $iterations++;
            $periodEnd = clone $current;
            $periodEnd->modify("+{$periodDays} days");
            $periodEnd->modify('-1 day');

            $totalDays = (int)$recStart->diff($current)->days;
            $periodIndex = (int)floor($totalDays / $periodDays);
            $parentKey = ($periodIndex % 2 === 0) ? 1 : 2;
            $parent = $parents[$parentKey];

            if ($periodEnd >= $rangeFrom && $current <= $rangeTo) {
                $events[] = [
                    'id'             => 'r_' . $schedule['id'] . '_' . $current->format('Ymd'),
                    'schedule_id'    => $schedule['id'],
                    'child_name'     => $schedule['child_name'],
                    'schedule_color' => $schedule['color'],
                    'parent_user_id' => $parent['id'],
                    'parent_name'    => $parent['name'],
                    'parent_color'   => $parent['color'],
                    'start_date'     => $current->format('Y-m-d'),
                    'end_date'       => $periodEnd->format('Y-m-d'),
                    'arrival_time'   => null,
                    'departure_time' => null,
                    'notes'          => null,
                    'is_recurring'   => true,
                ];
            }

            $current->modify("+{$periodDays} days");
        }

        return $events;
    }

    /**
     * Generate weekend-only recurring custody events.
     * weekends_every_2: alternating weekends (Sat+Sun) between parents
     * weekends_monthly: one weekend per month, alternating between parents
     */
    private static function generateWeekendEvents(array $schedule, string $rangeStart, string $rangeEnd): array
    {
        $recType   = $schedule['recurrence_type'];
        $recStart  = new \DateTime($schedule['recurrence_start']);
        $rangeFrom = new \DateTime($rangeStart);
        $rangeTo   = new \DateTime($rangeEnd);

        if ($recStart > $rangeTo) return [];

        $parents = [
            1 => [
                'id'    => $schedule['recurrence_parent1_id'],
                'name'  => $schedule['recurrence_parent1_label'] ?: ($schedule['parent1_name'] ?? 'Parent 1'),
                'color' => $schedule['recurrence_parent1_color'] ?: ($schedule['parent1_color'] ?? '#4A90D9'),
            ],
            2 => [
                'id'    => $schedule['recurrence_parent2_id'],
                'name'  => $schedule['recurrence_parent2_label'] ?: ($schedule['parent2_name'] ?? 'Parent 2'),
                'color' => $schedule['recurrence_parent2_color'] ?: ($schedule['parent2_color'] ?? '#E74C3C'),
            ],
        ];

        // Advance recStart to the first Saturday on or after it
        $firstSat = clone $recStart;
        $dow = (int)$firstSat->format('N'); // 1=Mon ... 6=Sat ... 7=Sun
        if ($dow !== 6) {
            $firstSat->modify('next saturday');
        }

        $events       = [];
        $weekendIndex = 0; // counts all weekends since firstSat
        $monthsSeen   = []; // for weekends_monthly: track distinct months

        // Collect all relevant Saturdays from firstSat to rangeTo
        $current = clone $firstSat;

        while ($current <= $rangeTo) {
            $sat = clone $current;
            $sun = clone $current;
            $sun->modify('+1 day');

            // Determine which parent gets this weekend
            if ($recType === 'weekends_every_2') {
                // Alternate every weekend: even index = P1, odd = P2
                $parentKey = ($weekendIndex % 2 === 0) ? 1 : 2;
            } else {
                // weekends_monthly: take only the 1st Saturday of each month, alternating P1/P2
                $monthKey = (int)$current->format('Ym'); // e.g. 202407

                if (!isset($monthsSeen[$monthKey])) {
                    $monthsSeen[$monthKey] = count($monthsSeen);
                }
                $monthIndex = $monthsSeen[$monthKey];

                // Only emit the FIRST weekend of the month (day <= 7)
                $dayOfMonth = (int)$current->format('j');
                if ($dayOfMonth > 7) {
                    $current->modify('next saturday');
                    $weekendIndex++;
                    continue;
                }
                $parentKey = ($monthIndex % 2 === 0) ? 1 : 2;
            }

            $parent = $parents[$parentKey];

            // Check overlap with range
            if ($sun >= $rangeFrom && $sat <= $rangeTo) {
                $events[] = [
                    'id'             => 'r_' . $schedule['id'] . '_' . $sat->format('Ymd'),
                    'schedule_id'    => $schedule['id'],
                    'child_name'     => $schedule['child_name'],
                    'schedule_color' => $schedule['color'],
                    'parent_user_id' => $parent['id'],
                    'parent_name'    => $parent['name'],
                    'parent_color'   => $parent['color'],
                    'start_date'     => $sat->format('Y-m-d'),
                    'end_date'       => $sun->format('Y-m-d'),
                    'arrival_time'   => null,
                    'departure_time' => null,
                    'notes'          => null,
                    'is_recurring'   => true,
                ];
            }

            $current->modify('next saturday');
            $weekendIndex++;
        }

        return $events;
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

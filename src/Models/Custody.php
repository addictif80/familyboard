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
             (family_id, child_name, color, notes, recurrence_type, recurrence_start, handover_weekday, extra_weekday, handover_time,
              recurrence_parent1_id, recurrence_parent2_id,
              recurrence_parent1_label, recurrence_parent1_color,
              recurrence_parent2_label, recurrence_parent2_color)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $childName, $color, $notes,
                $recurrence['type'] ?? 'none',
                $recurrence['start'] ?? null,
                $recurrence['handover_weekday'] ?: null,
                $recurrence['extra_weekday'] ?: null,
                self::normalizeHandoverTime($recurrence['handover_time'] ?? null),
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
            'UPDATE custody_schedules SET child_name=?, color=?, notes=?, recurrence_type=?, recurrence_start=?, handover_weekday=?, extra_weekday=?, handover_time=?,
             recurrence_parent1_id=?, recurrence_parent2_id=?,
             recurrence_parent1_label=?, recurrence_parent1_color=?,
             recurrence_parent2_label=?, recurrence_parent2_color=?
             WHERE id=?',
            [
                $childName, $color, $notes,
                $recurrence['type'] ?? 'none',
                $recurrence['start'] ?? null,
                $recurrence['handover_weekday'] ?: null,
                $recurrence['extra_weekday'] ?: null,
                self::normalizeHandoverTime($recurrence['handover_time'] ?? null),
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

    /** "HH:MM" (saisie utilisateur) -> "HH:MM:00", avec repli sur 18h si absent/invalide. */
    private static function normalizeHandoverTime(?string $time): string
    {
        if ($time && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) return $time . ':00';
        return '18:00:00';
    }

    /**
     * Combine un bloc de garde (start_date/end_date, jours entiers) avec l'heure de passage du
     * planning pour obtenir l'instant réel de début/fin — ex. "vendredi 18h" plutôt que
     * "vendredi 00h00". Le jour de fin reste exclusif (même convention +1 jour déjà en place
     * partout où ces blocs sont affichés) : c'est seulement l'HEURE de la bascule qui change,
     * jamais l'algorithme qui décide quel jour appartient à quel parent.
     */
    public static function toDisplayRange(array $event): array
    {
        $defaultTime = substr($event['handover_time'] ?? '18:00:00', 0, 5);
        // Un événement manuel peut avoir sa propre heure d'arrivée/départ (exception ponctuelle
        // à l'heure de passage habituelle du planning) — elle est prioritaire quand présente.
        $startTime = !empty($event['arrival_time']) ? substr($event['arrival_time'], 0, 5) : $defaultTime;
        $endTime   = !empty($event['departure_time']) ? substr($event['departure_time'], 0, 5) : $defaultTime;
        return [
            'start' => $event['start_date'] . ' ' . $startTime . ':00',
            'end'   => date('Y-m-d', strtotime($event['end_date'] . ' +1 day')) . ' ' . $endTime . ':00',
        ];
    }

    public static function deleteSchedule(int $id): void
    {
        Database::execute('DELETE FROM custody_schedules WHERE id=?', [$id]);
    }

    /** Plannings (enfants "garde alternée") accessibles à un utilisateur via custody_access,
     *  quelle que soit sa propre famille (co-parent restreint, ou parent avec sa propre famille). */
    public static function getSchedulesForUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT cs.*,
             u1.name as parent1_name, u1.color as parent1_color,
             u2.name as parent2_name, u2.color as parent2_color
             FROM custody_access ca
             JOIN custody_schedules cs ON cs.id = ca.schedule_id
             LEFT JOIN users u1 ON u1.id = cs.recurrence_parent1_id
             LEFT JOIN users u2 ON u2.id = cs.recurrence_parent2_id
             WHERE ca.user_id=? ORDER BY cs.child_name',
            [$userId]
        );
    }

    /** [user_id => ['Enfant A', 'Enfant B']] pour un ensemble d'utilisateurs (affichage réglages). */
    public static function getChildNamesByUserIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) return [];
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $rows = Database::fetchAll(
            "SELECT ca.user_id, cs.child_name FROM custody_access ca
             JOIN custody_schedules cs ON cs.id = ca.schedule_id
             WHERE ca.user_id IN ($ph)",
            $userIds
        );
        $byUser = [];
        foreach ($rows as $row) {
            $byUser[$row['user_id']][] = $row['child_name'];
        }
        return $byUser;
    }

    public static function userHasAccessToSchedule(int $userId, int $scheduleId): bool
    {
        return (bool)Database::fetch('SELECT 1 FROM custody_access WHERE user_id=? AND schedule_id=?', [$userId, $scheduleId]);
    }

    public static function grantAccess(int $userId, int $scheduleId): void
    {
        Database::execute(
            'INSERT IGNORE INTO custody_access (user_id, schedule_id) VALUES (?,?)',
            [$userId, $scheduleId]
        );
    }

    /** Co-parents (comptes distincts, qu'ils soient à accès restreint ou membres à part entière
     *  d'une autre famille) ayant accès à au moins un planning de cette famille, avec la liste
     *  des plannings concernés pour chacun (utile pour journaliser une notification par planning). */
    public static function getCoparentUsersForFamily(int $familyId): array
    {
        $rows = Database::fetchAll(
            'SELECT ca.user_id, ca.schedule_id, u.name, u.email
             FROM custody_access ca
             JOIN custody_schedules cs ON cs.id = ca.schedule_id
             JOIN users u ON u.id = ca.user_id
             WHERE cs.family_id = ?',
            [$familyId]
        );
        $byUser = [];
        foreach ($rows as $row) {
            $uid = (int)$row['user_id'];
            if (!isset($byUser[$uid])) {
                $byUser[$uid] = ['id' => $uid, 'name' => $row['name'], 'email' => $row['email'], 'schedule_ids' => []];
            }
            $byUser[$uid]['schedule_ids'][] = (int)$row['schedule_id'];
        }
        return array_values($byUser);
    }

    public static function getAllEventsForFamily(int $familyId, string $start, string $end): array
    {
        $manual = Database::fetchAll(
            'SELECT ce.*, cs.child_name, cs.color as schedule_color, cs.recurrence_type, cs.handover_time,
             u.name as parent_name, u.color as parent_color
             FROM custody_events ce
             JOIN custody_schedules cs ON cs.id=ce.schedule_id
             JOIN users u ON u.id=ce.parent_user_id
             WHERE cs.family_id=? AND ce.end_date >= ? AND ce.start_date <= ?
             ORDER BY ce.start_date',
            [$familyId, $start, $end]
        );

        $schedules = self::getSchedules($familyId);
        return array_merge(self::buildRecurringAndVacationEvents($schedules, $start, $end), $manual);
    }

    /** Même chose que getAllEventsForFamily, mais limité à une liste explicite de plannings
     *  (utilisé par la vue co-parent, où les plannings peuvent appartenir à une autre famille). */
    public static function getAllEventsForSchedules(array $scheduleIds, string $start, string $end): array
    {
        $scheduleIds = array_values(array_unique(array_map('intval', $scheduleIds)));
        if (empty($scheduleIds)) return [];

        $placeholders = implode(',', array_fill(0, count($scheduleIds), '?'));
        $manual = Database::fetchAll(
            "SELECT ce.*, cs.child_name, cs.color as schedule_color, cs.recurrence_type, cs.handover_time,
             u.name as parent_name, u.color as parent_color
             FROM custody_events ce
             JOIN custody_schedules cs ON cs.id=ce.schedule_id
             JOIN users u ON u.id=ce.parent_user_id
             WHERE ce.schedule_id IN ($placeholders) AND ce.end_date >= ? AND ce.start_date <= ?
             ORDER BY ce.start_date",
            [...$scheduleIds, $start, $end]
        );

        $schedules = array_filter(array_map([self::class, 'getScheduleById'], $scheduleIds));
        return array_merge(self::buildRecurringAndVacationEvents($schedules, $start, $end), $manual);
    }

    /**
     * Soustrait une liste de périodes [start_date, end_date] (bornes incluses) d'un
     * intervalle [start, end], et retourne les segments restants sous forme de
     * paires [start, end]. Utilisé pour ne masquer que les jours réellement
     * couverts par une période de vacances, pas tout le bloc récurrent qui la chevauche.
     */
    private static function subtractDateRanges(string $start, string $end, array $blockers): array
    {
        $segments = [[$start, $end]];
        foreach ($blockers as $b) {
            $next = [];
            foreach ($segments as [$s, $e]) {
                if ($b['end_date'] < $s || $b['start_date'] > $e) {
                    $next[] = [$s, $e];
                    continue;
                }
                if ($b['start_date'] > $s) {
                    $next[] = [$s, date('Y-m-d', strtotime($b['start_date'] . ' -1 day'))];
                }
                if ($b['end_date'] < $e) {
                    $next[] = [date('Y-m-d', strtotime($b['end_date'] . ' +1 day')), $e];
                }
            }
            $segments = $next;
        }
        return $segments;
    }

    /** Génère les événements récurrents + les surcharges de vacances pour une liste de plannings. */
    private static function buildRecurringAndVacationEvents(array $schedules, string $start, string $end): array
    {
        $events = [];
        foreach ($schedules as $schedule) {
            if ($schedule['recurrence_type'] !== 'none' && $schedule['recurrence_start']) {
                $recurring = self::generateRecurringEvents($schedule, $start, $end);
            } else {
                $recurring = [];
            }

            $vacations = self::getVacationPeriods((int)$schedule['id']);
            $relevantVacations = array_filter($vacations, fn($v) => $v['end_date'] >= $start && $v['start_date'] <= $end);

            if ($relevantVacations) {
                // Clip recurring events to the days NOT covered by a vacation period —
                // the vacation's own distribution takes over for those days, but days
                // just before/after the vacation window keep the normal recurrence.
                $clipped = [];
                foreach ($recurring as $e) {
                    $segments = self::subtractDateRanges($e['start_date'], $e['end_date'], $relevantVacations);
                    foreach ($segments as $i => [$segStart, $segEnd]) {
                        $id = count($segments) > 1 ? $e['id'] . '_s' . $i : $e['id'];
                        $clipped[] = array_merge($e, ['id' => $id, 'start_date' => $segStart, 'end_date' => $segEnd]);
                    }
                }
                $recurring = $clipped;
            }

            $events = array_merge($events, $recurring);

            $p1ok = $schedule['recurrence_parent1_id'] || !empty($schedule['recurrence_parent1_label']);
            $p2ok = $schedule['recurrence_parent2_id'] || !empty($schedule['recurrence_parent2_label']);
            if ($p1ok && $p2ok) {
                foreach ($relevantVacations as $vacation) {
                    $events = array_merge($events, self::generateVacationEvents($schedule, $vacation, $start, $end));
                }
            }
        }
        return $events;
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

        if (in_array($recType, ['weekends_every_2', 'weekends_monthly', 'weekends_every_2_plus_weekday'], true)) {
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

        // Passation un jour de semaine précis (ex: mercredi) plutôt que le jour
        // implicite de recurrence_start, pour every_other_week / every_2weeks / every_month.
        if (!empty($schedule['handover_weekday'])) {
            $targetDow = (int)$schedule['handover_weekday'];
            $curDow = (int)$recStart->format('N');
            if ($targetDow >= 1 && $targetDow <= 7 && $curDow !== $targetDow) {
                $recStart->modify('+' . (($targetDow - $curDow + 7) % 7) . ' days');
            }
        }

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
                    'handover_time'  => $schedule['handover_time'] ?? '18:00:00',
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

        // weekends_every_2_plus_weekday: on top of the alternating weekend, the parent who
        // does NOT have the weekend that week also gets a fixed weekday (e.g. Wednesday) —
        // the common French "1 weekend sur 2 + un mercredi" custody pattern.
        $extraWeekday = ($recType === 'weekends_every_2_plus_weekday' && !empty($schedule['extra_weekday']))
            ? (int)$schedule['extra_weekday'] : null;

        $events       = [];
        $weekendIndex = 0; // counts all weekends since firstSat
        $monthsSeen   = []; // for weekends_monthly: track distinct months

        // Collect all relevant Saturdays from firstSat to rangeTo
        $current = clone $firstSat;

        while ($current <= $rangeTo) {
            $sat = clone $current;
            $sun = clone $current;
            $sun->modify('+1 day');
            // Le week-end "réel" ne commence pas samedi minuit ni ne finit dimanche minuit :
            // le parent qui a le week-end récupère l'enfant vendredi (sortie d'école) et le
            // ramène lundi (rentrée d'école) — le passage précis dans la journée est ensuite
            // géré par handover_time (Custody::toDisplayRange), pas ici.
            $fri = clone $current; $fri->modify('-1 day');
            $mon = clone $current; $mon->modify('+2 days');

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
            if ($mon >= $rangeFrom && $fri <= $rangeTo) {
                $events[] = [
                    'id'             => 'r_' . $schedule['id'] . '_' . $sat->format('Ymd'),
                    'schedule_id'    => $schedule['id'],
                    'child_name'     => $schedule['child_name'],
                    'schedule_color' => $schedule['color'],
                    'parent_user_id' => $parent['id'],
                    'parent_name'    => $parent['name'],
                    'parent_color'   => $parent['color'],
                    'start_date'     => $fri->format('Y-m-d'),
                    'end_date'       => $mon->format('Y-m-d'),
                    'arrival_time'   => null,
                    'departure_time' => null,
                    'notes'          => null,
                    'is_recurring'   => true,
                    'handover_time'  => $schedule['handover_time'] ?? '18:00:00',
                ];
            }

            if ($extraWeekday) {
                $weekStart = clone $current;
                $weekStart->modify('-5 days'); // Monday of the week containing this Saturday
                $extraDate = clone $weekStart;
                $extraDate->modify('+' . ($extraWeekday - 1) . ' days');
                $otherParent = $parents[$parentKey === 1 ? 2 : 1];

                if ($extraDate >= $rangeFrom && $extraDate <= $rangeTo) {
                    $events[] = [
                        'id'             => 'rw_' . $schedule['id'] . '_' . $extraDate->format('Ymd'),
                        'schedule_id'    => $schedule['id'],
                        'child_name'     => $schedule['child_name'],
                        'schedule_color' => $schedule['color'],
                        'parent_user_id' => $otherParent['id'],
                        'parent_name'    => $otherParent['name'],
                        'parent_color'   => $otherParent['color'],
                        'start_date'     => $extraDate->format('Y-m-d'),
                        'end_date'       => $extraDate->format('Y-m-d'),
                        'arrival_time'   => null,
                        'departure_time' => null,
                        'notes'          => null,
                        'is_recurring'   => true,
                        'handover_time'  => $schedule['handover_time'] ?? '18:00:00',
                    ];
                }
            }

            $current->modify('next saturday');
            $weekendIndex++;
        }

        return $events;
    }

    // ── Périodes de vacances (répartition dédiée : grandes vacances, Noël...) ──

    public static function getVacationPeriods(int $scheduleId): array
    {
        return Database::fetchAll(
            'SELECT * FROM custody_vacation_periods WHERE schedule_id=? ORDER BY start_date',
            [$scheduleId]
        );
    }

    public static function getVacationPeriodById(int $id): ?array
    {
        return Database::fetch(
            'SELECT vp.*, cs.family_id FROM custody_vacation_periods vp
             JOIN custody_schedules cs ON cs.id = vp.schedule_id WHERE vp.id=?',
            [$id]
        );
    }

    public static function createVacationPeriod(int $scheduleId, array $data): int
    {
        return Database::insert(
            'INSERT INTO custody_vacation_periods (schedule_id, label, start_date, end_date, distribution_type, starting_parent)
             VALUES (?,?,?,?,?,?)',
            [
                $scheduleId,
                $data['label'] ?? null,
                $data['start_date'],
                $data['end_date'],
                $data['distribution_type'] ?? '1week_2',
                (int)($data['starting_parent'] ?? 1) === 2 ? 2 : 1,
            ]
        );
    }

    public static function updateVacationPeriod(int $id, array $data): void
    {
        Database::execute(
            'UPDATE custody_vacation_periods SET label=?, start_date=?, end_date=?, distribution_type=?, starting_parent=? WHERE id=?',
            [
                $data['label'] ?? null,
                $data['start_date'],
                $data['end_date'],
                $data['distribution_type'] ?? '1week_2',
                (int)($data['starting_parent'] ?? 1) === 2 ? 2 : 1,
                $id,
            ]
        );
    }

    public static function deleteVacationPeriod(int $id): void
    {
        Database::execute('DELETE FROM custody_vacation_periods WHERE id=?', [$id]);
    }

    /**
     * Génère les événements de garde pour une période de vacances, en appliquant
     * sa propre répartition (1 semaine sur 2 / 2 semaines sur 4 / semaines paires-impaires)
     * plutôt que la récurrence normale du planning.
     */
    public static function generateVacationEvents(array $schedule, array $vacation, string $rangeStart, string $rangeEnd): array
    {
        // Compute the day-by-day parity over the FULL vacation period, not clipped
        // to the query range — like generateRecurringEvents/generateWeekendEvents,
        // only the resulting blocks are filtered by overlap with [rangeStart, rangeEnd]
        // below. Clipping the loop itself here previously truncated (or entirely
        // dropped) the tail block of a vacation period whenever it fell right at
        // or past the edge of the requested range (e.g. viewing the month before
        // the one where the vacation ends).
        if ($vacation['end_date'] < $rangeStart || $vacation['start_date'] > $rangeEnd) return [];
        $vacStart = new \DateTime($vacation['start_date']);
        $vacEnd   = new \DateTime($vacation['end_date']);

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

        $startingParent = (int)$vacation['starting_parent'] === 2 ? 2 : 1;
        $otherParent    = $startingParent === 1 ? 2 : 1;
        $periodStart    = new \DateTime($vacation['start_date']);
        $blockDays      = $vacation['distribution_type'] === '2weeks_4' ? 14 : 7; // 1week_2 par défaut

        $pairs = [];
        $cursor = clone $vacStart;
        while ($cursor <= $vacEnd) {
            if ($vacation['distribution_type'] === 'odd_even_weeks') {
                $weekNum   = (int)$cursor->format('W');
                $parentKey = ($weekNum % 2 === 1) ? $startingParent : $otherParent;
            } else {
                $daysSinceStart = (int)$periodStart->diff($cursor)->days;
                $blockIndex     = (int)floor($daysSinceStart / $blockDays);
                $parentKey      = ($blockIndex % 2 === 0) ? $startingParent : $otherParent;
            }
            $pairs[] = ['date' => $cursor->format('Y-m-d'), 'parent' => $parentKey];
            $cursor->modify('+1 day');
        }

        $events = [];
        foreach (self::groupDaysIntoBlocks($pairs) as $block) {
            if ($block['end_date'] < $rangeStart || $block['start_date'] > $rangeEnd) continue;
            $parent = $parents[$block['parent']];
            $events[] = [
                'id'             => 'v_' . $vacation['id'] . '_' . str_replace('-', '', $block['start_date']),
                'schedule_id'    => $schedule['id'],
                'child_name'     => $schedule['child_name'],
                'schedule_color' => $schedule['color'],
                'parent_user_id' => $parent['id'],
                'parent_name'    => $parent['name'],
                'parent_color'   => $parent['color'],
                'start_date'     => $block['start_date'],
                'end_date'       => $block['end_date'],
                'arrival_time'   => null,
                'departure_time' => null,
                'notes'          => $vacation['label'] ?: 'Vacances',
                'is_recurring'   => true,
                'handover_time'  => $schedule['handover_time'] ?? '18:00:00',
            ];
        }
        return $events;
    }

    /**
     * Regroupe une liste de paires ['date' => 'Y-m-d', 'parent' => scalaire] en blocs
     * de dates consécutives pour un même parent. `parent` peut être un id utilisateur
     * réel (proposition manuelle) ou un simple slot 1/2 (génération de vacances).
     */
    public static function groupDaysIntoBlocks(array $days): array
    {
        $days = array_values(array_filter($days, fn($d) => isset($d['parent']) && $d['parent'] !== null && $d['parent'] !== ''));
        usort($days, fn($a, $b) => strcmp($a['date'], $b['date']));

        $blocks = [];
        $current = null;
        foreach ($days as $day) {
            if (
                $current &&
                $current['parent'] === $day['parent'] &&
                date('Y-m-d', strtotime($current['end_date'] . ' +1 day')) === $day['date']
            ) {
                $current['end_date'] = $day['date'];
                $blocks[count($blocks) - 1] = $current;
            } else {
                $current = ['parent' => $day['parent'], 'start_date' => $day['date'], 'end_date' => $day['date']];
                $blocks[] = $current;
            }
        }
        return $blocks;
    }

    /**
     * Applique une proposition de garde (jours peints jour par jour) en créant les
     * custody_events correspondants (blocs de jours consécutifs regroupés par parent).
     * Retourne le nombre de blocs créés.
     */
    public static function applyProposalDays(int $scheduleId, array $days): int
    {
        $pairs = [];
        foreach ($days as $day) {
            if (empty($day['parent_user_id'])) continue;
            $pairs[] = ['date' => $day['date'], 'parent' => (int)$day['parent_user_id']];
        }
        $blocks = self::groupDaysIntoBlocks($pairs);
        foreach ($blocks as $block) {
            self::createEvent($scheduleId, $block['parent'], [
                'start_date'     => $block['start_date'],
                'end_date'       => $block['end_date'],
                'arrival_time'   => null,
                'departure_time' => null,
                'notes'          => 'Proposition de garde',
            ]);
        }
        return count($blocks);
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

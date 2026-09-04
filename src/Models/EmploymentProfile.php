<?php
namespace App\Models;

use App\Core\Database;

class EmploymentProfile
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT ep.*, u.name as user_name FROM employment_profiles ep JOIN users u ON u.id=ep.user_id
             WHERE ep.family_id=? ORDER BY u.name',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch(
            'SELECT ep.*, u.name as user_name FROM employment_profiles ep JOIN users u ON u.id=ep.user_id WHERE ep.id=?',
            [$id]
        );
    }

    public static function create(int $familyId, int $createdBy, array $d): int
    {
        return Database::insert(
            'INSERT INTO employment_profiles
             (family_id, user_id, employer_siren, employer_name, employer_address, job_title, contract_type, hire_date, trial_period_end, color,
              pay_mode, hourly_rate_cents, monthly_gross_cents, contractual_weekly_hours, overtime_threshold_hours, overtime_rate1_pct, overtime_rate2_pct,
              leave_reset_month, leave_reset_day, leave_accrual_days_per_month, rtt_days_per_year, cotisation_rate_pct, pas_rate_pct, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$familyId, $d['user_id'], $d['employer_siren'], $d['employer_name'], $d['employer_address'], $d['job_title'], $d['contract_type'],
             $d['hire_date'], $d['trial_period_end'], $d['color'], $d['pay_mode'], $d['hourly_rate_cents'], $d['monthly_gross_cents'],
             $d['contractual_weekly_hours'], $d['overtime_threshold_hours'], $d['overtime_rate1_pct'], $d['overtime_rate2_pct'],
             $d['leave_reset_month'], $d['leave_reset_day'], $d['leave_accrual_days_per_month'], $d['rtt_days_per_year'],
             $d['cotisation_rate_pct'], $d['pas_rate_pct'], $createdBy]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE employment_profiles SET employer_siren=?, employer_name=?, employer_address=?, job_title=?, contract_type=?, hire_date=?, trial_period_end=?, color=?,
             pay_mode=?, hourly_rate_cents=?, monthly_gross_cents=?, contractual_weekly_hours=?, overtime_threshold_hours=?, overtime_rate1_pct=?, overtime_rate2_pct=?,
             leave_reset_month=?, leave_reset_day=?, leave_accrual_days_per_month=?, rtt_days_per_year=?, cotisation_rate_pct=?, pas_rate_pct=?
             WHERE id=? AND family_id=?',
            [$d['employer_siren'], $d['employer_name'], $d['employer_address'], $d['job_title'], $d['contract_type'], $d['hire_date'], $d['trial_period_end'], $d['color'],
             $d['pay_mode'], $d['hourly_rate_cents'], $d['monthly_gross_cents'], $d['contractual_weekly_hours'], $d['overtime_threshold_hours'], $d['overtime_rate1_pct'], $d['overtime_rate2_pct'],
             $d['leave_reset_month'], $d['leave_reset_day'], $d['leave_accrual_days_per_month'], $d['rtt_days_per_year'], $d['cotisation_rate_pct'], $d['pas_rate_pct'],
             $id, $familyId]
        );
    }

    public static function delete(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM employment_profiles WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    // ── Planning récurrent ──────────────────────────────────────────

    public static function getSchedule(int $profileId): array
    {
        return Database::fetchAll('SELECT * FROM employment_work_schedule WHERE profile_id=? ORDER BY day_of_week', [$profileId]);
    }

    /** Remplace l'ensemble du gabarit hebdomadaire. $days = [day_of_week => [start,end,break]]. */
    public static function setSchedule(int $profileId, array $days): void
    {
        Database::execute('DELETE FROM employment_work_schedule WHERE profile_id=?', [$profileId]);
        foreach ($days as $dow => $slot) {
            if (empty($slot['start']) || empty($slot['end'])) continue;
            Database::execute(
                'INSERT INTO employment_work_schedule (profile_id, day_of_week, start_time, end_time, break_minutes) VALUES (?,?,?,?,?)',
                [$profileId, (int)$dow, $slot['start'], $slot['end'], (int)($slot['break'] ?? 0)]
            );
        }
    }

    // ── Correctifs jour par jour ──────────────────────────────────

    public static function getExceptions(int $profileId, ?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT * FROM employment_schedule_exceptions WHERE profile_id=?';
        $params = [$profileId];
        if ($from) { $sql .= ' AND exception_date>=?'; $params[] = $from; }
        if ($to)   { $sql .= ' AND exception_date<=?'; $params[] = $to; }
        $sql .= ' ORDER BY exception_date DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function setException(int $profileId, string $date, float $hours, ?string $note): void
    {
        Database::execute(
            'INSERT INTO employment_schedule_exceptions (profile_id, exception_date, hours_worked, note) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE hours_worked=VALUES(hours_worked), note=VALUES(note)',
            [$profileId, $date, $hours, $note]
        );
    }

    public static function deleteException(int $id, int $profileId): void
    {
        Database::execute('DELETE FROM employment_schedule_exceptions WHERE id=? AND profile_id=?', [$id, $profileId]);
    }

    /** Heures travaillées un jour donné : correctif s'il existe, sinon gabarit récurrent. */
    public static function hoursForDate(array $schedule, array $exceptionsByDate, string $date): float
    {
        if (isset($exceptionsByDate[$date])) return (float)$exceptionsByDate[$date]['hours_worked'];
        $dow = (int)(new \DateTime($date))->format('N');
        foreach ($schedule as $slot) {
            if ((int)$slot['day_of_week'] === $dow) {
                $start = strtotime($date . ' ' . $slot['start_time']);
                $end   = strtotime($date . ' ' . $slot['end_time']);
                $minutes = max(0, ($end - $start) / 60 - (int)$slot['break_minutes']);
                return round($minutes / 60, 2);
            }
        }
        return 0.0;
    }

    /** Heures travaillées totales sur une période [from,to] inclus, planning + correctifs. */
    public static function workedHoursInRange(int $profileId, string $from, string $to): float
    {
        $schedule = self::getSchedule($profileId);
        $exceptions = self::getExceptions($profileId, $from, $to);
        $byDate = [];
        foreach ($exceptions as $e) $byDate[$e['exception_date']] = $e;

        $total = 0.0;
        $cursor = new \DateTime($from);
        $end = new \DateTime($to);
        while ($cursor <= $end) {
            $total += self::hoursForDate($schedule, $byDate, $cursor->format('Y-m-d'));
            $cursor->modify('+1 day');
        }
        return round($total, 2);
    }

    /** Nombre de jours "normalement travaillés" (planning > 0h) dans [from,to] — sert à ne
     *  déduire des congés que les jours qui auraient vraiment été travaillés. */
    public static function workingDaysInRange(int $profileId, string $from, string $to): int
    {
        $schedule = self::getSchedule($profileId);
        $exceptions = self::getExceptions($profileId, $from, $to);
        $byDate = [];
        foreach ($exceptions as $e) $byDate[$e['exception_date']] = $e;

        $count = 0;
        $cursor = new \DateTime($from);
        $end = new \DateTime($to);
        while ($cursor <= $end) {
            if (self::hoursForDate($schedule, $byDate, $cursor->format('Y-m-d')) > 0) $count++;
            $cursor->modify('+1 day');
        }
        return $count;
    }

    // ── Congés / RTT (via événements du Calendrier familial tagués) ────

    private static function currentResetAnchor(array $profile): \DateTime
    {
        $today = new \DateTime('today');
        $anchor = new \DateTime(sprintf('%d-%02d-%02d', (int)$today->format('Y'), (int)$profile['leave_reset_month'], (int)$profile['leave_reset_day']));
        if ($anchor > $today) $anchor->modify('-1 year');
        return $anchor;
    }

    public static function getLeaveEvents(int $profileId, string $leaveType, ?string $since = null): array
    {
        $sql = 'SELECT * FROM events WHERE employment_profile_id=? AND employment_leave_type=?';
        $params = [$profileId, $leaveType];
        if ($since) { $sql .= ' AND start_datetime>=?'; $params[] = $since . ' 00:00:00'; }
        $sql .= ' ORDER BY start_datetime DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function getLeaveAdjustments(int $profileId, string $leaveType): array
    {
        return Database::fetchAll(
            'SELECT * FROM employment_leave_adjustments WHERE profile_id=? AND leave_type=? ORDER BY adjustment_date DESC',
            [$profileId, $leaveType]
        );
    }

    public static function addLeaveAdjustment(int $profileId, string $leaveType, string $date, float $days, ?string $note): int
    {
        return Database::insert(
            'INSERT INTO employment_leave_adjustments (profile_id, leave_type, adjustment_date, days, note) VALUES (?,?,?,?,?)',
            [$profileId, $leaveType, $date, $days, $note]
        );
    }

    public static function deleteLeaveAdjustment(int $id, int $profileId): void
    {
        Database::execute('DELETE FROM employment_leave_adjustments WHERE id=? AND profile_id=?', [$id, $profileId]);
    }

    /** Solde congés payés OU RTT depuis la dernière remise à zéro. */
    public static function getLeaveBalance(array $profile, string $leaveType): array
    {
        $anchor = self::currentResetAnchor($profile);
        $today = new \DateTime('today');
        $monthsElapsed = max(0, ((int)$today->format('Y') - (int)$anchor->format('Y')) * 12 + ((int)$today->format('n') - (int)$anchor->format('n')));

        $acquired = $leaveType === 'rtt'
            ? round((float)$profile['rtt_days_per_year'] / 12 * $monthsElapsed, 2)
            : round((float)$profile['leave_accrual_days_per_month'] * $monthsElapsed, 2);

        $adjustments = array_sum(array_column(self::getLeaveAdjustments((int)$profile['id'], $leaveType), 'days'));

        $taken = 0.0;
        foreach (self::getLeaveEvents((int)$profile['id'], $leaveType, $anchor->format('Y-m-d')) as $ev) {
            $from = substr($ev['start_datetime'], 0, 10);
            $to = substr($ev['end_datetime'], 0, 10);
            $taken += self::workingDaysInRange((int)$profile['id'], $from, $to);
        }

        return [
            'anchor'      => $anchor->format('Y-m-d'),
            'acquired'    => $acquired,
            'adjustments' => round((float)$adjustments, 2),
            'taken'       => round($taken, 2),
            'balance'     => round($acquired + $adjustments - $taken, 2),
        ];
    }

    // ── Primes ──────────────────────────────────────────────────────

    public static function getPrimes(int $profileId, int $year, int $month): array
    {
        return Database::fetchAll(
            'SELECT * FROM employment_primes WHERE profile_id=? AND period_year=? AND period_month=? ORDER BY id',
            [$profileId, $year, $month]
        );
    }

    public static function addPrime(int $profileId, int $year, int $month, string $label, int $amountCents): int
    {
        return Database::insert(
            'INSERT INTO employment_primes (profile_id, period_year, period_month, label, amount_cents) VALUES (?,?,?,?,?)',
            [$profileId, $year, $month, $label, $amountCents]
        );
    }

    public static function deletePrime(int $id, int $profileId): void
    {
        Database::execute('DELETE FROM employment_primes WHERE id=? AND profile_id=?', [$id, $profileId]);
    }

    // ── Paie estimée ──────────────────────────────────────────────

    public static function getPayslip(int $profileId, int $year, int $month): ?array
    {
        return Database::fetch(
            'SELECT * FROM employment_payslips WHERE profile_id=? AND period_year=? AND period_month=?',
            [$profileId, $year, $month]
        );
    }

    public static function getPayslips(int $profileId): array
    {
        return Database::fetchAll(
            'SELECT * FROM employment_payslips WHERE profile_id=? ORDER BY period_year DESC, period_month DESC',
            [$profileId]
        );
    }

    /** Calcule (ou recalcule) l'estimation de paie d'un mois et l'enregistre. */
    public static function computePayslip(array $profile, int $year, int $month): array
    {
        $profileId = (int)$profile['id'];
        $from = sprintf('%d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));

        $workedHours = self::workedHoursInRange($profileId, $from, $to);

        $contractualMonthlyHours = round((float)$profile['contractual_weekly_hours'] * 52 / 12, 2);
        $overtimeMonthlyThreshold = round((float)$profile['overtime_threshold_hours'] * 52 / 12, 2);

        $overtimeTotal = max(0, $workedHours - $contractualMonthlyHours);
        $tier1Hours = min($overtimeTotal, $overtimeMonthlyThreshold);
        $tier2Hours = max(0, $overtimeTotal - $overtimeMonthlyThreshold);
        $regularHours = max(0, $workedHours - $tier1Hours - $tier2Hours);

        $hourlyRate = (int)($profile['hourly_rate_cents'] ?? 0);

        if ($profile['pay_mode'] === 'monthly' && !empty($profile['monthly_gross_cents'])) {
            $baseGross = (int)$profile['monthly_gross_cents'];
        } else {
            $baseGross = (int)round($regularHours * $hourlyRate);
        }
        $overtimeGross = (int)round($tier1Hours * $hourlyRate * (1 + (float)$profile['overtime_rate1_pct'] / 100))
                        + (int)round($tier2Hours * $hourlyRate * (1 + (float)$profile['overtime_rate2_pct'] / 100));

        $primesCents = (int)array_sum(array_column(self::getPrimes($profileId, $year, $month), 'amount_cents'));

        $grossTotal = $baseGross + $overtimeGross + $primesCents;

        $cotisationRate = $profile['cotisation_rate_pct'] !== null ? (float)$profile['cotisation_rate_pct'] : 0;
        $netSocial = (int)round($grossTotal * (1 - $cotisationRate / 100));

        $pasRate = $profile['pas_rate_pct'] !== null ? (float)$profile['pas_rate_pct'] : 0;
        $netAVerser = (int)round($netSocial * (1 - $pasRate / 100));

        Database::execute(
            'INSERT INTO employment_payslips
             (profile_id, period_year, period_month, worked_hours, overtime_tier1_hours, overtime_tier2_hours,
              base_gross_cents, overtime_gross_cents, primes_cents, gross_total_cents, cotisation_rate_pct, net_social_cents, pas_rate_pct, net_a_verser_cents, computed_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE worked_hours=VALUES(worked_hours), overtime_tier1_hours=VALUES(overtime_tier1_hours), overtime_tier2_hours=VALUES(overtime_tier2_hours),
              base_gross_cents=VALUES(base_gross_cents), overtime_gross_cents=VALUES(overtime_gross_cents), primes_cents=VALUES(primes_cents), gross_total_cents=VALUES(gross_total_cents),
              cotisation_rate_pct=VALUES(cotisation_rate_pct), net_social_cents=VALUES(net_social_cents), pas_rate_pct=VALUES(pas_rate_pct), net_a_verser_cents=VALUES(net_a_verser_cents), computed_at=NOW()',
            [$profileId, $year, $month, $workedHours, $tier1Hours, $tier2Hours, $baseGross, $overtimeGross, $primesCents, $grossTotal, $cotisationRate, $netSocial, $pasRate, $netAVerser]
        );

        return self::getPayslip($profileId, $year, $month);
    }

    // ── Absences (calendrier, employment_leave_type='unpaid') ──────

    public static function getUnpaidAbsences(int $profileId): array
    {
        return Database::fetchAll(
            "SELECT * FROM events WHERE employment_profile_id=? AND employment_leave_type='unpaid' ORDER BY start_datetime DESC",
            [$profileId]
        );
    }

    // ── Arrêts de travail ──────────────────────────────────────────

    public static function getSickLeaves(int $profileId): array
    {
        return Database::fetchAll('SELECT * FROM employment_sick_leaves WHERE profile_id=? ORDER BY start_date DESC', [$profileId]);
    }

    public static function getSickLeaveById(int $id, int $profileId): ?array
    {
        return Database::fetch('SELECT * FROM employment_sick_leaves WHERE id=? AND profile_id=?', [$id, $profileId]);
    }

    public static function addSickLeave(int $profileId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO employment_sick_leaves (profile_id, start_date, end_date, reason, ijss_total_cents, employer_complement_cents, notes, created_by) VALUES (?,?,?,?,?,?,?,?)',
            [$profileId, $d['start_date'], $d['end_date'], $d['reason'], $d['ijss_total_cents'], $d['employer_complement_cents'], $d['notes'], $userId]
        );
    }

    public static function updateSickLeave(int $id, int $profileId, array $d): void
    {
        Database::execute(
            'UPDATE employment_sick_leaves SET start_date=?, end_date=?, reason=?, ijss_total_cents=?, employer_complement_cents=?, notes=? WHERE id=? AND profile_id=?',
            [$d['start_date'], $d['end_date'], $d['reason'], $d['ijss_total_cents'], $d['employer_complement_cents'], $d['notes'], $id, $profileId]
        );
    }

    public static function deleteSickLeave(int $id, int $profileId): void
    {
        Database::execute('DELETE FROM employment_sick_leaves WHERE id=? AND profile_id=?', [$id, $profileId]);
    }

    // ── Documents liés ──────────────────────────────────────────────

    public static function getLinkedDocuments(int $profileId): array
    {
        return Database::fetchAll(
            'SELECT d.id, d.title FROM employment_documents ed JOIN documents d ON d.id=ed.document_id
             WHERE ed.profile_id=? ORDER BY d.title',
            [$profileId]
        );
    }

    public static function setLinkedDocuments(int $profileId, array $documentIds): void
    {
        Database::execute('DELETE FROM employment_documents WHERE profile_id=?', [$profileId]);
        foreach (array_unique(array_map('intval', $documentIds)) as $docId) {
            Database::execute('INSERT IGNORE INTO employment_documents (profile_id, document_id) VALUES (?,?)', [$profileId, $docId]);
        }
    }
}

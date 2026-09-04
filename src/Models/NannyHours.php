<?php
namespace App\Models;

use App\Core\Database;

/** Suivi des heures de nounou/baby-sitter, saisi manuellement (jour + heures), lié en option
 *  à un enfant de la famille (répertoire propre au module, comme SchoolStudent). */
class NannyHours
{
    // ── Enfants ──────────────────────────────────────────────────

    public static function getChildrenByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM nanny_children WHERE family_id=? ORDER BY name', [$familyId]);
    }

    public static function getChildById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM nanny_children WHERE id=?', [$id]);
    }

    public static function createChild(int $familyId, int $createdBy, array $d): int
    {
        return Database::insert(
            'INSERT INTO nanny_children (family_id, name, color, created_by) VALUES (?,?,?,?)',
            [$familyId, $d['name'], $d['color'], $createdBy]
        );
    }

    public static function updateChild(int $id, int $familyId, array $d): void
    {
        Database::execute('UPDATE nanny_children SET name=?, color=? WHERE id=? AND family_id=?', [$d['name'], $d['color'], $id, $familyId]);
    }

    public static function deleteChild(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM nanny_children WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    // ── Entrées d'heures ─────────────────────────────────────────

    public static function getEntryById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM nanny_hours_entries WHERE id=?', [$id]);
    }

    /** @param array{child_id:?int,year:?int,month:?int} $filters */
    public static function getEntries(int $familyId, array $filters = []): array
    {
        $sql = 'SELECT e.*, c.name AS child_name, c.color AS child_color
                FROM nanny_hours_entries e
                LEFT JOIN nanny_children c ON c.id = e.child_id
                WHERE e.family_id=?';
        $params = [$familyId];
        if (!empty($filters['child_id'])) {
            $sql .= ' AND e.child_id=?';
            $params[] = (int)$filters['child_id'];
        }
        if (!empty($filters['year'])) {
            $sql .= ' AND YEAR(e.entry_date)=?';
            $params[] = (int)$filters['year'];
        }
        if (!empty($filters['month'])) {
            $sql .= ' AND MONTH(e.entry_date)=?';
            $params[] = (int)$filters['month'];
        }
        $sql .= ' ORDER BY e.entry_date DESC, e.id DESC';
        return Database::fetchAll($sql, $params);
    }

    public static function addEntry(int $familyId, int $createdBy, array $d): int
    {
        return Database::insert(
            'INSERT INTO nanny_hours_entries (family_id, child_id, entry_date, hours, nanny_name, notes, created_by) VALUES (?,?,?,?,?,?,?)',
            [$familyId, $d['child_id'], $d['entry_date'], $d['hours'], $d['nanny_name'], $d['notes'], $createdBy]
        );
    }

    public static function updateEntry(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE nanny_hours_entries SET child_id=?, entry_date=?, hours=?, nanny_name=?, notes=? WHERE id=? AND family_id=?',
            [$d['child_id'], $d['entry_date'], $d['hours'], $d['nanny_name'], $d['notes'], $id, $familyId]
        );
    }

    public static function deleteEntry(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM nanny_hours_entries WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    // ── Totaux ───────────────────────────────────────────────────

    public static function monthlyTotal(int $familyId, int $year, int $month, ?int $childId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(hours),0) t FROM nanny_hours_entries WHERE family_id=? AND YEAR(entry_date)=? AND MONTH(entry_date)=?';
        $params = [$familyId, $year, $month];
        if ($childId) { $sql .= ' AND child_id=?'; $params[] = $childId; }
        return (float)(Database::fetch($sql, $params)['t'] ?? 0);
    }

    public static function annualTotal(int $familyId, int $year, ?int $childId = null): float
    {
        $sql = 'SELECT COALESCE(SUM(hours),0) t FROM nanny_hours_entries WHERE family_id=? AND YEAR(entry_date)=?';
        $params = [$familyId, $year];
        if ($childId) { $sql .= ' AND child_id=?'; $params[] = $childId; }
        return (float)(Database::fetch($sql, $params)['t'] ?? 0);
    }

    /** Total par mois (index 1..12) pour l'année donnée — sert au tableau récapitulatif et au
     *  rapport PDF annuel. */
    public static function monthlyBreakdown(int $familyId, int $year, ?int $childId = null): array
    {
        $sql = 'SELECT MONTH(entry_date) m, COALESCE(SUM(hours),0) t FROM nanny_hours_entries WHERE family_id=? AND YEAR(entry_date)=?';
        $params = [$familyId, $year];
        if ($childId) { $sql .= ' AND child_id=?'; $params[] = $childId; }
        $sql .= ' GROUP BY MONTH(entry_date)';
        $rows = Database::fetchAll($sql, $params);
        $breakdown = array_fill(1, 12, 0.0);
        foreach ($rows as $r) {
            $breakdown[(int)$r['m']] = (float)$r['t'];
        }
        return $breakdown;
    }

    /** Années distinctes ayant au moins une entrée, pour peupler le sélecteur de période. */
    public static function getYearsWithEntries(int $familyId): array
    {
        $rows = Database::fetchAll('SELECT DISTINCT YEAR(entry_date) y FROM nanny_hours_entries WHERE family_id=? ORDER BY y DESC', [$familyId]);
        $years = array_map(fn($r) => (int)$r['y'], $rows);
        $currentYear = (int)date('Y');
        if (!in_array($currentYear, $years, true)) {
            array_unshift($years, $currentYear);
        }
        return $years;
    }
}

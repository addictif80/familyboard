<?php
namespace App\Models;

use App\Core\Database;

class Health
{
    public const ENTRY_TYPES = [
        'vaccination' => ['label' => 'Vaccination', 'icon' => '💉'],
        'allergie'    => ['label' => 'Allergie',    'icon' => '⚠️'],
        'traitement'  => ['label' => 'Traitement',  'icon' => '💊'],
        'antecedent'  => ['label' => 'Antécédent',  'icon' => '📋'],
        'autre'       => ['label' => 'Autre',       'icon' => '🏥'],
    ];

    /** Membres (users, hors coparent) + enfants du registre familial — les deux "sujets"
     *  possibles d'une fiche santé. */
    public static function getSubjects(int $familyId): array
    {
        $members = array_map(fn($m) => ['type' => 'user', 'id' => (int)$m['id'], 'name' => $m['name'], 'color' => $m['color']],
            array_filter(User::getByFamily($familyId), fn($m) => $m['role'] !== 'coparent'));
        $children = array_map(fn($c) => ['type' => 'child', 'id' => (int)$c['id'], 'name' => $c['name'], 'color' => $c['color']],
            FamilyChild::getByFamily($familyId));
        return array_merge($members, $children);
    }

    public static function subjectLabel(int $familyId, string $type, int $id): ?array
    {
        foreach (self::getSubjects($familyId) as $s) {
            if ($s['type'] === $type && $s['id'] === $id) return $s;
        }
        return null;
    }

    // ── Carnet de santé ──────────────────────────────────────────

    public static function getEntries(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM health_entries WHERE family_id=? ORDER BY entry_date DESC, id DESC', [$familyId]);
    }

    public static function getEntryById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM health_entries WHERE id=?', [$id]);
    }

    public static function addEntry(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO health_entries (family_id, subject_type, subject_id, entry_type, title, description, entry_date, reminder_date, created_by) VALUES (?,?,?,?,?,?,?,?,?)',
            [$familyId, $d['subject_type'], $d['subject_id'], $d['entry_type'], $d['title'], $d['description'], $d['entry_date'], $d['reminder_date'], $userId]
        );
    }

    public static function deleteEntry(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM health_entries WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    // ── Croissance ───────────────────────────────────────────────

    public static function getGrowth(int $familyId, int $childId): array
    {
        return Database::fetchAll('SELECT * FROM health_growth WHERE family_id=? AND child_id=? ORDER BY measured_at', [$familyId, $childId]);
    }

    public static function getGrowthEntryById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM health_growth WHERE id=?', [$id]);
    }

    public static function addGrowth(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO health_growth (family_id, child_id, measured_at, height_cm, weight_kg, notes, created_by) VALUES (?,?,?,?,?,?,?)',
            [$familyId, $d['child_id'], $d['measured_at'], $d['height_cm'], $d['weight_kg'], $d['notes'], $userId]
        );
    }

    public static function deleteGrowth(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM health_growth WHERE id=? AND family_id=?', [$id, $familyId]);
    }

    // ── Carnet médical ───────────────────────────────────────────

    public static function getDoctors(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM health_doctors WHERE family_id=? ORDER BY name', [$familyId]);
    }

    public static function getDoctorById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM health_doctors WHERE id=?', [$id]);
    }

    public static function addDoctor(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO health_doctors (family_id, subject_type, subject_id, name, specialty, phone, address, next_appointment, prescription_renewal_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$familyId, $d['subject_type'], $d['subject_id'], $d['name'], $d['specialty'], $d['phone'], $d['address'], $d['next_appointment'], $d['prescription_renewal_date'], $d['notes'], $userId]
        );
    }

    public static function updateDoctor(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE health_doctors SET subject_type=?, subject_id=?, name=?, specialty=?, phone=?, address=?, next_appointment=?, prescription_renewal_date=?, notes=? WHERE id=? AND family_id=?',
            [$d['subject_type'], $d['subject_id'], $d['name'], $d['specialty'], $d['phone'], $d['address'], $d['next_appointment'], $d['prescription_renewal_date'], $d['notes'], $id, $familyId]
        );
    }

    public static function deleteDoctor(int $id, int $familyId): void
    {
        Database::execute('DELETE FROM health_doctors WHERE id=? AND family_id=?', [$id, $familyId]);
    }
}

<?php
namespace App\Models;

use App\Core\Database;

/** Traitement médical en cours — privé au membre concerné (owner_type='user') ou géré par un
 *  admin au nom d'un enfant du registre partagé (owner_type='child', voir FamilyChild) — jamais
 *  visible des autres membres adultes de la famille. Distinct du module "Santé" (Health),
 *  partagé à toute la famille. Les rappels de prise sont envoyés par notification push (voir
 *  cron.php, sendMedicalTreatmentReminders) — jamais par e-mail. */
class MedicalTreatment
{
    public static function getForUser(int $familyId, int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM medical_treatments WHERE family_id=? AND owner_type='user' AND owner_user_id=? ORDER BY active DESC, start_date DESC",
            [$familyId, $userId]
        );
    }

    public static function getForChild(int $familyId, int $childId): array
    {
        return Database::fetchAll(
            "SELECT * FROM medical_treatments WHERE family_id=? AND owner_type='child' AND owner_child_id=? ORDER BY active DESC, start_date DESC",
            [$familyId, $childId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM medical_treatments WHERE id=?', [$id]);
    }

    /** Traitements actifs de toute la famille, avec le nom du sujet (membre ou enfant) déjà
     *  résolu — pour le cron de rappels (voir cron.php), qui ne doit jamais interroger users/
     *  family_children une fois par traitement. */
    public static function getActiveForFamily(int $familyId): array
    {
        return Database::fetchAll(
            "SELECT t.*, COALESCE(u.name, c.name) AS subject_name
             FROM medical_treatments t
             LEFT JOIN users u ON u.id = t.owner_user_id AND t.owner_type='user'
             LEFT JOIN family_children c ON c.id = t.owner_child_id AND t.owner_type='child'
             WHERE t.family_id=? AND t.active=1
               AND t.start_date <= CURDATE()
               AND (t.end_date IS NULL OR t.end_date >= CURDATE())",
            [$familyId]
        );
    }

    public static function create(int $familyId, int $createdBy, array $d): int
    {
        return Database::insert(
            'INSERT INTO medical_treatments
                (family_id, owner_type, owner_user_id, owner_child_id, created_by, name, dosage, notes,
                 start_date, end_date, schedule_type, fixed_times, interval_hours, interval_anchor)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $familyId, $d['owner_type'], $d['owner_user_id'] ?: null, $d['owner_child_id'] ?: null, $createdBy,
                $d['name'], $d['dosage'], $d['notes'] ?: null, $d['start_date'], $d['end_date'] ?: null,
                $d['schedule_type'], $d['fixed_times'] !== null ? json_encode($d['fixed_times']) : null,
                $d['interval_hours'] ?: null, $d['interval_anchor'] ?: null,
            ]
        );
    }

    public static function update(int $id, array $d): void
    {
        Database::execute(
            'UPDATE medical_treatments SET name=?, dosage=?, notes=?, start_date=?, end_date=?,
                schedule_type=?, fixed_times=?, interval_hours=?, interval_anchor=?, active=? WHERE id=?',
            [
                $d['name'], $d['dosage'], $d['notes'] ?: null, $d['start_date'], $d['end_date'] ?: null,
                $d['schedule_type'], $d['fixed_times'] !== null ? json_encode($d['fixed_times']) : null,
                $d['interval_hours'] ?: null, $d['interval_anchor'] ?: null, $d['active'] ? 1 : 0, $id,
            ]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM medical_treatments WHERE id=?', [$id]);
    }

    /** true si ce créneau (slot_key, aujourd'hui) a déjà été notifié — voir
     *  medical_treatment_reminders, purement pour l'idempotence du cron. */
    public static function reminderAlreadySent(int $treatmentId, string $slotKey): bool
    {
        return (bool)Database::fetch(
            'SELECT id FROM medical_treatment_reminders WHERE treatment_id=? AND slot_key=? AND reminded_date=CURDATE()',
            [$treatmentId, $slotKey]
        );
    }

    public static function markReminderSent(int $treatmentId, string $slotKey): void
    {
        try {
            Database::execute(
                'INSERT INTO medical_treatment_reminders (treatment_id, slot_key, reminded_date) VALUES (?,?,CURDATE())',
                [$treatmentId, $slotKey]
            );
        } catch (\Throwable $e) {
            // Contrainte UNIQUE déjà en place (course entre deux passages de cron) — sans
            // conséquence, c'est exactement ce que cette table doit empêcher.
        }
    }
}

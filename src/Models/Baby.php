<?php
namespace App\Models;

use App\Core\Database;

class Baby
{
    // ── Babies ────────────────────────────────────────────────

    public static function getAllByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM babies WHERE family_id = ? ORDER BY birth_date ASC, created_at ASC',
            [$familyId]
        );
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM babies WHERE id = ?', [$id]);
    }

    public static function create(int $familyId, string $name, ?int $familyChildId = null): int
    {
        return Database::insert(
            'INSERT INTO babies (family_id, name, family_child_id) VALUES (?, ?, ?)',
            [$familyId, $name, $familyChildId]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE babies SET name=?, birth_date=?, birth_time=?, birth_weight=?, birth_height=?, birth_details=? WHERE id=?',
            [
                $data['name'],
                $data['birth_date'] ?: null,
                $data['birth_time'] ?: null,
                isset($data['birth_weight']) && $data['birth_weight'] !== '' ? (float)$data['birth_weight'] : null,
                isset($data['birth_height']) && $data['birth_height'] !== '' ? (float)$data['birth_height'] : null,
                $data['birth_details'] ?: null,
                $id,
            ]
        );
    }

    public static function updateAvatar(int $id, string $path): void
    {
        Database::execute('UPDATE babies SET avatar=? WHERE id=?', [$path, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM babies WHERE id=?', [$id]);
    }

    // ── Pregnancies ───────────────────────────────────────────

    public static function getPregnancy(int $familyId, ?int $babyId): ?array
    {
        if ($babyId) {
            return Database::fetch(
                'SELECT * FROM pregnancies WHERE family_id=? AND baby_id=? ORDER BY id DESC LIMIT 1',
                [$familyId, $babyId]
            );
        }
        return Database::fetch(
            'SELECT * FROM pregnancies WHERE family_id=? AND baby_id IS NULL ORDER BY id DESC LIMIT 1',
            [$familyId]
        );
    }

    public static function getPregnancyById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM pregnancies WHERE id=?', [$id]);
    }

    public static function upsertPregnancy(int $familyId, ?int $babyId, array $data): int
    {
        $existing = self::getPregnancy($familyId, $babyId);
        $lmp  = $data['lmp_date']  ?: null;
        $due  = $data['due_date']  ?: null;
        $notes = $data['notes'] ?: null;

        if ($lmp && !$due) {
            $dt = new \DateTime($lmp);
            $dt->modify('+280 days');
            $due = $dt->format('Y-m-d');
        }

        if ($existing) {
            Database::execute(
                'UPDATE pregnancies SET lmp_date=?, due_date=?, notes=?, baby_id=? WHERE id=?',
                [$lmp, $due, $notes, $babyId, $existing['id']]
            );
            return $existing['id'];
        }

        return Database::insert(
            'INSERT INTO pregnancies (family_id, baby_id, lmp_date, due_date, notes) VALUES (?,?,?,?,?)',
            [$familyId, $babyId, $lmp, $due, $notes]
        );
    }

    // ── Pregnancy consultations ───────────────────────────────

    public static function getConsultationsByPregnancy(int $pregnancyId): array
    {
        return Database::fetchAll(
            'SELECT pc.*, GROUP_CONCAT(pi.id ORDER BY pi.id SEPARATOR ",") AS image_ids,
                    GROUP_CONCAT(pi.file_path ORDER BY pi.id SEPARATOR "||") AS image_paths,
                    GROUP_CONCAT(pi.original_name ORDER BY pi.id SEPARATOR "||") AS image_names
             FROM pregnancy_consultations pc
             LEFT JOIN pregnancy_images pi ON pi.consultation_id = pc.id
             WHERE pc.pregnancy_id = ?
             GROUP BY pc.id
             ORDER BY pc.consult_date DESC',
            [$pregnancyId]
        );
    }

    public static function getPregnancyConsultationById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM pregnancy_consultations WHERE id=?', [$id]);
    }

    public static function createPregnancyConsultation(int $pregnancyId, int $familyId, array $data): int
    {
        return Database::insert(
            'INSERT INTO pregnancy_consultations (pregnancy_id, family_id, practitioner, consult_date, details) VALUES (?,?,?,?,?)',
            [$pregnancyId, $familyId, $data['practitioner'] ?: null, $data['consult_date'], $data['details'] ?: null]
        );
    }

    public static function updatePregnancyConsultation(int $id, array $data): void
    {
        Database::execute(
            'UPDATE pregnancy_consultations SET practitioner=?, consult_date=?, details=? WHERE id=?',
            [$data['practitioner'] ?: null, $data['consult_date'], $data['details'] ?: null, $id]
        );
    }

    public static function deletePregnancyConsultation(int $id): void
    {
        Database::execute('DELETE FROM pregnancy_consultations WHERE id=?', [$id]);
    }

    public static function addPregnancyImage(int $consultationId, int $familyId, string $filePath, string $originalName): int
    {
        return Database::insert(
            'INSERT INTO pregnancy_images (consultation_id, family_id, file_path, original_name) VALUES (?,?,?,?)',
            [$consultationId, $familyId, $filePath, $originalName]
        );
    }

    public static function getPregnancyImageById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM pregnancy_images WHERE id=?', [$id]);
    }

    public static function deletePregnancyImage(int $id): void
    {
        Database::execute('DELETE FROM pregnancy_images WHERE id=?', [$id]);
    }

    // ── Baby events ───────────────────────────────────────────

    public static function getEventsByDay(int $babyId, string $date): array
    {
        return Database::fetchAll(
            'SELECT * FROM baby_events WHERE baby_id=? AND DATE(event_at)=? ORDER BY event_at ASC',
            [$babyId, $date]
        );
    }

    public static function getRecentEvents(int $babyId, int $days = 7): array
    {
        return Database::fetchAll(
            'SELECT * FROM baby_events WHERE baby_id=? AND event_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY event_at DESC',
            [$babyId, $days]
        );
    }

    public static function createEvent(int $babyId, int $familyId, array $data): int
    {
        return Database::insert(
            'INSERT INTO baby_events (baby_id, family_id, type, event_at, quantity, notes) VALUES (?,?,?,?,?,?)',
            [
                $babyId, $familyId,
                $data['type'],
                $data['event_at'],
                isset($data['quantity']) && $data['quantity'] !== '' ? (float)$data['quantity'] : null,
                $data['notes'] ?: null,
            ]
        );
    }

    public static function getEventById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM baby_events WHERE id=?', [$id]);
    }

    public static function updateEvent(int $id, array $data): void
    {
        Database::execute(
            'UPDATE baby_events SET type=?, event_at=?, quantity=?, notes=? WHERE id=?',
            [
                $data['type'],
                $data['event_at'],
                isset($data['quantity']) && $data['quantity'] !== '' ? (float)$data['quantity'] : null,
                $data['notes'] ?: null,
                $id,
            ]
        );
    }

    public static function deleteEvent(int $id): void
    {
        Database::execute('DELETE FROM baby_events WHERE id=?', [$id]);
    }

    // ── Baby sleeps ───────────────────────────────────────────

    public static function getSleepsByDay(int $babyId, string $date): array
    {
        return Database::fetchAll(
            'SELECT * FROM baby_sleeps WHERE baby_id=? AND DATE(start_at)=? ORDER BY start_at ASC',
            [$babyId, $date]
        );
    }

    public static function getActiveSleep(int $babyId): ?array
    {
        return Database::fetch(
            'SELECT * FROM baby_sleeps WHERE baby_id=? AND end_at IS NULL ORDER BY start_at DESC LIMIT 1',
            [$babyId]
        );
    }

    public static function getRecentSleeps(int $babyId, int $days = 7): array
    {
        return Database::fetchAll(
            'SELECT * FROM baby_sleeps WHERE baby_id=? AND start_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY start_at DESC',
            [$babyId, $days]
        );
    }

    public static function createSleep(int $babyId, int $familyId, string $startAt, ?string $endAt, ?string $notes): int
    {
        return Database::insert(
            'INSERT INTO baby_sleeps (baby_id, family_id, start_at, end_at, notes) VALUES (?,?,?,?,?)',
            [$babyId, $familyId, $startAt, $endAt ?: null, $notes ?: null]
        );
    }

    public static function getSleepById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM baby_sleeps WHERE id=?', [$id]);
    }

    public static function updateSleep(int $id, string $startAt, ?string $endAt, ?string $notes): void
    {
        Database::execute(
            'UPDATE baby_sleeps SET start_at=?, end_at=?, notes=? WHERE id=?',
            [$startAt, $endAt ?: null, $notes ?: null, $id]
        );
    }

    public static function endSleep(int $id, string $endAt): void
    {
        Database::execute('UPDATE baby_sleeps SET end_at=? WHERE id=?', [$endAt, $id]);
    }

    public static function deleteSleep(int $id): void
    {
        Database::execute('DELETE FROM baby_sleeps WHERE id=?', [$id]);
    }

    // ── Baby consultations ────────────────────────────────────

    public static function getConsultationsByBaby(int $babyId): array
    {
        return Database::fetchAll(
            'SELECT * FROM baby_consultations WHERE baby_id=? ORDER BY consult_at DESC',
            [$babyId]
        );
    }

    public static function getConsultationById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM baby_consultations WHERE id=?', [$id]);
    }

    public static function createConsultation(int $babyId, int $familyId, array $data): int
    {
        return Database::insert(
            'INSERT INTO baby_consultations (baby_id, family_id, practitioner, consult_at, details) VALUES (?,?,?,?,?)',
            [$babyId, $familyId, $data['practitioner'] ?: null, $data['consult_at'], $data['details'] ?: null]
        );
    }

    public static function updateConsultation(int $id, array $data): void
    {
        Database::execute(
            'UPDATE baby_consultations SET practitioner=?, consult_at=?, details=? WHERE id=?',
            [$data['practitioner'] ?: null, $data['consult_at'], $data['details'] ?: null, $id]
        );
    }

    public static function deleteConsultation(int $id): void
    {
        Database::execute('DELETE FROM baby_consultations WHERE id=?', [$id]);
    }

    // ── Stats helpers ─────────────────────────────────────────

    /** Horodatage du dernier événement de chaque type (feeding, diaper, stool, urine, bath...),
     *  pour un affichage "dernier repas il y a 2h" plutôt qu'un simple compteur du jour. */
    public static function getLastEventTimes(int $babyId): array
    {
        $rows = Database::fetchAll(
            'SELECT type, MAX(event_at) as last_at FROM baby_events WHERE baby_id=? GROUP BY type',
            [$babyId]
        );
        $out = [];
        foreach ($rows as $r) $out[$r['type']] = $r['last_at'];
        return $out;
    }

    public static function getDailyStats(int $babyId, string $date): array
    {
        $events = self::getEventsByDay($babyId, $date);
        $sleeps = self::getSleepsByDay($babyId, $date);

        $stats = ['feeding' => 0, 'stool' => 0, 'urine' => 0, 'diaper' => 0, 'bath' => 0,
                  'feeding_ml' => 0, 'sleep_min' => 0];

        foreach ($events as $e) {
            $stats[$e['type']]++;
            if ($e['type'] === 'feeding' && $e['quantity']) {
                $stats['feeding_ml'] += (float)$e['quantity'];
            }
        }
        foreach ($sleeps as $s) {
            if ($s['end_at']) {
                $diff = (strtotime($s['end_at']) - strtotime($s['start_at'])) / 60;
                $stats['sleep_min'] += max(0, (int)$diff);
            }
        }
        return $stats;
    }
}

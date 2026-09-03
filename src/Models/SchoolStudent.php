<?php
namespace App\Models;

use App\Core\Database;
use App\Core\OcrHelper;

class SchoolStudent
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll('SELECT * FROM school_students WHERE family_id=? ORDER BY name', [$familyId]);
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM school_students WHERE id=?', [$id]);
    }

    /** Fiches élève dont ce compte est LE compte co-parent lié — un co-parent ne voit jamais la
     *  liste complète des enfants de la famille, seulement celles où il est explicitement lié. */
    public static function getByLinkedCoparent(int $userId): array
    {
        return Database::fetchAll('SELECT * FROM school_students WHERE linked_coparent_id=? ORDER BY name', [$userId]);
    }

    public static function isReadOnlyFor(array $student, array $user): bool
    {
        if ((int)$user['id'] === (int)($student['linked_user_id'] ?? 0)) return true;
        if ((int)$user['id'] === (int)($student['linked_coparent_id'] ?? 0)) return true;
        return false;
    }

    public static function create(int $familyId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO school_students (family_id, name, school_name, class_name, color, created_by) VALUES (?,?,?,?,?,?)',
            [$familyId, $d['name'], $d['school_name'], $d['class_name'], $d['color'], $userId]
        );
    }

    public static function update(int $id, int $familyId, array $d): void
    {
        Database::execute(
            'UPDATE school_students SET name=?, school_name=?, class_name=?, color=? WHERE id=? AND family_id=?',
            [$d['name'], $d['school_name'], $d['class_name'], $d['color'], $id, $familyId]
        );
    }

    /** Liens (compte élève, co-parent, liste de tâches) — séparé de update() : validés/résolus
     *  par le contrôleur (appartenance à la famille) avant d'arriver ici. */
    public static function updateLinks(int $id, int $familyId, ?int $linkedUserId, ?int $linkedCoparentId, ?int $linkedTaskListId): void
    {
        Database::execute(
            'UPDATE school_students SET linked_user_id=?, linked_coparent_id=?, linked_task_list_id=? WHERE id=? AND family_id=?',
            [$linkedUserId, $linkedCoparentId, $linkedTaskListId, $id, $familyId]
        );
    }

    public static function getLinkedDocuments(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT d.id, d.title FROM school_student_documents sd JOIN documents d ON d.id=sd.document_id
             WHERE sd.student_id=? ORDER BY d.title',
            [$studentId]
        );
    }

    /** Remplace l'ensemble des documents liés (les ids hors famille sont filtrés par le contrôleur
     *  avant l'appel). */
    public static function setLinkedDocuments(int $studentId, array $documentIds): void
    {
        Database::execute('DELETE FROM school_student_documents WHERE student_id=?', [$studentId]);
        foreach (array_unique(array_map('intval', $documentIds)) as $docId) {
            Database::execute('INSERT IGNORE INTO school_student_documents (student_id, document_id) VALUES (?,?)', [$studentId, $docId]);
        }
    }

    public static function delete(int $id, int $familyId): void
    {
        $docs = self::getDocuments($id);
        Database::execute('DELETE FROM school_students WHERE id=? AND family_id=?', [$id, $familyId]);
        foreach ($docs as $doc) {
            $abs = BASE_PATH . $doc['file_path'];
            if (is_file($abs)) @unlink($abs);
        }
    }

    // ── Matières & professeurs ────────────────────────────────────

    public static function getSubjects(int $studentId): array
    {
        return Database::fetchAll('SELECT * FROM school_subjects WHERE student_id=? ORDER BY name', [$studentId]);
    }

    public static function getSubjectById(int $id, int $studentId): ?array
    {
        return Database::fetch('SELECT * FROM school_subjects WHERE id=? AND student_id=?', [$id, $studentId]);
    }

    public static function addSubject(int $studentId, array $d): int
    {
        return Database::insert(
            'INSERT INTO school_subjects (student_id, name, teacher_name, color) VALUES (?,?,?,?)',
            [$studentId, $d['name'], $d['teacher_name'], $d['color']]
        );
    }

    public static function updateSubject(int $id, int $studentId, array $d): void
    {
        Database::execute(
            'UPDATE school_subjects SET name=?, teacher_name=?, color=? WHERE id=? AND student_id=?',
            [$d['name'], $d['teacher_name'], $d['color'], $id, $studentId]
        );
    }

    public static function deleteSubject(int $id, int $studentId): void
    {
        Database::execute('DELETE FROM school_subjects WHERE id=? AND student_id=?', [$id, $studentId]);
    }

    // ── Emploi du temps ────────────────────────────────────────────

    public static function getTimetable(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT t.*, s.name as subject_name, s.teacher_name, s.color as subject_color
             FROM school_timetable_slots t JOIN school_subjects s ON s.id=t.subject_id
             WHERE t.student_id=? ORDER BY t.day_of_week, t.start_time',
            [$studentId]
        );
    }

    public static function addTimetableSlot(int $studentId, array $d): int
    {
        return Database::insert(
            'INSERT INTO school_timetable_slots (student_id, subject_id, day_of_week, start_time, end_time, room) VALUES (?,?,?,?,?,?)',
            [$studentId, $d['subject_id'], $d['day_of_week'], $d['start_time'], $d['end_time'], $d['room']]
        );
    }

    public static function deleteTimetableSlot(int $id, int $studentId): void
    {
        Database::execute('DELETE FROM school_timetable_slots WHERE id=? AND student_id=?', [$id, $studentId]);
    }

    // ── Notes ──────────────────────────────────────────────────────

    public static function getGrades(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT g.*, s.name as subject_name, s.color as subject_color, u.name as author_name
             FROM school_grades g JOIN school_subjects s ON s.id=g.subject_id JOIN users u ON u.id=g.created_by
             WHERE g.student_id=? ORDER BY g.grade_date DESC, g.id DESC',
            [$studentId]
        );
    }

    /** Moyenne par matière (sur 20, ramenée depuis grade_max) et moyenne générale. */
    public static function getAverages(int $studentId): array
    {
        $rows = Database::fetchAll(
            'SELECT s.id, s.name, s.color, AVG(g.grade_value / g.grade_max * 20) as average
             FROM school_subjects s LEFT JOIN school_grades g ON g.subject_id=s.id
             WHERE s.student_id=? GROUP BY s.id, s.name, s.color ORDER BY s.name',
            [$studentId]
        );
        $withGrades = array_filter($rows, fn($r) => $r['average'] !== null);
        $overall = $withGrades ? array_sum(array_column($withGrades, 'average')) / count($withGrades) : null;
        return ['subjects' => $rows, 'overall' => $overall];
    }

    public static function addGrade(int $studentId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO school_grades (student_id, subject_id, title, grade_value, grade_max, grade_date, comment, created_by) VALUES (?,?,?,?,?,?,?,?)',
            [$studentId, $d['subject_id'], $d['title'], $d['grade_value'], $d['grade_max'], $d['grade_date'], $d['comment'], $userId]
        );
    }

    public static function deleteGrade(int $id, int $studentId): void
    {
        Database::execute('DELETE FROM school_grades WHERE id=? AND student_id=?', [$id, $studentId]);
    }

    // ── Absences ───────────────────────────────────────────────────

    public static function getAbsences(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT a.*, s.name as subject_name, u.name as author_name
             FROM school_absences a LEFT JOIN school_subjects s ON s.id=a.subject_id JOIN users u ON u.id=a.created_by
             WHERE a.student_id=? ORDER BY a.absence_date DESC, a.id DESC',
            [$studentId]
        );
    }

    public static function addAbsence(int $studentId, int $userId, array $d): int
    {
        return Database::insert(
            'INSERT INTO school_absences (student_id, subject_id, absence_date, duration, reason, justified, created_by) VALUES (?,?,?,?,?,?,?)',
            [$studentId, $d['subject_id'] ?: null, $d['absence_date'], $d['duration'], $d['reason'], $d['justified'] ? 1 : 0, $userId]
        );
    }

    public static function setAbsenceJustified(int $id, int $studentId, bool $justified): void
    {
        Database::execute('UPDATE school_absences SET justified=? WHERE id=? AND student_id=?', [(int)$justified, $id, $studentId]);
    }

    public static function deleteAbsence(int $id, int $studentId): void
    {
        Database::execute('DELETE FROM school_absences WHERE id=? AND student_id=?', [$id, $studentId]);
    }

    // ── Activités extra-scolaires ──────────────────────────────────

    public static function getActivities(int $studentId): array
    {
        return Database::fetchAll('SELECT * FROM school_activities WHERE student_id=? ORDER BY name', [$studentId]);
    }

    public static function addActivity(int $studentId, array $d): int
    {
        return Database::insert(
            'INSERT INTO school_activities (student_id, name, location, schedule_info, contact_info, notes) VALUES (?,?,?,?,?,?)',
            [$studentId, $d['name'], $d['location'], $d['schedule_info'], $d['contact_info'], $d['notes']]
        );
    }

    public static function deleteActivity(int $id, int $studentId): void
    {
        Database::execute('DELETE FROM school_activities WHERE id=? AND student_id=?', [$id, $studentId]);
    }

    // ── Documents & bulletins ──────────────────────────────────────

    public static function getDocuments(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT d.*, u.name as uploader_name FROM school_documents d JOIN users u ON u.id=d.uploaded_by
             WHERE d.student_id=? ORDER BY d.uploaded_at DESC',
            [$studentId]
        );
    }

    public static function getDocumentById(int $id, int $studentId): ?array
    {
        return Database::fetch('SELECT * FROM school_documents WHERE id=? AND student_id=?', [$id, $studentId]);
    }

    public static function addDocument(int $studentId, int $familyId, int $userId, string $title, string $docType, array $file): int
    {
        [$path, $original, $mime] = OcrHelper::saveUploadedFile($file, 'school', $familyId, OcrHelper::DISPUTE_DOC_MIMES);
        return Database::insert(
            'INSERT INTO school_documents (student_id, uploaded_by, title, doc_type, file_path, file_original, file_mime) VALUES (?,?,?,?,?,?,?)',
            [$studentId, $userId, $title, $docType, $path, $original, $mime]
        );
    }

    public static function deleteDocument(int $id, int $studentId): void
    {
        $doc = self::getDocumentById($id, $studentId);
        if (!$doc) return;
        Database::execute('DELETE FROM school_documents WHERE id=? AND student_id=?', [$id, $studentId]);
        $abs = BASE_PATH . $doc['file_path'];
        if (is_file($abs)) @unlink($abs);
    }
}

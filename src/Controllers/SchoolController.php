<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Document;
use App\Models\FamilyChild;
use App\Models\SchoolStudent;
use App\Models\TaskList;
use App\Models\User;

class SchoolController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth(true);
        $this->requireModule('school');
        $user = Session::user();
        $familyId = (int)$user['family_id'];
        $isCoparent = $user['role'] === 'coparent';

        // Un co-parent ne voit jamais la liste complète des enfants de la famille — seulement
        // les fiches où il a explicitement été lié (voir updateLinks()).
        $students = $isCoparent ? SchoolStudent::getByLinkedCoparent((int)$user['id']) : SchoolStudent::getByFamily($familyId);
        $familyChildren = $isCoparent ? [] : FamilyChild::getByFamily($familyId);

        $selectedId = (int)($_GET['id'] ?? 0);
        $selected = null;
        if ($selectedId) {
            $selected = SchoolStudent::getById($selectedId);
            if (!$selected || (int)$selected['family_id'] !== $familyId) $selected = null;
            if ($selected && $isCoparent && (int)($selected['linked_coparent_id'] ?? 0) !== (int)$user['id']) $selected = null;
        } elseif ($students) {
            $selected = $students[0];
            $selectedId = (int)$selected['id'];
        }

        $subjects = $timetable = $grades = $averages = $absences = $activities = $documents = [];
        $readOnly = false; // notes/absences/bulletins seuls, pour le compte membre OU co-parent lié
        $familyMembers = $familyCoparents = $familyTaskLists = $familyDocuments = [];
        $linkedTaskList = null;
        $linkedDocuments = [];
        if ($selected) {
            $subjects   = SchoolStudent::getSubjects($selectedId);
            $timetable  = SchoolStudent::getTimetable($selectedId);
            $grades     = SchoolStudent::getGrades($selectedId);
            $averages   = SchoolStudent::getAverages($selectedId);
            $absences   = SchoolStudent::getAbsences($selectedId);
            $activities = SchoolStudent::getActivities($selectedId);
            $documents  = SchoolStudent::getDocuments($selectedId);
            $readOnly   = SchoolStudent::isReadOnlyFor($selected, $user);
            $linkedDocuments = SchoolStudent::getLinkedDocuments($selectedId);

            if (!$isCoparent) {
                $allMembers = User::getByFamily($familyId);
                $familyMembers   = array_values(array_filter($allMembers, fn($m) => $m['role'] !== 'coparent'));
                $familyCoparents = array_values(array_filter($allMembers, fn($m) => $m['role'] === 'coparent'));
                $familyTaskLists = TaskList::getByFamily($familyId);
                $familyDocuments = Document::getAll($familyId);
                if ($selected['linked_task_list_id']) $linkedTaskList = TaskList::getById((int)$selected['linked_task_list_id']);
            }
        }

        require BASE_PATH . '/templates/school/index.php';
    }

    // ── Élèves ────────────────────────────────────────────────────

    public function createStudent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            if ($user['role'] === 'coparent') return ['success' => false, 'error' => 'Accès refusé.'];
            $familyId = (int)$user['family_id'];
            $data = $this->jsonInput();

            // L'élève est toujours identifié via le registre familial central (voir
            // App\Models\FamilyChild) : soit une fiche déjà existante, soit un nouveau nom
            // (enregistré dans le registre en même temps) — jamais un simple champ libre
            // propre au module, pour rester réutilisable dans Nounou/Garde alternée/Bébé.
            $newChildName = trim($data['new_child_name'] ?? '');
            $familyChildId = (int)($data['family_child_id'] ?? 0) ?: null;
            if ($newChildName !== '') {
                $familyChildId = FamilyChild::findOrCreateByName($familyId, (int)$user['id'], $newChildName);
            } elseif ($familyChildId) {
                $fc = FamilyChild::getById($familyChildId);
                if (!$fc || (int)$fc['family_id'] !== $familyId) $familyChildId = null;
            }
            if (!$familyChildId) return ['success' => false, 'error' => 'Choisissez un enfant ou saisissez le nom d\'un nouvel enfant.'];
            $familyChild = FamilyChild::getById($familyChildId);

            $d = [
                'name' => $familyChild['name'],
                'school_name' => trim($data['school_name'] ?? ''),
                'class_name' => trim($data['class_name'] ?? ''),
                'color' => $familyChild['color'],
                'family_child_id' => $familyChildId,
            ];
            $id = SchoolStudent::create($familyId, (int)$user['id'], $d);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateStudent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || $user['role'] === 'coparent') return ['success' => false, 'error' => 'Élève introuvable.'];
            $d = $this->validatedStudent($this->jsonInput());
            if (!$d) return ['success' => false, 'error' => 'Le nom est requis.'];
            SchoolStudent::update((int)$params['id'], (int)$user['family_id'], $d);
            return ['success' => true];
        });
    }

    public function deleteStudent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || $user['role'] === 'coparent') return ['success' => false];
            SchoolStudent::delete((int)$params['id'], (int)$user['family_id']);
            return ['success' => true];
        });
    }

    // ── Liens (compte membre, co-parent, liste de tâches, documents) ────

    public function updateLinks(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || $user['role'] === 'coparent') return ['success' => false, 'error' => 'Élève introuvable.'];
            $data = $this->jsonInput();
            $familyId = (int)$user['family_id'];

            $linkedUserId = (int)($data['linked_user_id'] ?? 0) ?: null;
            if ($linkedUserId) {
                $target = User::findById($linkedUserId);
                if (!$target || (int)$target['family_id'] !== $familyId || $target['role'] === 'coparent') $linkedUserId = null;
            }

            $linkedCoparentId = !empty($data['is_coparent']) ? ((int)($data['linked_coparent_id'] ?? 0) ?: null) : null;
            if ($linkedCoparentId) {
                $target = User::findById($linkedCoparentId);
                if (!$target || (int)$target['family_id'] !== $familyId || $target['role'] !== 'coparent') $linkedCoparentId = null;
            }

            $linkedTaskListId = (int)($data['linked_task_list_id'] ?? 0) ?: null;
            if ($linkedTaskListId) {
                $list = TaskList::getById($linkedTaskListId);
                if (!$list || $list['family_id'] !== $familyId) $linkedTaskListId = null;
            }

            SchoolStudent::updateLinks((int)$student['id'], $familyId, $linkedUserId, $linkedCoparentId, $linkedTaskListId);
            return ['success' => true];
        });
    }

    public function updateLinkedDocuments(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || $user['role'] === 'coparent') return ['success' => false];
            $ids = array_map('intval', (array)($this->jsonInput()['document_ids'] ?? []));
            // Ne garde que les documents appartenant réellement à la famille — un id hors famille
            // ne doit pas pouvoir être lié en falsifiant la requête.
            $familyDocIds = array_column(Document::getAll((int)$user['family_id']), 'id');
            $ids = array_values(array_intersect($ids, $familyDocIds));
            SchoolStudent::setLinkedDocuments((int)$student['id'], $ids);
            return ['success' => true];
        });
    }

    // ── Matières & professeurs ────────────────────────────────────

    public function addSubject(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || $user['role'] === 'coparent') return ['success' => false, 'error' => 'Élève introuvable.'];
            $data = $this->jsonInput();
            $name = trim($data['name'] ?? '');
            if ($name === '') return ['success' => false, 'error' => 'Nom de la matière requis.'];
            $id = SchoolStudent::addSubject((int)$student['id'], [
                'name' => $name,
                'teacher_name' => trim($data['teacher_name'] ?? ''),
                'color' => $this->safeColor($data['color'] ?? null),
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteSubject(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || $user['role'] === 'coparent') return ['success' => false];
            SchoolStudent::deleteSubject((int)$params['subjectId'], (int)$student['id']);
            return ['success' => true];
        });
    }

    // ── Emploi du temps ────────────────────────────────────────────

    public function addTimetableSlot(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || $user['role'] === 'coparent') return ['success' => false, 'error' => 'Élève introuvable.'];
            $data = $this->jsonInput();
            $subject = SchoolStudent::getSubjectById((int)($data['subject_id'] ?? 0), (int)$student['id']);
            $day = (int)($data['day_of_week'] ?? 0);
            if (!$subject || $day < 1 || $day > 7 || empty($data['start_time']) || empty($data['end_time'])) {
                return ['success' => false, 'error' => 'Champs obligatoires manquants.'];
            }
            $id = SchoolStudent::addTimetableSlot((int)$student['id'], [
                'subject_id' => $subject['id'],
                'day_of_week' => $day,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'room' => trim($data['room'] ?? ''),
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteTimetableSlot(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || $user['role'] === 'coparent') return ['success' => false];
            SchoolStudent::deleteTimetableSlot((int)$params['slotId'], (int)$student['id']);
            return ['success' => true];
        });
    }

    // ── Notes ──────────────────────────────────────────────────────

    public function addGrade(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student) return ['success' => false, 'error' => 'Élève introuvable.'];
            if (SchoolStudent::isReadOnlyFor($student, $user)) return ['success' => false, 'error' => 'Lecture seule.'];
            $data = $this->jsonInput();
            $subject = SchoolStudent::getSubjectById((int)($data['subject_id'] ?? 0), (int)$student['id']);
            $value = is_numeric($data['grade_value'] ?? null) ? (float)$data['grade_value'] : null;
            $max = is_numeric($data['grade_max'] ?? null) && (float)$data['grade_max'] > 0 ? (float)$data['grade_max'] : 20;
            $date = trim($data['grade_date'] ?? '');
            if (!$subject || $value === null || $date === '') {
                return ['success' => false, 'error' => 'Champs obligatoires manquants.'];
            }
            $id = SchoolStudent::addGrade((int)$student['id'], (int)$user['id'], [
                'subject_id' => $subject['id'],
                'title' => trim($data['title'] ?? ''),
                'grade_value' => $value,
                'grade_max' => $max,
                'grade_date' => $date,
                'comment' => trim($data['comment'] ?? ''),
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteGrade(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || SchoolStudent::isReadOnlyFor($student, $user)) return ['success' => false];
            SchoolStudent::deleteGrade((int)$params['gradeId'], (int)$student['id']);
            return ['success' => true];
        });
    }

    // ── Absences ───────────────────────────────────────────────────

    public function addAbsence(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student) return ['success' => false, 'error' => 'Élève introuvable.'];
            if (SchoolStudent::isReadOnlyFor($student, $user)) return ['success' => false, 'error' => 'Lecture seule.'];
            $data = $this->jsonInput();
            $date = trim($data['absence_date'] ?? '');
            if ($date === '') return ['success' => false, 'error' => 'Date requise.'];
            $subjectId = (int)($data['subject_id'] ?? 0);
            $subject = $subjectId ? SchoolStudent::getSubjectById($subjectId, (int)$student['id']) : null;
            $id = SchoolStudent::addAbsence((int)$student['id'], (int)$user['id'], [
                'subject_id' => $subject['id'] ?? null,
                'absence_date' => $date,
                'duration' => trim($data['duration'] ?? ''),
                'reason' => trim($data['reason'] ?? ''),
                'justified' => !empty($data['justified']),
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function toggleAbsenceJustified(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || SchoolStudent::isReadOnlyFor($student, $user)) return ['success' => false];
            SchoolStudent::setAbsenceJustified((int)$params['absenceId'], (int)$student['id'], !empty($this->jsonInput()['justified']));
            return ['success' => true];
        });
    }

    public function deleteAbsence(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || SchoolStudent::isReadOnlyFor($student, $user)) return ['success' => false];
            SchoolStudent::deleteAbsence((int)$params['absenceId'], (int)$student['id']);
            return ['success' => true];
        });
    }

    // ── Activités extra-scolaires ──────────────────────────────────

    public function addActivity(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || $user['role'] === 'coparent') return ['success' => false, 'error' => 'Élève introuvable.'];
            $data = $this->jsonInput();
            $name = trim($data['name'] ?? '');
            if ($name === '') return ['success' => false, 'error' => "Nom de l'activité requis."];
            $id = SchoolStudent::addActivity((int)$student['id'], [
                'name' => $name,
                'location' => trim($data['location'] ?? ''),
                'schedule_info' => trim($data['schedule_info'] ?? ''),
                'contact_info' => trim($data['contact_info'] ?? ''),
                'notes' => trim($data['notes'] ?? ''),
            ]);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteActivity(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || $user['role'] === 'coparent') return ['success' => false];
            SchoolStudent::deleteActivity((int)$params['activityId'], (int)$student['id']);
            return ['success' => true];
        });
    }

    // ── Documents & bulletins ──────────────────────────────────────

    public function uploadDocument(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student) return ['success' => false, 'error' => 'Élève introuvable.'];
            if (SchoolStudent::isReadOnlyFor($student, $user)) return ['success' => false, 'error' => 'Lecture seule.'];
            $file = $_FILES['file'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                $message = match ($file['error'] ?? null) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux pour la configuration actuelle du serveur.',
                    UPLOAD_ERR_PARTIAL => "Envoi interrompu (connexion coupée en cours d'envoi) — réessayez.",
                    UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => "Erreur serveur : impossible d'écrire le fichier temporaire.",
                    default => 'Aucun fichier reçu.',
                };
                return ['success' => false, 'error' => $message];
            }
            $data = $_POST;
            $title = trim($data['title'] ?? '') ?: $file['name'];
            $docType = in_array($data['doc_type'] ?? '', ['bulletin', 'other'], true) ? $data['doc_type'] : 'other';
            try {
                $id = SchoolStudent::addDocument((int)$student['id'], (int)$user['family_id'], (int)$user['id'], $title, $docType, $file);
            } catch (\RuntimeException $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteDocument(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $student = $this->ownedStudent((int)$params['id']);
            if (!$student || SchoolStudent::isReadOnlyFor($student, $user)) return ['success' => false];
            SchoolStudent::deleteDocument((int)$params['docId'], (int)$student['id']);
            return ['success' => true];
        });
    }

    public function serveFile(array $params): void
    {
        $this->requireAuth(true);
        $student = $this->ownedStudent((int)$params['id']);
        if (!$student) { http_response_code(404); echo 'Introuvable.'; return; }
        $doc = SchoolStudent::getDocumentById((int)$params['docId'], (int)$student['id']);
        if (!$doc) { http_response_code(404); echo 'Introuvable.'; return; }
        $path = BASE_PATH . $doc['file_path'];
        if (!file_exists($path)) { http_response_code(404); echo 'Introuvable.'; return; }

        header('Content-Type: ' . $doc['file_mime']);
        header('Content-Length: ' . filesize($path));
        header($this->contentDispositionHeader($doc['file_original']));
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────

    /** Un co-parent ne peut agir que sur la fiche à laquelle il est explicitement lié — les
     *  écritures y sont de toute façon bloquées au cas par cas via isReadOnlyFor()/role, mais ce
     *  filtrage empêche déjà un co-parent de seulement consulter/référencer une autre fiche. */
    private function ownedStudent(int $studentId): ?array
    {
        $user = Session::user();
        $student = SchoolStudent::getById($studentId);
        if (!$student || (int)$student['family_id'] !== (int)$user['family_id']) return null;
        if ($user['role'] === 'coparent' && (int)($student['linked_coparent_id'] ?? 0) !== (int)$user['id']) return null;
        return $student;
    }

    private function validatedStudent(array $data): ?array
    {
        $name = trim($data['name'] ?? '');
        if ($name === '') return null;
        return [
            'name' => $name,
            'school_name' => trim($data['school_name'] ?? ''),
            'class_name' => trim($data['class_name'] ?? ''),
            'color' => $this->safeColor($data['color'] ?? null),
        ];
    }
}

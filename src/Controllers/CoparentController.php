<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\Custody;
use App\Models\CommLogMessage;
use App\Models\Document;
use App\Models\Event;
use App\Models\Family;
use App\Models\Notification;
use App\Models\User;

/**
 * Vue dédiée pour un accès "garde partagée" restreint : soit un compte
 * entièrement restreint (role=coparent), soit un membre à part entière d'une
 * autre famille qui a en plus reçu un accès (custody_access) à un ou plusieurs
 * plannings de garde de CETTE famille. Dans les deux cas, tout est filtré par
 * les schedule_id auxquels l'utilisateur a explicitement accès — jamais par
 * family_id, puisque les plannings peuvent appartenir à une autre famille.
 */
class CoparentController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth(true);
        $user = Session::user();
        $schedules = Custody::getSchedulesForUser($user['id']);
        if (empty($schedules) && $user['role'] !== 'coparent') {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
        require BASE_PATH . '/templates/coparent/index.php';
    }

    /**
     * Un compte à accès restreint (role=coparent) peut, s'il le souhaite, se
     * créer sa propre famille FamilyBoard complète. On bascule alors son
     * family_id/role — mais on ne touche jamais à custody_access, qui référence
     * les plannings indépendamment de la famille : il garde donc son accès
     * "Garde partagée" via la nouvelle vue /coparent, en plus de son propre
     * tableau de bord complet.
     */
    public function createFamily(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            if ($user['role'] !== 'coparent') {
                return ['success' => false, 'error' => 'Vous avez déjà votre propre famille.'];
            }
            $familyName = trim($this->jsonInput()['family_name'] ?? '');
            if (!$familyName) {
                return ['success' => false, 'error' => 'Veuillez donner un nom à votre famille.'];
            }

            $familyId = Family::create($familyName);
            Database::execute('UPDATE users SET family_id=?, role=? WHERE id=?', [$familyId, 'admin', $user['id']]);
            Session::login(User::findById($user['id']));

            return ['success' => true];
        });
    }

    public function apiCustodyEvents(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $scheduleId = (int)($_GET['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) return [];
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            $events = Custody::getAllEventsForSchedules([$scheduleId], $start, $end);
            return array_map(fn($e) => [
                'id'              => 'custody_' . $e['id'],
                'title'           => $e['child_name'] . ' chez ' . $e['parent_name'],
                'start'           => $e['start_date'],
                'end'             => date('Y-m-d', strtotime($e['end_date'] . ' +1 day')),
                'allDay'          => true,
                'color'           => $e['parent_color'],
                'backgroundColor' => $e['schedule_color'],
                'borderColor'     => $e['parent_color'],
                'extendedProps'   => [
                    'parent_name'  => $e['parent_name'],
                    'notes'        => $e['notes'],
                    'is_recurring' => !empty($e['is_recurring']),
                ],
            ], $events);
        });
    }

    public function proposeCustody(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) {
                return ['success' => false, 'error' => 'Accès non autorisé'];
            }
            $schedule = Custody::getScheduleById($scheduleId);
            $days = $data['days'] ?? [];
            if (empty($days)) return ['success' => true, 'created' => 0];

            $created = Custody::applyProposalDays($scheduleId, $days);

            Notification::notifyFamily(
                (int)$schedule['family_id'], $user['id'], 'custody',
                'Proposition de garde reçue',
                $user['name'] . ' a proposé des jours de garde pour ' . $schedule['child_name'] . '.',
                BASE_URL . '/custody'
            );

            return ['success' => true, 'created' => $created];
        });
    }

    public function journal(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $scheduleId = (int)($_GET['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) return ['messages' => []];

            $after = (int)($_GET['after'] ?? 0);
            $messages = $after > 0
                ? CommLogMessage::getNewForSchedules([$scheduleId], $after)
                : CommLogMessage::getForSchedules([$scheduleId]);
            return ['messages' => $messages];
        });
    }

    public function journalSend(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) {
                return ['success' => false, 'error' => 'Accès non autorisé'];
            }
            $content = trim($data['content'] ?? '');
            if (!$content) return ['success' => false];
            if (mb_strlen($content) > 4000) return ['success' => false, 'error' => 'Message trop long'];

            $schedule = Custody::getScheduleById($scheduleId);
            $id = CommLogMessage::create((int)$schedule['family_id'], $user['id'], $content, $scheduleId);

            Notification::notifyFamily((int)$schedule['family_id'], $user['id'], 'comm_log',
                'Journal parental', $user['name'] . ' a ajouté un message au sujet de ' . $schedule['child_name'] . '.',
                BASE_URL . '/comm-log');

            return [
                'success' => true,
                'message' => [
                    'id' => $id,
                    'content' => $content,
                    'user_name' => $user['name'],
                    'user_color' => $user['color'],
                    'user_avatar' => $user['avatar'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'user_id' => $user['id'],
                ],
            ];
        });
    }

    public function documentsList(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $scheduleId = (int)($_GET['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) return [];
            return Document::getForSchedules([$scheduleId]);
        });
    }

    public function documentsUpload(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $data = $_POST ?: $this->jsonInput();
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) {
                return ['success' => false, 'error' => 'Accès non autorisé'];
            }
            $schedule = Custody::getScheduleById($scheduleId);
            $file = $_FILES['file'] ?? null;
            $data['custody_schedule_id'] = $scheduleId;
            $id = Document::create((int)$schedule['family_id'], $user['id'], $data, $file);
            return ['success' => true, 'id' => $id];
        });
    }

    public function eventsList(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $scheduleId = (int)($_GET['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) return [];
            $start = $_GET['start'] ?? date('Y-m-01') . ' 00:00:00';
            $end = $_GET['end'] ?? date('Y-m-t') . ' 23:59:59';
            return Event::getForSchedules([$scheduleId], $start, $end);
        });
    }

    public function eventsCreate(array $params): void
    {
        $this->requireAuth(true);
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            if (!Custody::userHasAccessToSchedule($user['id'], $scheduleId)) {
                return ['success' => false, 'error' => 'Accès non autorisé'];
            }
            $schedule = Custody::getScheduleById($scheduleId);
            $data['family_id'] = (int)$schedule['family_id'];
            $data['user_id'] = $user['id'];
            $data['custody_schedule_id'] = $scheduleId;
            $id = Event::create($data);
            return ['success' => true, 'id' => $id];
        });
    }
}

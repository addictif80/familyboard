<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Custody;
use App\Models\CustodyActivityLog;
use App\Models\User;
use App\Models\Notification;
use App\Models\EmailContent;
use App\Core\EmailLayout;

class CustodyController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('custody');
        $user = Session::user();
        $schedules = Custody::getSchedules($user['family_id']);
        $members = User::getByFamily($user['family_id']);
        require BASE_PATH . '/templates/custody/index.php';
    }

    public function apiEvents(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            $events = Custody::getAllEventsForFamily($user['family_id'], $start, $end);
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
                    'child_name'     => $e['child_name'],
                    'parent_name'    => $e['parent_name'],
                    'arrival_time'   => $e['arrival_time'],
                    'departure_time' => $e['departure_time'],
                    'notes'          => $e['notes'],
                    'is_recurring'   => !empty($e['is_recurring']),
                    'type'           => 'custody',
                ],
            ], $events);
        });
    }

    public function createSchedule(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $recurrence = [
                'type'             => $data['recurrence_type'] ?? 'none',
                'start'            => $data['recurrence_start'] ?? null,
                'handover_weekday' => $data['handover_weekday'] ?? null,
                'extra_weekday'    => $data['extra_weekday'] ?? null,
                'parent1_id'       => $this->resolveScheduleParentId($data['recurrence_parent1_id'] ?? null, $user['family_id']),
                'parent2_id'       => $this->resolveScheduleParentId($data['recurrence_parent2_id'] ?? null, $user['family_id']),
                'parent1_label'    => $data['recurrence_parent1_label'] ?? null,
                'parent1_color'    => $data['recurrence_parent1_color'] ?? '#4A90D9',
                'parent2_label'    => $data['recurrence_parent2_label'] ?? null,
                'parent2_color'    => $data['recurrence_parent2_color'] ?? '#E74C3C',
            ];
            $id = Custody::createSchedule($user['family_id'], $data['child_name'], $data['color'] ?? '#E67E22', $data['notes'] ?? '', $recurrence);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateSchedule(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $schedule = Custody::getScheduleById($id);
            if (!$schedule || $schedule['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            $recurrence = [
                'type'             => $data['recurrence_type'] ?? 'none',
                'start'            => $data['recurrence_start'] ?? null,
                'handover_weekday' => $data['handover_weekday'] ?? null,
                'extra_weekday'    => $data['extra_weekday'] ?? null,
                'parent1_id'       => $this->resolveScheduleParentId($data['recurrence_parent1_id'] ?? null, $user['family_id'], $id),
                'parent2_id'       => $this->resolveScheduleParentId($data['recurrence_parent2_id'] ?? null, $user['family_id'], $id),
                'parent1_label'    => $data['recurrence_parent1_label'] ?? null,
                'parent1_color'    => $data['recurrence_parent1_color'] ?? '#4A90D9',
                'parent2_label'    => $data['recurrence_parent2_label'] ?? null,
                'parent2_color'    => $data['recurrence_parent2_color'] ?? '#E74C3C',
            ];
            Custody::updateSchedule($id, $data['child_name'], $data['color'] ?? '#E67E22', $data['notes'] ?? '', $recurrence);
            CustodyActivityLog::record($id, $user['id'], 'schedule_updated');
            return ['success' => true];
        });
    }

    public function deleteSchedule(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $schedule = Custody::getScheduleById($id);
            if (!$schedule || $schedule['family_id'] !== $user['family_id']) return ['success' => false];
            CustodyActivityLog::record($id, $user['id'], 'schedule_deleted');
            Custody::deleteSchedule($id);
            return ['success' => true];
        });
    }

    public function createEvent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $schedule = Custody::getScheduleById((int)$data['schedule_id']);
            if (!$schedule || $schedule['family_id'] !== $user['family_id']) return ['success' => false];
            $parentUserId = $this->resolveCustodyParent($data['parent_user_id'] ?? null, $user, (int)$data['schedule_id']);
            $id = Custody::createEvent($data['schedule_id'], $parentUserId, $data);
            CustodyActivityLog::record((int)$data['schedule_id'], $user['id'], 'custody_event_created', $data['notes'] ?? null);
            Notification::notifyFamily($user['family_id'], $user['id'], 'custody', 'Garde mise à jour', 'Le planning de garde de ' . $schedule['child_name'] . ' a été mis à jour.', BASE_URL . '/custody');
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateEvent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $event = Custody::getEvent($id);
            if (!$event || $event['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            if (isset($data['parent_user_id'])) {
                $data['parent_user_id'] = $this->resolveCustodyParent($data['parent_user_id'], $user, (int)$event['schedule_id']);
            }
            Custody::updateEvent($id, $data);
            CustodyActivityLog::record((int)$event['schedule_id'], $user['id'], 'custody_event_updated');
            return ['success' => true];
        });
    }

    /**
     * Valide le parent assigné à un évènement de garde : soit un membre de la famille, soit un
     * co-parent explicitement autorisé sur ce planning via custody_access (cas normal d'un
     * parent séparé rattaché à un autre compte famille) — jamais un ID arbitraire fourni par le
     * client sans lien avec ce planning.
     */
    /**
     * Valide le parent1_id/parent2_id d'un planning (garde partagée) : soit un membre de la
     * famille, soit (à l'édition d'un planning existant) un co-parent déjà autorisé via
     * custody_access — jamais un ID arbitraire, qui exposerait le nom/couleur d'un utilisateur
     * d'une autre famille dans ce planning. Retourne null si non fourni ou invalide (au lieu de
     * imposer une valeur par défaut : ce champ est optionnel).
     */
    private function resolveScheduleParentId(mixed $parentId, int $familyId, ?int $scheduleId = null): ?int
    {
        if ($parentId === null || $parentId === '') return null;
        $id = (int)$parentId;
        if (User::belongsToFamily($id, $familyId)) return $id;
        if ($scheduleId !== null && Custody::userHasAccessToSchedule($id, $scheduleId)) return $id;
        return null;
    }

    private function resolveCustodyParent(mixed $parentUserId, array $user, int $scheduleId): int
    {
        $id = (int)($parentUserId ?? $user['id']);
        if ($id === $user['id']) return $id;
        if (User::belongsToFamily($id, $user['family_id'])) return $id;
        if (Custody::userHasAccessToSchedule($id, $scheduleId)) return $id;
        return $user['id'];
    }

    public function deleteEvent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $event = Custody::getEvent($id);
            if (!$event || $event['family_id'] !== $user['family_id']) return ['success' => false];
            CustodyActivityLog::record((int)$event['schedule_id'], $user['id'], 'custody_event_deleted');
            Custody::deleteEvent($id);
            return ['success' => true];
        });
    }

    public function applyProposal(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user  = Session::user();
            $data  = $this->jsonInput();
            $scheduleId = (int)($data['schedule_id'] ?? 0);
            $days  = $data['days'] ?? [];

            $schedule = Custody::getScheduleById($scheduleId);
            if (!$schedule || $schedule['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Planning introuvable'];
            }

            if (empty($days)) return ['success' => true, 'created' => 0];

            $created = Custody::applyProposalDays($scheduleId, $days);
            CustodyActivityLog::record($scheduleId, $user['id'], 'proposal_applied', $created . ' bloc(s)');

            Notification::notifyFamily(
                $user['family_id'], $user['id'], 'custody',
                'Proposition de garde appliquée',
                'Le planning de garde de ' . $schedule['child_name'] . ' a été mis à jour.',
                BASE_URL . '/custody'
            );

            return ['success' => true, 'created' => $created];
        });
    }

    public function listVacationPeriods(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $scheduleId = (int)$params['id'];
            $schedule = Custody::getScheduleById($scheduleId);
            if (!$schedule || $schedule['family_id'] !== $user['family_id']) return [];
            return Custody::getVacationPeriods($scheduleId);
        });
    }

    public function createVacationPeriod(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $scheduleId = (int)$params['id'];
            $schedule = Custody::getScheduleById($scheduleId);
            if (!$schedule || $schedule['family_id'] !== $user['family_id']) return ['success' => false];
            $data = $this->jsonInput();
            $id = Custody::createVacationPeriod($scheduleId, $data);
            CustodyActivityLog::record($scheduleId, $user['id'], 'vacation_created', $data['label'] ?? null);
            return ['success' => true, 'id' => $id];
        });
    }

    public function updateVacationPeriod(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $vacation = Custody::getVacationPeriodById($id);
            if (!$vacation || $vacation['family_id'] !== $user['family_id']) return ['success' => false];
            Custody::updateVacationPeriod($id, $this->jsonInput());
            CustodyActivityLog::record((int)$vacation['schedule_id'], $user['id'], 'vacation_updated', $vacation['label'] ?? null);
            return ['success' => true];
        });
    }

    public function deleteVacationPeriod(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $vacation = Custody::getVacationPeriodById($id);
            if (!$vacation || $vacation['family_id'] !== $user['family_id']) return ['success' => false];
            CustodyActivityLog::record((int)$vacation['schedule_id'], $user['id'], 'vacation_deleted', $vacation['label'] ?? null);
            Custody::deleteVacationPeriod($id);
            return ['success' => true];
        });
    }

    public function activityLog(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $schedule = Custody::getScheduleById($id);
            if (!$schedule || $schedule['family_id'] !== $user['family_id']) return ['active' => false, 'entries' => []];

            return [
                'active'  => CustodyActivityLog::isActiveForSchedule($id),
                'entries' => array_map(fn($e) => [
                    'id'         => $e['id'],
                    'action'     => $e['action'],
                    'label'      => CustodyActivityLog::labelFor($e['action']),
                    'details'    => $e['details'],
                    'ip'         => $e['ip'],
                    'user_name'  => $e['user_name'] ?? 'Compte supprimé',
                    'user_color' => $e['user_color'] ?? '#95A5A6',
                    'created_at' => $e['created_at'],
                ], CustodyActivityLog::getForSchedule($id)),
            ];
        });
    }

    /** Invite un co-parent à accès restreint (garde + journal + docs/évènements tagués) pour un ou plusieurs enfants. */
    public function inviteCoparent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $anchorSchedule = Custody::getScheduleById((int)$params['id']);
            if (!$anchorSchedule || $anchorSchedule['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Planning introuvable'];
            }

            $data  = $this->jsonInput();
            $email = trim($data['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'error' => 'Adresse email invalide.'];
            }

            $scheduleIds = array_map('intval', $data['schedule_ids'] ?? [(int)$params['id']]);
            $scheduleIds = array_values(array_unique(array_filter($scheduleIds)));
            $childNames = [];
            foreach ($scheduleIds as $sid) {
                $s = Custody::getScheduleById($sid);
                if (!$s || $s['family_id'] !== $user['family_id']) {
                    return ['success' => false, 'error' => 'Planning introuvable'];
                }
                $childNames[] = $s['child_name'];
            }
            if (empty($scheduleIds)) {
                return ['success' => false, 'error' => 'Sélectionnez au moins un enfant.'];
            }

            $token = \App\Models\Invitation::create($user['family_id'], $user['id'], $email, 'coparent', $scheduleIds);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $inviteUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/invite/' . $token;
            $family = \App\Models\Family::findById($user['family_id']);
            $childList = implode(', ', $childNames);

            $rendered = EmailContent::render('custody_invitation', [
                'sender_name' => $user['name'],
                'family_name' => $family['name'] ?? '',
                'child_list'  => $childList,
            ]);
            $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
                'label' => "Accepter l'invitation",
                'url'   => $inviteUrl,
            ]);

            $ok = \App\Core\Mail::send($user['family_id'], $email, $email, $rendered['subject'], $html, 'invitation', $user['id']);
            if ($ok) return ['success' => true];
            return ['success' => false, 'error' => "Erreur d'envoi. Vérifiez la configuration SMTP."];
        });
    }
}

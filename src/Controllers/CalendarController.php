<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\CustodyActivityLog;
use App\Models\Event;
use App\Models\CalDAVSource;
use App\Models\Family;
use App\Models\User;
use App\Models\Vacation;
use App\Models\SchoolHoliday;
use App\Models\Notification;
use App\Models\FamilyFriend;
use App\Models\EventShare;

class CalendarController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $this->requireModule('calendar');
        $user = Session::user();
        $familyId = $user['family_id'];
        $members = User::getByFamily($familyId);
        $family = Family::findById($familyId);
        $schoolZone = $family['school_zone'] ?? null;

        try {
            $caldavSources = CalDAVSource::getByFamily($familyId);
            $this->autoSyncCalDAV($caldavSources, $family, $familyId, $user['id']);
            // Reload sources after potential sync so last_sync timestamps are fresh
            $caldavSources = CalDAVSource::getByFamily($familyId);
        } catch (\Exception $e) {
            $caldavSources = [];
        }

        $custodySchedules = \App\Models\Custody::getSchedules($familyId);
        $hasProjects = !empty(\App\Models\Project::getByFamily($familyId));
        $friendFamilies = FamilyFriend::getAcceptedFor($familyId);
        require BASE_PATH . '/templates/calendar/index.php';
    }

    private function autoSyncCalDAV(array $sources, array $family, int $familyId, int $userId): void
    {
        $interval = (int)($family['caldav_sync_interval'] ?? 0);
        if ($interval <= 0 || empty($sources)) return;

        foreach ($sources as $source) {
            $lastSync = $source['last_sync'];
            $due = !$lastSync || (time() - strtotime($lastSync)) >= $interval * 60;
            if ($due) {
                try {
                    $this->doCalDAVSync($source, $familyId, $userId);
                } catch (\Exception $e) {
                    // Skip failing source silently — don't block calendar load
                }
            }
        }
    }

    public function apiEvents(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $start = $_GET['start'] ?? date('Y-m-01');
            $end = $_GET['end'] ?? date('Y-m-t');
            $events = Event::getByFamily($user['family_id'], $start, $end);
            $formatted = array_map(fn($e) => [
                'id' => $e['id'],
                'title' => $e['title'],
                'start' => $e['is_all_day'] ? substr($e['start_datetime'], 0, 10) : $e['start_datetime'],
                'end' => $e['is_all_day'] ? substr($e['end_datetime'], 0, 10) : $e['end_datetime'],
                'allDay' => (bool)$e['is_all_day'],
                'color' => $e['color'],
                'extendedProps' => [
                    'description' => $e['description'],
                    'user_name' => $e['user_name'],
                    'user_color' => $e['user_color'],
                    'caldav' => (bool)$e['caldav_uid'],
                    'type' => 'event',
                    'custody_schedule_ids' => $e['custody_schedule_ids'] ?? null,
                    'professional_name' => $e['professional_name'] ?? null,
                    'location' => $e['location'] ?? null,
                    'location_lat' => $e['location_lat'] ?? null,
                    'location_lng' => $e['location_lng'] ?? null,
                    'shared_from_family_name' => $e['shared_from_family_name'] ?? null,
                ],
            ], $events);

            // Optionally include custody events
            if (!empty($_GET['custody'])) {
                $custodyEvents = \App\Models\Custody::getAllEventsForFamily($user['family_id'], $start, $end);
                foreach ($custodyEvents as $e) {
                    $range = \App\Models\Custody::toDisplayRange($e);
                    $formatted[] = [
                        'id'    => 'custody_' . $e['id'],
                        'title' => $e['child_name'] . ' chez ' . $e['parent_name'],
                        'start' => $range['start'],
                        'end'   => $range['end'],
                        'allDay' => false,
                        'color' => $e['parent_color'] ?: '#aaa',
                        'extendedProps' => [
                            'type'        => 'custody',
                            'child_name'  => $e['child_name'],
                            'parent_name' => $e['parent_name'],
                            'is_recurring' => !empty($e['is_recurring']),
                        ],
                    ];
                }
            }

            // Personal vacations
            if (!empty($_GET['vacations'])) {
                $vacations = Vacation::getByFamily($user['family_id'], $start, $end);
                foreach ($vacations as $v) {
                    $formatted[] = [
                        'id'    => 'vacation_' . $v['id'],
                        'title' => '🏖 ' . $v['title'] . ' — ' . $v['user_name'],
                        'start' => $v['start_date'],
                        'end'   => date('Y-m-d', strtotime($v['end_date'] . ' +1 day')),
                        'allDay'=> true,
                        'color' => $v['user_color'],
                        'extendedProps' => [
                            'type'        => 'vacation',
                            'vacation_id' => (int)$v['id'],
                            'user_id'     => (int)$v['user_id'],
                        ],
                    ];
                }
            }

            // School holidays (if family has a configured zone)
            if (!empty($_GET['school'])) {
                $family     = Family::findById($user['family_id']);
                $schoolZone = $family['school_zone'] ?? null;
                if ($schoolZone) {
                    $holidays = SchoolHoliday::getByZone($schoolZone, $start, $end);
                    foreach ($holidays as $h) {
                        $formatted[] = [
                            'id'    => 'school_' . md5($h['zone'] . $h['start_date']),
                            'title' => '🎓 ' . $h['description'],
                            'start' => $h['start_date'],
                            'end'   => date('Y-m-d', strtotime($h['end_date'] . ' +1 day')),
                            'allDay'=> true,
                            'color' => '#7C3AED',
                            'extendedProps' => ['type' => 'school_holiday'],
                        ];
                    }
                }
            }

            // Birthdays from contacts directory
            $contactsWithBirthday = \App\Core\Database::fetchAll(
                'SELECT id, first_name, last_name, birthday, color FROM contacts
                  WHERE family_id=? AND birthday IS NOT NULL AND birthday != \'\' AND is_system=0',
                [$user['family_id']]
            );
            if (!empty($contactsWithBirthday)) {
                $startYear = (int)substr($start, 0, 4);
                $endYear   = (int)substr($end,   0, 4);
                foreach ($contactsWithBirthday as $c) {
                    [$bYear, $bMonth, $bDay] = explode('-', $c['birthday']);
                    for ($yr = $startYear; $yr <= $endYear; $yr++) {
                        if ($bMonth === '02' && $bDay === '29' && !checkdate(2, 29, $yr)) continue;
                        $bDate = sprintf('%04d-%s-%s', $yr, $bMonth, $bDay);
                        if ($bDate < $start || $bDate > $end) continue;
                        $age  = $yr - (int)$bYear;
                        $name = trim($c['first_name'] . ' ' . $c['last_name']);
                        $formatted[] = [
                            'id'    => 'birthday_' . $c['id'] . '_' . $yr,
                            'title' => '🎂 ' . $name . ($age > 0 ? ' (' . $age . ' ans)' : ''),
                            'start' => $bDate,
                            'end'   => $bDate,
                            'allDay'=> true,
                            'color' => $c['color'] ?: '#E91E63',
                            'extendedProps' => [
                                'type' => 'birthday',
                                'name' => $name,
                                'age'  => $age,
                            ],
                        ];
                    }
                }
            }

            // Optionally include project deadlines
            if (!empty($_GET['projects'])) {
                $deadlines = \App\Models\Project::getDeadlines($user['family_id'], $start, $end);
                foreach ($deadlines as $p) {
                    $formatted[] = [
                        'id'     => 'project_' . $p['id'],
                        'title'  => $p['name'],
                        'start'  => $p['date'],
                        'end'    => $p['date'],
                        'allDay' => true,
                        'color'  => $p['color'],
                        'extendedProps' => [
                            'type'       => 'project',
                            'project_id' => (int)$p['id'],
                        ],
                    ];
                }
            }

            return $formatted;
        });
    }

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $data['family_id'] = $user['family_id'];
            $data['user_id'] = $user['id'];
            $data['color'] = $this->safeColor($data['color'] ?? null);
            $data['custody_schedule_ids'] = $this->validateFamilyScheduleIds($data['custody_schedule_ids'] ?? [], $user['family_id']);
            $id = Event::create($data);
            $event = Event::getById($id);
            foreach ($data['custody_schedule_ids'] as $scheduleId) {
                CustodyActivityLog::record($scheduleId, $user['id'], 'event_created', $data['title'] ?? null);
            }
            Notification::notifyFamily($user['family_id'], $user['id'], 'calendar', 'Nouvel événement', $user['name'] . ' a ajouté : ' . $data['title'], BASE_URL . '/calendar');

            $shareFamilyIds = array_values(array_intersect(
                array_map('intval', (array)($data['share_family_ids'] ?? [])),
                array_column(FamilyFriend::getAcceptedFor((int)$user['family_id']), 'family_id')
            ));
            if ($shareFamilyIds) {
                EventShare::invite($id, (int)$user['family_id'], $shareFamilyIds, (int)$user['id']);
            }

            return ['success' => true, 'id' => $id, 'event' => $event];
        });
    }

    public function update(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $event = Event::getById($id);
            if (!$event || $event['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Non autorisé'];
            }
            $data = $this->jsonInput();
            $data['color'] = $this->safeColor($data['color'] ?? null);
            $data['custody_schedule_ids'] = $this->validateFamilyScheduleIds($data['custody_schedule_ids'] ?? [], $user['family_id']);
            Event::update($id, $data);
            foreach ($data['custody_schedule_ids'] as $scheduleId) {
                CustodyActivityLog::record($scheduleId, $user['id'], 'event_updated', $data['title'] ?? $event['title']);
            }
            Notification::notifyFamily($user['family_id'], $user['id'], 'calendar', 'Événement modifié',
                $user['name'] . ' a modifié : ' . ($data['title'] ?? $event['title']), BASE_URL . '/calendar');

            // Jamais répercuté automatiquement chez les familles participantes : ça crée une
            // alerte à résoudre individuellement, voir EventShare::propagateUpdate().
            EventShare::propagateUpdate($id, $data);

            return ['success' => true];
        });
    }

    public function delete(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $event = Event::getById($id);
            if (!$event || $event['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Non autorisé'];
            }
            // Avant suppression : les familles participantes reçoivent une alerte à résoudre
            // (accepter ou refuser la suppression), jamais une répercussion automatique.
            EventShare::propagateDelete($id);
            Event::delete($id);
            Notification::notifyFamily($user['family_id'], $user['id'], 'calendar', 'Événement supprimé',
                $user['name'] . ' a supprimé : ' . $event['title'], BASE_URL . '/calendar');
            return ['success' => true];
        });
    }

    // ── Partage inter-familles ──────────────────────────────────────

    /** Familles amies acceptées, pour la case à cocher à la création d'un événement. */
    public function apiFriendFamilies(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            return ['families' => FamilyFriend::getAcceptedFor((int)$user['family_id'])];
        });
    }

    public function apiShareInvitations(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            return [
                'invitations' => EventShare::getPendingInvitations((int)$user['family_id']),
                'changes'     => EventShare::getPendingChanges((int)$user['family_id']),
            ];
        });
    }

    public function acceptShareInvitation(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $ok = EventShare::accept((int)$params['id'], (int)$user['family_id'], (int)$user['id']);
            return ['success' => $ok];
        });
    }

    public function declineShareInvitation(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $ok = EventShare::decline((int)$params['id'], (int)$user['family_id']);
            return ['success' => $ok];
        });
    }

    public function resolveShareChange(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $data = $this->jsonInput();
            $decision = ($data['decision'] ?? '') === 'accept' ? 'accept' : 'decline';
            $reason = trim($data['reason'] ?? '');
            $ok = EventShare::resolveChange((int)$params['id'], (int)$user['family_id'], $decision, $reason ?: null);
            return ['success' => $ok];
        });
    }

    // ── Vacations ────────────────────────────────────────────────

    public function createVacation(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            if (empty($data['start_date']) || empty($data['end_date'])) {
                return ['success' => false, 'error' => 'Dates requises'];
            }
            if ($data['start_date'] > $data['end_date']) {
                return ['success' => false, 'error' => 'La date de fin doit être après la date de début'];
            }
            $id = Vacation::create($user['family_id'], $user['id'], $data);
            return ['success' => true, 'id' => $id];
        });
    }

    public function deleteVacation(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user     = Session::user();
            $id       = (int)$params['id'];
            $vacation = Vacation::getById($id);
            if (!$vacation || $vacation['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Non autorisé'];
            }
            // Only the owner can delete their vacation
            if ($vacation['user_id'] !== $user['id']) {
                return ['success' => false, 'error' => 'Vous ne pouvez supprimer que vos propres congés'];
            }
            Vacation::delete($id);
            return ['success' => true];
        });
    }

    public function addCalDAV(array $params): void
    {
        // La synchronisation déclenche une requête HTTP serveur vers une URL fournie par le
        // client (SSRF potentiel vers le réseau interne) — réservé aux admins de la famille.
        $this->requireAdmin();
        $this->json(function () {
            $user = Session::user();
            $data = $this->jsonInput();
            $id = CalDAVSource::create($user['family_id'], $user['id'], $data);
            // Initial sync
            $source = CalDAVSource::getById($id);
            $this->doCalDAVSync($source, $user['family_id'], $user['id']);
            return ['success' => true, 'id' => $id];
        });
    }

    public function syncCalDAV(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $source = CalDAVSource::getById($id);
            if (!$source || $source['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Non autorisé'];
            }
            $count = $this->doCalDAVSync($source, $user['family_id'], $user['id']);
            return ['success' => true, 'count' => $count];
        });
    }

    private function doCalDAVSync(array $source, int $familyId, int $userId): int
    {
        $events = CalDAVSource::fetchEvents($source);
        if (empty($events)) {
            // Don't wipe existing events if fetch returned nothing (network error / empty feed)
            CalDAVSource::updateSyncTime($source['id']);
            return 0;
        }
        Event::deleteBySource($source['id']);
        $count = 0;
        foreach ($events as $e) {
            if ($e['start_datetime'] && $e['end_datetime']) {
                Event::createFromCalDAV($familyId, $userId, $source['id'], array_merge($e, ['color' => $source['color']]));
                $count++;
            }
        }
        CalDAVSource::updateSyncTime($source['id']);
        return $count;
    }

    public function deleteCalDAV(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $source = CalDAVSource::getById($id);
            if (!$source || $source['family_id'] !== $user['family_id']) {
                return ['success' => false, 'error' => 'Non autorisé'];
            }
            Event::deleteBySource($id);
            CalDAVSource::delete($id);
            return ['success' => true];
        });
    }
}

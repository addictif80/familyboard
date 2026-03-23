<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Event;
use App\Models\CalDAVSource;
use App\Models\User;
use App\Models\Notification;

class CalendarController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();
        $familyId = $user['family_id'];
        $members = User::getByFamily($familyId);
        try {
            $caldavSources = CalDAVSource::getByFamily($familyId);
        } catch (\Exception $e) {
            $caldavSources = [];
        }
        $custodySchedules = \App\Models\Custody::getSchedules($familyId);
        require BASE_PATH . '/templates/calendar/index.php';
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
                ],
            ], $events);

            // Optionally include custody events
            if (!empty($_GET['custody'])) {
                $custodyEvents = \App\Models\Custody::getAllEventsForFamily($user['family_id'], $start, $end);
                foreach ($custodyEvents as $e) {
                    $formatted[] = [
                        'id'    => 'custody_' . $e['id'],
                        'title' => $e['child_name'] . ' chez ' . $e['parent_name'],
                        'start' => $e['start_date'],
                        'end'   => date('Y-m-d', strtotime($e['end_date'] . ' +1 day')),
                        'allDay' => true,
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
            $id = Event::create($data);
            $event = Event::getById($id);
            Notification::notifyFamily($user['family_id'], $user['id'], 'calendar', 'Nouvel événement', $user['name'] . ' a ajouté : ' . $data['title'], BASE_URL . '/calendar');
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
            Event::update($id, $data);
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
            Event::delete($id);
            return ['success' => true];
        });
    }

    public function addCalDAV(array $params): void
    {
        $this->requireAuth();
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

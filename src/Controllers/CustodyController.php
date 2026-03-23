<?php
namespace App\Controllers;

use App\Core\Session;
use App\Models\Custody;
use App\Models\User;
use App\Models\Notification;

class CustodyController extends BaseController
{
    public function index(array $params): void
    {
        $this->requireAuth();
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
                'type'          => $data['recurrence_type'] ?? 'none',
                'start'         => $data['recurrence_start'] ?? null,
                'parent1_id'    => $data['recurrence_parent1_id'] ?? null,
                'parent2_id'    => $data['recurrence_parent2_id'] ?? null,
                'parent1_label' => $data['recurrence_parent1_label'] ?? null,
                'parent1_color' => $data['recurrence_parent1_color'] ?? '#4A90D9',
                'parent2_label' => $data['recurrence_parent2_label'] ?? null,
                'parent2_color' => $data['recurrence_parent2_color'] ?? '#E74C3C',
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
                'type'          => $data['recurrence_type'] ?? 'none',
                'start'         => $data['recurrence_start'] ?? null,
                'parent1_id'    => $data['recurrence_parent1_id'] ?? null,
                'parent2_id'    => $data['recurrence_parent2_id'] ?? null,
                'parent1_label' => $data['recurrence_parent1_label'] ?? null,
                'parent1_color' => $data['recurrence_parent1_color'] ?? '#4A90D9',
                'parent2_label' => $data['recurrence_parent2_label'] ?? null,
                'parent2_color' => $data['recurrence_parent2_color'] ?? '#E74C3C',
            ];
            Custody::updateSchedule($id, $data['child_name'], $data['color'] ?? '#E67E22', $data['notes'] ?? '', $recurrence);
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
            $id = Custody::createEvent($data['schedule_id'], $data['parent_user_id'] ?? $user['id'], $data);
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
            Custody::updateEvent($id, $this->jsonInput());
            return ['success' => true];
        });
    }

    public function deleteEvent(array $params): void
    {
        $this->requireAuth();
        $this->json(function () use ($params) {
            $user = Session::user();
            $id = (int)$params['id'];
            $event = Custody::getEvent($id);
            if (!$event || $event['family_id'] !== $user['family_id']) return ['success' => false];
            Custody::deleteEvent($id);
            return ['success' => true];
        });
    }
}

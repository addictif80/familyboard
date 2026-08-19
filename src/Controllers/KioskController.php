<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Family;
use App\Models\KioskLink;
use App\Models\MealPlan;
use App\Models\NameDay;
use App\Models\SitterLink;
use App\Models\TaskList;

class KioskController extends BaseController
{
    // ── Admin management (from Settings) ────────────────────────────

    public function create(array $params): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->requireModule('kiosk');
        $this->json(function () {
            $user  = Session::user();
            $label = trim($this->jsonInput()['label'] ?? '') ?: 'Écran mural';
            $link  = KioskLink::create($user['family_id'], $user['id'], $label);
            $link['url'] = rtrim($this->originUrl(), '/') . BASE_URL . '/kiosk/' . $link['token'];
            return ['success' => true, 'link' => $link];
        });
    }

    public function revoke(array $params): void
    {
        $this->requireAuth();
        $this->requireAdmin();
        $this->json(function () use ($params) {
            $user = Session::user();
            $link = Database::fetch('SELECT * FROM kiosk_links WHERE id=?', [(int)$params['id']]);
            if (!$link || $link['family_id'] !== $user['family_id']) return ['success' => false];
            KioskLink::revoke($link['id']);
            return ['success' => true];
        });
    }

    // ── Public kiosk display (token-scoped, no login) ───────────────

    public function view(array $params): void
    {
        $token = $params['token'] ?? '';
        $kiosk = KioskLink::findValidByToken($token);
        if (!$kiosk) {
            http_response_code(404);
            require BASE_PATH . '/templates/kiosk/view.php';
            return;
        }

        $family        = Family::findById((int)$kiosk['family_id']);
        $activeSitter  = SitterLink::getActiveForFamily((int)$kiosk['family_id']);
        $sitterUrl     = $activeSitter
            ? rtrim($this->originUrl(), '/') . BASE_URL . '/sitter/' . $activeSitter['token']
            : null;

        require BASE_PATH . '/templates/kiosk/view.php';
    }

    public function data(array $params): void
    {
        $this->json(function () use ($params) {
            $kiosk = KioskLink::findValidByToken($params['token'] ?? '');
            if (!$kiosk) return ['success' => false, 'error' => 'invalid_token'];

            $familyId = (int)$kiosk['family_id'];
            $activeSitter = SitterLink::getActiveForFamily($familyId);
            if ($activeSitter) {
                return [
                    'success'       => true,
                    'sitter_active' => true,
                    'sitter_url'    => rtrim($this->originUrl(), '/') . BASE_URL . '/sitter/' . $activeSitter['token'],
                ];
            }

            return ['success' => true, 'sitter_active' => false] + $this->buildBoard($familyId);
        });
    }

    public function createTask(array $params): void
    {
        $this->json(function () use ($params) {
            $kiosk = KioskLink::findValidByToken($params['token'] ?? '');
            if (!$kiosk) return ['success' => false];

            $data   = $this->jsonInput();
            $listId = (int)($data['list_id'] ?? 0);
            $title  = trim($data['title'] ?? '');
            if (!$listId || !$title) return ['success' => false];

            $list = TaskList::getById($listId);
            if (!$list || $list['family_id'] !== $kiosk['family_id']) return ['success' => false];

            TaskList::createTask($listId, (int)$kiosk['created_by'], ['title' => $title]);
            return ['success' => true];
        });
    }

    public function toggleTask(array $params): void
    {
        $this->json(function () use ($params) {
            $kiosk = KioskLink::findValidByToken($params['token'] ?? '');
            if (!$kiosk) return ['success' => false];

            $task = TaskList::getTask((int)$params['id']);
            if (!$task) return ['success' => false];
            $list = TaskList::getById($task['list_id']);
            if (!$list || $list['family_id'] !== $kiosk['family_id']) return ['success' => false];

            $completed = TaskList::toggleTask($task['id']);
            return ['success' => true, 'completed' => $completed];
        });
    }

    private function buildBoard(int $familyId): array
    {
        $family = Family::findById($familyId);

        $taskLists = $shoppingLists = [];
        foreach (TaskList::getByFamily($familyId) as $list) {
            $all = TaskList::getTasks($list['id']);
            $list['pending'] = array_values(array_filter($all, fn($t) => !$t['is_completed']));
            if ($list['type'] === 'shopping') {
                $shoppingLists[] = $list;
            } else {
                $taskLists[] = $list;
            }
        }

        $today = date('Y-m-d');
        $endStr = date('Y-m-d', strtotime('+6 days'));
        $events = Event::getByFamily($familyId, $today . ' 00:00:00', $endStr . ' 23:59:59');

        $meals = [];
        try {
            $week = MealPlan::getWeek($familyId, $today);
            foreach ($week as $key => $meal) {
                $meals[] = [
                    'date'  => $meal['date'],
                    'type'  => $meal['meal_type'],
                    'title' => $meal['recipe_title'] ?? $meal['custom_title'] ?? '',
                ];
            }
            usort($meals, fn($a, $b) => $a['date'] <=> $b['date']);
        } catch (\Throwable) {}

        $contacts = [];
        try {
            $contacts = Contact::getByFamily($familyId);
        } catch (\Throwable) {}

        return [
            'family_name'    => $family['name'] ?? 'Famille',
            'task_lists'     => $taskLists,
            'shopping_lists' => $shoppingLists,
            'events'         => $events,
            'meals'          => $meals,
            'contacts'       => $contacts,
            'name_day'       => NameDay::today(),
        ];
    }

    private function originUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}

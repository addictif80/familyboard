<?php
namespace App\Controllers;

use App\Core\Database;
use App\Models\Event;
use App\Models\Post;
use App\Models\Custody;
use App\Models\TaskList;
use App\Models\Baby;
use App\Models\Birthday;
use App\Models\Family;
use App\Core\Session;

class DashboardController extends \App\Controllers\BaseController
{
    public const AVAILABLE_WIDGETS = [
        'calendar'  => ['label' => 'Calendrier',       'icon' => '📅'],
        'wall'      => ['label' => 'Mur familial',      'icon' => '📸'],
        'tasks'     => ['label' => 'Tâches',            'icon' => '✅'],
        'custody'   => ['label' => 'Garde alternée',    'icon' => '👶'],
        'chat'      => ['label' => 'Chat',              'icon' => '💬'],
        'budget'    => ['label' => 'Budget',            'icon' => '💰'],
        'projects'  => ['label' => 'Projets',           'icon' => '📋'],
        'baby'      => ['label' => 'Bébé',              'icon' => '🍼'],
        'birthdays' => ['label' => 'Anniversaires',     'icon' => '🎂'],
    ];

    public function index(array $params): void
    {
        if (!Session::isLoggedIn()) {
            require BASE_PATH . '/templates/landing.php';
            return;
        }
        // Un compte à accès restreint (role=coparent) n'a pas de tableau de bord
        // classique — requireAuth() par défaut le redirigerait ici en boucle,
        // donc on l'aiguille vers sa vue dédiée avant tout autre traitement.
        if ((Session::user()['role'] ?? null) === 'coparent') {
            header('Location: ' . BASE_URL . '/coparent');
            exit;
        }
        $this->requireAuth();
        $user     = Session::user();
        $familyId = $user['family_id'];

        $family          = Family::findById($familyId);
        $disabledModules = Family::getDisabledModules($family ?? []);
        $widgetConfig    = $this->getUserWidgets($user, $disabledModules);

        // Fetch data for each enabled widget
        $widgetData = [];
        foreach ($widgetConfig as $w) {
            if (!$w['enabled']) continue;
            $widgetData[$w['slug']] = $this->fetchWidgetData($w['slug'], $familyId, $user);
        }

        require BASE_PATH . '/templates/dashboard.php';
    }

    public function saveWidgets(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();

        $body = json_decode(file_get_contents('php://input'), true);
        if (!isset($body['widgets']) || !is_array($body['widgets'])) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid']);
            return;
        }

        // Validate: only known slugs
        $clean = [];
        foreach ($body['widgets'] as $w) {
            $slug = $w['slug'] ?? '';
            if (!isset(self::AVAILABLE_WIDGETS[$slug])) continue;
            $clean[] = ['slug' => $slug, 'enabled' => (bool)($w['enabled'] ?? true)];
        }

        Database::execute(
            'UPDATE users SET dashboard_config=? WHERE id=?',
            [json_encode(['widgets' => $clean]), $user['id']]
        );

        // Refresh session
        $updated = \App\Models\User::findById($user['id']);
        Session::set('user', $updated);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    // ── private helpers ──────────────────────────────────────────

    private function getUserWidgets(array $user, array $disabledModules): array
    {
        $default = [
            ['slug' => 'calendar', 'enabled' => true],
            ['slug' => 'wall',     'enabled' => true],
            ['slug' => 'tasks',    'enabled' => true],
            ['slug' => 'custody',  'enabled' => true],
        ];

        $saved = null;
        if (!empty($user['dashboard_config'])) {
            $parsed = json_decode($user['dashboard_config'], true);
            if (isset($parsed['widgets']) && is_array($parsed['widgets'])) {
                $saved = $parsed['widgets'];
            }
        }

        $config = $saved ?? $default;

        // Ensure all known widgets appear (append missing ones as disabled)
        $present = array_column($config, 'slug');
        foreach (self::AVAILABLE_WIDGETS as $slug => $_) {
            if (!in_array($slug, $present)) {
                $config[] = ['slug' => $slug, 'enabled' => false];
            }
        }

        // Filter out widgets whose module is disabled
        foreach ($config as &$w) {
            if (in_array($w['slug'], $disabledModules)) {
                $w['enabled'] = false;
            }
        }
        unset($w);

        return $config;
    }

    private function fetchWidgetData(string $slug, int $familyId, array $user): array
    {
        $today = date('Y-m-d');
        switch ($slug) {
            case 'calendar':
                return ['events' => Event::getUpcoming($familyId)];
            case 'wall':
                $visibleAuthorIds = \App\Models\Follow::getVisibleAuthorIds((int)$user['id']);
                return ['posts' => Post::getVisibleForUser($familyId, (int)$user['id'], $visibleAuthorIds, 5)];
            case 'tasks':
                $lists = TaskList::getByFamily($familyId);
                $pending = [];
                foreach ($lists as $list) {
                    if ($list['type'] === 'shopping') continue;
                    $tasks = TaskList::getTasks((int)$list['id']);
                    foreach ($tasks as $t) {
                        if (!$t['is_completed']) $pending[] = array_merge($t, ['list_name' => $list['name']]);
                    }
                }
                usort($pending, fn($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
                return ['tasks' => array_slice($pending, 0, 8)];
            case 'custody':
                return ['custody' => Custody::getAllEventsForFamily($familyId, $today, $today)];
            case 'baby':
                $babies = Baby::getAllByFamily($familyId);
                $babyData = [];
                foreach (array_slice($babies, 0, 1) as $baby) {
                    $babyData[] = [
                        'baby'  => $baby,
                        'stats' => Baby::getDailyStats((int)$baby['id'], $today),
                        'sleep' => Baby::getActiveSleep((int)$baby['id']),
                    ];
                }
                return ['babies' => $babyData];
            case 'birthdays':
                return ['birthdays' => Birthday::getUpcoming($familyId, 60)];
            default:
                return [];
        }
    }
}

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
use App\Models\NameDay;
use App\Models\AiWeeklyDigest;
use App\Models\Message;
use App\Models\Budget;
use App\Models\Project;
use App\Models\DirectMessage;
use App\Models\StockItem;
use App\Models\Poll;
use App\Models\WishlistItem;
use App\Models\MedicalTreatment;
use App\Models\Vehicle;
use App\Models\Pet;
use App\Models\Travel;
use App\Models\Meter;
use App\Models\OfficialAlert;
use App\Models\Announcement;
use App\Models\User;
use App\Core\Session;

class DashboardController extends \App\Controllers\BaseController
{
    public const AVAILABLE_WIDGETS = [
        'quick_access' => ['label' => 'Accès rapides',        'icon' => '⚡', 'cat' => 'quotidien'],
        'calendar'   => ['label' => 'Calendrier',             'icon' => '📅', 'cat' => 'quotidien'],
        'wall'       => ['label' => 'Mur familial',           'icon' => '📸', 'cat' => 'quotidien'],
        'tasks'      => ['label' => 'Tâches',                 'icon' => '✅', 'cat' => 'quotidien'],
        'stock'      => ['label' => 'Stock frigo/congélo',    'icon' => '🛒', 'cat' => 'quotidien'],
        'polls'      => ['label' => 'Sondages',               'icon' => '🗳️', 'cat' => 'quotidien'],
        'chat'       => ['label' => 'Chat',                   'icon' => '💬', 'cat' => 'quotidien'],
        'custody'    => ['label' => 'Garde alternée',         'icon' => '👶', 'cat' => 'famille'],
        'baby'       => ['label' => 'Bébé',                   'icon' => '🍼', 'cat' => 'famille'],
        'birthdays'  => ['label' => 'Anniversaires',          'icon' => '🎂', 'cat' => 'famille'],
        'wishlist'   => ['label' => 'Liste de cadeaux',       'icon' => '🎁', 'cat' => 'famille'],
        'medication' => ['label' => 'Traitements en cours',   'icon' => '💊', 'cat' => 'famille'],
        'budget'     => ['label' => 'Budget',                 'icon' => '💰', 'cat' => 'biens'],
        'projects'   => ['label' => 'Projets',                'icon' => '📋', 'cat' => 'biens'],
        'vehicles'   => ['label' => 'Véhicules',              'icon' => '🚗', 'cat' => 'biens'],
        'pets'       => ['label' => 'Animaux de compagnie',   'icon' => '🐾', 'cat' => 'biens'],
        'travels'    => ['label' => 'Voyages & réservations', 'icon' => '✈️', 'cat' => 'biens'],
        'meters'     => ['label' => 'Compteurs & consommation', 'icon' => '📊', 'cat' => 'biens'],
    ];

    public const WIDGET_CATEGORIES = [
        'quotidien' => 'Quotidien',
        'famille'   => 'Famille & santé',
        'biens'     => 'Biens & déplacements',
    ];

    public function index(array $params): void
    {
        if (!Session::isLoggedIn()) {
            $billingEnabled = \App\Models\FamilySubscription::billingEnabled();
            $pricingPlans = $billingEnabled ? \App\Models\Plan::getAll(true) : [];
            $annualDiscount = (int)(\App\Models\AppSetting::get('sub_annual_discount_pct') ?? '20');
            $trialDays = (int)(\App\Models\AppSetting::get('sub_trial_days') ?? '14');
            $testimonials = \App\Models\Testimonial::getApproved();
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
        $familyId = (int)$user['family_id'];

        $family          = Family::findById($familyId);
        $disabledModules = Family::getDisabledModules($family ?? []);
        $widgetConfig    = $this->getUserWidgets($user, $disabledModules);
        $todayNameDay    = NameDay::today();

        try {
            $weeklyDigest = AiWeeklyDigest::getUnseenForUser((int)$familyId, (int)$user['id']);
        } catch (\Throwable) {
            $weeklyDigest = null;
        }

        // Fetch data for each enabled widget
        $widgetData = [];
        foreach ($widgetConfig as $w) {
            if (!$w['enabled']) continue;
            $widgetData[$w['slug']] = $this->fetchWidgetData($w['slug'], $familyId, $user);
        }

        $headerStats  = $this->computeHeaderStats($familyId, (int)$user['id'], $disabledModules);
        $timelineDays = $this->computeTimeline($familyId);
        $familyMembers = User::getByFamily($familyId);
        $quickAccess   = $this->computeQuickAccess($user, $disabledModules);

        $city = trim((string)($family['weather_city'] ?? '')) ?: null;
        $officialAlerts = [];
        try {
            $officialAlerts = array_slice(OfficialAlert::getActiveForFamily($city), 0, 3);
        } catch (\Throwable) {
        }
        $announcements = [];
        try {
            $announcements = array_slice(Announcement::getPublished(), 0, 2);
        } catch (\Throwable) {
        }

        require BASE_PATH . '/templates/dashboard.php';
    }

    /** Appelé quand la popup du digest hebdomadaire IA est fermée — pour ne pas la réafficher
     *  tant qu'un nouveau digest n'a pas été généré la semaine suivante. */
    public function dismissWeeklyDigest(array $params): void
    {
        $this->requireAuth();
        $this->json(function () {
            $user = Session::user();
            AiWeeklyDigest::markSeen((int)$user['id'], AiWeeklyDigest::currentWeekStart());
            return ['success' => true];
        });
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

    /** Sélection personnelle (par utilisateur, pas par famille) de modules épinglés dans le
     *  widget "Accès rapides" — stockée dans dashboard_config['quick_access'], à défaut un
     *  ordre de priorité fixe (voir Family::defaultQuickAccess). */
    public function saveQuickAccess(array $params): void
    {
        $this->requireAuth();
        $user = Session::user();

        $body  = json_decode(file_get_contents('php://input'), true) ?: [];
        $slugs = is_array($body['slugs'] ?? null) ? $body['slugs'] : null;
        if ($slugs === null) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid']);
            return;
        }

        $clean = array_values(array_intersect(array_map('strval', $slugs), array_keys(Family::QUICK_NAV_ROUTES)));

        $config = [];
        if (!empty($user['dashboard_config'])) {
            $config = json_decode($user['dashboard_config'], true) ?: [];
        }
        $config['quick_access'] = $clean;

        Database::execute('UPDATE users SET dashboard_config=? WHERE id=?', [json_encode($config), $user['id']]);

        $updated = \App\Models\User::findById($user['id']);
        Session::set('user', $updated);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    // ── private helpers ──────────────────────────────────────────

    private function computeQuickAccess(array $user, array $disabledModules): array
    {
        $availableSlugs = array_values(array_diff(array_keys(Family::QUICK_NAV_ROUTES), $disabledModules));

        $saved = null;
        if (!empty($user['dashboard_config'])) {
            $parsed = json_decode($user['dashboard_config'], true);
            if (isset($parsed['quick_access']) && is_array($parsed['quick_access'])) {
                $saved = array_values(array_intersect($parsed['quick_access'], $availableSlugs));
            }
        }
        $selected = $saved ?? Family::defaultQuickAccess($availableSlugs);

        $make = fn(string $slug) => [
            'slug'  => $slug,
            'route' => Family::QUICK_NAV_ROUTES[$slug],
            'icon'  => Family::MODULES[$slug]['icon'] ?? '▫️',
            'label' => Family::MODULES[$slug]['label'] ?? $slug,
        ];

        return [
            'available' => array_map($make, $availableSlugs),
            'selected'  => array_map($make, $selected),
        ];
    }

    private function getUserWidgets(array $user, array $disabledModules): array
    {
        $default = [
            ['slug' => 'quick_access', 'enabled' => true],
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
                $friendFamilyIds = \App\Models\FamilyFriend::getAcceptedFamilyIds($familyId);
                return ['posts' => Post::getVisibleForUser($familyId, (int)$user['id'], $visibleAuthorIds, $friendFamilyIds, 5)];
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
            case 'chat':
                return ['messages' => array_reverse(Message::getByFamily($familyId, 5))];
            case 'budget':
                return ['summary' => Budget::getSummary($familyId, date('Y-m'))];
            case 'projects':
                return ['projects' => array_slice(Project::getByFamily($familyId), 0, 4)];
            case 'stock':
                return ['expiring' => StockItem::getExpiringSoon($familyId, 5)];
            case 'polls':
                $polls = Poll::getByFamily($familyId, (int)$user['id']);
                $open  = array_values(array_filter($polls, fn($p) => !$p['is_closed']));
                return ['polls' => array_slice($open, 0, 3)];
            case 'wishlist':
                $items = WishlistItem::getByFamily($familyId);
                $open  = array_values(array_filter($items, fn($i) => empty($i['is_purchased'])));
                return ['items' => array_slice($open, 0, 4)];
            case 'medication':
                return ['treatments' => array_values(array_filter(
                    MedicalTreatment::getForUser($familyId, (int)$user['id']),
                    fn($t) => !empty($t['active'])
                ))];
            case 'vehicles':
                return ['vehicles' => array_slice(Vehicle::getByFamily($familyId), 0, 4)];
            case 'pets':
                return ['pets' => array_slice(Pet::getByFamily($familyId), 0, 4)];
            case 'travels':
                return ['travels' => array_slice(Travel::getByFamily($familyId), 0, 3)];
            case 'meters':
                return ['meters' => array_slice(Meter::getByFamily($familyId), 0, 4)];
            default:
                return [];
        }
    }

    /** Chiffres clés affichés en haut du tableau de bord — toujours calculés à partir de
     *  données réelles ; un module désactivé pour la famille retombe sur null plutôt que
     *  d'afficher un chiffre inventé (voir dashboard.php, qui masque la carte dans ce cas). */
    private function computeHeaderStats(int $familyId, int $userId, array $disabledModules): array
    {
        $today = date('Y-m-d');

        // Tâches du jour (toutes listes hors "courses"), sur l'échéance du jour
        $tasksToday = null;
        $lists = TaskList::getByFamily($familyId);
        $dueToday = 0; $dueTodayDone = 0;
        foreach ($lists as $list) {
            if ($list['type'] === 'shopping') continue;
            foreach (TaskList::getTasks((int)$list['id']) as $t) {
                if (($t['due_date'] ?? null) === $today) {
                    $dueToday++;
                    if (!empty($t['is_completed'])) $dueTodayDone++;
                }
            }
        }
        $tasksToday = ['total' => $dueToday, 'done' => $dueTodayDone, 'remaining' => $dueToday - $dueTodayDone];

        // Événements à venir (7 jours) + le tout prochain
        $upcoming = Event::getUpcoming($familyId, 7);
        $eventsStat = ['count' => count($upcoming), 'next' => $upcoming[0] ?? null];

        // Budget du mois (% des revenus déjà dépensé)
        $budgetStat = null;
        if (!in_array('budget', $disabledModules)) {
            $summary = Budget::getSummary($familyId, date('Y-m'));
            $pct = $summary['income'] > 0 ? min(100, round($summary['expenses'] / $summary['income'] * 100)) : null;
            $budgetStat = ['percent' => $pct, 'income' => $summary['income'], 'expenses' => $summary['expenses']];
        }

        // Messages privés non lus (le chat familial n'a pas de suivi de lecture par membre)
        $unreadMessages = null;
        if (!in_array('chat', $disabledModules)) {
            $threads = DirectMessage::getThreadsForUser($userId);
            $unreadMessages = array_sum(array_column($threads, 'unread_count'));
        }

        return [
            'tasks'   => $tasksToday,
            'events'  => $eventsStat,
            'budget'  => $budgetStat,
            'unread'  => $unreadMessages,
        ];
    }

    /** Évènements + gardes d'aujourd'hui et demain, fusionnés et triés — alimente la carte
     *  "Aujourd'hui & demain" (vue condensée ; le widget Calendrier garde la liste complète). */
    private function computeTimeline(int $familyId): array
    {
        $today    = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $events  = Event::getUpcoming($familyId, 2);
        $custody = Custody::getAllEventsForFamily($familyId, $today, $tomorrow);

        $items = [];
        foreach ($events as $e) {
            $date = substr($e['start_datetime'], 0, 10);
            if ($date !== $today && $date !== $tomorrow) continue;
            $items[] = [
                'type'  => 'event',
                'date'  => $date,
                'sort'  => $e['start_datetime'],
                'color' => $e['color'] ?? '#7C5CFF',
                'label' => ($e['is_all_day'] ? 'Journée' : substr($e['start_datetime'], 11, 5)) . ' · ' . $e['title'],
            ];
        }
        foreach ($custody as $c) {
            $date = $c['start_date'] ?? $today;
            if ($date !== $today && $date !== $tomorrow) continue;
            $items[] = [
                'type'  => 'custody',
                'date'  => $date,
                'sort'  => $date . ' 00:01',
                'color' => $c['schedule_color'] ?? '#2BD4A8',
                'label' => trim(($c['child_name'] ?? '') . ' chez ' . ($c['parent_name'] ?? '—')),
            ];
        }
        usort($items, fn($a, $b) => strcmp($a['sort'], $b['sort']));

        return ['today' => $today, 'tomorrow' => $tomorrow, 'items' => $items];
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';


// Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\Session;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\CalendarController;
use App\Controllers\WallController;
use App\Controllers\AlbumController;
use App\Controllers\TaskController;
use App\Controllers\ChatController;
use App\Controllers\BudgetController;
use App\Controllers\CustodyController;
use App\Controllers\ProjectController;
use App\Controllers\SettingsController;
use App\Controllers\InvitationController;
use App\Controllers\CoparentController;
use App\Controllers\ContactController;
use App\Controllers\AdminController;
use App\Controllers\SupportController;
use App\Controllers\WarrantyController;
use App\Controllers\DocumentController;
use App\Controllers\FamilyWallController;
use App\Controllers\CameraController;
use App\Controllers\BabyController;
use App\Controllers\PushController;
use App\Controllers\LocationController;
use App\Controllers\EmergencyController;
use App\Controllers\CommLogController;
use App\Controllers\MealController;
use App\Controllers\SitterController;
use App\Controllers\KioskController;
use App\Controllers\NotificationController;
use App\Controllers\ErrorReportController;

Session::start();

// Global exception handler — ensures a clean HTTP response even on fatal
// errors, and automatically opens a support ticket in the affected user's
// name (continuous technical-error monitoring — see App\Core\ErrorReporter).
set_exception_handler(function (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
    }
    \App\Core\ErrorReporter::report('server', $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        echo '<h1>Erreur interne</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    exit;
});

// Catches the remaining fatal errors that PHP does not raise as a Throwable
// (memory exhaustion, parse errors in included files…) so they too trigger
// an automatic support ticket instead of failing completely silently.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        \App\Core\ErrorReporter::report('server', $error['message'], [
            'file' => $error['file'],
            'line' => $error['line'],
        ]);
    }
});

// ── IP block check (skip admin routes) ──────────────────────
if (!str_starts_with(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', (BASE_URL ?: '') . '/admin')) {
    try {
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $clientIp = trim(explode(',', $clientIp)[0]);
        if ($clientIp) {
            $blockedIp = \App\Core\Database::fetch('SELECT reason FROM blocked_ips WHERE ip=? LIMIT 1', [$clientIp]);
            if ($blockedIp) {
                http_response_code(403);
                $reason = $blockedIp['reason'] ?? '';
                require BASE_PATH . '/templates/blocked.php';
                exit;
            }
        }
    } catch (\Throwable) {}
}

// Auto-apply any pending SQL migrations
try {
    \App\Core\Database::autoMigrate(BASE_PATH . '/database');
} catch (\Throwable $e) {
    error_log('Migration error: ' . $e->getMessage());
}

// Restore a persistent login (PWA) from the remember-me cookie if the PHP
// session has expired or been cleared.
if (!Session::isLoggedIn()) {
    try {
        \App\Core\RememberMe::attempt();
    } catch (\Throwable $e) {
        error_log('RememberMe error: ' . $e->getMessage());
    }
}

// Apply family timezone as early as possible (graceful fallback if migration not yet applied)
$_appTz = 'Europe/Paris';
if (Session::isLoggedIn()) {
    try {
        $_appTz = \App\Models\Family::getTimezone(Session::user()['family_id']);
    } catch (\Throwable) { /* column may not exist yet */ }
}
date_default_timezone_set($_appTz);
define('APP_TIMEZONE', $_appTz);
unset($_appTz);

$router = new Router();

// Auth
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/logout', [AuthController::class, 'logout']);

// Dashboard
$router->get('/', [DashboardController::class, 'index']);
$router->post('/api/dashboard/widgets', [DashboardController::class, 'saveWidgets']);

// Calendar
$router->get('/calendar', [CalendarController::class, 'index']);
$router->get('/api/calendar/events', [CalendarController::class, 'apiEvents']);
$router->post('/api/calendar/events', [CalendarController::class, 'create']);
$router->post('/api/calendar/events/:id', [CalendarController::class, 'update']);
$router->post('/api/calendar/events/:id/delete', [CalendarController::class, 'delete']);
$router->post('/api/calendar/vacations', [CalendarController::class, 'createVacation']);
$router->post('/api/calendar/vacations/:id/delete', [CalendarController::class, 'deleteVacation']);
$router->post('/api/calendar/caldav', [CalendarController::class, 'addCalDAV']);
$router->post('/api/calendar/caldav/:id/sync', [CalendarController::class, 'syncCalDAV']);
$router->post('/api/calendar/caldav/:id/delete', [CalendarController::class, 'deleteCalDAV']);

// Wall
$router->get('/wall', [WallController::class, 'index']);
$router->post('/wall', [WallController::class, 'create']);
$router->post('/wall/:id/delete', [WallController::class, 'delete']);
$router->post('/api/wall/:id/update', [WallController::class, 'update']);
$router->post('/api/wall/:id/comment', [WallController::class, 'addComment']);
$router->post('/api/wall/:id/react', [WallController::class, 'toggleReaction']);
$router->get('/api/wall/more', [WallController::class, 'loadMore']);

// Albums photo
$router->get('/albums', [AlbumController::class, 'index']);
$router->get('/albums/:id', [AlbumController::class, 'show']);
$router->post('/api/albums', [AlbumController::class, 'create']);
$router->post('/api/albums/:id', [AlbumController::class, 'update']);
$router->post('/api/albums/:id/delete', [AlbumController::class, 'delete']);
$router->post('/api/albums/:id/share', [AlbumController::class, 'share']);
$router->post('/albums/:id/photos', [AlbumController::class, 'addPhoto']);
$router->post('/api/albums/photos/:id/delete', [AlbumController::class, 'deletePhoto']);
$router->post('/api/albums/:id/public-link', [AlbumController::class, 'publicLinkCreate']);
$router->post('/api/albums/:id/public-link/update', [AlbumController::class, 'publicLinkUpdate']);
$router->post('/api/albums/:id/public-link/revoke', [AlbumController::class, 'publicLinkRevoke']);
$router->get('/album/:token', [AlbumController::class, 'publicView']);
$router->post('/album/:token/photos', [AlbumController::class, 'publicUpload']);

// Tasks
$router->get('/tasks', [TaskController::class, 'index']);
$router->post('/tasks/list', [TaskController::class, 'createList']);
$router->post('/tasks/list/:id/delete', [TaskController::class, 'deleteList']);
$router->post('/api/tasks/list/:id/task', [TaskController::class, 'createTask']);
$router->post('/api/tasks/task/:id/toggle', [TaskController::class, 'toggleTask']);
$router->post('/api/tasks/task/:id/update', [TaskController::class, 'updateTask']);
$router->post('/api/tasks/task/:id/delete', [TaskController::class, 'deleteTask']);

// Chat
$router->get('/chat', [ChatController::class, 'index']);
$router->post('/api/chat/send', [ChatController::class, 'send']);
$router->get('/api/chat/poll', [ChatController::class, 'poll']);
$router->post('/api/chat/:id/delete', [ChatController::class, 'delete']);
$router->get('/api/chat/:id/audio', [ChatController::class, 'serveAudio']);

// Budget
$router->get('/budget', [BudgetController::class, 'index']);
$router->get('/api/budget/data', [BudgetController::class, 'apiData']);
$router->post('/api/budget/category', [BudgetController::class, 'createCategory']);
$router->post('/api/budget/category/:id/delete', [BudgetController::class, 'deleteCategory']);
$router->post('/api/budget/transaction', [BudgetController::class, 'createTransaction']);
$router->post('/api/budget/transaction/:id', [BudgetController::class, 'updateTransaction']);
$router->post('/api/budget/transaction/:id/delete', [BudgetController::class, 'deleteTransaction']);
$router->post('/api/budget/goal', [BudgetController::class, 'createGoal']);
$router->post('/api/budget/goal/:id', [BudgetController::class, 'updateGoal']);
$router->post('/api/budget/goal/:id/delete', [BudgetController::class, 'deleteGoal']);
$router->post('/api/budget/recurring', [BudgetController::class, 'createRecurring']);
$router->post('/api/budget/recurring/:id', [BudgetController::class, 'updateRecurring']);
$router->post('/api/budget/recurring/:id/delete', [BudgetController::class, 'deleteRecurring']);
$router->get('/api/budget/chart', [BudgetController::class, 'chartData']);
$router->get('/api/budget/suggest-category', [BudgetController::class, 'suggestCategory']);

// Custody
$router->get('/custody', [CustodyController::class, 'index']);
$router->get('/api/custody/events', [CustodyController::class, 'apiEvents']);
$router->post('/api/custody/schedule', [CustodyController::class, 'createSchedule']);
$router->post('/api/custody/schedule/:id', [CustodyController::class, 'updateSchedule']);
$router->post('/api/custody/schedule/:id/delete', [CustodyController::class, 'deleteSchedule']);
$router->post('/api/custody/event', [CustodyController::class, 'createEvent']);
$router->post('/api/custody/event/:id', [CustodyController::class, 'updateEvent']);
$router->post('/api/custody/event/:id/delete', [CustodyController::class, 'deleteEvent']);
$router->post('/api/custody/proposal/apply', [CustodyController::class, 'applyProposal']);
$router->get('/api/custody/schedule/:id/vacation-list', [CustodyController::class, 'listVacationPeriods']);
$router->post('/api/custody/schedule/:id/vacation', [CustodyController::class, 'createVacationPeriod']);
$router->post('/api/custody/vacation/:id', [CustodyController::class, 'updateVacationPeriod']);
$router->post('/api/custody/vacation/:id/delete', [CustodyController::class, 'deleteVacationPeriod']);
$router->post('/api/custody/schedule/:id/invite-coparent', [CustodyController::class, 'inviteCoparent']);
$router->get('/api/custody/schedule/:id/activity-log', [CustodyController::class, 'activityLog']);

// Vue co-parent à accès restreint
$router->get('/coparent', [CoparentController::class, 'index']);
$router->post('/api/coparent/create-family', [CoparentController::class, 'createFamily']);
$router->get('/api/coparent/custody-events', [CoparentController::class, 'apiCustodyEvents']);
$router->post('/api/coparent/custody-proposal', [CoparentController::class, 'proposeCustody']);
$router->get('/api/coparent/journal', [CoparentController::class, 'journal']);
$router->post('/api/coparent/journal', [CoparentController::class, 'journalSend']);
$router->get('/api/coparent/journal/:id/audio', [CoparentController::class, 'journalAudio']);
$router->get('/api/coparent/activity-log', [CoparentController::class, 'activityLog']);
$router->get('/api/coparent/documents', [CoparentController::class, 'documentsList']);
$router->post('/api/coparent/documents', [CoparentController::class, 'documentsUpload']);
$router->get('/api/coparent/events', [CoparentController::class, 'eventsList']);
$router->post('/api/coparent/events', [CoparentController::class, 'eventsCreate']);
$router->get('/api/coparent/albums', [CoparentController::class, 'albumsList']);
$router->get('/api/coparent/albums/:id', [CoparentController::class, 'albumShow']);
$router->post('/api/coparent/albums/:id/photos', [CoparentController::class, 'albumPhotoUpload']);

// Projects
$router->get('/projects', [ProjectController::class, 'index']);
$router->get('/api/projects/calendar', [ProjectController::class, 'apiCalendar']);
$router->get('/api/projects/:id/calendar', [ProjectController::class, 'apiProjectCalendar']);
$router->get('/projects/:id', [ProjectController::class, 'show']);
$router->post('/api/projects', [ProjectController::class, 'create']);
$router->post('/api/projects/:id', [ProjectController::class, 'update']);
$router->post('/api/projects/:id/delete', [ProjectController::class, 'delete']);
$router->post('/api/projects/:id/task', [ProjectController::class, 'createTask']);
$router->post('/api/projects/task/:id', [ProjectController::class, 'updateTask']);
$router->post('/api/projects/task/:id/delete', [ProjectController::class, 'deleteTask']);
$router->post('/api/projects/:id/expense', [ProjectController::class, 'createExpense']);
$router->post('/api/projects/expense/:id/delete', [ProjectController::class, 'deleteExpense']);
$router->post('/api/projects/:id/material', [ProjectController::class, 'createMaterial']);
$router->post('/api/projects/material/:id', [ProjectController::class, 'updateMaterial']);
$router->post('/api/projects/material/:id/toggle', [ProjectController::class, 'toggleMaterial']);
$router->post('/api/projects/material/:id/delete', [ProjectController::class, 'deleteMaterial']);

// Admin
$router->get('/admin/login', [AdminController::class, 'showLogin']);
$router->post('/admin/login', [AdminController::class, 'login']);
$router->get('/admin/logout', [AdminController::class, 'logout']);
$router->get('/admin', [AdminController::class, 'index']);
$router->post('/admin/users/:id/block', [AdminController::class, 'blockUser']);
$router->post('/admin/users/:id/unblock', [AdminController::class, 'unblockUser']);
$router->post('/admin/users/:id/impersonate', [AdminController::class, 'impersonate']);
$router->get('/admin/stop-impersonating', [AdminController::class, 'stopImpersonating']);
$router->post('/admin/ips', [AdminController::class, 'addIp']);
$router->post('/admin/ips/:id/delete', [AdminController::class, 'deleteIp']);
$router->get('/admin/tickets/:id', [AdminController::class, 'viewTicket']);
$router->post('/admin/tickets/:id/reply', [AdminController::class, 'replyTicket']);
$router->post('/admin/tickets/:id/close', [AdminController::class, 'closeTicket']);
$router->post('/admin/tickets/:id/reopen', [AdminController::class, 'reopenTicket']);
$router->get('/admin/profile', [AdminController::class, 'showProfile']);
$router->post('/admin/profile', [AdminController::class, 'updateProfile']);
$router->post('/admin/smtp', [AdminController::class, 'updateSmtp']);
$router->post('/admin/smtp/test', [AdminController::class, 'testSmtp']);
$router->post('/admin/smtp/send-test', [AdminController::class, 'sendTestEmail']);
$router->post('/admin/email-content', [AdminController::class, 'saveEmailContent']);
$router->post('/admin/email-content/:type/reset', [AdminController::class, 'resetEmailContent']);
$router->get('/admin/email-content/:type/preview', [AdminController::class, 'previewEmailContent']);
$router->post('/admin/notifications/send', [AdminController::class, 'sendSystemNotification']);

// Support (user-facing)
$router->get('/support', [SupportController::class, 'index']);
$router->post('/support', [SupportController::class, 'create']);
$router->get('/support/:id', [SupportController::class, 'show']);
$router->post('/support/:id/reply', [SupportController::class, 'reply']);

// Contacts
$router->get('/contacts', [ContactController::class, 'index']);
$router->get('/contacts/export.vcf', [ContactController::class, 'exportVcf']);
$router->get('/contacts/:id/vcard', [ContactController::class, 'downloadVcard']);
$router->post('/api/contacts', [ContactController::class, 'create']);
$router->post('/api/contacts/:id', [ContactController::class, 'update']);
$router->post('/api/contacts/:id/delete', [ContactController::class, 'delete']);
$router->post('/api/contacts/:id/avatar', [ContactController::class, 'uploadAvatar']);

// Cameras
$router->get('/cameras', [CameraController::class, 'index']);
$router->post('/api/cameras', [CameraController::class, 'create']);
$router->post('/api/cameras/:id', [CameraController::class, 'update']);
$router->post('/api/cameras/:id/delete', [CameraController::class, 'delete']);
$router->post('/api/cameras/:id/go2rtc',         [CameraController::class, 'go2rtcRegister']);
$router->get('/api/cameras/:id/go2rtc',          [CameraController::class, 'go2rtcStream']);
$router->get('/api/cameras/:id/go2rtc/hls',      [CameraController::class, 'go2rtcHls']);
$router->get('/api/cameras/:id/go2rtc/hls/seg',  [CameraController::class, 'go2rtcHlsSeg']);

// Settings
$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/profile', [SettingsController::class, 'updateProfile']);
$router->post('/settings/family',   [SettingsController::class, 'updateFamily']);
$router->post('/settings/modules',  [SettingsController::class, 'updateModules']);
$router->post('/settings/family/code', [SettingsController::class, 'regenerateCode']);
$router->post('/settings/member/:id/remove', [SettingsController::class, 'removeMember']);
$router->get('/settings/export', [SettingsController::class, 'exportData']);
$router->post('/settings/delete-account', [SettingsController::class, 'deleteAccount']);
$router->get('/app/android', [SettingsController::class, 'downloadAndroidApk']);
$router->get('/api/notifications', [SettingsController::class, 'getNotifications']);
$router->post('/api/notifications/:id/read', [SettingsController::class, 'markNotificationRead']);
$router->post('/api/notifications/read-all', [SettingsController::class, 'markAllNotificationsRead']);
$router->post('/settings/notify', [SettingsController::class, 'sendNotification']);
$router->get('/notifications/:id', [NotificationController::class, 'show']);

// Push notifications
$router->get('/api/push/vapid-public-key', [PushController::class, 'vapidPublicKey']);
$router->post('/api/push/subscribe', [PushController::class, 'subscribe']);
$router->post('/api/push/unsubscribe', [PushController::class, 'unsubscribe']);

// Remontée automatique d'erreurs techniques (surveillance continue)
$router->post('/api/errors/report', [ErrorReportController::class, 'report']);
$router->post('/api/errors/report-manual', [ErrorReportController::class, 'reportManual']);

// Warranties
$router->get('/warranties', [WarrantyController::class, 'index']);
$router->get('/warranties/file/:id', [WarrantyController::class, 'serveFile']);
$router->post('/api/warranties', [WarrantyController::class, 'create']);
$router->post('/api/warranties/ocr', [WarrantyController::class, 'ocr']);
$router->get('/api/warranties/search', [WarrantyController::class, 'search']);
$router->get('/api/warranties/ocr-check', [WarrantyController::class, 'ocrCheck']);
$router->post('/api/warranties/:id', [WarrantyController::class, 'update']);
$router->post('/api/warranties/:id/delete', [WarrantyController::class, 'delete']);

// Documents
$router->get('/documents', [DocumentController::class, 'index']);
$router->get('/documents/file/:id', [DocumentController::class, 'serveFile']);
$router->post('/api/documents', [DocumentController::class, 'create']);
$router->post('/api/documents/ocr', [DocumentController::class, 'ocr']);
$router->get('/api/documents/search', [DocumentController::class, 'search']);
$router->post('/api/documents/:id', [DocumentController::class, 'update']);
$router->post('/api/documents/:id/delete', [DocumentController::class, 'delete']);

// Baby
$router->get('/baby', [BabyController::class, 'index']);
$router->post('/api/baby', [BabyController::class, 'createBaby']);
$router->post('/api/baby/:id', [BabyController::class, 'updateBaby']);
$router->post('/api/baby/:id/avatar', [BabyController::class, 'uploadBabyAvatar']);
$router->post('/api/baby/:id/delete', [BabyController::class, 'deleteBaby']);
$router->get('/api/baby/:baby_id/pregnancy', [BabyController::class, 'getPregnancy']);
$router->post('/api/baby/:baby_id/pregnancy', [BabyController::class, 'upsertPregnancy']);
$router->post('/api/baby/pregnancy/:pregnancy_id/consultations', [BabyController::class, 'createPregnancyConsultation']);
$router->post('/api/baby/pregnancy-consultations/:id/delete', [BabyController::class, 'deletePregnancyConsultation']);
$router->post('/api/baby/pregnancy-images/:id/delete', [BabyController::class, 'deletePregnancyImage']);
$router->get('/api/baby/:baby_id/events', [BabyController::class, 'apiEvents']);
$router->post('/api/baby/:baby_id/events', [BabyController::class, 'createEvent']);
$router->post('/api/baby/:baby_id/events/:id', [BabyController::class, 'updateEvent']);
$router->post('/api/baby/:baby_id/events/:id/delete', [BabyController::class, 'deleteEvent']);
$router->post('/api/baby/:baby_id/sleeps', [BabyController::class, 'createSleep']);
$router->post('/api/baby/:baby_id/sleeps/:id', [BabyController::class, 'updateSleep']);
$router->post('/api/baby/:baby_id/sleeps/:id/end', [BabyController::class, 'endSleep']);
$router->post('/api/baby/:baby_id/sleeps/:id/delete', [BabyController::class, 'deleteSleep']);
$router->get('/api/baby/:baby_id/consultations', [BabyController::class, 'apiConsultations']);
$router->post('/api/baby/:baby_id/consultations', [BabyController::class, 'createConsultation']);
$router->post('/api/baby/:baby_id/consultations/:id', [BabyController::class, 'updateConsultation']);
$router->post('/api/baby/:baby_id/consultations/:id/delete', [BabyController::class, 'deleteConsultation']);

// Family Wall (écran mural)
$router->get('/family-wall', [FamilyWallController::class, 'index']);
$router->get('/api/family-wall/data', [FamilyWallController::class, 'apiData']);

// Location (partage de position ponctuel)
$router->get('/location', [LocationController::class, 'index']);
$router->get('/api/location/current', [LocationController::class, 'apiCurrent']);
$router->post('/api/location/places', [LocationController::class, 'createPlace']);
$router->post('/api/location/places/:id/delete', [LocationController::class, 'deletePlace']);
$router->post('/api/location/checkin', [LocationController::class, 'checkin']);
$router->post('/api/location/clear', [LocationController::class, 'clear']);

// Emergency cards (fiches urgence)
$router->get('/emergency', [EmergencyController::class, 'index']);
$router->post('/api/emergency', [EmergencyController::class, 'save']);
$router->get('/emergency/public/:token', [EmergencyController::class, 'publicView']);

// Journal parental (communication horodatée, immuable)
$router->get('/comm-log', [CommLogController::class, 'index']);
$router->post('/api/comm-log/send', [CommLogController::class, 'send']);
$router->get('/api/comm-log/poll', [CommLogController::class, 'poll']);
$router->get('/api/comm-log/:id/audio', [CommLogController::class, 'serveAudio']);

// Repas
$router->get('/meals', [MealController::class, 'index']);
$router->post('/api/meals/recipes', [MealController::class, 'createRecipe']);
$router->post('/api/meals/recipes/:id', [MealController::class, 'updateRecipe']);
$router->post('/api/meals/recipes/:id/delete', [MealController::class, 'deleteRecipe']);
$router->post('/api/meals/recipes/:id/add-to-shopping-list', [MealController::class, 'addIngredientsToShoppingList']);
$router->post('/api/meals/plan', [MealController::class, 'setMeal']);
$router->post('/api/meals/plan/clear', [MealController::class, 'clearMeal']);

// Mode baby-sitter
$router->post('/api/sitter/links', [SitterController::class, 'create']);
$router->post('/api/sitter/links/:id/revoke', [SitterController::class, 'revoke']);
$router->get('/sitter/:token', [SitterController::class, 'view']);

// Mode kiosque (écran mural)
$router->post('/api/kiosk/links', [KioskController::class, 'create']);
$router->post('/api/kiosk/links/:id/revoke', [KioskController::class, 'revoke']);
$router->get('/kiosk/:token', [KioskController::class, 'view']);
$router->get('/kiosk/:token/data', [KioskController::class, 'data']);
$router->post('/kiosk/:token/tasks', [KioskController::class, 'createTask']);
$router->post('/kiosk/:token/tasks/:id/toggle', [KioskController::class, 'toggleTask']);

// Invitations
$router->get('/invite/:token', [InvitationController::class, 'show']);
$router->post('/invite/:token', [InvitationController::class, 'accept']);
$router->post('/api/settings/invite', [InvitationController::class, 'sendInvite']);

$router->dispatch();

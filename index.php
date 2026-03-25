<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

// Global exception handler — ensures a clean HTTP response even on fatal errors
set_exception_handler(function (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
    }
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
use App\Controllers\TaskController;
use App\Controllers\ChatController;
use App\Controllers\BudgetController;
use App\Controllers\CustodyController;
use App\Controllers\ProjectController;
use App\Controllers\SettingsController;
use App\Controllers\InvitationController;
use App\Controllers\WarrantyController;
use App\Controllers\DocumentController;
use App\Controllers\FamilyWallController;
use App\Controllers\CameraController;

Session::start();

// Auto-apply any pending SQL migrations
try {
    \App\Core\Database::autoMigrate(BASE_PATH . '/database/migrations');
} catch (\Throwable $e) {
    error_log('Migration error: ' . $e->getMessage());
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

// Calendar
$router->get('/calendar', [CalendarController::class, 'index']);
$router->get('/api/calendar/events', [CalendarController::class, 'apiEvents']);
$router->post('/api/calendar/events', [CalendarController::class, 'create']);
$router->post('/api/calendar/events/:id', [CalendarController::class, 'update']);
$router->post('/api/calendar/events/:id/delete', [CalendarController::class, 'delete']);
$router->post('/api/calendar/caldav', [CalendarController::class, 'addCalDAV']);
$router->post('/api/calendar/caldav/:id/sync', [CalendarController::class, 'syncCalDAV']);
$router->post('/api/calendar/caldav/:id/delete', [CalendarController::class, 'deleteCalDAV']);

// Wall
$router->get('/wall', [WallController::class, 'index']);
$router->post('/wall', [WallController::class, 'create']);
$router->post('/wall/:id/delete', [WallController::class, 'delete']);
$router->post('/api/wall/:id/comment', [WallController::class, 'addComment']);
$router->post('/api/wall/:id/react', [WallController::class, 'toggleReaction']);
$router->get('/api/wall/more', [WallController::class, 'loadMore']);

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

// Custody
$router->get('/custody', [CustodyController::class, 'index']);
$router->get('/api/custody/events', [CustodyController::class, 'apiEvents']);
$router->post('/api/custody/schedule', [CustodyController::class, 'createSchedule']);
$router->post('/api/custody/schedule/:id', [CustodyController::class, 'updateSchedule']);
$router->post('/api/custody/schedule/:id/delete', [CustodyController::class, 'deleteSchedule']);
$router->post('/api/custody/event', [CustodyController::class, 'createEvent']);
$router->post('/api/custody/event/:id', [CustodyController::class, 'updateEvent']);
$router->post('/api/custody/event/:id/delete', [CustodyController::class, 'deleteEvent']);

// Projects
$router->get('/projects', [ProjectController::class, 'index']);
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

// Cameras
$router->get('/cameras', [CameraController::class, 'index']);
$router->post('/api/cameras', [CameraController::class, 'create']);
$router->post('/api/cameras/:id', [CameraController::class, 'update']);
$router->post('/api/cameras/:id/delete', [CameraController::class, 'delete']);
$router->get('/api/cameras/:id/stream', [CameraController::class, 'stream']);

// Settings
$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/profile', [SettingsController::class, 'updateProfile']);
$router->post('/settings/family', [SettingsController::class, 'updateFamily']);
$router->post('/settings/family/code', [SettingsController::class, 'regenerateCode']);
$router->post('/settings/smtp', [SettingsController::class, 'updateSmtp']);
$router->post('/api/settings/smtp/test', [SettingsController::class, 'testSmtp']);
$router->post('/api/settings/smtp/send-test', [SettingsController::class, 'sendTestEmail']);
$router->post('/settings/member/:id/remove', [SettingsController::class, 'removeMember']);
$router->post('/api/settings/email-template', [SettingsController::class, 'saveEmailTemplate']);
$router->post('/api/settings/email-template/:type/reset', [SettingsController::class, 'resetEmailTemplate']);
$router->get('/api/notifications', [SettingsController::class, 'getNotifications']);
$router->post('/api/notifications/:id/read', [SettingsController::class, 'markNotificationRead']);
$router->post('/api/notifications/read-all', [SettingsController::class, 'markAllNotificationsRead']);

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

// Family Wall (écran mural)
$router->get('/family-wall', [FamilyWallController::class, 'index']);
$router->get('/api/family-wall/data', [FamilyWallController::class, 'apiData']);

// Invitations
$router->get('/invite/:token', [InvitationController::class, 'show']);
$router->post('/invite/:token', [InvitationController::class, 'accept']);
$router->post('/api/settings/invite', [InvitationController::class, 'sendInvite']);

$router->dispatch();

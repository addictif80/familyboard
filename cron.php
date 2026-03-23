<?php
/**
 * FamilyBoard — Cron script for email reminders.
 * Run via cron: * * * * * php /path/to/familyboard/cron.php >> /var/log/familyboard-cron.log 2>&1
 * Recommended: every hour  →  0 * * * *
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

spl_autoload_register(function (string $class) {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/src/';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\Database;
use App\Core\Mail;
use App\Models\Family;
use App\Models\User;
use App\Models\Event;
use App\Models\TaskList;
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use App\Models\SmtpSettings;

$appUrl = (getenv('APP_URL') ?: 'https://board.abhd.fr') . BASE_URL;

// Get all families that have SMTP configured
$families = Database::fetchAll(
    'SELECT DISTINCT f.* FROM families f JOIN smtp_settings ss ON ss.family_id = f.id'
);

foreach ($families as $family) {
    $familyId = (int)$family['id'];
    $members  = User::getByFamily($familyId);

    sendEventReminders($familyId, $members, $appUrl);
    sendTaskReminders($familyId, $members, $appUrl);
    sendShoppingReminders($familyId, $members, $appUrl);
}

echo '[' . date('Y-m-d H:i:s') . '] Cron complete.' . PHP_EOL;

// ──────────────────────────────────────────────────────────────────────────
// Event reminders: events starting in next 24h, not yet reminded
// ──────────────────────────────────────────────────────────────────────────
function sendEventReminders(int $familyId, array $members, string $appUrl): void
{
    $events = Database::fetchAll(
        'SELECT e.*, u.name as user_name FROM events e JOIN users u ON u.id=e.user_id
         WHERE e.family_id=?
           AND e.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
           AND e.caldav_source_id IS NULL',
        [$familyId]
    );

    foreach ($events as $event) {
        foreach ($members as $member) {
            if (empty($member['email'])) continue;

            // Avoid duplicate: check if reminder already sent for this event+member
            $key = 'event_' . $event['id'];
            $alreadySent = Database::fetch(
                'SELECT id FROM email_logs WHERE family_id=? AND type=? AND to_email=? AND subject LIKE ?
                 AND created_at > DATE_SUB(NOW(), INTERVAL 25 HOUR)',
                [$familyId, 'event_reminder', $member['email'], '%' . $event['id'] . '%']
            );
            if ($alreadySent) continue;

            $date = date('d/m/Y à H:i', strtotime($event['start_datetime']));
            $rendered = EmailTemplate::render($familyId, 'event_reminder', [
                'family_name'       => '',
                'user_name'         => $member['name'],
                'event_title'       => $event['title'],
                'event_date'        => $date,
                'event_description' => $event['description'] ?: '',
                'app_url'           => $appUrl,
            ]);

            Mail::send($familyId, $member['email'], $member['name'],
                $rendered['subject'], $rendered['body'], 'event_reminder');

            echo "  → Event reminder sent to {$member['email']} for event #{$event['id']}" . PHP_EOL;
        }
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Task reminders: tasks pending for 7+ days
// ──────────────────────────────────────────────────────────────────────────
function sendTaskReminders(int $familyId, array $members, string $appUrl): void
{
    $tasks = Database::fetchAll(
        'SELECT t.*, tl.name as list_name, tl.is_shopping FROM tasks t
         JOIN task_lists tl ON tl.id = t.list_id
         WHERE tl.family_id=? AND t.is_done=0 AND tl.is_shopping=0
           AND t.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)',
        [$familyId]
    );

    foreach ($tasks as $task) {
        // Send to the assigned user or all members if no assignment
        $recipients = [];
        if (!empty($task['assigned_to'])) {
            $assignee = Database::fetch('SELECT * FROM users WHERE id=?', [$task['assigned_to']]);
            if ($assignee) $recipients[] = $assignee;
        } else {
            $recipients = $members;
        }

        foreach ($recipients as $member) {
            if (empty($member['email'])) continue;

            $alreadySent = Database::fetch(
                'SELECT id FROM email_logs WHERE family_id=? AND type=? AND to_email=?
                 AND created_at > DATE_SUB(NOW(), INTERVAL 8 DAY)',
                [$familyId, 'task_reminder', $member['email']]
            );
            if ($alreadySent) continue;

            $rendered = EmailTemplate::render($familyId, 'task_reminder', [
                'family_name' => '',
                'user_name'   => $member['name'],
                'task_name'   => $task['title'],
                'list_name'   => $task['list_name'],
                'task_created' => date('d/m/Y', strtotime($task['created_at'])),
                'app_url'     => $appUrl,
            ]);

            Mail::send($familyId, $member['email'], $member['name'],
                $rendered['subject'], $rendered['body'], 'task_reminder');

            echo "  → Task reminder sent to {$member['email']} for task #{$task['id']}" . PHP_EOL;
        }
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Shopping reminders: shopping lists with pending items for 7+ days
// ──────────────────────────────────────────────────────────────────────────
function sendShoppingReminders(int $familyId, array $members, string $appUrl): void
{
    $lists = Database::fetchAll(
        'SELECT tl.* FROM task_lists tl
         WHERE tl.family_id=? AND tl.is_shopping=1
           AND EXISTS (
             SELECT 1 FROM tasks t WHERE t.list_id=tl.id AND t.is_done=0
               AND t.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
           )',
        [$familyId]
    );

    foreach ($lists as $list) {
        // Already sent reminder for this list this week?
        $alreadySent = Database::fetch(
            'SELECT id FROM email_logs WHERE family_id=? AND type=? AND subject LIKE ?
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)',
            [$familyId, 'shopping_reminder', '%' . $list['name'] . '%']
        );
        if ($alreadySent) continue;

        $items = Database::fetchAll(
            'SELECT title FROM tasks WHERE list_id=? AND is_done=0 ORDER BY created_at',
            [$list['id']]
        );
        $itemsHtml = implode('', array_map(fn($i) => '<li>' . htmlspecialchars($i['title']) . '</li>', $items));

        foreach ($members as $member) {
            if (empty($member['email'])) continue;

            $rendered = EmailTemplate::render($familyId, 'shopping_reminder', [
                'family_name' => '',
                'user_name'   => $member['name'],
                'list_name'   => $list['name'],
                'items'       => $itemsHtml,
                'app_url'     => $appUrl,
            ]);

            Mail::send($familyId, $member['email'], $member['name'],
                $rendered['subject'], $rendered['body'], 'shopping_reminder');

            echo "  → Shopping reminder sent to {$member['email']} for list #{$list['id']}" . PHP_EOL;
        }
    }
}

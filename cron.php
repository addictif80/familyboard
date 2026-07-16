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
use App\Core\DateHelper;
use App\Models\Family;
use App\Models\User;
use App\Models\Event;
use App\Models\TaskList;
use App\Models\EmailContent;
use App\Models\EmailLog;
use App\Models\CalDAVSource;
use App\Models\Notification;
use App\Core\EmailLayout;

$appUrl = (getenv('APP_URL') ?: 'https://board.abhd.fr') . BASE_URL;

// Auto-sync CalDAV sources for families that have configured an interval
syncCalDAVSources();

// SMTP is a single, global configuration (not per-family) — Mail::send()
// already no-ops gracefully when it isn't set up, so every family is processed
// the same way regardless of whether the system administrator configured it.
$families = Database::fetchAll('SELECT * FROM families');

foreach ($families as $family) {
    $familyId = (int)$family['id'];
    $members  = User::getByFamily($familyId);

    // Apply family timezone for date calculations
    $tz = $family['timezone'] ?? 'Europe/Paris';
    date_default_timezone_set($tz);

    sendEventReminders($familyId, $members, $appUrl);
    sendTomorrowEventDigest($familyId, $members, $appUrl);
    sendTaskReminders($familyId, $members, $appUrl);
    sendShoppingReminders($familyId, $members, $appUrl);
    sendRecurringAlerts($familyId, $appUrl);
}

echo '[' . date('Y-m-d H:i:s') . '] Cron complete.' . PHP_EOL;

// ──────────────────────────────────────────────────────────────────────────
// CalDAV auto-sync: synchronise les sources dont last_sync est dépassé
// ──────────────────────────────────────────────────────────────────────────
function syncCalDAVSources(): void
{
    $families = Database::fetchAll(
        'SELECT * FROM families WHERE caldav_sync_interval IS NOT NULL AND caldav_sync_interval > 0'
    );

    foreach ($families as $family) {
        $familyId = (int)$family['id'];
        $interval = (int)$family['caldav_sync_interval'];

        // Find an admin user for this family (needed to create events)
        $admin = Database::fetch(
            'SELECT id FROM users WHERE family_id=? AND role="admin" LIMIT 1',
            [$familyId]
        );
        if (!$admin) continue;
        $userId = (int)$admin['id'];

        // Sources due for sync: never synced, or synced more than $interval minutes ago
        $sources = Database::fetchAll(
            'SELECT * FROM caldav_sources
             WHERE family_id=?
               AND (last_sync IS NULL OR last_sync <= DATE_SUB(NOW(), INTERVAL ? MINUTE))',
            [$familyId, $interval]
        );

        foreach ($sources as $source) {
            $events = CalDAVSource::fetchEvents($source);
            if (empty($events)) {
                CalDAVSource::updateSyncTime($source['id']);
                echo "  [CalDAV] Source #{$source['id']} ({$source['name']}): fetch vide, last_sync mis à jour." . PHP_EOL;
                continue;
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
            echo "  [CalDAV] Source #{$source['id']} ({$source['name']}): {$count} événement(s) synchronisé(s)." . PHP_EOL;
        }
    }
}

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

            $date = DateHelper::longTime($event['start_datetime']);
            $rendered = EmailContent::render('event_reminder', [
                'user_name'         => $member['name'],
                'event_title'       => $event['title'],
                'event_date'        => $date,
                'event_description' => $event['description'] ?: '',
            ]);
            $extra = EmailLayout::box(
                '<strong>' . htmlspecialchars($event['title']) . '</strong><br>'
                . '📅 ' . htmlspecialchars($date)
                . ($event['description'] ? '<br>' . nl2br(htmlspecialchars($event['description'])) : '')
            );
            $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
                'label' => 'Voir le calendrier',
                'url'   => $appUrl . '/calendar',
            ], $extra);

            Mail::send($familyId, $member['email'], $member['name'],
                $rendered['subject'], $html, 'event_reminder');

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
        'SELECT t.*, tl.name as list_name FROM tasks t
         JOIN task_lists tl ON tl.id = t.list_id
         WHERE tl.family_id=? AND t.is_completed=0 AND tl.type != \'shopping\'
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

            $taskCreated = DateHelper::long($task['created_at']);
            $rendered = EmailContent::render('task_reminder', [
                'user_name'    => $member['name'],
                'task_name'    => $task['title'],
                'list_name'    => $task['list_name'],
                'task_created' => $taskCreated,
            ]);
            $extra = EmailLayout::box(
                '<strong>' . htmlspecialchars($task['title']) . '</strong><br>'
                . 'Liste : ' . htmlspecialchars($task['list_name']) . '<br>'
                . 'Créée le : ' . htmlspecialchars($taskCreated)
            );
            $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
                'label' => 'Voir les tâches',
                'url'   => $appUrl . '/tasks',
            ], $extra);

            Mail::send($familyId, $member['email'], $member['name'],
                $rendered['subject'], $html, 'task_reminder');

            echo "  → Task reminder sent to {$member['email']} for task #{$task['id']}" . PHP_EOL;
        }
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Recurring alerts: notify the assigned member N days before day_of_month
// ──────────────────────────────────────────────────────────────────────────
function sendRecurringAlerts(int $familyId, string $appUrl): void
{
    $today    = (int)date('j');
    $todayStr = date('Y-m-d');

    // Fetch active recurring items for this family
    $items = Database::fetchAll(
        'SELECT br.*, u.name as user_name, u.email as user_email,
                bc.name as category_name
         FROM budget_recurring br
         JOIN users u ON u.id = br.user_id
         LEFT JOIN budget_categories bc ON bc.id = br.category_id
         WHERE br.family_id = ? AND br.is_active = 1',
        [$familyId]
    );

    foreach ($items as $item) {
        $alertDay = (int)$item['alert_days_before'];
        if ($alertDay <= 0) continue;

        // The day we should send the alert
        $triggerDay = (int)$item['day_of_month'] - $alertDay;
        // Handle month wrap (e.g. day=1 with 3 days alert → trigger on last days of prev month)
        if ($triggerDay < 1) {
            // Calculate the trigger date properly
            $dueDate  = new \DateTime(date('Y-m') . '-' . str_pad((string)$item['day_of_month'], 2, '0', STR_PAD_LEFT));
            $trigDate = clone $dueDate;
            $trigDate->modify("-{$alertDay} days");
            if ($trigDate->format('Y-m-d') !== $todayStr) continue;
        } else {
            if ($today !== $triggerDay) continue;
        }

        // Deduplication: already sent this month?
        if (!empty($item['last_alert_sent'])) {
            $lastSent = new \DateTime($item['last_alert_sent']);
            $now      = new \DateTime($todayStr);
            if ($lastSent->format('Y-m') === $now->format('Y-m')) continue;
        }

        $typeLabel = $item['type'] === 'income' ? 'virement' : 'prélèvement';
        $amountFmt = number_format((float)$item['amount'], 2, ',', ' ') . ' €';
        $dueStr    = DateHelper::format(date('Y-m') . '-' . str_pad((string)$item['day_of_month'], 2, '0', STR_PAD_LEFT), 'j F Y');

        $rendered = EmailContent::render('budget_recurring', [
            'user_name'  => $item['user_name'],
            'type_label' => $typeLabel,
            'title'      => $item['title'],
            'amount'     => $amountFmt,
            'due_date'   => $dueStr,
        ]);
        $extra = $item['category_name'] ? EmailLayout::box('Catégorie : ' . htmlspecialchars($item['category_name'])) : '';
        $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
            'label' => 'Voir le budget',
            'url'   => $appUrl . '/budget',
        ], $extra);

        Notification::create((int)$item['user_id'], 'budget',
            "{$typeLabel} à venir", "« {$item['title']} » de {$amountFmt} le {$dueStr}.", BASE_URL . '/budget');

        if (!empty($item['user_email'])) {
            Mail::send($familyId, $item['user_email'], $item['user_name'], $rendered['subject'], $html, 'budget_recurring');
        }

        Database::execute(
            'UPDATE budget_recurring SET last_alert_sent=? WHERE id=?',
            [$todayStr, $item['id']]
        );
        echo "  → Recurring alert sent for item #{$item['id']} ({$item['title']})" . PHP_EOL;
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Shopping reminders: shopping lists with pending items for 7+ days
// ──────────────────────────────────────────────────────────────────────────
function sendShoppingReminders(int $familyId, array $members, string $appUrl): void
{
    $lists = Database::fetchAll(
        'SELECT tl.* FROM task_lists tl
         WHERE tl.family_id=? AND tl.type = \'shopping\'
           AND EXISTS (
             SELECT 1 FROM tasks t WHERE t.list_id=tl.id AND t.is_completed=0
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
            'SELECT title FROM tasks WHERE list_id=? AND is_completed=0 ORDER BY created_at',
            [$list['id']]
        );
        $itemsHtml = '<ul style="margin:0;padding-left:1.2em">'
            . implode('', array_map(fn($i) => '<li>' . htmlspecialchars($i['title']) . '</li>', $items))
            . '</ul>';

        foreach ($members as $member) {
            if (empty($member['email'])) continue;

            $rendered = EmailContent::render('shopping_reminder', [
                'user_name' => $member['name'],
                'list_name' => $list['name'],
            ]);
            $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
                'label' => 'Voir les tâches',
                'url'   => $appUrl . '/tasks',
            ], EmailLayout::box($itemsHtml));

            Mail::send($familyId, $member['email'], $member['name'],
                $rendered['subject'], $html, 'shopping_reminder');

            echo "  → Shopping reminder sent to {$member['email']} for list #{$list['id']}" . PHP_EOL;
        }
    }
}

// ──────────────────────────────────────────────────────────────────────────
// J-1 digest: one email per member listing all events tomorrow
// ──────────────────────────────────────────────────────────────────────────
function sendTomorrowEventDigest(int $familyId, array $members, string $appUrl): void
{
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    $events = Database::fetchAll(
        'SELECT e.*, u.name as user_name FROM events e JOIN users u ON u.id=e.user_id
         WHERE e.family_id=?
           AND DATE(e.start_datetime) = ?
         ORDER BY e.start_datetime ASC',
        [$familyId, $tomorrow]
    );

    if (empty($events)) return;

    foreach ($members as $member) {
        if (empty($member['email'])) continue;

        // Deduplicate: one digest per member per day
        $alreadySent = Database::fetch(
            'SELECT id FROM email_logs WHERE family_id=? AND type=? AND to_email=?
             AND DATE(created_at) = CURDATE()',
            [$familyId, 'event_tomorrow_digest', $member['email']]
        );
        if ($alreadySent) continue;

        $rows = '';
        foreach ($events as $e) {
            $time = $e['is_all_day']
                ? 'Toute la journée'
                : date('H\hi', strtotime($e['start_datetime']));
            $rows .= '<tr><td style="padding:.3rem .6rem;font-weight:600">' . htmlspecialchars($e['title']) . '</td>'
                   . '<td style="padding:.3rem .6rem;color:#7C7568">' . $time . '</td>'
                   . '<td style="padding:.3rem .6rem;color:#7C7568">' . htmlspecialchars($e['user_name']) . '</td></tr>';
        }
        $table = '<table style="border-collapse:collapse;width:100%"><thead><tr>'
              . '<th style="text-align:left;padding:.3rem .6rem;border-bottom:2px solid #E6DFD1">Titre</th>'
              . '<th style="text-align:left;padding:.3rem .6rem;border-bottom:2px solid #E6DFD1">Heure</th>'
              . '<th style="text-align:left;padding:.3rem .6rem;border-bottom:2px solid #E6DFD1">Ajouté par</th>'
              . '</tr></thead><tbody>' . $rows . '</tbody></table>';

        $rendered = EmailContent::render('event_tomorrow_digest', [
            'user_name'   => $member['name'],
            'event_count' => (string)count($events),
        ]);
        $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
            'label' => 'Voir le calendrier',
            'url'   => $appUrl . '/calendar',
        ], $table);

        Mail::send($familyId, $member['email'], $member['name'],
            $rendered['subject'], $html, 'event_tomorrow_digest');

        echo "  → Tomorrow digest sent to {$member['email']} (" . count($events) . " events)" . PHP_EOL;
    }
}

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
use App\Models\EmailTemplate;
use App\Models\EmailLog;
use App\Models\SmtpSettings;
use App\Models\CalDAVSource;
use App\Models\Notification;

$appUrl = (getenv('APP_URL') ?: 'https://board.abhd.fr') . BASE_URL;

// Auto-sync CalDAV sources for families that have configured an interval
syncCalDAVSources();

// Get all families that have SMTP configured
$families = Database::fetchAll(
    'SELECT DISTINCT f.* FROM families f JOIN smtp_settings ss ON ss.family_id = f.id'
);

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
}

// Recurring budget alerts run for every family — the push notification
// doesn't need SMTP; the email is only sent if SMTP happens to be configured.
$allFamilies = Database::fetchAll('SELECT * FROM families');
foreach ($allFamilies as $family) {
    $tz = $family['timezone'] ?? 'Europe/Paris';
    date_default_timezone_set($tz);
    sendRecurringAlerts((int)$family['id'], $appUrl);
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
                'task_created' => DateHelper::long($task['created_at']),
                'app_url'     => $appUrl,
            ]);

            Mail::send($familyId, $member['email'], $member['name'],
                $rendered['subject'], $rendered['body'], 'task_reminder');

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

        $subject = "Rappel : {$typeLabel} « {$item['title']} » de {$amountFmt} le {$dueStr}";
        $body = '<p>Bonjour ' . htmlspecialchars($item['user_name']) . ',</p>'
            . '<p>Rappel : votre <strong>' . htmlspecialchars($typeLabel) . '</strong> '
            . '« <strong>' . htmlspecialchars($item['title']) . '</strong> » '
            . 'de <strong>' . $amountFmt . '</strong> '
            . 'est prévu le <strong>' . $dueStr . '</strong>.</p>'
            . ($item['category_name'] ? '<p>Catégorie : ' . htmlspecialchars($item['category_name']) . '</p>' : '')
            . '<p><a href="' . $appUrl . '/budget">Voir le budget</a></p>';

        Notification::create((int)$item['user_id'], 'budget',
            "{$typeLabel} à venir", "« {$item['title']} » de {$amountFmt} le {$dueStr}.", BASE_URL . '/budget');

        if (!empty($item['user_email'])) {
            Mail::send($familyId, $item['user_email'], $item['user_name'], $subject, $body, 'budget_recurring');
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
                   . '<td style="padding:.3rem .6rem;color:#666">' . $time . '</td>'
                   . '<td style="padding:.3rem .6rem;color:#666">' . htmlspecialchars($e['user_name']) . '</td></tr>';
        }

        $body = '<!DOCTYPE html><html><body style="font-family:sans-serif;color:#222">'
              . '<h2>📅 Événements du ' . DateHelper::format($tomorrow, 'd/m/Y') . '</h2>'
              . '<p>Bonjour ' . htmlspecialchars($member['name']) . ',</p>'
              . '<p>Voici les événements prévus demain :</p>'
              . '<table style="border-collapse:collapse;width:100%"><thead><tr>'
              . '<th style="text-align:left;padding:.3rem .6rem;border-bottom:2px solid #eee">Titre</th>'
              . '<th style="text-align:left;padding:.3rem .6rem;border-bottom:2px solid #eee">Heure</th>'
              . '<th style="text-align:left;padding:.3rem .6rem;border-bottom:2px solid #eee">Ajouté par</th>'
              . '</tr></thead><tbody>' . $rows . '</tbody></table>'
              . '<p style="margin-top:1.5rem"><a href="' . $appUrl . '/calendar" style="color:#4f6ef7">Voir le calendrier</a></p>'
              . '</body></html>';

        $subject = '📅 ' . count($events) . ' événement' . (count($events) > 1 ? 's' : '') . ' demain';

        Mail::send($familyId, $member['email'], $member['name'],
            $subject, $body, 'event_tomorrow_digest');

        echo "  → Tomorrow digest sent to {$member['email']} (" . count($events) . " events)" . PHP_EOL;
    }
}

<?php
/**
 * FamilyBoard — Cron script for email reminders.
 * Recommended: every minute  →  * * * * * php /path/to/familyboard/cron.php >> /var/log/familyboard-cron.log 2>&1
 * (needed for a responsive CalDAV sync; reminder dedup is time-window based, not run-count
 * based, so the frequent execution never causes duplicate reminders)
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/vendor/autoload.php';

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
use App\Models\Health;
use App\Models\AiWeeklyDigest;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Event;
use App\Models\TaskList;
use App\Models\EmailContent;
use App\Models\EmailLog;
use App\Models\CalDAVSource;
use App\Models\Notification;
use App\Models\LocationCheckin;
use App\Models\SupportTicket;
use App\Models\Budget;
use App\Models\Birthday;
use App\Models\Custody;
use App\Core\EmailLayout;
use App\Models\AppSetting;
use App\Core\Push;
use App\Models\HomePresence;
use App\Models\FamilySubscription;
use App\Core\PremiumDataPurge;
use App\Core\PlatformHealth;
use App\Models\MedicalTreatment;

// Le cron tourne chaque minute (pour la synchro CalDAV) mais un run peut dépasser 60s (envois
// SMTP séquentiels, appels HTTP externes) — un verrou évite que deux exécutions se chevauchent
// et lisent email_logs avant que l'autre n'ait eu le temps d'y insérer sa ligne, ce qui
// recréerait le problème de doublons que la dédup ci-dessous cherche justement à éviter.
// Nom de fichier qualifié par le chemin de l'appli (pas juste "familyboard-cron.lock") pour ne
// pas entrer en collision avec une autre instance (staging, autre client) partageant le même
// /tmp sur un hébergement mutualisé.
$lockPath = sys_get_temp_dir() . '/familyboard-cron-' . md5(__DIR__) . '.lock';
$lockHandle = fopen($lockPath, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Une autre exécution de cron.php est déjà en cours, on abandonne." . PHP_EOL;
    exit(0);
}
register_shutdown_function(function () use ($lockHandle) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
});

// Heartbeat pour le tableau de bord santé plateforme (/admin?tab=health) — indépendant du succès
// des jobs qui suivent : un heartbeat qui ne bouge plus signale que le cron lui-même s'est arrêté.
try {
    PlatformHealth::recordCronHeartbeat();
} catch (\Throwable) {
    // Jamais bloquant : mieux vaut un heartbeat manquant qu'un cron qui échoue à cause de lui.
}

$appUrl = (getenv('APP_URL') ?: 'https://board.abhd.fr') . BASE_URL;

// Veille informationnelle (alertes enlèvement/canicule/inondation/feu de forêt/climatique/
// industrielle) : le contenu de ces flux ne change pas à la minute près, donc on ne les
// interroge qu'au maximum une fois toutes les 15 minutes malgré la fréquence du cron, pour ne
// pas marteler ces services externes.
try {
    $lastPoll = AppSetting::get('official_alert_feed_last_poll');
    if (!$lastPoll || strtotime($lastPoll) <= time() - 15 * 60) {
        \App\Core\OfficialAlertFeed::poll();
        AppSetting::set('official_alert_feed_last_poll', date('Y-m-d H:i:s'));
    }
} catch (\Throwable $e) {
    error_log('OfficialAlertFeed poll error: ' . $e->getMessage());
}

// Auto-sync CalDAV sources for families that have configured an interval
syncCalDAVSources();

// SMTP is a single, global configuration (not per-family) — Mail::send()
// already no-ops gracefully when it isn't set up, so every family is processed
// the same way regardless of whether the system administrator configured it.
$families = Database::fetchAll('SELECT * FROM families');

foreach ($families as $family) {
    $familyId = (int)$family['id'];
    $members  = User::getByFamily($familyId);
    // Ces rappels (calendrier générique, courses, tâches non assignées) ne concernent jamais un
    // enfant en garde partagée précis — un compte à accès restreint (role=coparent) ne doit donc
    // pas les recevoir, contrairement à une tâche qui lui a été explicitement assignée.
    $membersExcludingCoparent = array_values(array_filter($members, fn ($m) => $m['role'] !== 'coparent'));

    // Apply family timezone for date calculations
    $tz = $family['timezone'] ?? 'Europe/Paris';
    date_default_timezone_set($tz);

    sendEventReminders($familyId, $membersExcludingCoparent, $appUrl);
    sendBirthdayReminders($familyId, $membersExcludingCoparent, $appUrl);
    sendTomorrowEventDigest($familyId, $membersExcludingCoparent, $appUrl);
    sendTaskReminders($familyId, $members, $appUrl);
    sendShoppingReminders($familyId, $membersExcludingCoparent, $appUrl);
    sendRecurringAlerts($familyId, $appUrl);
    sendWeeklyDigest($familyId, $family, $membersExcludingCoparent, $appUrl);
    sendExpiryReminders($familyId, $membersExcludingCoparent, $appUrl);
    sendStockExpiryReminders($familyId, $membersExcludingCoparent, $appUrl);

    try {
        generateAiWeeklyDigest($familyId);
    } catch (\Throwable $e) {
        error_log('generateAiWeeklyDigest error: ' . $e->getMessage());
    }

    try {
        checkTimerAlerts($familyId, $membersExcludingCoparent);
    } catch (\Throwable $e) {
        error_log('checkTimerAlerts error: ' . $e->getMessage());
    }

    try {
        sendMedicalTreatmentReminders($familyId);
    } catch (\Throwable $e) {
        error_log('sendMedicalTreatmentReminders error: ' . $e->getMessage());
    }
}

// Résumé hebdomadaire des accès de garde partagée (co-parent) — transverse à toutes les
// familles puisqu'un accès custody_access peut porter sur une famille différente de celle
// du compte qui le reçoit ; traité une seule fois, hors de la boucle par famille ci-dessus.
sendCoparentWeeklyDigests($appUrl);

// Purges RGPD (minimisation / durée de conservation) — voir LocationCheckin::purgeExpired()
// et SupportTicket::purgeClosedOlderThan() pour le raisonnement.
try {
    $purgedLocations = LocationCheckin::purgeExpired();
    $purgedTickets = SupportTicket::purgeClosedOlderThan();
    echo "  → Purge RGPD : $purgedLocations check-in(s) position, $purgedTickets ticket(s) support clos anciens" . PHP_EOL;
} catch (\Throwable $e) {
    error_log('Purge RGPD error: ' . $e->getMessage());
}

// Relances + suppression définitive des données Premium après un impayé/essai non converti —
// transverse (une famille en défaut n'a pas de raison de coïncider avec l'itération ci-dessus).
try {
    processSubscriptionLapses($appUrl);
} catch (\Throwable $e) {
    error_log('Subscription lapse processing error: ' . $e->getMessage());
}

// Rapport mensuel de chiffre d'affaires (déclaration URSSAF) — transverse, pas lié à une
// famille en particulier : le chiffre d'affaires est celui de la plateforme elle-même.
try {
    sendUrssafReport();
} catch (\Throwable $e) {
    error_log('URSSAF report error: ' . $e->getMessage());
}

// Provisioning stockage perso (compte LDAP + quota Synology, voir
// App\Core\StorageProvisioningRunner) — sans effet tant qu'aucun backend n'est activé par
// l'admin (storage_provisioner_backend). Déclenchable aussi à la demande depuis /admin.
try {
    \App\Core\StorageProvisioningRunner::processOutstanding();
} catch (\Throwable $e) {
    error_log('Storage provisioning error: ' . $e->getMessage());
}

// Synchronisation calendrier → Nextcloud (voir App\Core\NextcloudCalendarSync) — sans effet
// tant que nextcloud_calendar_sync_enabled=0. Throttlée (toutes les 15 min) : une
// réconciliation complète par membre à chaque passage serait inutilement coûteuse vu la
// fréquence du cron ; déclenchable aussi immédiatement depuis /admin.
try {
    $lastSync = AppSetting::get('nextcloud_calendar_sync_last_run');
    if (!$lastSync || strtotime($lastSync) <= time() - 15 * 60) {
        \App\Core\NextcloudCalendarSync::syncAllMembers();
        AppSetting::set('nextcloud_calendar_sync_last_run', date('Y-m-d H:i:s'));
    }
} catch (\Throwable $e) {
    error_log('Nextcloud calendar sync error: ' . $e->getMessage());
}

// Import automatique de factures Cozy Cloud dans Documents (voir App\Core\CozyDocumentSync) —
// sans effet tant qu'aucune famille n'a de connexion 'connected'. Throttlé (toutes les 20 min) :
// les Konnectors eux-mêmes tournent sur leur propre fréquence côté Cozy, pas besoin de vérifier
// plus souvent.
try {
    $lastCozySync = AppSetting::get('cozy_sync_last_run');
    if (!$lastCozySync || strtotime($lastCozySync) <= time() - 20 * 60) {
        \App\Core\CozyDocumentSync::syncAllFamilies();
        AppSetting::set('cozy_sync_last_run', date('Y-m-d H:i:s'));
    }
} catch (\Throwable $e) {
    error_log('Cozy document sync error: ' . $e->getMessage());
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
// Minuteurs familiaux : quand un lancement en cours dépasse son échéance, décide s'il faut
// l'alerte normale (déjà gérée côté client, rien à faire ici) ou la retenir + notifier par push
// si personne n'est chez soi (held_for_return), puis la libérer dès que quelqu'un revient — voir
// App\Models\FamilyTimer et App\Models\HomePresence.
// ──────────────────────────────────────────────────────────────────────────
function checkTimerAlerts(int $familyId, array $members): void
{
    $runs = Database::fetchAll(
        "SELECT r.id, r.held_for_return, t.label FROM family_timer_runs r
         JOIN family_timers t ON t.id = r.timer_id
         WHERE r.family_id=? AND r.status='running' AND r.ends_at <= NOW()",
        [$familyId]
    );
    if (!$runs) return;

    $isHome = HomePresence::isAnyoneHome($familyId);

    foreach ($runs as $run) {
        if (!$run['held_for_return']) {
            if ($isHome) continue; // rien à faire : l'alarme sonne déjà normalement côté client
            Database::execute(
                'UPDATE family_timer_runs SET held_for_return=1, away_notified_at=NOW() WHERE id=?',
                [$run['id']]
            );
            foreach ($members as $m) {
                Push::sendToUser(
                    (int)$m['id'],
                    '⏰ Minuteur terminé',
                    $run['label'] . ' est terminé, mais personne n\'est à la maison.',
                    BASE_URL . '/family-wall'
                );
            }
        } elseif ($isHome) {
            // Quelqu'un vient de rentrer : on relâche, le client déclenche l'alarme dès son
            // prochain rafraîchissement (poll de l'écran mural / kiosque).
            Database::execute('UPDATE family_timer_runs SET held_for_return=0 WHERE id=?', [$run['id']]);
        }
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Rappels de prise de traitement médical (voir App\Models\MedicalTreatment) — notification push
// uniquement (desktop & PWA), jamais par e-mail. Un traitement 'user' notifie son propriétaire ;
// un traitement 'child' notifie tous les admins de la famille (l'enfant n'a pas de compte).
// L'heure courante est déjà dans le fuseau de la famille (date_default_timezone_set() appliqué
// par l'appelant, voir la boucle principale) — createur: la fenêtre de tolérance (±7 min autour
// de l'horaire exact) absorbe le fait que ce cron ne tourne pas forcément à la minute près.
// ──────────────────────────────────────────────────────────────────────────
function sendMedicalTreatmentReminders(int $familyId): void
{
    $treatments = MedicalTreatment::getActiveForFamily($familyId);
    if (!$treatments) return;

    $now = new \DateTime();
    $toleranceMinutes = 7;

    foreach ($treatments as $t) {
        $dueSlot = null;

        if ($t['schedule_type'] === 'fixed') {
            $times = json_decode($t['fixed_times'] ?? '[]', true) ?: [];
            foreach ($times as $hm) {
                [$h, $m] = array_map('intval', explode(':', $hm));
                $slot = (clone $now)->setTime($h, $m, 0);
                $diffMinutes = abs(($now->getTimestamp() - $slot->getTimestamp()) / 60);
                if ($diffMinutes <= $toleranceMinutes) { $dueSlot = $hm; break; }
            }
        } elseif ($t['interval_hours'] && $t['interval_anchor']) {
            $anchor = new \DateTime($t['interval_anchor']);
            if ($anchor <= $now) {
                $hoursSince = ($now->getTimestamp() - $anchor->getTimestamp()) / 3600;
                $remainder = fmod($hoursSince, (float)$t['interval_hours']);
                $minutesOff = min($remainder, $t['interval_hours'] - $remainder) * 60;
                if ($minutesOff <= $toleranceMinutes) $dueSlot = $now->format('H:i');
            }
        }

        if (!$dueSlot) continue;
        if (MedicalTreatment::reminderAlreadySent((int)$t['id'], $dueSlot)) continue;

        $title = '💊 ' . $t['name'];
        $message = ($t['subject_name'] ? $t['subject_name'] . ' — ' : '') . $t['dosage'];

        if ($t['owner_type'] === 'user') {
            Notification::create((int)$t['owner_user_id'], 'medication', $title, $message, BASE_URL . '/medication');
        } else {
            foreach (Database::fetchAll("SELECT id FROM users WHERE family_id=? AND role='admin'", [$familyId]) as $admin) {
                Notification::create((int)$admin['id'], 'medication', $title, $message, BASE_URL . '/medication');
            }
        }

        MedicalTreatment::markReminderSent((int)$t['id'], $dueSlot);
        echo "  → Medical treatment reminder sent for treatment #{$t['id']} ({$dueSlot})" . PHP_EOL;
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Échéances Garanties/Documents : rappel à J-30 et J-7 avant warranty_end_date / expiry_date.
// ──────────────────────────────────────────────────────────────────────────
function sendExpiryReminders(int $familyId, array $members, string $appUrl): void
{
    $today = new \DateTime('today');
    $items = [];

    foreach (Database::fetchAll('SELECT id, title, warranty_end_date FROM warranties WHERE family_id=? AND warranty_end_date IS NOT NULL', [$familyId]) as $w) {
        $items[] = ['kind' => 'La garantie', 'ref' => 'warranty_' . $w['id'], 'label' => $w['title'], 'date' => $w['warranty_end_date'], 'url' => '/warranties'];
    }
    foreach (Database::fetchAll('SELECT id, title, expiry_date FROM documents WHERE family_id=? AND expiry_date IS NOT NULL', [$familyId]) as $d) {
        $items[] = ['kind' => 'Le document', 'ref' => 'document_' . $d['id'], 'label' => $d['title'], 'date' => $d['expiry_date'], 'url' => '/documents'];
    }
    foreach (Database::fetchAll('SELECT id, name, insurance_expiry, technical_control_expiry FROM vehicles WHERE family_id=?', [$familyId]) as $v) {
        if ($v['insurance_expiry']) {
            $items[] = ['kind' => "L'assurance de", 'ref' => 'vehicle_insurance_' . $v['id'], 'label' => $v['name'], 'date' => $v['insurance_expiry'], 'url' => '/vehicles?id=' . $v['id']];
        }
        if ($v['technical_control_expiry']) {
            $items[] = ['kind' => 'Le contrôle technique de', 'ref' => 'vehicle_ct_' . $v['id'], 'label' => $v['name'], 'date' => $v['technical_control_expiry'], 'url' => '/vehicles?id=' . $v['id']];
        }
    }
    foreach (Database::fetchAll(
        'SELECT pc.id, pc.title, pc.reminder_date, p.id AS pet_id, p.name AS pet_name
         FROM pet_care_entries pc JOIN pets p ON p.id=pc.pet_id
         WHERE p.family_id=? AND pc.reminder_date IS NOT NULL', [$familyId]
    ) as $c) {
        $items[] = ['kind' => 'Le rappel de', 'ref' => 'pet_care_' . $c['id'], 'label' => $c['pet_name'] . ' — ' . $c['title'], 'date' => $c['reminder_date'], 'url' => '/pets?id=' . $c['pet_id']];
    }
    foreach (Database::fetchAll('SELECT id, title, deadline_date FROM admin_procedures WHERE family_id=? AND done=0', [$familyId]) as $p) {
        $items[] = ['kind' => 'La démarche', 'ref' => 'admin_procedure_' . $p['id'], 'label' => $p['title'], 'date' => $p['deadline_date'], 'url' => '/admin-procedures'];
    }
    // Rappels de vaccination (et autres rappels du carnet de santé) : health_entries.reminder_date
    // n'avait jamais été relié à ce mécanisme — restait saisi sans jamais rien rappeler à personne.
    foreach (Database::fetchAll(
        'SELECT id, title, reminder_date, entry_type, subject_type, subject_id FROM health_entries
         WHERE family_id=? AND reminder_date IS NOT NULL', [$familyId]
    ) as $h) {
        $subject = Health::subjectLabel($familyId, $h['subject_type'], (int)$h['subject_id']);
        $kind = $h['entry_type'] === 'vaccination' ? 'Le rappel de vaccination de' : 'Le rappel santé de';
        $items[] = ['kind' => $kind, 'ref' => 'health_' . $h['id'], 'label' => ($subject['name'] ?? '?') . ' — ' . $h['title'], 'date' => $h['reminder_date'], 'url' => '/health'];
    }

    foreach ($items as $item) {
        $end = new \DateTime($item['date']);
        $daysRemaining = (int)$today->diff($end)->days * ($end >= $today ? 1 : -1);
        if (!in_array($daysRemaining, [30, 7], true)) continue;

        foreach ($members as $member) {
            if (empty($member['email'])) continue;

            // Clé de déduplication propre à cet élément + ce seuil (J-30 et J-7 sont deux
            // rappels distincts, chacun envoyé une seule fois) — voir sendBirthdayReminders()
            // pour le même principe de marqueur dans le corps du mail.
            $key = 'expiry_' . $item['ref'] . '_' . $daysRemaining . '_' . date('Y');
            $alreadySent = Database::fetch(
                "SELECT id FROM email_logs WHERE family_id=? AND type=? AND to_email=? AND body LIKE ? AND status='sent'
                 AND created_at > DATE_SUB(NOW(), INTERVAL 8 DAY)",
                [$familyId, 'expiry_reminder', $member['email'], '%' . $key . '%']
            );
            if ($alreadySent) continue;

            $rendered = EmailContent::render('expiry_reminder', [
                'user_name'      => $member['name'],
                'item_label'     => $item['label'],
                'item_kind'      => $item['kind'],
                'expiry_date'    => date('d/m/Y', strtotime($item['date'])),
                'days_remaining' => (string)$daysRemaining,
            ]);
            $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
                'label' => 'Voir',
                'url'   => $appUrl . $item['url'],
            ]) . "<!-- {$key} -->";

            Mail::send($familyId, $member['email'], $member['name'], $rendered['subject'], $html, 'expiry_reminder');

            echo "  → Expiry reminder sent to {$member['email']} for {$item['label']} (J-{$daysRemaining})" . PHP_EOL;
        }
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Péremption du stock frigo/congélo : rappel à J-3 et le jour même (jamais J-30/J-7 comme
// sendExpiryReminders() ci-dessus — un produit alimentaire se périme en jours, pas en mois).
// ──────────────────────────────────────────────────────────────────────────
function sendStockExpiryReminders(int $familyId, array $members, string $appUrl): void
{
    $today = new \DateTime('today');

    foreach (StockItem::getExpiringSoon($familyId, 3) as $item) {
        $end = new \DateTime($item['expiry_date']);
        $daysRemaining = (int)$today->diff($end)->days * ($end >= $today ? 1 : -1);
        if (!in_array($daysRemaining, [3, 0], true)) continue;

        foreach ($members as $member) {
            if (empty($member['email'])) continue;

            $key = 'stock_' . $item['id'] . '_' . $daysRemaining . '_' . date('Y-m-d');
            $alreadySent = Database::fetch(
                "SELECT id FROM email_logs WHERE family_id=? AND type=? AND to_email=? AND body LIKE ? AND status='sent'
                 AND created_at > DATE_SUB(NOW(), INTERVAL 8 DAY)",
                [$familyId, 'expiry_reminder', $member['email'], '%' . $key . '%']
            );
            if ($alreadySent) continue;

            $rendered = EmailContent::render('expiry_reminder', [
                'user_name'      => $member['name'],
                'item_label'     => $item['name'],
                'item_kind'      => 'Le produit',
                'expiry_date'    => date('d/m/Y', strtotime($item['expiry_date'])),
                'days_remaining' => (string)$daysRemaining,
            ]);
            $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
                'label' => 'Voir le stock',
                'url'   => $appUrl . '/stock',
            ]) . "<!-- {$key} -->";

            Mail::send($familyId, $member['email'], $member['name'], $rendered['subject'], $html, 'expiry_reminder');

            echo "  → Stock expiry reminder sent to {$member['email']} for {$item['name']} (J-{$daysRemaining})" . PHP_EOL;
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

            // Avoid duplicate: check if reminder already sent for this event+member. The
            // subject only contains the event title (never its id), so a LIKE on the id was
            // never matching anything — this silently resent the same reminder every hour for
            // as long as the event stayed in the 24h window (hourly for a recurring event, for
            // as many days as it kept recurring). Marker embedded in the body instead, which we
            // control completely.
            $marker = '<!-- evt:' . $event['id'] . ' -->';
            $alreadySent = Database::fetch(
                "SELECT id FROM email_logs WHERE family_id=? AND type=? AND to_email=? AND body LIKE ? AND status='sent'
                 AND created_at > DATE_SUB(NOW(), INTERVAL 25 HOUR)",
                [$familyId, 'event_reminder', $member['email'], '%' . $marker . '%']
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
            ], $extra) . $marker;

            Mail::send($familyId, $member['email'], $member['name'],
                $rendered['subject'], $html, 'event_reminder');

            echo "  → Event reminder sent to {$member['email']} for event #{$event['id']}" . PHP_EOL;
        }
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Birthday reminders: J-7 before a member/baby/contact's birthday
// ──────────────────────────────────────────────────────────────────────────
function sendBirthdayReminders(int $familyId, array $members, string $appUrl): void
{
    $upcoming = array_filter(\App\Models\Birthday::getUpcoming($familyId, 7), fn($b) => $b['days_until'] === 7);
    if (!$upcoming) return;

    foreach ($members as $member) {
        if (empty($member['email'])) continue;

        foreach ($upcoming as $b) {
            // Une personne n'a pas besoin d'être prévenue que son propre anniversaire approche
            // via ce canal (elle en est déjà informée par construction) — évite un e-mail
            // qui casserait la surprise si un cadeau est en préparation via la liste de souhaits.
            if ($b['type'] === 'user' && $b['id'] === (int)$member['id']) continue;

            // Clé de déduplication propre à cette personne + cette année (un même contact peut
            // apparaître deux fois dans une fenêtre de 7 jours d'une année sur l'autre, mais
            // jamais deux fois la même année) — recherchée dans le corps du mail (colonne
            // `body`), jamais dans le sujet : le sujet est un en-tête affiché tel quel au
            // destinataire, il ne doit contenir aucun marqueur technique.
            $key = 'birthday_' . $b['type'] . '_' . $b['id'] . '_' . date('Y');
            $alreadySent = Database::fetch(
                "SELECT id FROM email_logs WHERE family_id=? AND type=? AND to_email=? AND body LIKE ? AND status='sent'
                 AND created_at > DATE_SUB(NOW(), INTERVAL 8 DAY)",
                [$familyId, 'birthday_reminder', $member['email'], '%' . $key . '%']
            );
            if ($alreadySent) continue;

            $rendered = EmailContent::render('birthday_reminder', [
                'user_name'     => $member['name'],
                'birthday_name' => $b['name'],
                'birthday_age'  => (string)$b['age'],
                'birthday_date' => date('d/m', strtotime($b['date'])),
            ]);
            $html = EmailLayout::render($rendered['subject'], $rendered['message_html'])
                  . "<!-- {$key} -->";

            Mail::send($familyId, $member['email'], $member['name'], $rendered['subject'], $html, 'birthday_reminder');

            echo "  → Birthday reminder sent to {$member['email']} for {$b['name']}" . PHP_EOL;
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
        // Send to the assigned user (even a coparent, if explicitly assigned) or, if no
        // assignment, to all members except coparent accounts — this generic reminder is never
        // scoped to a custody schedule, so it isn't relevant to a restricted coparent account.
        $recipients = [];
        if (!empty($task['assigned_to'])) {
            $assignee = Database::fetch('SELECT * FROM users WHERE id=?', [$task['assigned_to']]);
            if ($assignee) $recipients[] = $assignee;
        } else {
            $recipients = array_filter($members, fn ($m) => $m['role'] !== 'coparent');
        }

        foreach ($recipients as $member) {
            if (empty($member['email'])) continue;

            $alreadySent = Database::fetch(
                "SELECT id FROM email_logs WHERE family_id=? AND type=? AND to_email=? AND status='sent'
                 AND created_at > DATE_SUB(NOW(), INTERVAL 8 DAY)",
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

        $isYearly = ($item['frequency'] ?? 'monthly') === 'yearly';
        $day = (int)$item['day_of_month'];

        if ($isYearly) {
            // Un abonnement annuel (assurance, cotisation...) ne tombe qu'une fois par an, au
            // mois renewal_month — on calcule la date d'échéance de CETTE année (ou de l'année
            // suivante si elle est déjà passée) plutôt que de supposer le mois courant.
            $month = (int)($item['renewal_month'] ?? 0) ?: 1;
            $year = (int)date('Y');
            $dueDate = new \DateTime(sprintf('%04d-%02d-%02d', $year, $month, min($day, 28)));
            if ($dueDate < new \DateTime($todayStr)) {
                $dueDate = new \DateTime(sprintf('%04d-%02d-%02d', $year + 1, $month, min($day, 28)));
            }
            $trigDate = (clone $dueDate)->modify("-{$alertDay} days");
            if ($trigDate->format('Y-m-d') !== $todayStr) continue;
        } else {
            // The day we should send the alert
            $triggerDay = $day - $alertDay;
            // Handle month wrap (e.g. day=1 with 3 days alert → trigger on last days of prev month)
            if ($triggerDay < 1) {
                // Calculate the trigger date properly
                $dueDate  = new \DateTime(date('Y-m') . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT));
                $trigDate = clone $dueDate;
                $trigDate->modify("-{$alertDay} days");
                if ($trigDate->format('Y-m-d') !== $todayStr) continue;
            } else {
                if ($today !== $triggerDay) continue;
                $dueDate = new \DateTime(date('Y-m') . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT));
            }
        }

        // Déduplication : déjà envoyée pour cette échéance ? (année pour un abonnement annuel,
        // mois pour une opération mensuelle)
        if (!empty($item['last_alert_sent'])) {
            $lastSent = new \DateTime($item['last_alert_sent']);
            $now      = new \DateTime($todayStr);
            $samePeriod = $isYearly ? ($lastSent->format('Y') === $now->format('Y')) : ($lastSent->format('Y-m') === $now->format('Y-m'));
            if ($samePeriod) continue;
        }

        $typeLabel = $item['type'] === 'income' ? 'virement' : 'prélèvement';
        $amountFmt = number_format((float)$item['amount'], 2, ',', ' ') . ' €';
        $dueStr    = DateHelper::format($dueDate->format('Y-m-d'), 'j F Y');

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
        $items = Database::fetchAll(
            'SELECT title FROM tasks WHERE list_id=? AND is_completed=0 ORDER BY created_at',
            [$list['id']]
        );
        $itemsHtml = '<ul style="margin:0;padding-left:1.2em">'
            . implode('', array_map(fn($i) => '<li>' . htmlspecialchars($i['title']) . '</li>', $items))
            . '</ul>';

        foreach ($members as $member) {
            if (empty($member['email'])) continue;

            // Déduplication par membre (pas juste par liste) : un envoi en échec pour un membre
            // ne doit pas priver les autres membres de la famille du rappel.
            $alreadySent = Database::fetch(
                "SELECT id FROM email_logs WHERE family_id=? AND type=? AND to_email=? AND subject LIKE ? AND status='sent'
                 AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$familyId, 'shopping_reminder', $member['email'], '%' . $list['name'] . '%']
            );
            if ($alreadySent) continue;

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
            "SELECT id FROM email_logs WHERE family_id=? AND type=? AND to_email=? AND status='sent'
             AND DATE(created_at) = CURDATE()",
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

// ──────────────────────────────────────────────────────────────────────────
// Résumé hebdomadaire (dimanche soir) : semaine à venir pour toute la famille
// ──────────────────────────────────────────────────────────────────────────
function sendWeeklyDigest(int $familyId, array $family, array $members, string $appUrl): void
{
    // Envoyé une fois par semaine, le dimanche en fin d'après-midi/soirée (le cron tourne
    // toutes les heures — la dédup ci-dessous, sur CURDATE(), garantit qu'un seul envoi a
    // lieu même si plusieurs passages cron ont lieu après ce seuil le même jour).
    if ((int)date('N') !== 7 || (int)date('G') < 18) return;

    $weekStart = date('Y-m-d 00:00:00');
    $weekEnd   = date('Y-m-d 23:59:59', strtotime('+6 days'));

    $events = Database::fetchAll(
        'SELECT e.* FROM events e WHERE e.family_id=? AND e.start_datetime BETWEEN ? AND ? ORDER BY e.start_datetime',
        [$familyId, $weekStart, $weekEnd]
    );

    $lists = TaskList::getByFamily($familyId);
    $pendingTasks = 0;
    foreach ($lists as $list) {
        if ($list['type'] === 'shopping') continue;
        foreach (TaskList::getTasks((int)$list['id']) as $t) {
            if (!$t['is_completed']) $pendingTasks++;
        }
    }

    $balance = Budget::getSummary($familyId, date('Y-m'));
    $birthdays = array_filter(Birthday::getUpcoming($familyId, 7), fn($b) => $b['days_until'] <= 6);

    foreach ($members as $member) {
        if (empty($member['email'])) continue;

        $alreadySent = Database::fetch(
            "SELECT id FROM email_logs WHERE family_id=? AND type='weekly_digest' AND to_email=? AND status='sent' AND DATE(created_at)=CURDATE()",
            [$familyId, $member['email']]
        );
        if ($alreadySent) continue;

        $rendered = EmailContent::render('weekly_digest', [
            'user_name'   => $member['name'],
            'family_name' => $family['name'],
        ]);

        $eventsHtml = empty($events) ? '<em>Aucun événement prévu.</em>' : ('<ul style="margin:4px 0;padding-left:18px">' . implode('', array_map(
            fn($e) => '<li>' . htmlspecialchars(DateHelper::format($e['start_datetime'], 'D d/m')) . ' — <strong>' . htmlspecialchars($e['title']) . '</strong></li>',
            $events
        )) . '</ul>');

        $birthdaysHtml = empty($birthdays) ? '' : ('<p>🎂 ' . implode(', ', array_map(
            fn($b) => htmlspecialchars($b['name']) . ' (' . ($b['days_until'] === 0 ? "aujourd'hui" : 'dans ' . $b['days_until'] . 'j') . ')',
            $birthdays
        )) . '</p>');

        $extra = EmailLayout::box(
            '<strong>📅 Cette semaine</strong>' . $eventsHtml
            . '<p>✅ ' . $pendingTasks . ' tâche(s) en attente</p>'
            . '<p>💰 Solde du mois : ' . number_format($balance['balance'], 2, ',', ' ') . ' €</p>'
            . $birthdaysHtml
        );

        $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
            'label' => 'Voir le tableau de bord',
            'url'   => $appUrl . '/',
        ], $extra);

        Mail::send($familyId, $member['email'], $member['name'], $rendered['subject'], $html, 'weekly_digest');

        echo "  → Weekly digest sent to {$member['email']}" . PHP_EOL;
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Digest hebdomadaire généré par l'IA (popup au prochain login/lancement de la PWA) : distinct du
// résumé email sendWeeklyDigest() ci-dessus. Même créneau (dimanche en fin d'après-midi/soirée),
// pour que le digest soit prêt dès le lundi matin — la contrainte UNIQUE (family_id, week_start)
// de AiWeeklyDigest::generate() rend l'appel idempotent si le cron tourne plusieurs fois après ce
// créneau le même dimanche.
// ──────────────────────────────────────────────────────────────────────────
function generateAiWeeklyDigest(int $familyId): void
{
    if ((int)date('N') !== 7 || (int)date('G') < 18) return;
    if (AiWeeklyDigest::getForWeek($familyId, AiWeeklyDigest::currentWeekStart())) return;
    if (AiWeeklyDigest::generate($familyId)) {
        echo "  → AI weekly digest generated for family {$familyId}" . PHP_EOL;
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Abonnements en défaut de paiement (essai non converti ou paiement échoué) : bascule déjà
// immédiate côté accès (FamilySubscription::isEntitled(), voir BaseController::requireModule())
// — ici on gère uniquement les relances email et, une fois grace_ends_at dépassé, la suppression
// définitive des données des modules premium (voir PremiumDataPurge). Chaque relance n'est
// envoyée qu'une fois (colonnes reminder_*_sent_at), remises à zéro dès que la famille redevient
// à jour (voir FamilySubscription::syncFromStripe()).
// ──────────────────────────────────────────────────────────────────────────
function processSubscriptionLapses(string $appUrl): void
{
    $now = new \DateTime();

    foreach (FamilySubscription::getLapsed() as $sub) {
        $familyId = (int)$sub['family_id'];
        $familyName = $sub['family_name'];
        $graceEnd = new \DateTime($sub['grace_ends_at']);
        $members = array_values(array_filter(User::getByFamily($familyId), fn($m) => $m['role'] !== 'coparent'));
        if (!$members) continue;

        // 1) Bascule immédiate — un seul envoi, dès la détection.
        if (!$sub['reminder_downgrade_sent_at']) {
            foreach ($members as $member) {
                if (empty($member['email'])) continue;
                $rendered = EmailContent::render('subscription_downgraded', [
                    'user_name' => $member['name'], 'family_name' => $familyName,
                    'grace_end_date' => $graceEnd->format('d/m/Y'),
                ]);
                $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], ['label' => 'Gérer mon abonnement', 'url' => $appUrl . '/settings#tab-abonnement']);
                Mail::send($familyId, $member['email'], $member['name'], $rendered['subject'], $html, 'subscription_downgraded');
            }
            FamilySubscription::markReminderSent($familyId, 'downgrade');
            echo "  → Subscription downgraded: family #$familyId notified" . PHP_EOL;
        }

        // 2) Rappel à mi-parcours du délai de rétention.
        if (!$sub['reminder_midpoint_sent_at'] && $sub['grace_started_at']) {
            $graceStart = new \DateTime($sub['grace_started_at']);
            $midpoint = (clone $graceStart)->modify('+' . (int)(($graceEnd->getTimestamp() - $graceStart->getTimestamp()) / 2) . ' seconds');
            if ($now >= $midpoint) {
                $daysRemaining = max(0, (int)$now->diff($graceEnd)->days);
                foreach ($members as $member) {
                    if (empty($member['email'])) continue;
                    $rendered = EmailContent::render('subscription_retention_reminder', [
                        'user_name' => $member['name'], 'family_name' => $familyName,
                        'grace_end_date' => $graceEnd->format('d/m/Y'), 'days_remaining' => (string)$daysRemaining,
                    ]);
                    $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], ['label' => 'Gérer mon abonnement', 'url' => $appUrl . '/settings#tab-abonnement']);
                    Mail::send($familyId, $member['email'], $member['name'], $rendered['subject'], $html, 'subscription_retention_reminder');
                }
                FamilySubscription::markReminderSent($familyId, 'midpoint');
                echo "  → Subscription retention reminder: family #$familyId notified" . PHP_EOL;
            }
        }

        // 3) Dernier avertissement (J-2 avant suppression définitive).
        if (!$sub['reminder_final_sent_at']) {
            $finalWarningAt = (clone $graceEnd)->modify('-2 days');
            if ($now >= $finalWarningAt) {
                foreach ($members as $member) {
                    if (empty($member['email'])) continue;
                    $rendered = EmailContent::render('subscription_final_warning', [
                        'user_name' => $member['name'], 'family_name' => $familyName,
                        'grace_end_date' => $graceEnd->format('d/m/Y'),
                    ]);
                    $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], ['label' => 'Gérer mon abonnement', 'url' => $appUrl . '/settings#tab-abonnement']);
                    Mail::send($familyId, $member['email'], $member['name'], $rendered['subject'], $html, 'subscription_final_warning');
                }
                FamilySubscription::markReminderSent($familyId, 'final');
                echo "  → Subscription final warning: family #$familyId notified" . PHP_EOL;
            }
        }

        // 4) Délai de rétention dépassé : suppression définitive, avec instantané préalable.
        if ($now >= $graceEnd) {
            try {
                $modules = PremiumDataPurge::premiumModulesWithData();
                $exportHtml = $modules ? PremiumDataPurge::exportHtml($familyId, $modules, $familyName) : null;
                $purgedModules = PremiumDataPurge::purge($familyId);
                Database::execute(
                    'INSERT INTO premium_data_purges (family_id, family_name, modules_purged, export_html) VALUES (?,?,?,?)',
                    [$familyId, $familyName, implode(',', $purgedModules), $exportHtml]
                );
                FamilySubscription::markDataPurged($familyId);

                $modulesLabels = array_map(
                    fn($slug) => \App\Models\Family::MODULES[$slug]['label'] ?? $slug,
                    $purgedModules
                );
                foreach ($members as $member) {
                    if (empty($member['email'])) continue;
                    $rendered = EmailContent::render('subscription_data_purged', [
                        'user_name' => $member['name'], 'family_name' => $familyName,
                        'modules_list' => implode(', ', $modulesLabels),
                    ]);
                    $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], ['label' => 'Voir les offres', 'url' => $appUrl . '/settings#tab-abonnement']);
                    Mail::send($familyId, $member['email'], $member['name'], $rendered['subject'], $html, 'subscription_data_purged');
                }
                echo "  → Subscription data purged: family #$familyId (" . implode(',', $purgedModules) . ")" . PHP_EOL;
            } catch (\Throwable $e) {
                error_log("Premium data purge error for family #$familyId: " . $e->getMessage());
            }
        }
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Rapport mensuel de chiffre d'affaires (déclaration URSSAF de l'auto-entrepreneur
// exploitant la plateforme) — voir App\Core\UrssafReport. Envoyé le jour du mois configuré
// (urssaf_report_day), pour le mois civil précédent (le seul complet à cette date). Idempotent
// via urssaf_report_last_sent (période "Y-m" déjà envoyée) — le cron tournant chaque minute,
// sans cette garde le rapport serait renvoyé à chaque exécution du bon jour.
// ──────────────────────────────────────────────────────────────────────────
function sendUrssafReport(): void
{
    if ((int)(AppSetting::get('urssaf_report_enabled') ?? '0') !== 1) return;
    if (!\App\Core\StripeGateway::isConfigured()) return;

    $day = max(1, min(28, (int)(AppSetting::get('urssaf_report_day') ?? '5')));
    if ((int)date('j') !== $day) return;

    $currentPeriod = date('Y-m');
    if (AppSetting::get('urssaf_report_last_sent') === $currentPeriod) return;

    $adminEmail = AppSetting::get('admin_email');
    if (!$adminEmail) return;

    $prevMonth = new \DateTimeImmutable('first day of last month');
    $year = (int)$prevMonth->format('Y');
    $month = (int)$prevMonth->format('n');

    $sent = \App\Core\UrssafReport::sendForMonth($year, $month, $adminEmail);

    if ($sent) {
        AppSetting::set('urssaf_report_last_sent', $currentPeriod);
        echo '  → URSSAF report sent for ' . \App\Core\UrssafReport::monthLabel($year, $month) . PHP_EOL;
    }
}

// ──────────────────────────────────────────────────────────────────────────
// Résumé hebdomadaire — accès co-parent (garde partagée) : UNIQUEMENT le
// planning de garde et les rendez-vous liés à l'enfant concerné, jamais les
// données génériques de la famille à laquelle appartient le planning.
// ──────────────────────────────────────────────────────────────────────────
function sendCoparentWeeklyDigests(string $appUrl): void
{
    if ((int)date('N') !== 7 || (int)date('G') < 18) return;

    $userIds = Database::fetchAll('SELECT DISTINCT user_id FROM custody_access');
    $weekStart = date('Y-m-d');
    $weekEnd   = date('Y-m-d', strtotime('+6 days'));
    $weekStartDt = date('Y-m-d 00:00:00');
    $weekEndDt   = date('Y-m-d 23:59:59', strtotime('+6 days'));

    foreach ($userIds as $row) {
        $user = User::findById((int)$row['user_id']);
        if (!$user || empty($user['email'])) continue;

        $schedules = Custody::getSchedulesForUser((int)$user['id']);
        if (empty($schedules)) continue;

        $alreadySent = Database::fetch(
            "SELECT id FROM email_logs WHERE type='weekly_digest_coparent' AND to_email=? AND status='sent' AND DATE(created_at)=CURDATE()",
            [$user['email']]
        );
        if ($alreadySent) continue;

        $scheduleIds = array_map(fn($s) => (int)$s['id'], $schedules);
        $childNames  = implode(', ', array_unique(array_column($schedules, 'child_name')));

        $custodyBlocks = Custody::getAllEventsForSchedules($scheduleIds, $weekStart, $weekEnd);
        usort($custodyBlocks, fn($a, $b) => $a['start_date'] <=> $b['start_date']);

        $childEvents = Event::getForSchedules($scheduleIds, $weekStartDt, $weekEndDt);

        // Rien à annoncer cette semaine pour cet accès : on n'envoie pas de mail vide.
        if (empty($custodyBlocks) && empty($childEvents)) continue;

        $planningHtml = empty($custodyBlocks) ? '<em>Aucun planning renseigné pour cette semaine.</em>' : ('<ul style="margin:4px 0;padding-left:18px">' . implode('', array_map(
            fn($b) => '<li>' . htmlspecialchars(date('d/m', strtotime($b['start_date']))) . ' → ' . htmlspecialchars(date('d/m', strtotime($b['end_date'])))
                . ' — <strong>' . htmlspecialchars($b['child_name']) . '</strong> chez ' . htmlspecialchars($b['parent_name']) . '</li>',
            $custodyBlocks
        )) . '</ul>');

        $eventsHtml = empty($childEvents) ? '' : ('<p><strong>📅 Rendez-vous</strong></p><ul style="margin:4px 0;padding-left:18px">' . implode('', array_map(
            fn($e) => '<li>' . htmlspecialchars(DateHelper::format($e['start_datetime'], 'D d/m')) . ' — <strong>' . htmlspecialchars($e['title']) . '</strong></li>',
            $childEvents
        )) . '</ul>');

        $rendered = EmailContent::render('weekly_digest_coparent', [
            'user_name'   => $user['name'],
            'child_names' => $childNames,
        ]);

        $extra = EmailLayout::box('<strong>👶 Planning de garde</strong>' . $planningHtml . $eventsHtml);

        $html = EmailLayout::render($rendered['subject'], $rendered['message_html'], [
            'label' => 'Voir l\'accès garde partagée',
            'url'   => $appUrl . '/coparent',
        ], $extra);

        // family_id de l'e-mail = celui du destinataire (journalisation), pas celui du
        // planning consulté — cohérent avec le reste de email_logs qui rattache toujours
        // un envoi au compte qui le reçoit.
        Mail::send((int)$user['family_id'], $user['email'], $user['name'], $rendered['subject'], $html, 'weekly_digest_coparent');

        echo "  → Coparent weekly digest sent to {$user['email']} ({$childNames})" . PHP_EOL;
    }
}

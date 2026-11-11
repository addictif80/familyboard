<?php
namespace App\Core;

use App\Models\CalendarFeed;

/**
 * Synchronisation à sens unique FamilyBoard → Nextcloud (calendrier), par membre — voir
 * App\Core\NextcloudCalDavClient pour le pourquoi d'un calendrier dédié possédé par le compte
 * technique et partagé en lecture seule, plutôt qu'une écriture directe dans le calendrier
 * personnel du membre.
 *
 * Fonctionne par RÉCONCILIATION plutôt que par hook sur chaque écriture : les occurrences
 * récurrentes de garde alternée (voir Custody::generateRecurringEvents) n'ont pas de ligne DB
 * stable, seulement un identifiant déterministe recalculé à chaque appel de
 * CalendarFeed::buildItems() — exactement la même fonction déjà utilisée par le flux ICS mobile
 * existant, qui gère donc déjà la visibilité par rôle (coparent restreint aux plannings
 * autorisés). nextcloud_calendar_items garde la trace de ce qui a été poussé pour détecter les
 * changements (hash) et les suppressions (UID plus présent dans l'état désiré).
 */
class NextcloudCalendarSync
{
    public static function isEnabled(): bool
    {
        return \App\Models\AppSetting::get('nextcloud_calendar_sync_enabled', '0') === '1';
    }

    /** @return array{pushed:int, updated:int, deleted:int, skipped:bool, error:?string} */
    public static function syncMember(array $user): array
    {
        $result = ['pushed' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => false, 'error' => null];

        if (!self::isEnabled() || empty($user['ldap_username'])) {
            $result['skipped'] = true;
            return $result;
        }
        $username = $user['ldap_username'];

        if (!NextcloudCalDavClient::ensureCalendar($username)) {
            $result['error'] = 'Impossible de créer/joindre le calendrier Nextcloud.';
            return $result;
        }
        NextcloudCalDavClient::ensureSharedReadOnly($username); // idempotent, échec non bloquant (déjà partagé la plupart du temps)

        $desired = CalendarFeed::buildItems($user); // uid => contenu VEVENT
        $tracked = \App\Models\NextcloudCalendarItem::getForUser((int)$user['id']); // uid => hash

        foreach ($desired as $uid => $vevent) {
            $hash = hash('sha256', $vevent);
            if (!isset($tracked[$uid])) {
                if (NextcloudCalDavClient::putEvent($username, $uid, self::wrapVevent($vevent))) {
                    \App\Models\NextcloudCalendarItem::upsert((int)$user['id'], $uid, $hash);
                    $result['pushed']++;
                }
            } elseif ($tracked[$uid] !== $hash) {
                if (NextcloudCalDavClient::putEvent($username, $uid, self::wrapVevent($vevent))) {
                    \App\Models\NextcloudCalendarItem::upsert((int)$user['id'], $uid, $hash);
                    $result['updated']++;
                }
            }
        }

        foreach ($tracked as $uid => $hash) {
            if (!isset($desired[$uid])) {
                if (NextcloudCalDavClient::deleteEvent($username, $uid)) {
                    \App\Models\NextcloudCalendarItem::delete((int)$user['id'], $uid);
                    $result['deleted']++;
                }
            }
        }

        return $result;
    }

    /** Un VEVENT seul n'est pas un fichier .ics valide pour un client CalDAV : il doit être
     *  enveloppé dans son propre VCALENDAR (une ressource DAV = un objet calendrier complet). */
    private static function wrapVevent(string $vevent): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//FamilyBoard//Calendrier global//FR\r\n"
            . $vevent . "\r\nEND:VCALENDAR\r\n";
    }

    /** Synchronise tous les membres éligibles (compte LDAP provisionné) — utilisé par le cron
     *  (throttlé) et par le déclenchement manuel admin. */
    public static function syncAllMembers(): array
    {
        $totals = ['members' => 0, 'pushed' => 0, 'updated' => 0, 'deleted' => 0, 'errors' => []];
        if (!self::isEnabled()) return $totals;

        foreach (\App\Core\Database::fetchAll("SELECT * FROM users WHERE ldap_username IS NOT NULL") as $user) {
            $r = self::syncMember($user);
            if ($r['skipped']) continue;
            $totals['members']++;
            $totals['pushed'] += $r['pushed'];
            $totals['updated'] += $r['updated'];
            $totals['deleted'] += $r['deleted'];
            if ($r['error']) $totals['errors'][] = $user['name'] . ' : ' . $r['error'];
        }
        return $totals;
    }
}

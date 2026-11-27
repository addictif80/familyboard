<?php
namespace App\Models;

use App\Core\Database;

/**
 * Export du calendrier global de la famille (événements, garde alternée, congés — et les
 * calendriers CalDAV externes déjà synchronisés, puisqu'ils deviennent de simples lignes de la
 * table `events` une fois importés, voir Event::createFromCalDAV()) en flux iCalendar public,
 * pour qu'une app calendrier de smartphone (Google Calendar, Apple Calendar, Outlook...) puisse
 * s'y abonner et se resynchroniser seule, sans jamais avoir de session FamilyBoard.
 *
 * Un compte à accès restreint (role=coparent) ne voit ici QUE les événements de garde des
 * plannings auxquels il a accès — jamais les événements familiaux génériques — exactement la
 * même règle que partout ailleurs dans l'app pour ce rôle (voir Notification::notifyFamily()).
 */
class CalendarFeed
{
    /** Fenêtre glissante raisonnable pour un flux rafraîchi périodiquement par l'app calendrier
     *  du téléphone : le passé récent (rendez-vous qu'on consulte encore un peu après coup) et
     *  une bonne année à venir. */
    private const PAST_DAYS = 60;
    private const FUTURE_DAYS = 365;

    public static function getOrCreateToken(int $userId): string
    {
        $user = Database::fetch('SELECT calendar_feed_token FROM users WHERE id=?', [$userId]);
        if ($user && $user['calendar_feed_token']) return $user['calendar_feed_token'];
        return self::regenerateToken($userId);
    }

    public static function regenerateToken(int $userId): string
    {
        $token = bin2hex(random_bytes(24));
        Database::execute('UPDATE users SET calendar_feed_token=? WHERE id=?', [$token, $userId]);
        return $token;
    }

    public static function findUserByToken(string $token): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE calendar_feed_token=?', [$token]);
    }

    public static function buildIcs(array $user): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//FamilyBoard//Calendrier global//FR',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:' . self::escape('FamilyBoard — ' . ($user['name'] ?? 'Famille')),
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
            'X-PUBLISHED-TTL:PT1H',
        ];
        foreach (self::buildItems($user) as $uid => $vevent) {
            $lines[] = $vevent;
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Même contenu que buildIcs(), mais un VEVENT à la fois, indexé par UID — utilisé aussi par
     * App\Core\NextcloudCalendarSync pour pousser/retirer un événement à la fois dans le
     * calendrier Nextcloud dédié de ce membre (voir sa docblock de classe pour le raisonnement
     * sur les occurrences récurrentes de garde alternée, qui n'ont pas de ligne DB stable mais
     * un UID déterministe).
     *
     * @return array<string, string> uid => "BEGIN:VEVENT\r\n...\r\nEND:VEVENT"
     */
    public static function buildItems(array $user): array
    {
        $familyId = (int)$user['family_id'];
        $tz = Family::getTimezone($familyId);
        $start = date('Y-m-d', strtotime('-' . self::PAST_DAYS . ' days'));
        $end   = date('Y-m-d', strtotime('+' . self::FUTURE_DAYS . ' days'));

        $items = [];
        if ($user['role'] === 'coparent') {
            $scheduleIds = array_column(Custody::getSchedulesForUser((int)$user['id']), 'id');
            foreach (Custody::getAllEventsForSchedules($scheduleIds, $start, $end) as $e) {
                [$uid, $vevent] = self::custodyEvent($e);
                $items[$uid] = $vevent;
            }
        } else {
            foreach (Event::getByFamily($familyId, $start . ' 00:00:00', $end . ' 23:59:59') as $e) {
                [$uid, $vevent] = self::calendarEvent($e, $tz);
                $items[$uid] = $vevent;
            }
            foreach (Custody::getAllEventsForFamily($familyId, $start, $end) as $e) {
                [$uid, $vevent] = self::custodyEvent($e);
                $items[$uid] = $vevent;
            }
            foreach (Vacation::getByFamily($familyId, $start, $end) as $v) {
                [$uid, $vevent] = self::vacationEvent($v);
                $items[$uid] = $vevent;
            }
        }
        return $items;
    }

    /** @return array{0: string, 1: string} [uid, "BEGIN:VEVENT...END:VEVENT"] */
    private static function calendarEvent(array $e, string $tz): array
    {
        $uid = 'event-' . $e['id'] . '@familyboard';
        $lines = ['BEGIN:VEVENT', 'UID:' . $uid];
        if (!empty($e['is_all_day'])) {
            $lines[] = 'DTSTART;VALUE=DATE:' . self::localDate($e['start_datetime']);
            $lines[] = 'DTEND;VALUE=DATE:' . self::localDate($e['end_datetime'], 1);
        } else {
            $lines[] = 'DTSTART:' . self::toUtc($e['start_datetime'], $tz);
            $lines[] = 'DTEND:' . self::toUtc($e['end_datetime'], $tz);
        }
        $lines[] = 'SUMMARY:' . self::escape($e['title']);
        if (!empty($e['description'])) $lines[] = 'DESCRIPTION:' . self::escape($e['description']);
        if (!empty($e['location'])) $lines[] = 'LOCATION:' . self::escape($e['location']);
        $lines[] = 'END:VEVENT';
        return [$uid, implode("\r\n", $lines)];
    }

    /** @return array{0: string, 1: string} */
    private static function custodyEvent(array $e): array
    {
        $uid = 'custody-' . $e['id'] . '@familyboard';
        $title = ($e['child_name'] ?? 'Enfant') . ' chez ' . ($e['parent_name'] ?? '?');
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTART;VALUE=DATE:' . self::localDate($e['start_date']),
            'DTEND;VALUE=DATE:' . self::localDate($e['end_date'], 1),
            'SUMMARY:' . self::escape('👨‍👧 ' . $title),
            'END:VEVENT',
        ];
        return [$uid, implode("\r\n", $lines)];
    }

    /** @return array{0: string, 1: string} */
    private static function vacationEvent(array $v): array
    {
        $uid = 'vacation-' . $v['id'] . '@familyboard';
        $title = trim(($v['user_name'] ?? '') . ' — ' . ($v['title'] ?? 'Congé'));
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTART;VALUE=DATE:' . self::localDate($v['start_date']),
            'DTEND;VALUE=DATE:' . self::localDate($v['end_date'], 1),
            'SUMMARY:' . self::escape('🏖️ ' . $title),
            'END:VEVENT',
        ];
        return [$uid, implode("\r\n", $lines)];
    }

    /** Date pure (YYYYMMDD), avec un décalage de jours optionnel (DTEND est exclusive en
     *  iCalendar : une journée se terminant le même jour doit donc être repoussée de +1). */
    private static function localDate(string $date, int $addDays = 0): string
    {
        $dt = new \DateTime(substr($date, 0, 10));
        if ($addDays) $dt->modify("+{$addDays} day");
        return $dt->format('Ymd');
    }

    /** start_datetime/end_datetime sont enregistrés tels quels depuis un <input datetime-local>
     *  (heure locale de la famille) — on les interprète donc explicitement dans son fuseau
     *  avant de les convertir en UTC, seul format garanti sans ambiguïté pour une app
     *  calendrier tierce (évite d'avoir à fournir un bloc VTIMEZONE complet). */
    private static function toUtc(string $datetime, string $tz): string
    {
        $dt = new \DateTime($datetime, new \DateTimeZone($tz));
        $dt->setTimezone(new \DateTimeZone('UTC'));
        return $dt->format('Ymd\THis\Z');
    }

    private static function escape(string $text): string
    {
        return str_replace(["\\", ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], trim($text));
    }
}

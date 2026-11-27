<?php
namespace App\Models;

use App\Core\Database;

class CalDAVSource
{
    public static function getByFamily(int $familyId): array
    {
        return Database::fetchAll(
            'SELECT cs.*, u.name as user_name FROM caldav_sources cs JOIN users u ON u.id=cs.user_id WHERE cs.family_id=? ORDER BY cs.name',
            [$familyId]
        );
    }

    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM caldav_sources WHERE id=?', [$id]);
    }

    public static function create(int $familyId, int $userId, array $data): int
    {
        return Database::insert(
            'INSERT INTO caldav_sources (family_id, user_id, name, url, username, password, color) VALUES (?,?,?,?,?,?,?)',
            [$familyId, $userId, $data['name'], $data['url'], $data['username'] ?? null, $data['password'] ?? null, $data['color'] ?? '#4A90D9']
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM caldav_sources WHERE id=?', [$id]);
    }

    public static function updateSyncTime(int $id): void
    {
        Database::execute('UPDATE caldav_sources SET last_sync=NOW() WHERE id=?', [$id]);
    }

    /**
     * Parse iCal/CalDAV events from a URL.
     * Uses cURL (works even with allow_url_fopen=Off). Handles webcal:// scheme.
     */
    public static function fetchEvents(array $source): array
    {
        $url = $source['url'];
        // Convert webcal:// and webcals:// to http(s)://
        $url = preg_replace('/^webcals?:\/\//i', 'https://', $url);
        $url = preg_replace('/^webcal:\/\//i', 'http://', $url);

        // Un calendrier CalDAV est un service externe (Google, iCloud, Nextcloud...), jamais une
        // adresse interne : on refuse tout ce qui ne résout pas vers une IP publique, pour éviter
        // qu'une URL de calendrier ne serve à faire sonder le réseau local par le serveur (SSRF).
        $pinnedIp = self::safePublicIp($url);
        if ($pinnedIp === null) return [];

        if (function_exists('curl_init')) {
            $data = self::curlGet($url, $source['username'] ?? null, $source['password'] ?? null, $pinnedIp);
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'header' => $source['username']
                        ? 'Authorization: Basic ' . base64_encode($source['username'] . ':' . $source['password']) . "\r\n"
                        : '',
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $data = @file_get_contents($url, false, $ctx);
        }

        if (!$data || !str_contains($data, 'BEGIN:VCALENDAR')) return [];

        return self::parseICAL($data, $source['color'] ?? '#4A90D9');
    }

    /** Rejette les schémas non-HTTP(S) et toute résolution DNS vers une plage privée/loopback/
     *  link-local, et retourne l'IP publique validée (ou null si le contrôle échoue). Le
     *  résultat est ensuite épinglé sur la requête réelle (CURLOPT_RESOLVE, voir curlGet()) :
     *  sans ça, un domaine attaquant avec un TTL DNS très court pourrait présenter une IP
     *  publique à ce moment précis, puis une IP interne au moment de curl_exec() (DNS
     *  rebinding) — refaire la résolution DNS dans curl_init($url) n'offrirait aucune garantie
     *  que c'est la même IP qui a été validée ici. */
    private static function safePublicIp(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return null;
        }
        $host = $parts['host'];
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : (gethostbyname($host) !== $host ? gethostbyname($host) : null);
        if ($ip === null) return null;
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false ? $ip : null;
    }

    private static function curlGet(string $url, ?string $user, ?string $pass, string $pinnedIp): string|false
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $host = $parts['host'];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            // Pas de redirection automatique : une redirection HTTP pourrait pointer vers une
            // adresse interne alors que l'URL d'origine, elle, a passé la vérification
            // safePublicIp() — on préfère ne pas suivre plutôt que rouvrir la faille.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
            // Épingle la connexion sur l'IP déjà validée par safePublicIp() plutôt que de
            // laisser curl refaire sa propre résolution DNS (fenêtre de DNS rebinding).
            CURLOPT_RESOLVE        => ["$host:$port:$pinnedIp"],
        ]);
        if ($user) {
            curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . ($pass ?? ''));
        }
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }

    private static function parseICAL(string $data, string $color): array
    {
        $events = [];
        // Unfold long lines (RFC 5545 line folding)
        $data = preg_replace('/\r\n[ \t]/', '', $data);
        $data = preg_replace('/\n[ \t]/', '', $data);
        $lines = preg_split('/\r\n|\n|\r/', $data);

        $inEvent = false;
        $current = [];

        foreach ($lines as $rawLine) {
            $line = rtrim($rawLine);
            if ($line === '') continue;

            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $current = ['color' => $color];
                continue;
            }
            if ($line === 'END:VEVENT' && $inEvent) {
                $inEvent = false;
                // If no DTEND, derive from DTSTART (all-day single-day or 1h event)
                if (isset($current['start_datetime']) && !isset($current['end_datetime'])) {
                    if (!empty($current['is_all_day'])) {
                        $dt = new \DateTime($current['start_datetime']);
                        $dt->modify('+1 day');
                        $current['end_datetime'] = $dt->format('Y-m-d H:i:s');
                    } else {
                        $dt = new \DateTime($current['start_datetime']);
                        $dt->modify('+1 hour');
                        $current['end_datetime'] = $dt->format('Y-m-d H:i:s');
                    }
                }
                if (isset($current['title'], $current['start_datetime'], $current['end_datetime'])) {
                    $events[] = $current;
                }
                continue;
            }
            if (!$inEvent) continue;

            // Split on first colon only; value may contain colons (e.g. URLs, TZIDs)
            $colonPos = strpos($line, ':');
            if ($colonPos === false) continue;
            $prop  = substr($line, 0, $colonPos);
            $value = substr($line, $colonPos + 1);

            // Property name is before the first semicolon
            $propName = strtoupper(explode(';', $prop)[0]);

            switch ($propName) {
                case 'SUMMARY':
                    $current['title'] = self::unescapeICAL($value);
                    break;
                case 'DESCRIPTION':
                    $current['description'] = self::unescapeICAL($value);
                    break;
                case 'UID':
                    $current['uid'] = $value;
                    break;
                case 'DTSTART':
                    [$current['start_datetime'], $current['is_all_day']] = self::parseDate($value, $prop);
                    break;
                case 'DTEND':
                    [$current['end_datetime']] = self::parseDate($value, $prop);
                    break;
                case 'DURATION':
                    // Store duration to compute end_datetime after DTSTART is known
                    $current['_duration'] = $value;
                    break;
            }
        }

        // Second pass: resolve DURATION-based end_datetime
        foreach ($events as &$e) {
            if (!isset($e['end_datetime']) && isset($e['_duration'], $e['start_datetime'])) {
                try {
                    $dt = new \DateTime($e['start_datetime']);
                    $dt->add(new \DateInterval($e['_duration']));
                    $e['end_datetime'] = $dt->format('Y-m-d H:i:s');
                } catch (\Exception) {
                    // ignore unparseable duration
                }
            }
            unset($e['_duration']);
        }
        unset($e);

        return $events;
    }

    private static function parseDate(string $value, string $prop): array
    {
        $isAllDay = str_contains($prop, 'VALUE=DATE') || (strlen($value) === 8 && ctype_digit($value));
        if ($isAllDay) {
            $dt = \DateTime::createFromFormat('Ymd', substr($value, 0, 8));
            return [$dt ? $dt->format('Y-m-d') . ' 00:00:00' : null, 1];
        }

        // Extract TZID if present: DTSTART;TZID=Europe/Paris:20260315T120000
        $tzid = null;
        if (preg_match('/TZID=([^;:]+)/', $prop, $m)) {
            $tzid = $m[1];
        }

        $isUtc = str_ends_with($value, 'Z');
        $clean = rtrim($value, 'Z');

        $dt = \DateTime::createFromFormat('Ymd\THis', $clean)
            ?: \DateTime::createFromFormat('Y-m-d\TH:i:s', $clean)
            ?: \DateTime::createFromFormat('Ymd\THis\Z', $value);

        if (!$dt) return [null, 0];

        if ($isUtc) {
            // UTC → local server time (or keep as-is; events stored in UTC)
            $dt->setTimezone(new \DateTimeZone(date_default_timezone_get() ?: 'UTC'));
        } elseif ($tzid) {
            try {
                $tz = new \DateTimeZone($tzid);
                $dt->setTimezone($tz);
                $dt->setTimezone(new \DateTimeZone(date_default_timezone_get() ?: 'UTC'));
            } catch (\Exception) {
                // Keep as-is if timezone unknown
            }
        }

        return [$dt->format('Y-m-d H:i:s'), 0];
    }

    private static function unescapeICAL(string $value): string
    {
        return str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $value);
    }
}

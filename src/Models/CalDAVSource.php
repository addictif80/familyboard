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

        if (function_exists('curl_init')) {
            $data = self::curlGet($url, $source['username'] ?? null, $source['password'] ?? null);
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 20,
                    'header' => $source['username']
                        ? 'Authorization: Basic ' . base64_encode($source['username'] . ':' . $source['password']) . "\r\n"
                        : '',
                ],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);
            $data = @file_get_contents($url, false, $ctx);
        }

        if (!$data || !str_contains($data, 'BEGIN:VCALENDAR')) return [];

        return self::parseICAL($data, $source['color'] ?? '#4A90D9');
    }

    private static function curlGet(string $url, ?string $user, ?string $pass): string|false
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'FamilyBoard/1.0',
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

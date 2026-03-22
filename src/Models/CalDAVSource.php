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
     * Parse iCal/CalDAV events from a URL
     */
    public static function fetchEvents(array $source): array
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => $source['username']
                    ? 'Authorization: Basic ' . base64_encode($source['username'] . ':' . $source['password']) . "\r\n"
                    : '',
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);

        $data = @file_get_contents($source['url'], false, $ctx);
        if (!$data) return [];

        return self::parseICAL($data, $source['color'] ?? '#4A90D9');
    }

    private static function parseICAL(string $data, string $color): array
    {
        $events = [];
        // Unfold long lines
        $data = preg_replace('/\r\n[ \t]/', '', $data);
        $lines = preg_split('/\r\n|\n|\r/', $data);

        $inEvent = false;
        $current = [];

        foreach ($lines as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;
                $current = ['color' => $color];
            } elseif ($line === 'END:VEVENT' && $inEvent) {
                $inEvent = false;
                if (isset($current['title'], $current['start_datetime'], $current['end_datetime'])) {
                    $events[] = $current;
                }
            } elseif ($inEvent) {
                [$prop, $value] = array_pad(explode(':', $line, 2), 2, '');
                // Handle property parameters (e.g. DTSTART;TZID=...)
                $propName = explode(';', $prop)[0];
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
                }
            }
        }

        return $events;
    }

    private static function parseDate(string $value, string $prop): array
    {
        $isAllDay = str_contains($prop, 'VALUE=DATE') || strlen($value) === 8;
        if ($isAllDay) {
            $dt = \DateTime::createFromFormat('Ymd', substr($value, 0, 8));
            return [$dt ? $dt->format('Y-m-d H:i:s') : null, 1];
        }
        // Handle UTC Z suffix
        $value = rtrim($value, 'Z');
        $dt = \DateTime::createFromFormat('Ymd\THis', $value);
        if (!$dt) $dt = \DateTime::createFromFormat('Y-m-d\TH:i:s', $value);
        return [$dt ? $dt->format('Y-m-d H:i:s') : null, 0];
    }

    private static function unescapeICAL(string $value): string
    {
        return str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $value);
    }
}

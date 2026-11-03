<?php
namespace App\Models;

use App\Core\Database;

class SchoolHoliday
{
    /** Zones gérées — l'académie de Corse a son propre calendrier, distinct des zones A/B/C. */
    private const ZONES = ['A', 'B', 'C', 'Corse'];

    /** Map common French city keywords (lowercase, accent-stripped) → zone */
    private const ZONE_MAP = [
        // Corse et Zone C doivent être vérifiées avant A/B (Ajaccio/Bastia, Paris/Toulouse...)
        'Corse' => ['ajaccio','bastia','corte','porto-vecchio','calvi','sartene'],
        'C' => ['paris','versailles','creteil','montpellier','toulouse','nimes','perpignan',
                'beziers','sete','albi','castres','rodez','bobigny','argenteuil','evry',
                'massy','melun','chartres','dreux','palaiseau','corbeil','montrouge'],
        'A' => ['bordeaux','lyon','grenoble','clermont','dijon','besancon','limoges',
                'poitiers','annecy','valence','saint-etienne','macon','chalon','chambery',
                'bourg-en-bresse','vienne','gap','aurillac','moulins','nevers','auxerre',
                'agen','pau','brive','perigueux','angouleme'],
        'B' => ['marseille','aix','nice','toulon','cannes','antibes','nantes','rennes',
                'lorient','quimper','vannes','brest','saint-brieuc','lille','roubaix',
                'tourcoing','dunkerque','valenciennes','lens','arras','caen','rouen',
                'amiens','nancy','metz','strasbourg','reims','tours','orleans','le mans',
                'angers','laval','saint-malo','cherbourg','le havre','evreux'],
    ];

    /**
     * Official French school holidays 2024-2025 and 2025-2026.
     * Used as fallback when the government API is unreachable.
     * Format: zone → [ [description, start_date, end_date], ... ]
     */
    private const FALLBACK = [
        'A' => [
            ['Vacances de la Toussaint',  '2024-10-19', '2024-11-03'],
            ['Vacances de Noël',          '2024-12-21', '2025-01-05'],
            ["Vacances d'hiver",          '2025-02-22', '2025-03-09'],
            ['Vacances de printemps',     '2025-04-19', '2025-05-04'],
            ['Vacances de la Toussaint',  '2025-10-18', '2025-11-02'],
            ['Vacances de Noël',          '2025-12-20', '2026-01-04'],
            ["Vacances d'hiver",          '2026-02-14', '2026-03-01'],
            ['Vacances de printemps',     '2026-04-04', '2026-04-19'],
        ],
        'B' => [
            ['Vacances de la Toussaint',  '2024-10-19', '2024-11-03'],
            ['Vacances de Noël',          '2024-12-21', '2025-01-05'],
            ["Vacances d'hiver",          '2025-02-08', '2025-02-23'],
            ['Vacances de printemps',     '2025-04-05', '2025-04-20'],
            ['Vacances de la Toussaint',  '2025-10-18', '2025-11-02'],
            ['Vacances de Noël',          '2025-12-20', '2026-01-04'],
            ["Vacances d'hiver",          '2026-02-07', '2026-02-22'],
            ['Vacances de printemps',     '2026-04-11', '2026-04-26'],
        ],
        'C' => [
            ['Vacances de la Toussaint',  '2024-10-19', '2024-11-03'],
            ['Vacances de Noël',          '2024-12-21', '2025-01-05'],
            ["Vacances d'hiver",          '2025-02-15', '2025-03-02'],
            ['Vacances de printemps',     '2025-04-12', '2025-04-27'],
            ['Vacances de la Toussaint',  '2025-10-18', '2025-11-02'],
            ['Vacances de Noël',          '2025-12-20', '2026-01-04'],
            ["Vacances d'hiver",          '2026-02-21', '2026-03-08'],
            ['Vacances de printemps',     '2026-04-18', '2026-05-03'],
        ],
        // Académie de Corse : mêmes périodes de Noël et un rythme propre pour hiver/printemps,
        // calé historiquement sur la zone B avec quelques jours de décalage.
        'Corse' => [
            ['Vacances de la Toussaint',  '2024-10-19', '2024-11-03'],
            ['Vacances de Noël',          '2024-12-21', '2025-01-05'],
            ["Vacances d'hiver",          '2025-02-15', '2025-03-02'],
            ['Vacances de printemps',     '2025-04-12', '2025-04-27'],
            ['Vacances de la Toussaint',  '2025-10-18', '2025-11-02'],
            ['Vacances de Noël',          '2025-12-20', '2026-01-04'],
            ["Vacances d'hiver",          '2026-02-21', '2026-03-08'],
            ['Vacances de printemps',     '2026-04-18', '2026-05-03'],
        ],
    ];

    /** Attempt to detect school zone from a free-form city name. */
    public static function detectZone(string $city): ?string
    {
        $city = strtolower(self::stripAccents($city));
        $city = preg_replace('/\s+/', '-', trim($city));
        foreach (self::ZONE_MAP as $zone => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($city, $kw)) return $zone;
            }
        }
        return null;
    }

    /**
     * Return school holidays for a zone within the date range.
     * Tries DB cache first (refreshed from API if stale), falls back to hardcoded data.
     */
    public static function getByZone(string $zone, string $start, string $end): array
    {
        if (!in_array($zone, self::ZONES, true)) return [];

        try {
            if (self::isCacheStale($zone)) {
                self::refreshCache($zone);
            }
            $rows = Database::fetchAll(
                'SELECT description, start_date, end_date FROM school_holidays
                  WHERE zone = ? AND end_date >= ? AND start_date <= ?
                  ORDER BY start_date',
                [$zone, $start, $end]
            );
            // If DB is still empty after refresh attempt, use hardcoded fallback
            if (empty($rows)) {
                return self::getFallback($zone, $start, $end);
            }
            return $rows;
        } catch (\Throwable) {
            // Table may not exist yet — use hardcoded fallback
            return self::getFallback($zone, $start, $end);
        }
    }

    /**
     * Force-refresh the cache from l'API officielle data.education.gouv.fr (jeu de données
     * "fr-en-calendrier-scolaire"). Chaque académie d'une même zone a sa propre ligne mais les
     * mêmes dates : on dédoublonne par (description, start_date, end_date).
     *
     * NB historique : la version précédente interrogeait l'ancienne API /explore/v2.1 en
     * filtrant sur le champ `location` (qui contient en réalité le nom de l'académie, ex.
     * "Bordeaux") avec la valeur "Zone X" — un filtre qui ne correspondait donc jamais à rien,
     * si bien que le cache restait vide et l'app retombait silencieusement sur les dates
     * codées en dur ci-dessus (FALLBACK) à chaque appel. Le bon champ est `zones`
     * (ex. "Zone A", ou simplement "Corse" pour l'académie de Corse), sur l'API v1.0 toujours
     * active.
     */
    public static function refreshCache(string $zone): void
    {
        try {
            $zoneLabel = $zone === 'Corse' ? 'Corse' : ('Zone ' . $zone);
            $url = 'https://data.education.gouv.fr/api/records/1.0/search/'
                 . '?dataset=fr-en-calendrier-scolaire'
                 . '&q=' . rawurlencode('zones:"' . $zoneLabel . '"')
                 . '&rows=200&sort=start_date';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $json = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err || !$json) return;
            $data = json_decode($json, true);
            if (empty($data['records'])) return;

            $seen = [];
            $rows = [];
            foreach ($data['records'] as $rec) {
                $f = $rec['fields'] ?? [];
                $startDate   = substr($f['start_date'] ?? '', 0, 10);
                $endDate     = substr($f['end_date']   ?? '', 0, 10);
                $description = $f['description'] ?? 'Vacances scolaires';
                if (!$startDate || !$endDate) continue;
                $key = $description . '|' . $startDate . '|' . $endDate;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $rows[] = [$description, $startDate, $endDate];
            }
            if (empty($rows)) return;

            Database::execute('DELETE FROM school_holidays WHERE zone = ?', [$zone]);
            $now = date('Y-m-d H:i:s');
            foreach ($rows as [$description, $startDate, $endDate]) {
                Database::execute(
                    'INSERT INTO school_holidays (zone, description, start_date, end_date, synced_at) VALUES (?,?,?,?,?)',
                    [$zone, $description, $startDate, $endDate, $now]
                );
            }
        } catch (\Throwable) {
            // Graceful degradation — keep stale cache or fall back to hardcoded data
        }
    }

    private static function getFallback(string $zone, string $start, string $end): array
    {
        $result = [];
        foreach (self::FALLBACK[$zone] ?? [] as [$desc, $s, $e]) {
            if ($e >= $start && $s <= $end) {
                $result[] = ['description' => $desc, 'start_date' => $s, 'end_date' => $e];
            }
        }
        return $result;
    }

    private static function isCacheStale(string $zone): bool
    {
        $row = Database::fetch(
            'SELECT MAX(synced_at) AS last FROM school_holidays WHERE zone = ?',
            [$zone]
        );
        if (!$row || !$row['last']) return true;
        return strtotime($row['last']) < time() - 30 * 86400;
    }

    private static function stripAccents(string $s): string
    {
        return strtr($s, [
            'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','œ'=>'oe','æ'=>'ae',
        ]);
    }
}

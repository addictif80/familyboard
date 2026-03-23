<?php
namespace App\Core;

/**
 * French date formatting helper.
 * Avoids relying on setlocale() which is unreliable on shared hosting.
 */
class DateHelper
{
    private static array $months = [
        1  => 'janvier',   2  => 'février',  3  => 'mars',
        4  => 'avril',     5  => 'mai',       6  => 'juin',
        7  => 'juillet',   8  => 'août',      9  => 'septembre',
        10 => 'octobre',   11 => 'novembre',  12 => 'décembre',
    ];

    private static array $monthsShort = [
        1  => 'jan.',  2  => 'fév.',  3  => 'mars',
        4  => 'avr.',  5  => 'mai',   6  => 'juin',
        7  => 'juil.', 8  => 'août',  9  => 'sep.',
        10 => 'oct.',  11 => 'nov.',  12 => 'déc.',
    ];

    private static array $days = [
        0 => 'dimanche', 1 => 'lundi',    2 => 'mardi',
        3 => 'mercredi', 4 => 'jeudi',    5 => 'vendredi',
        6 => 'samedi',
    ];

    private static array $daysShort = [
        0 => 'dim.', 1 => 'lun.', 2 => 'mar.',
        3 => 'mer.', 4 => 'jeu.', 5 => 'ven.', 6 => 'sam.',
    ];

    /**
     * Format a datetime string using standard PHP format characters,
     * replacing 'F' (full month), 'M' (short month), 'l' (full day), 'D' (short day)
     * with their French equivalents.
     *
     * Example: DateHelper::format('2026-03-15', 'F Y') → 'mars 2026'
     *          DateHelper::format('2026-03-15', 'j F Y à H\hi') → '15 mars 2026 à 14h30'
     */
    public static function format(string $datetime, string $fmt = 'd/m/Y'): string
    {
        try {
            $dt = new \DateTime($datetime);
        } catch (\Exception) {
            return '';
        }
        $n = (int)$dt->format('n');
        $w = (int)$dt->format('w');

        // Build translation map for the tokens we replace
        $replace = [];
        if (str_contains($fmt, 'F')) $replace[$dt->format('F')] = self::$months[$n];
        if (str_contains($fmt, 'M')) $replace[$dt->format('M')] = self::$monthsShort[$n];
        if (str_contains($fmt, 'l')) $replace[$dt->format('l')] = self::$days[$w];
        if (str_contains($fmt, 'D')) $replace[$dt->format('D')] = self::$daysShort[$w];

        $result = $dt->format($fmt);
        foreach ($replace as $en => $fr) {
            $result = str_replace($en, $fr, $result);
        }
        return $result;
    }

    /** "mars 2026" */
    public static function monthYear(string $datetime): string
    {
        return self::format($datetime, 'F Y');
    }

    /** "Mars 2026" (capitalized) */
    public static function monthYearUc(string $datetime): string
    {
        return ucfirst(self::monthYear($datetime));
    }

    /** "15 mars 2026" */
    public static function long(string $datetime): string
    {
        return self::format($datetime, 'j F Y');
    }

    /** "15 mars 2026 à 14h30" */
    public static function longTime(string $datetime): string
    {
        return self::format($datetime, 'j F Y \à H\hi');
    }

    /** "lun. 15 mars" */
    public static function shortDay(string $datetime): string
    {
        return self::format($datetime, 'D j F');
    }

    /**
     * Popular timezones list for the selector, grouped by region.
     */
    public static function timezoneList(): array
    {
        return [
            'Europe' => [
                'Europe/Paris'     => 'Paris (France)',
                'Europe/Brussels'  => 'Bruxelles (Belgique)',
                'Europe/Zurich'    => 'Zurich (Suisse)',
                'Europe/Luxembourg'=> 'Luxembourg',
                'Europe/London'    => 'Londres (UK)',
                'Europe/Berlin'    => 'Berlin (Allemagne)',
                'Europe/Madrid'    => 'Madrid (Espagne)',
                'Europe/Rome'      => 'Rome (Italie)',
                'Europe/Amsterdam' => 'Amsterdam (Pays-Bas)',
                'Europe/Lisbon'    => 'Lisbonne (Portugal)',
                'Europe/Warsaw'    => 'Varsovie (Pologne)',
            ],
            'Amérique' => [
                'America/Montreal'   => 'Montréal (Canada)',
                'America/Toronto'    => 'Toronto (Canada)',
                'America/New_York'   => 'New York (USA)',
                'America/Chicago'    => 'Chicago (USA)',
                'America/Denver'     => 'Denver (USA)',
                'America/Los_Angeles'=> 'Los Angeles (USA)',
                'America/Guadeloupe' => 'Guadeloupe (France)',
                'America/Martinique' => 'Martinique (France)',
            ],
            'Afrique & Océan Indien' => [
                'Indian/Reunion'  => 'La Réunion (France)',
                'Indian/Mayotte'  => 'Mayotte (France)',
                'Africa/Abidjan'  => 'Abidjan (Côte d\'Ivoire)',
                'Africa/Dakar'    => 'Dakar (Sénégal)',
            ],
            'Pacifique' => [
                'Pacific/Tahiti'       => 'Tahiti (Polynésie française)',
                'Pacific/Noumea'       => 'Nouméa (Nouvelle-Calédonie)',
                'Pacific/Auckland'     => 'Auckland (Nouvelle-Zélande)',
                'Australia/Sydney'     => 'Sydney (Australie)',
            ],
            'Asie' => [
                'Asia/Dubai'   => 'Dubaï',
                'Asia/Tokyo'   => 'Tokyo (Japon)',
                'Asia/Shanghai'=> 'Shanghai (Chine)',
            ],
            'UTC' => [
                'UTC' => 'UTC (temps universel)',
            ],
        ];
    }
}

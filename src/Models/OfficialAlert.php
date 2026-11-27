<?php
namespace App\Models;

use App\Core\Database;

class OfficialAlert
{
    /** scoped=true : l'alerte n'est affichée qu'aux familles dont weather_city correspond. */
    public const CATEGORIES = [
        'enlevement'   => ['label' => 'Alerte enlèvement',       'icon' => '🚨', 'scoped' => false],
        'canicule'     => ['label' => 'Canicule',                'icon' => '🌡️', 'scoped' => true],
        'inondation'   => ['label' => 'Inondation',              'icon' => '🌊', 'scoped' => true],
        'feu_foret'    => ['label' => 'Feu de forêt',            'icon' => '🔥', 'scoped' => false],
        'climatique'   => ['label' => 'Alerte climatique',       'icon' => '⛈️', 'scoped' => true],
        'industrielle' => ['label' => 'Catastrophe industrielle', 'icon' => '☣️', 'scoped' => true],
    ];

    public static function existsByDedupeKey(string $key): bool
    {
        return (bool)Database::fetch('SELECT 1 FROM official_alerts WHERE dedupe_key=?', [$key]);
    }

    /**
     * Vrai si une alerte de la même catégorie (et, si fournie, de la même ville) a déjà été
     * enregistrée dans les dernières $hours heures. Sert à éviter de renvoyer une notification
     * push par article de presse quand plusieurs médias couvrent le même évènement en cours
     * (ex. 5 articles sur un même incendie en une après-midi) : l'alerte est toujours stockée
     * et visible dans la bannière, seule la notification est limitée à une par période.
     */
    public static function wasNotifiedRecently(string $category, ?string $city, int $hours = 12): bool
    {
        if ($city === null) {
            return (bool)Database::fetch(
                'SELECT 1 FROM official_alerts WHERE category=? AND city_match IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)',
                [$category, $hours]
            );
        }
        return (bool)Database::fetch(
            'SELECT 1 FROM official_alerts WHERE category=? AND city_match=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)',
            [$category, $city, $hours]
        );
    }

    public static function create(
        string $category,
        string $title,
        ?string $summary,
        string $sourceName,
        string $sourceUrl,
        ?string $cityMatch,
        string $dedupeKey,
        ?string $publishedAt
    ): int {
        return Database::insert(
            'INSERT INTO official_alerts (category, title, summary, source_name, source_url, city_match, dedupe_key, published_at) VALUES (?,?,?,?,?,?,?,?)',
            [$category, $title, $summary, $sourceName, $sourceUrl, $cityMatch, $dedupeKey, $publishedAt]
        );
    }

    /** Alertes des 7 derniers jours pertinentes pour une famille : nationales + celles ciblant sa ville. */
    public static function getActiveForFamily(?string $city): array
    {
        if ($city) {
            return Database::fetchAll(
                'SELECT * FROM official_alerts
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 AND (city_match IS NULL OR city_match = ?)
                 ORDER BY published_at DESC, created_at DESC
                 LIMIT 15',
                [$city]
            );
        }
        return Database::fetchAll(
            'SELECT * FROM official_alerts
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND city_match IS NULL
             ORDER BY published_at DESC, created_at DESC
             LIMIT 15'
        );
    }

    /**
     * Historique (30 jours) des alertes d'une seule catégorie pour une famille : alimente la
     * page dédiée "S'informer" ouverte depuis le bandeau, filtrée sur la catégorie affichée au
     * moment du clic. Fenêtre plus large que getActiveForFamily() (7 jours) car cette page est
     * consultée volontairement, pas affichée en continu.
     */
    public static function getActiveForFamilyByCategory(string $category, ?string $city): array
    {
        if ($city) {
            return Database::fetchAll(
                'SELECT * FROM official_alerts
                 WHERE category = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 AND (city_match IS NULL OR city_match = ?)
                 ORDER BY published_at DESC, created_at DESC
                 LIMIT 50',
                [$category, $city]
            );
        }
        return Database::fetchAll(
            'SELECT * FROM official_alerts
             WHERE category = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND city_match IS NULL
             ORDER BY published_at DESC, created_at DESC
             LIMIT 50',
            [$category]
        );
    }
}

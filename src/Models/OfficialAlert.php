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
}

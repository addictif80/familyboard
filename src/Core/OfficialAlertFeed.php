<?php
namespace App\Core;

use App\Models\Notification;
use App\Models\OfficialAlert;

/**
 * Veille informationnelle par mots-clés sur des flux RSS d'actualité français. Ce n'est PAS
 * un raccordement à un flux officiel d'alerte structuré : aucun n'existe pour couvrir toutes
 * les catégories visées (FR-Alert ne propose aucune API — c'est un système de diffusion
 * cellulaire, pas un flux consultable ; l'alerte enlèvement n'a plus de flux RSS actif ;
 * Météo-France Vigilance nécessite une clé API que ce projet n'a pas). On détecte donc les
 * articles pertinents par mots-clés dans des flux RSS d'actualité générale, ce qui peut rater
 * des alertes réelles ou, plus rarement, mal classer un article — à traiter comme un
 * complément de veille, pas comme une source officielle temps réel.
 *
 * Pour les catégories géolocalisées (canicule, inondation, climatique, industrielle), un
 * article n'est retenu que si le nom de la ville d'une famille (App\Models\Family::weather_city)
 * apparaît dans le texte — approximation par mot-clé également, pas un géociblage précis.
 */
class OfficialAlertFeed
{
    private const FEEDS = [
        ['name' => 'France Info',       'url' => 'https://www.francetvinfo.fr/titres.rss'],
        ['name' => 'France Info',       'url' => 'https://www.francetvinfo.fr/faits-divers.rss'],
        ['name' => 'Le Monde',          'url' => 'https://www.lemonde.fr/rss/une.xml'],
        ['name' => 'BFMTV',             'url' => 'https://www.bfmtv.com/rss/news-24-7/'],
        ['name' => 'Ici (France Bleu)', 'url' => 'https://www.francebleu.fr/rss/a-la-une.xml'],
    ];

    private const KEYWORDS = [
        'enlevement'   => ['alerte enlevement', 'enfant enleve', 'enfant disparu', 'avis de recherche'],
        'canicule'     => ['canicule', 'vague de chaleur', 'chaleur extreme', 'vigilance canicule'],
        'inondation'   => ['inondation', 'crue', 'crues', 'vigilance crues'],
        'feu_foret'    => ['feu de foret', 'feux de foret', 'incendie de foret', 'incendie de vegetation', 'megafeu', 'feu de vegetation', 'incendie ravage', 'incendies ravagent', 'brasier'],
        'climatique'   => ['catastrophe naturelle', 'tempete', 'evenement climatique extreme', 'vigilance rouge', 'vigilance orange'],
        'industrielle' => ['accident industriel', 'usine seveso', 'explosion usine', 'fuite chimique', 'nuage toxique', 'confinement des populations'],
    ];

    public static function poll(): void
    {
        $cities = self::getDistinctFamilyCities();

        foreach (self::FEEDS as $feed) {
            try {
                $items = self::fetchItems($feed['url']);
            } catch (\Throwable $e) {
                error_log('OfficialAlertFeed fetch error (' . $feed['url'] . '): ' . $e->getMessage());
                continue;
            }
            foreach ($items as $item) {
                self::processItem($feed['name'], $item, $cities);
            }
        }
    }

    private static function processItem(string $sourceName, array $item, array $cities): void
    {
        $haystack = self::normalize($item['title'] . ' ' . $item['description']);

        foreach (self::KEYWORDS as $category => $keywords) {
            $matched = false;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, self::normalize($kw))) { $matched = true; break; }
            }
            if (!$matched) continue;

            if (OfficialAlert::CATEGORIES[$category]['scoped']) {
                foreach ($cities as $city) {
                    if (self::matchesCity($haystack, $city)) {
                        self::saveAlert($category, $item, $sourceName, $city);
                    }
                }
            } else {
                self::saveAlert($category, $item, $sourceName, null);
            }
        }
    }

    private static function saveAlert(string $category, array $item, string $sourceName, ?string $city): void
    {
        $dedupeKey = md5(($item['guid'] ?: $item['link']) . '|' . ($city ?? ''));
        if (OfficialAlert::existsByDedupeKey($dedupeKey)) return;

        $summary = mb_strimwidth(trim(strip_tags($item['description'])), 0, 300, '…');
        $id = OfficialAlert::create(
            $category,
            mb_strimwidth($item['title'], 0, 500, '…'),
            $summary ?: null,
            $sourceName,
            $item['link'],
            $city,
            $dedupeKey,
            $item['pubDate']
        );

        self::notifyConcernedFamilies($category, $item['title'], $item['link'], $city);
        unset($id);
    }

    private static function notifyConcernedFamilies(string $category, string $title, string $sourceUrl, ?string $city): void
    {
        $label = OfficialAlert::CATEGORIES[$category]['label'] ?? $category;
        $notifMsg = mb_strimwidth($title, 0, 150, '…');

        try {
            if ($city === null) {
                $userIds = Database::fetchAll('SELECT id FROM users');
            } else {
                $userIds = Database::fetchAll(
                    'SELECT u.id FROM users u JOIN families f ON f.id = u.family_id WHERE f.weather_city = ?',
                    [$city]
                );
            }
            foreach ($userIds as $row) {
                Notification::create((int)$row['id'], 'official_alert', $label, $notifMsg, $sourceUrl);
            }
        } catch (\Throwable $e) {
            error_log('OfficialAlertFeed notify error: ' . $e->getMessage());
        }
    }

    private static function getDistinctFamilyCities(): array
    {
        $rows = Database::fetchAll("SELECT DISTINCT weather_city FROM families WHERE weather_city IS NOT NULL AND weather_city <> ''");
        return array_column($rows, 'weather_city');
    }

    private static function matchesCity(string $normalizedHaystack, string $city): bool
    {
        $needle = self::normalize($city);
        if ($needle === '') return false;
        return (bool)preg_match('/\b' . preg_quote($needle, '/') . '\b/u', $normalizedHaystack);
    }

    private static function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        return $translit !== false ? $translit : $s;
    }

    private static function fetchItems(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'FamilyBoardAlertBot/1.0 (+https://familyboard.app)',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if (!$body) return [];

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        if (!$xml || !isset($xml->channel->item)) return [];

        $items = [];
        foreach ($xml->channel->item as $entry) {
            $link = trim((string)$entry->link);
            if (!$link) continue;
            $items[] = [
                'title'       => trim((string)$entry->title),
                'link'        => $link,
                'description' => trim((string)$entry->description),
                'guid'        => trim((string)$entry->guid) ?: $link,
                'pubDate'     => self::parseDate((string)$entry->pubDate),
            ];
        }
        return $items;
    }

    private static function parseDate(string $raw): ?string
    {
        $ts = $raw ? strtotime($raw) : false;
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}

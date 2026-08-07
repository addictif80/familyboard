<?php
namespace App\Core;

use App\Models\AppSetting;
use App\Models\OfficialAlert;

/**
 * Veille informationnelle : les alertes proviennent d'un unique flux RSS, la catégorie dédiée
 * "familyboard-alert" du blog familial (leblogateur.fr), complétée par la véritable API
 * officielle Vigilance de Météo-France (canicule + autres risques météo, par département —
 * voir pollMeteoFranceVigilance()). Contrairement à une veille automatique par mots-clés sur
 * des flux d'actualité générale (imprécise, faux positifs), chaque article de cette catégorie
 * est rédigé volontairement pour FamilyBoard : le titre doit commencer par un préfixe indiquant
 * la catégorie d'alerte, avec une ville optionnelle pour les catégories géolocalisées, ex. :
 *   "[canicule] Vague de chaleur cette semaine"          → alerte nationale
 *   "[inondation:Bordeaux] Crue de la Garonne"            → alerte ciblée sur la ville "Bordeaux"
 * Le préfixe doit correspondre à une clé de App\Models\OfficialAlert::CATEGORIES ; un article
 * sans préfixe reconnu est ignoré (et journalisé) plutôt que deviné.
 */
class OfficialAlertFeed
{
    private const FEED_URL = 'https://leblogateur.fr/categories/familyboard-alert/rss.xml';
    private const SOURCE_NAME = 'Le Blogateur';

    /** Préfixe de titre : "[categorie]" ou "[categorie:Ville]". */
    private const TITLE_PREFIX_PATTERN = '/^\[([a-z_]+)(?::([^\]]+))?\]\s*(.+)$/ui';

    public static function poll(): void
    {
        try {
            $items = self::fetchItems(self::FEED_URL);
            foreach ($items as $item) {
                self::processItem($item);
            }
        } catch (\Throwable $e) {
            error_log('OfficialAlertFeed fetch error (' . self::FEED_URL . '): ' . $e->getMessage());
        }

        try {
            self::pollMeteoFranceVigilance(self::getDistinctFamilyCities());
        } catch (\Throwable $e) {
            error_log('OfficialAlertFeed Météo-France Vigilance error: ' . $e->getMessage());
        }
    }

    private static function processItem(array $item): void
    {
        if (!preg_match(self::TITLE_PREFIX_PATTERN, trim($item['title']), $m)) {
            error_log('OfficialAlertFeed: article sans préfixe de catégorie ignoré — ' . $item['link']);
            return;
        }

        $category = mb_strtolower($m[1]);
        $city = isset($m[2]) && $m[2] !== '' ? trim($m[2]) : null;
        $title = trim($m[3]);

        if (!isset(OfficialAlert::CATEGORIES[$category])) {
            error_log('OfficialAlertFeed: catégorie inconnue "' . $category . '" ignorée — ' . $item['link']);
            return;
        }

        self::saveAlert($category, array_merge($item, ['title' => $title]), self::SOURCE_NAME, $city);
    }

    /** Délai minimum entre deux notifications push pour une même catégorie (et ville) : évite
     *  qu'un même évènement couvert par plusieurs articles déclenche une rafale de pushs. */
    private const NOTIFY_COOLDOWN_HOURS = 12;

    private static function saveAlert(string $category, array $item, string $sourceName, ?string $city): void
    {
        $dedupeKey = md5(($item['guid'] ?: $item['link']) . '|' . ($city ?? ''));
        if (OfficialAlert::existsByDedupeKey($dedupeKey)) return;

        $shouldNotify = !OfficialAlert::wasNotifiedRecently($category, $city, self::NOTIFY_COOLDOWN_HOURS);

        $summary = mb_strimwidth(trim(strip_tags($item['description'])), 0, 300, '…');
        OfficialAlert::create(
            $category,
            mb_strimwidth($item['title'], 0, 500, '…'),
            $summary ?: null,
            $sourceName,
            $item['link'],
            $city,
            $dedupeKey,
            $item['pubDate']
        );

        if ($shouldNotify) {
            self::notifyConcernedFamilies($category, $item['title'], $city);
        }
    }

    /**
     * Envoie uniquement une notification push (pas d'entrée dans la cloche de notifications) :
     * la cloche sert aux notifications actionnables (tâches, courses, évènements...), les
     * alertes officielles ont leur propre bandeau + page dédiée "S'informer" par catégorie
     * (voir AlertController::category), qui fait double emploi avec la cloche si on y ajoute
     * aussi une entrée par article.
     */
    private static function notifyConcernedFamilies(string $category, string $title, ?string $city): void
    {
        $label = OfficialAlert::CATEGORIES[$category]['label'] ?? $category;
        $notifMsg = mb_strimwidth($title, 0, 150, '…');
        $link = BASE_URL . '/alertes/' . urlencode($category);

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
                Push::sendToUser((int)$row['id'], $label, $notifMsg, $link);
            }
        } catch (\Throwable $e) {
            error_log('OfficialAlertFeed notify error: ' . $e->getMessage());
        }
    }

    // ── Météo-France Vigilance (complément optionnel, nécessite une clé API — voir /admin) ──

    private const VIGILANCE_ENDPOINT = 'https://public-api.meteofrance.fr/public/DPVigilance/v1/cartevigilance/encours';

    /** Codes phénomène standards Météo-France, stables depuis la vigilance historique. */
    private const VIGILANCE_PHENOMENA = [
        1 => 'vent',
        2 => 'pluie-inondation',
        3 => 'orages',
        4 => 'crues',
        5 => 'neige-verglas',
        6 => 'canicule',
        7 => 'grand-froid',
        8 => 'avalanches',
        9 => 'vagues-submersion',
    ];

    /** Couleurs de vigilance : 1=vert, 2=jaune, 3=orange, 4=rouge. On n'alerte qu'à partir de l'orange. */
    private const VIGILANCE_ALERT_THRESHOLD = 3;

    private static function pollMeteoFranceVigilance(array $cities): void
    {
        $apiKey = AppSetting::get('meteofrance_api_key');
        if (!$apiKey || !$cities) return;

        $data = self::fetchVigilanceData($apiKey);
        if ($data === null) return;

        $byDepartment = self::extractDepartmentPhenomena($data);
        if (!$byDepartment) return;

        foreach ($cities as $city) {
            $dept = self::resolveDepartment($city);
            if (!$dept || !isset($byDepartment[$dept])) continue;

            foreach ($byDepartment[$dept] as $entry) {
                [$phenomenonId, $color] = $entry;
                if ($color < self::VIGILANCE_ALERT_THRESHOLD) continue;

                $category = match ($phenomenonId) {
                    6       => 'canicule',
                    2, 4    => 'inondation',
                    default => 'climatique',
                };

                self::saveVigilanceAlert($category, $dept, $city, $phenomenonId, $color);
            }
        }
    }

    private static function saveVigilanceAlert(string $category, string $dept, string $city, int $phenomenonId, int $color): void
    {
        $colorLabel      = $color >= 4 ? 'rouge' : 'orange';
        $phenomenonLabel = self::VIGILANCE_PHENOMENA[$phenomenonId] ?? ('phénomène ' . $phenomenonId);
        $title = 'Vigilance ' . $colorLabel . ' — ' . $phenomenonLabel . ' (département ' . $dept . ')';
        $sourceUrl = 'https://vigilance.meteofrance.fr/fr';

        // Une entrée par jour et par (département, phénomène, couleur) : la vigilance ne
        // change pas à chaque sondage, inutile de renotifier à chaque exécution du cron
        // tant que le niveau reste le même.
        $dedupeKey = md5('meteofrance|' . $dept . '|' . $phenomenonId . '|' . $color . '|' . date('Y-m-d'));
        if (OfficialAlert::existsByDedupeKey($dedupeKey)) return;

        OfficialAlert::create(
            $category,
            $title,
            'Vigilance météo officielle Météo-France pour votre département.',
            'Météo-France Vigilance',
            $sourceUrl,
            $city,
            $dedupeKey,
            date('Y-m-d H:i:s')
        );

        self::notifyConcernedFamilies($category, $title, $city);
    }

    /**
     * Interroge l'API Vigilance avec la clé fournie. Retourne le JSON décodé, ou null en cas
     * d'échec (clé invalide, réseau, etc.) — utilisé aussi bien par le sondage périodique que
     * par le bouton "Tester la clé" de /admin.
     */
    public static function fetchVigilanceData(string $apiKey): ?array
    {
        $ch = curl_init(self::VIGILANCE_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['apikey: ' . $apiKey, 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$body || $status < 200 || $status >= 300) return null;
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Analyse tolérante du JSON Vigilance : le schéma exact (bloc "détail par phénomène" vs.
     * simple couleur maximale par département) n'a pas pu être vérifié en conditions réelles
     * sans clé API valide au moment de l'écriture. On essaie d'abord de trouver un détail par
     * phénomène ; à défaut, on retombe sur la couleur maximale du département, reportée comme
     * phénomène générique "climatique" (impossible de distinguer canicule d'un autre risque
     * dans ce cas). Retourne [dept_code => [[phenomenon_id, color_id], ...]].
     */
    private static function extractDepartmentPhenomena(array $data): array
    {
        $result = [];
        $domains = self::findDomainList($data);
        foreach ($domains as $domain) {
            $dept = (string)($domain['domain_id'] ?? $domain['domainId'] ?? $domain['id'] ?? '');
            if ($dept === '') continue;

            $phenomena = $domain['phenomenon_items'] ?? $domain['phenomenons'] ?? $domain['phenomenon_max_color_ids'] ?? null;
            if (is_array($phenomena) && $phenomena) {
                foreach ($phenomena as $p) {
                    $pid = (int)($p['phenomenon_id'] ?? $p['phenomenonId'] ?? 0);
                    $color = (int)($p['phenomenon_max_color_id'] ?? $p['max_color_id'] ?? $p['color_id'] ?? 0);
                    if ($pid && $color) $result[$dept][] = [$pid, $color];
                }
                continue;
            }

            $maxColor = (int)($domain['max_color_id'] ?? $domain['color_id'] ?? 0);
            if ($maxColor) {
                // Pas de détail par phénomène disponible : on ne peut pas identifier la canicule
                // précisément, seulement remonter un niveau global — traité comme "climatique".
                $result[$dept][] = [0, $maxColor];
            }
        }
        return $result;
    }

    /** Cherche récursivement un tableau de "domaines" (départements) dans le JSON, quelle que soit sa profondeur. */
    private static function findDomainList(array $data): array
    {
        foreach (['domain_ids', 'domainIds', 'domains'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) return $data[$key];
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = self::findDomainList($value);
                if ($found) return $found;
            }
        }
        return [];
    }

    /** Résout une ville en code département via l'API officielle gouv.fr (gratuite, sans clé). Cache mémoire le temps du sondage. */
    private static function resolveDepartment(string $city): ?string
    {
        static $cache = [];
        if (array_key_exists($city, $cache)) return $cache[$city];

        $ch = curl_init('https://api-adresse.data.gouv.fr/search/?type=municipality&limit=1&q=' . urlencode($city));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $body = curl_exec($ch);
        curl_close($ch);

        $dept = null;
        $data = $body ? json_decode($body, true) : null;
        if (is_array($data) && !empty($data['features'][0]['properties']['depcode'])) {
            $dept = (string)$data['features'][0]['properties']['depcode'];
        }
        return $cache[$city] = $dept;
    }

    private static function getDistinctFamilyCities(): array
    {
        $rows = Database::fetchAll("SELECT DISTINCT weather_city FROM families WHERE weather_city IS NOT NULL AND weather_city <> ''");
        return array_column($rows, 'weather_city');
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

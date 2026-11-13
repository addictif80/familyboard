<?php
namespace App\Core;

use App\Models\Family;
use App\Models\FamilySubscription;

/**
 * Registre du guide d'utilisation (/aide) : chapitres de démarrage, chapitres transverses et une
 * page par module. Le contenu rédactionnel vit dans templates/help/content/<slug>.php (un
 * tableau PHP par chapitre) ; ce registre décide seulement de l'ordre, du regroupement (calqué
 * sur la barre de gauche de l'application) et de ce que chaque profil a le droit de voir.
 *
 * Dans les textes, {{app}} est remplacé par APP_NAME : un changement de nom de marque ne demande
 * donc aucune réécriture du guide.
 */
class HelpGuide
{
    /** Modules accessibles à un accès co-parent restreint (voir templates/layout.php). */
    public const COPARENT_MODULES = ['custody', 'comm_log', 'documents', 'calendar', 'albums', 'links', 'additions'];

    /** Chapitres qui ne sont pas des modules activables : slug => [titre, icône]. */
    private const EXTRA = [
        'demarrer'            => ['Bien démarrer', '🚀'],
        'profils'             => ['Rôles et profils', '👥'],
        'tableau-de-bord'     => ['Tableau de bord', '🏠'],
        'recherche-alertes'   => ['Recherche, alertes et notifications', '🔎'],
        'mon-compte'          => ['Mon compte et mes réglages', '👤'],
        'famille'             => ['Gérer la famille (administrateur)', '👨‍👩‍👧'],
        'stockage-abonnement' => ['Stockage et abonnement', '💳'],
        'vie-privee'          => ['Vie privée et sécurité', '🛡️'],
        'appli-mobile'        => ['Application mobile (PWA)', '📲'],
        'cloud'               => ['Cloud, mail et coffre-fort associés', '☁️'],
        'messages'            => ['Messages privés', '✉️'],
        'bilan-annuel'        => ['Bilan annuel', '🎞️'],
        'vault_passwords'     => ['Coffre-fort de mots de passe', '🔑'],
        'liens-publics'       => ['Liens partagés sans compte', '🔗'],
        'coparent-espace'     => ['Espace co-parent', '🔒'],
    ];

    /** Regroupement du sommaire (même découpage que la navigation de l'application). */
    public static function groups(): array
    {
        return [
            ['key' => 'start',    'title' => 'Pour commencer',          'icon' => '🚀', 'items' => ['demarrer', 'profils', 'tableau-de-bord', 'mon-compte', 'famille']],
            ['key' => 'daily',    'title' => 'Quotidien',               'icon' => '🗓️', 'items' => ['wall', 'messages', 'chat', 'calendar', 'tasks', 'contacts', 'meals', 'stock', 'links', 'polls', 'family-wall', 'kiosk', 'sitter', 'ai_assistant']],
            ['key' => 'memories', 'title' => 'Souvenirs & famille',     'icon' => '📸', 'items' => ['albums', 'bilan-annuel', 'wishlist', 'baby']],
            ['key' => 'custody',  'title' => 'Garde partagée',          'icon' => '👶', 'items' => ['custody', 'coparent-espace', 'comm_log']],
            ['key' => 'finance',  'title' => 'Finances',                'icon' => '💰', 'items' => ['budget', 'deals', 'additions']],
            ['key' => 'health',   'title' => 'Santé & sécurité',        'icon' => '🏥', 'items' => ['health', 'medication', 'emergency', 'location']],
            ['key' => 'legal',    'title' => 'Juridique & administratif', 'icon' => '⚖️', 'items' => ['documents', 'warranties', 'letters', 'disputes', 'admin_procedures', 'vault', 'vault_passwords', 'school', 'employment', 'nanny', 'leave']],
            ['key' => 'assets',   'title' => 'Biens & déplacements',    'icon' => '🏡', 'items' => ['vehicles', 'pets', 'meters', 'travels', 'projects']],
            ['key' => 'platform', 'title' => 'Aller plus loin',         'icon' => '🧭', 'items' => ['recherche-alertes', 'stockage-abonnement', 'cloud', 'liens-publics', 'vie-privee', 'appli-mobile']],
        ];
    }

    public static function meta(string $slug): array
    {
        if (isset(self::EXTRA[$slug])) {
            return ['title' => self::EXTRA[$slug][0], 'icon' => self::EXTRA[$slug][1]];
        }
        $m = Family::MODULES[$slug] ?? null;
        return ['title' => $m['label'] ?? ucfirst($slug), 'icon' => $m['icon'] ?? '📦'];
    }

    public static function isModule(string $slug): bool
    {
        return isset(Family::MODULES[$slug]);
    }

    /** Un slug de chapitre est-il visible pour cet utilisateur (rôle, modules désactivés) ? */
    public static function isVisible(string $slug, array $user, array $disabledModules): bool
    {
        $role = $user['role'] ?? 'member';
        if ($role === 'coparent') {
            return in_array($slug, array_merge(self::COPARENT_MODULES, ['coparent-espace', 'demarrer', 'profils', 'mon-compte', 'vie-privee', 'appli-mobile']), true);
        }
        if ($slug === 'coparent-espace') {
            return !in_array('custody', $disabledModules, true);
        }
        if (self::isModule($slug)) {
            if (in_array($slug, $disabledModules, true)) return false;
            if ($role === 'ado' && in_array($slug, Family::ADO_RESTRICTED_MODULES, true)) return false;
        }
        if ($slug === 'vault_passwords' && ($role === 'ado')) return false;
        if ($slug === 'bilan-annuel' || $slug === 'messages') return true;
        return true;
    }

    /** Groupes filtrés pour un utilisateur : seuls les chapitres existants et visibles restent. */
    public static function visibleGroups(array $user, array $disabledModules): array
    {
        $out = [];
        foreach (self::groups() as $g) {
            $items = [];
            foreach ($g['items'] as $slug) {
                if (!self::isVisible($slug, $user, $disabledModules)) continue;
                if (!self::exists($slug)) continue;
                $items[] = $slug;
            }
            if ($items) { $g['items'] = $items; $out[] = $g; }
        }
        return $out;
    }

    public static function exists(string $slug): bool
    {
        return (bool)preg_match('/^[a-z0-9_\-]+$/', $slug) && is_file(self::file($slug));
    }

    private static function file(string $slug): string
    {
        return BASE_PATH . '/templates/help/content/' . $slug . '.php';
    }

    /** Charge le chapitre pour un rôle : un accès co-parent peut avoir sa propre version. */
    public static function load(string $slug, string $role = 'member'): ?array
    {
        if (!self::exists($slug)) return null;
        $c = require self::file($slug);
        if (!is_array($c)) return null;
        if ($role === 'coparent' && isset($c['coparent']) && is_array($c['coparent'])) {
            $c = array_merge($c, $c['coparent']);
        }
        unset($c['coparent']);
        $c['sections'] = array_map(fn (array $s) => self::applyName($s), $c['sections'] ?? []);
        $c['summary']  = self::applyName(['html' => $c['summary'] ?? ''])['html'];
        return $c;
    }

    private static function applyName(array $s): array
    {
        foreach (['title', 'html'] as $k) {
            if (isset($s[$k])) $s[$k] = str_replace(['{{app}}', '{{app_url}}'], [APP_NAME, BASE_URL], $s[$k]);
        }
        return $s;
    }

    /** Un module est-il réservé à l'offre Premium (selon la configuration de la plateforme) ? */
    public static function isPremium(string $slug): bool
    {
        if (!self::isModule($slug)) return false;
        try {
            if (!FamilySubscription::billingEnabled()) return false;
            return !in_array($slug, FamilySubscription::freeModules(), true);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Recherche plein texte dans les chapitres visibles : retourne titre, extrait et lien. */
    public static function search(string $q, array $user, array $disabledModules, int $limit = 12): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $role = $user['role'] ?? 'member';
        $needle = mb_strtolower($q);
        $res = [];
        foreach (self::visibleGroups($user, $disabledModules) as $g) {
            foreach ($g['items'] as $slug) {
                $c = self::load($slug, $role);
                if (!$c) continue;
                $meta = self::meta($slug);
                $titleHit = mb_stripos($meta['title'], $q) !== false;
                foreach ($c['sections'] as $s) {
                    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</li>', '</p>', '<br>'], ' ', $s['html'] ?? '')))));
                    $hay = mb_strtolower(($s['title'] ?? '') . ' ' . $text);
                    $pos = mb_strpos($hay, $needle);
                    if ($pos === false && !$titleHit) continue;
                    $titleHit = false; // un seul résultat "titre" par chapitre
                    $tp = mb_strpos(mb_strtolower($text), $needle);
                    $start = max(0, ($tp === false ? 0 : $tp) - 40);
                    $snippet = mb_substr($text, $start, 150);
                    $res[] = [
                        'slug' => $slug, 'icon' => $meta['icon'], 'chapter' => $meta['title'],
                        'section' => $s['title'] ?? '', 'anchor' => $s['id'] ?? '', 'snippet' => ($start > 0 ? '…' : '') . $snippet . '…',
                        'rank' => ($pos === false ? 2 : (mb_stripos($s['title'] ?? '', $q) !== false ? 0 : 1)),
                    ];
                }
            }
        }
        usort($res, fn ($a, $b) => $a['rank'] <=> $b['rank']);
        return array_slice($res, 0, $limit);
    }
}

<?php
namespace App\Models;

use App\Core\Database;

class Family
{
    /** All disableable modules: slug => [label, icon] */
    public const MODULES = [
        'wall'        => ['label' => 'Mur familial',     'icon' => '📸'],
        'albums'      => ['label' => 'Albums photo',     'icon' => '🖼️'],
        'calendar'    => ['label' => 'Calendrier',        'icon' => '📅'],
        'custody'     => ['label' => 'Garde alternée',    'icon' => '👶'],
        'tasks'       => ['label' => 'Tâches & Courses',  'icon' => '✅'],
        'chat'        => ['label' => 'Chat familial',     'icon' => '💬'],
        'budget'      => ['label' => 'Budget',            'icon' => '💰'],
        'projects'    => ['label' => 'Projets',           'icon' => '📋'],
        'contacts'    => ['label' => 'Répertoire',        'icon' => '📒'],
        'warranties'  => ['label' => 'Garanties',         'icon' => '🛡️'],
        'documents'   => ['label' => 'Documents',         'icon' => '🗂️'],
        'family-wall' => ['label' => 'Écran mural',       'icon' => '📺'],
        'baby'        => ['label' => 'Bébé',               'icon' => '🍼'],
        'location'    => ['label' => 'Position',          'icon' => '📍'],
        'emergency'   => ['label' => 'Fiches urgence',    'icon' => '🚑'],
        'comm_log'    => ['label' => 'Journal parental',  'icon' => '📝'],
        'meals'       => ['label' => 'Repas',              'icon' => '🍽️'],
        'sitter'      => ['label' => 'Accès baby-sitter',  'icon' => '👶'],
        'kiosk'       => ['label' => 'Écran mural (kiosque)', 'icon' => '🖥️'],
        'wishlist'    => ['label' => 'Liste de cadeaux',   'icon' => '🎁'],
        'polls'       => ['label' => 'Sondages familiaux', 'icon' => '🗳️'],
        'links'       => ['label' => 'Portail de liens',   'icon' => '🔗'],
        'additions'   => ['label' => 'Additions',          'icon' => '🧾'],
        'letters'     => ['label' => 'Courriers',          'icon' => '✉️'],
        'disputes'    => ['label' => 'Dossiers de litige',  'icon' => '⚖️'],
        'school'      => ['label' => 'Suivi scolaire',      'icon' => '🎓'],
    ];

    /** Modules ayant une page de destination directe (donc utilisables dans la barre de
     *  navigation rapide) : exclut 'sitter' et 'kiosk', qui se gèrent depuis les réglages via
     *  des liens générés plutôt que d'être des pages qu'on visite au quotidien. */
    public const QUICK_NAV_ROUTES = [
        'wall' => '/wall', 'albums' => '/albums', 'calendar' => '/calendar', 'custody' => '/custody',
        'tasks' => '/tasks', 'chat' => '/chat', 'budget' => '/budget', 'projects' => '/projects',
        'contacts' => '/contacts', 'warranties' => '/warranties', 'documents' => '/documents',
        'family-wall' => '/family-wall', 'baby' => '/baby', 'location' => '/location',
        'emergency' => '/emergency', 'comm_log' => '/comm-log', 'meals' => '/meals',
        'wishlist' => '/wishlist', 'polls' => '/polls', 'links' => '/links',
        'additions' => '/additions', 'letters' => '/letters', 'disputes' => '/disputes',
        'school' => '/school',
    ];

    /** Sélection par défaut de la barre de navigation rapide (mobile/PWA), tant que
     *  l'utilisateur n'a pas choisi la sienne — un ordre de priorité fixe, filtré sur les
     *  modules réellement activés pour la famille, jamais recalculé depuis l'usage réel
     *  (une barre qui bouge toute seule casse la mémoire musculaire). */
    public static function defaultQuickNav(array $availableSlugs): array
    {
        $priority = ['calendar', 'tasks', 'wall', 'chat', 'custody', 'budget', 'contacts', 'documents', 'wishlist', 'albums'];
        return array_slice(array_values(array_intersect($priority, $availableSlugs)), 0, 3);
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM families WHERE id = ?', [$id]);
    }

    public static function findByInviteCode(string $code): ?array
    {
        return Database::fetch('SELECT * FROM families WHERE invite_code = ?', [$code]);
    }

    public static function create(string $name): int
    {
        $code = self::generateCode();
        $slug = self::generateMailAliasSlug($name);
        return Database::insert(
            'INSERT INTO families (name, invite_code, mail_alias_slug) VALUES (?, ?, ?)',
            [$name, $code, $slug]
        );
    }

    public static function update(int $id, string $name, array $settings = []): void
    {
        $schoolZone = isset($settings['school_zone']) ? (trim($settings['school_zone']) ?: null) : null;
        if ($schoolZone !== null && !in_array($schoolZone, ['A', 'B', 'C'], true)) $schoolZone = null;

        $allowedIntervals = [15, 30, 60, 120, 360, 720, 1440];
        $syncInterval = isset($settings['caldav_sync_interval']) ? (int)$settings['caldav_sync_interval'] : 0;
        $syncInterval = in_array($syncInterval, $allowedIntervals, true) ? $syncInterval : null;

        $darkModeType = $settings['dark_mode_type'] ?? 'off';
        if (!in_array($darkModeType, ['off', 'fixed', 'sunset'], true)) $darkModeType = 'off';
        $timePattern = '/^([01]\d|2[0-3]):[0-5]\d$/';
        $darkStart = ($darkModeType === 'fixed' && preg_match($timePattern, $settings['dark_mode_start'] ?? '')) ? $settings['dark_mode_start'] : null;
        $darkEnd   = ($darkModeType === 'fixed' && preg_match($timePattern, $settings['dark_mode_end'] ?? ''))   ? $settings['dark_mode_end']   : null;
        // Horaires fixes incomplets (un seul des deux renseigné) : la programmation ne peut pas
        // fonctionner, on retombe sur "désactivé" plutôt que de bloquer en sombre ou en clair.
        if ($darkModeType === 'fixed' && (!$darkStart || !$darkEnd)) $darkModeType = 'off';

        Database::execute(
            'UPDATE families SET name=?, timezone=COALESCE(?,timezone), weather_city=?, school_zone=?, caldav_sync_interval=?,
             dark_mode_type=?, dark_mode_start=?, dark_mode_end=?, sender_address=?, sender_postal_city=? WHERE id=?',
            [
                $name,
                $settings['timezone'] ?: null,
                isset($settings['weather_city']) ? (trim($settings['weather_city']) ?: null) : null,
                $schoolZone,
                $syncInterval,
                $darkModeType,
                $darkStart,
                $darkEnd,
                isset($settings['sender_address']) ? (trim($settings['sender_address']) ?: null) : null,
                isset($settings['sender_postal_city']) ? (trim($settings['sender_postal_city']) ?: null) : null,
                $id,
            ]
        );
    }

    /** Returns list of disabled module slugs for a family row (never throws). */
    public static function getDisabledModules(array $family): array
    {
        if (empty($family['disabled_modules'])) return [];
        $decoded = json_decode($family['disabled_modules'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function setDisabledModules(int $id, array $disabled): void
    {
        Database::execute(
            'UPDATE families SET disabled_modules = ? WHERE id = ?',
            [empty($disabled) ? null : json_encode(array_values($disabled)), $id]
        );
    }

    public static function getTimezone(int $id): string
    {
        $row = Database::fetch('SELECT timezone FROM families WHERE id = ?', [$id]);
        return $row['timezone'] ?? 'Europe/Paris';
    }

    public static function regenerateCode(int $id): string
    {
        $code = self::generateCode();
        Database::execute('UPDATE families SET invite_code = ? WHERE id = ?', [$code, $id]);
        return $code;
    }

    private static function generateCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $existing = Database::fetch('SELECT id FROM families WHERE invite_code = ?', [$code]);
        } while ($existing);
        return $code;
    }

    /** Attribue un slug à une famille créée avant l'ajout de cette fonctionnalité (auto-guérison,
     *  même principe que KioskLink::ensureShortCode()). */
    public static function ensureMailAliasSlug(int $id): ?string
    {
        $family = self::findById($id);
        if (!$family) return null;
        if (!empty($family['mail_alias_slug'])) return $family['mail_alias_slug'];
        $slug = self::generateMailAliasSlug($family['name']);
        Database::execute('UPDATE families SET mail_alias_slug=? WHERE id=? AND mail_alias_slug IS NULL', [$slug, $id]);
        $row = Database::fetch('SELECT mail_alias_slug FROM families WHERE id=?', [$id]);
        return $row['mail_alias_slug'] ?? $slug;
    }

    /**
     * Slug stable pour l'alias e-mail famille (<slug>@<domaine configuré>, voir App\Core\Mailcow)
     * — dérivé du nom au moment de la création puis jamais recalculé, y compris si la famille
     * est renommée ensuite (cf. update() ci-dessus), pour ne jamais casser un alias déjà
     * communiqué à des tiers. Un suffixe numérique désambiguïse les noms de famille identiques.
     */
    private static function generateMailAliasSlug(string $name): string
    {
        $base = strtolower((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = trim((string)$base, '-');
        if ($base === '') $base = 'famille';
        $base = substr($base, 0, 60);

        $slug = $base;
        $i = 2;
        while (Database::fetch('SELECT id FROM families WHERE mail_alias_slug = ?', [$slug])) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }
}

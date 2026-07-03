<?php
namespace App\Models;

use App\Core\Database;

class Family
{
    /** All disableable modules: slug => [label, icon] */
    public const MODULES = [
        'wall'        => ['label' => 'Mur familial',     'icon' => '📸'],
        'calendar'    => ['label' => 'Calendrier',        'icon' => '📅'],
        'custody'     => ['label' => 'Garde alternée',    'icon' => '👶'],
        'tasks'       => ['label' => 'Tâches & Courses',  'icon' => '✅'],
        'chat'        => ['label' => 'Chat familial',     'icon' => '💬'],
        'budget'      => ['label' => 'Budget',            'icon' => '💰'],
        'projects'    => ['label' => 'Projets',           'icon' => '📋'],
        'contacts'    => ['label' => 'Répertoire',        'icon' => '📒'],
        'warranties'  => ['label' => 'Garanties',         'icon' => '🛡️'],
        'documents'   => ['label' => 'Documents',         'icon' => '🗂️'],
        'cameras'     => ['label' => 'Caméras',           'icon' => '🎥'],
        'family-wall' => ['label' => 'Écran mural',       'icon' => '📺'],
        'baby'        => ['label' => 'Bébé',               'icon' => '🍼'],
        'location'    => ['label' => 'Position',          'icon' => '📍'],
    ];

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
        return Database::insert(
            'INSERT INTO families (name, invite_code) VALUES (?, ?)',
            [$name, $code]
        );
    }

    public static function update(int $id, string $name, array $settings = []): void
    {
        $schoolZone = isset($settings['school_zone']) ? (trim($settings['school_zone']) ?: null) : null;
        if ($schoolZone !== null && !in_array($schoolZone, ['A', 'B', 'C'], true)) $schoolZone = null;

        $allowedIntervals = [15, 30, 60, 120, 360, 720, 1440];
        $syncInterval = isset($settings['caldav_sync_interval']) ? (int)$settings['caldav_sync_interval'] : 0;
        $syncInterval = in_array($syncInterval, $allowedIntervals, true) ? $syncInterval : null;

        Database::execute(
            'UPDATE families SET name=?, timezone=COALESCE(?,timezone), weather_city=?, go2rtc_url=?, school_zone=?, caldav_sync_interval=? WHERE id=?',
            [
                $name,
                $settings['timezone'] ?: null,
                isset($settings['weather_city']) ? (trim($settings['weather_city']) ?: null) : null,
                isset($settings['go2rtc_url'])   ? (trim($settings['go2rtc_url'])   ?: null) : null,
                $schoolZone,
                $syncInterval,
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
}

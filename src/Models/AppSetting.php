<?php
namespace App\Models;

use App\Core\Database;

class AppSetting
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $row = Database::fetch('SELECT `value` FROM app_settings WHERE `key`=?', [$key]);
        return $row ? $row['value'] : $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::execute(
            'INSERT INTO app_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)',
            [$key, $value]
        );
    }

    public static function getVapidKeys(): ?array
    {
        $pub  = self::get('vapid_public');
        $priv = self::get('vapid_private');
        if (!$pub || !$priv) return null;
        return ['public' => $pub, 'private' => $priv];
    }
}

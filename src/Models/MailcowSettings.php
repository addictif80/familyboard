<?php
namespace App\Models;

/**
 * Configuration globale de l'instance Mailcow auto-hébergée utilisée pour créer un alias e-mail
 * par famille (<slug>@<domaine>) redirigeant vers les adresses de ses membres — voir
 * App\Core\Mailcow. Renseignée une seule fois par l'administrateur système (panneau /admin),
 * jamais par un membre de famille.
 */
class MailcowSettings
{
    public static function get(): ?array
    {
        $url = AppSetting::get('mailcow_url');
        $apiKey = AppSetting::get('mailcow_api_key');
        $domain = AppSetting::get('mailcow_domain');
        if (!$url || !$apiKey || !$domain) return null;

        return [
            'url'     => rtrim($url, '/'),
            'api_key' => $apiKey,
            'domain'  => strtolower(trim($domain)),
        ];
    }

    public static function save(string $url, string $apiKey, string $domain): void
    {
        AppSetting::set('mailcow_url', rtrim(trim($url), '/'));
        AppSetting::set('mailcow_api_key', trim($apiKey));
        AppSetting::set('mailcow_domain', strtolower(trim($domain)));
    }
}

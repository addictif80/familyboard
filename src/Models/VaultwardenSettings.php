<?php
namespace App\Models;

/**
 * Configuration globale de l'instance Vaultwarden auto-hébergée (coffre-fort de mots de passe
 * proposé aux membres non co-parent — voir App\Core\Vaultwarden) : URL publique + jeton
 * d'administration, renseignés une seule fois par l'administrateur système (panneau /admin),
 * jamais par un membre de famille.
 */
class VaultwardenSettings
{
    public static function get(): ?array
    {
        $url = AppSetting::get('vaultwarden_url');
        $token = AppSetting::get('vaultwarden_admin_token');
        if (!$url || !$token) return null;

        return [
            'url'   => rtrim($url, '/'),
            'token' => $token,
        ];
    }

    public static function save(string $url, string $token): void
    {
        AppSetting::set('vaultwarden_url', rtrim(trim($url), '/'));
        AppSetting::set('vaultwarden_admin_token', trim($token));
    }
}

<?php
namespace App\Models;

use App\Core\Crypto;

/**
 * Configuration globale de l'intégration Cozy Cloud (import automatique de factures dans
 * Documents) — renseignée une seule fois par l'administrateur système. Le jeton du relais est
 * chiffré via App\Core\Crypto (même exigence que les mots de passe LDAP/Synology, voir
 * StorageSettings) : il permet de créer/supprimer des instances Cozy, une capacité sensible.
 */
class CozySettings
{
    public static function get(): ?array
    {
        $domain = AppSetting::get('cozy_stack_domain');
        $wrapperUrl = AppSetting::get('cozy_wrapper_url');
        $encryptedToken = AppSetting::get('cozy_wrapper_token');
        if (!$domain || !$wrapperUrl || !$encryptedToken) return null;

        $token = Crypto::decrypt($encryptedToken);
        if ($token === null) return null;

        return [
            'domain'        => strtolower(trim($domain)),
            'wrapper_url'   => rtrim($wrapperUrl, '/'),
            'wrapper_token' => $token,
        ];
    }

    public static function save(string $domain, string $wrapperUrl, string $wrapperToken): void
    {
        AppSetting::set('cozy_stack_domain', strtolower(trim($domain)));
        AppSetting::set('cozy_wrapper_url', rtrim(trim($wrapperUrl), '/'));
        AppSetting::set('cozy_wrapper_token', Crypto::encrypt(trim($wrapperToken)));
    }
}

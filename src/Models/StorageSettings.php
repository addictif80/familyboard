<?php
namespace App\Models;

use App\Core\Crypto;

/**
 * Configuration globale LDAP/Synology pour le provisioning du stockage perso (Phase 4) —
 * renseignée une seule fois par l'administrateur système, jamais par un membre de famille.
 * Contrairement à MailcowSettings/VaultwardenSettings (clés stockées en clair en base), les
 * mots de passe LDAP/Synology sont chiffrés via App\Core\Crypto avant écriture : exigence
 * explicite de la mission stockage ("jamais en clair en base ni dans les logs").
 */
class StorageSettings
{
    public static function get(): ?array
    {
        $host = AppSetting::get('storage_ldap_host');
        $bindDn = AppSetting::get('storage_ldap_bind_dn');
        $baseDn = AppSetting::get('storage_ldap_base_dn');
        if (!$host || !$bindDn || !$baseDn) return null;

        $encPassword = AppSetting::get('storage_ldap_bind_password');

        return [
            'ldap_host'          => $host,
            'ldap_bind_dn'       => $bindDn,
            'ldap_bind_password' => $encPassword ? self::decryptOrEmpty($encPassword) : '',
            'ldap_base_dn'       => $baseDn,
            'synology_url'            => AppSetting::get('storage_synology_url') ?? '',
            'synology_username'       => AppSetting::get('storage_synology_username') ?? '',
            'synology_password'       => self::decryptOrEmpty(AppSetting::get('storage_synology_password') ?? ''),
            'synology_shared_folder'  => AppSetting::get('storage_synology_shared_folder') ?? '',
        ];
    }

    private static function decryptOrEmpty(string $value): string
    {
        if ($value === '') return '';
        try {
            return Crypto::decrypt($value);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function save(array $data): void
    {
        AppSetting::set('storage_ldap_host', trim($data['ldap_host'] ?? ''));
        AppSetting::set('storage_ldap_bind_dn', trim($data['ldap_bind_dn'] ?? ''));
        AppSetting::set('storage_ldap_base_dn', trim($data['ldap_base_dn'] ?? ''));
        if (!empty($data['ldap_bind_password'])) {
            AppSetting::set('storage_ldap_bind_password', Crypto::encrypt($data['ldap_bind_password']));
        }
        AppSetting::set('storage_synology_url', rtrim(trim($data['synology_url'] ?? ''), '/'));
        AppSetting::set('storage_synology_username', trim($data['synology_username'] ?? ''));
        if (!empty($data['synology_password'])) {
            AppSetting::set('storage_synology_password', Crypto::encrypt($data['synology_password']));
        }
        AppSetting::set('storage_synology_shared_folder', trim($data['synology_shared_folder'] ?? ''));
    }
}

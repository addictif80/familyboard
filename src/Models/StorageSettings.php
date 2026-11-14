<?php
namespace App\Models;

use App\Core\Crypto;

/**
 * Configuration globale LDAP/Synology/Nextcloud pour le provisioning du stockage perso —
 * renseignée une seule fois par l'administrateur système, jamais par un membre de famille.
 * Contrairement à VaultwardenSettings (clés stockées en clair en base), les
 * mots de passe sont chiffrés via App\Core\Crypto avant écriture : exigence explicite de la
 * mission stockage ("jamais en clair en base ni dans les logs").
 *
 * L'identité (compte LDAP) et le quota (Synology et/ou Nextcloud) sont des préoccupations
 * indépendantes : get() retourne toujours un tableau (jamais null), chaque appelant (LdapService,
 * SynologyService, NextcloudService) ne regarde que les champs qui le concernent, pour pouvoir
 * tester/configurer l'un sans que l'autre soit déjà renseigné.
 */
class StorageSettings
{
    public static function get(): array
    {
        $encLdapPassword = AppSetting::get('storage_ldap_bind_password') ?? '';
        $encSynologyPassword = AppSetting::get('storage_synology_password') ?? '';
        $encNextcloudPassword = AppSetting::get('storage_nextcloud_admin_password') ?? '';

        return [
            'ldap_host'          => AppSetting::get('storage_ldap_host') ?? '',
            'ldap_bind_dn'       => AppSetting::get('storage_ldap_bind_dn') ?? '',
            'ldap_bind_password' => $encLdapPassword ? self::decryptOrEmpty($encLdapPassword) : '',
            'ldap_base_dn'       => AppSetting::get('storage_ldap_base_dn') ?? '',
            'synology_url'            => AppSetting::get('storage_synology_url') ?? '',
            'synology_username'       => AppSetting::get('storage_synology_username') ?? '',
            'synology_password'       => $encSynologyPassword ? self::decryptOrEmpty($encSynologyPassword) : '',
            'synology_shared_folder'  => AppSetting::get('storage_synology_shared_folder') ?? '',
            'nextcloud_url'           => AppSetting::get('storage_nextcloud_url') ?? '',
            'nextcloud_admin_user'    => AppSetting::get('storage_nextcloud_admin_user') ?? '',
            'nextcloud_admin_password' => $encNextcloudPassword ? self::decryptOrEmpty($encNextcloudPassword) : '',
        ];
    }

    private static function decryptOrEmpty(string $value): string
    {
        if ($value === '') return '';
        try {
            return Crypto::decrypt($value) ?? '';
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
        AppSetting::set('storage_nextcloud_url', rtrim(trim($data['nextcloud_url'] ?? ''), '/'));
        AppSetting::set('storage_nextcloud_admin_user', trim($data['nextcloud_admin_user'] ?? ''));
        if (!empty($data['nextcloud_admin_password'])) {
            AppSetting::set('storage_nextcloud_admin_password', Crypto::encrypt($data['nextcloud_admin_password']));
        }
    }
}

<?php
namespace App\Core;

use App\Models\AppSetting;

/**
 * Résout l'état du provisioning de stockage :
 * - ldapEnabled() : les comptes dépendent toujours du Serveur LDAP Synology (storage_ldap_enabled).
 * - activeQuotaBackends() : 0, 1 ou 2 backends de quota actifs simultanément, au choix de
 *   l'admin (storage_quota_backends, CSV parmi "synology","nextcloud").
 */
class StorageProvisionerFactory
{
    private const AVAILABLE = ['synology' => SynologyQuotaBackend::class, 'nextcloud' => NextcloudQuotaBackend::class];

    public static function ldapEnabled(): bool
    {
        return AppSetting::get('storage_ldap_enabled', '0') === '1';
    }

    /** @return StorageQuotaBackend[] */
    public static function activeQuotaBackends(): array
    {
        $slugs = array_filter(array_map('trim', explode(',', AppSetting::get('storage_quota_backends', '') ?: '')));
        $backends = [];
        foreach ($slugs as $slug) {
            if (isset(self::AVAILABLE[$slug])) {
                $class = self::AVAILABLE[$slug];
                $backends[] = new $class();
            }
        }
        return $backends;
    }

    public static function summaryLabel(): string
    {
        $parts = [];
        $parts[] = 'Comptes LDAP : ' . (self::ldapEnabled() ? 'activés' : 'désactivés');
        $backends = array_map(fn($b) => $b->slug(), self::activeQuotaBackends());
        $parts[] = 'Quota : ' . ($backends ? implode(' + ', $backends) : 'aucun backend');
        return implode(' — ', $parts);
    }
}

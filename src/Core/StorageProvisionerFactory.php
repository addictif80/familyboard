<?php
namespace App\Core;

use App\Models\AppSetting;

/** Résout le backend de provisioning actif selon le réglage admin
 *  (storage_provisioner_backend, "none" par défaut) — voir StorageProvisioner. */
class StorageProvisionerFactory
{
    public static function active(): ?StorageProvisioner
    {
        return match (AppSetting::get('storage_provisioner_backend', 'none')) {
            'synology' => new SynologyProvisioner(),
            default    => null,
        };
    }

    public static function backendLabel(): string
    {
        return match (AppSetting::get('storage_provisioner_backend', 'none')) {
            'synology' => 'Synology (LDAP + quota DSM)',
            default    => 'Aucun (comptabilité applicative seulement)',
        };
    }
}

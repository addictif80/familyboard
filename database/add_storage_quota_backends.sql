-- Découple l'identité (toujours Synology LDAP Server, voir App\Core\LdapService) des backends
-- de quota de stockage : l'admin peut activer Synology et/ou Nextcloud pour le push de quota
-- (storage_quota_backends, CSV), indépendamment de storage_ldap_enabled (comptes). Remplace
-- storage_provisioner_backend (none|synology), devenu trop restrictif.

INSERT INTO app_settings (`key`, `value`) VALUES
    ('storage_ldap_enabled', '0'),
    ('storage_quota_backends', ''),
    ('storage_nextcloud_url', ''),
    ('storage_nextcloud_admin_user', ''),
    ('storage_nextcloud_admin_password', '')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- Reprend la valeur de l'ancien réglage avant de le retirer : qui avait déjà choisi "synology"
-- garde ses comptes LDAP actifs ET son quota poussé vers Synology, sans action supplémentaire.
UPDATE app_settings SET value = '1' WHERE `key` = 'storage_ldap_enabled'
    AND (SELECT value FROM app_settings WHERE `key` = 'storage_provisioner_backend') = 'synology';
UPDATE app_settings SET value = 'synology' WHERE `key` = 'storage_quota_backends'
    AND (SELECT value FROM app_settings WHERE `key` = 'storage_provisioner_backend') = 'synology';

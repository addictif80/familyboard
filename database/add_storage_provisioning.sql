-- Provisioning LDAP/Synology du stockage perso (voir App\Core\LdapService, SynologyService,
-- StorageProvisioner) — compte posixAccount par membre dans le groupe posixGroup configuré
-- (défaut "familyboard"), quota Synology par utilisateur sur le dossier partagé configuré
-- (voir add_storage_billing.sql pour les identifiants chiffrés StorageSettings).
--
-- storage_provisioner_backend contrôle l'activation réelle : tant qu'il vaut "none" (défaut),
-- aucun appel LDAP/Synology n'est effectué — comme pour storage_billing_enabled, l'ajout de ce
-- backend ne doit jamais se déclencher rétroactivement sans action explicite de l'admin.

INSERT INTO app_settings (`key`, `value`) VALUES
    ('storage_provisioner_backend', 'none'),
    ('storage_ldap_group', 'familyboard'),
    ('storage_ldap_next_uid', '10000')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- La détection d'un compte/groupe LDAP déjà existant se fait désormais par une recherche dans
-- tout l'arbre (voir App\Core\LdapService), jamais par un DN deviné — mais CRÉER une entrée
-- toute neuve a besoin d'un conteneur cible explicite. Ces deux réglages sont optionnels
-- (vides = utilise le DN de base tel quel, comportement d'avant cette migration) ; à renseigner
-- si le serveur LDAP exige un conteneur dédié (ex. "cn=users,<DN de base>" chez Synology).
INSERT INTO app_settings (`key`, `value`) VALUES
    ('storage_ldap_people_dn', ''),
    ('storage_ldap_groups_dn', '')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- Quota illimité, perso et/ou familial — réservé à l'administrateur système. Par défaut (0),
-- rien ne change pour personne (pas de verrouillage ni déverrouillage rétroactif).
-- Perso : propagé aux backends externes (Synology/Nextcloud) comme "pas de limite" plutôt
-- qu'un chiffre. Familial : contourne entièrement FileStore::enforceQuota().
ALTER TABLE users ADD COLUMN IF NOT EXISTS storage_quota_unlimited TINYINT(1) NOT NULL DEFAULT 0;

INSERT INTO app_settings (`key`, `value`) VALUES
    ('storage_family_quota_unlimited', '0')
ON DUPLICATE KEY UPDATE `key` = `key`;

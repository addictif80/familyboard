-- Registre unique de tous les fichiers stockés par les familles (hors avatars/CMS admin,
-- voir storage_quota_excluded_modules) — point d'entrée App\Core\FileStore. Remplace à terme
-- les décomptes ad hoc par table (AccountDeletion::OWNED_FILE_TABLES) par une source unique de
-- vérité, utilisée pour le quota familial partagé, l'indicateur de consommation par module et
-- la purge RGPD. Les fichiers déjà sur disque avant cette migration sont réenregistrés par le
-- script de rattrapage (voir scripts/backfill_stored_files.php), jamais par un trigger
-- automatique qui risquerait de bloquer une famille déjà au-delà du quota (grandfathering).

CREATE TABLE IF NOT EXISTS stored_files (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT NOT NULL,
    user_id       INT NULL DEFAULT NULL,
    module        VARCHAR(30)  NOT NULL,
    path          VARCHAR(255) NOT NULL UNIQUE, -- relatif à BASE_PATH, ex. /storage/documents/12/abcd.pdf
    original_name VARCHAR(255) NULL DEFAULT NULL,
    mime          VARCHAR(100) NULL DEFAULT NULL,
    size_bytes    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_stored_files_family_module (family_id, module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modules exclus du quota familial (avatars/cosmétique par défaut) — liste CSV de slugs de
-- module, modifiable depuis le panneau admin (onglet Stockage perso → Quota familial).
INSERT INTO app_settings (`key`, `value`) VALUES
    ('storage_quota_excluded_modules', 'avatar')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- Prépare un futur add-on de stockage familial payant (distinct des add-ons perso par membre,
-- voir add_storage_billing.sql) sans construire le flux de facturation correspondant ici :
-- 'member' = add-on perso existant, 'family' = augmente storage_family_quota_gb.
ALTER TABLE storage_products ADD COLUMN IF NOT EXISTS scope ENUM('member','family') NOT NULL DEFAULT 'member' AFTER quota_gb;

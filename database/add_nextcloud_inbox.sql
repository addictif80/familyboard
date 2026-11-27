-- Détection des fichiers déposés directement dans Nextcloud (hors FamilyBoard), dans le
-- sous-dossier dédié "a-rattacher" de l'espace familial — voir App\Core\NextcloudInboxSync.
CREATE TABLE IF NOT EXISTS nextcloud_inbox_files (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    family_id         INT NOT NULL,
    remote_path       VARCHAR(500) NOT NULL,
    original_name     VARCHAR(255) NOT NULL,
    nextcloud_file_id VARCHAR(50)  NULL DEFAULT NULL,
    nextcloud_etag    VARCHAR(100) NULL DEFAULT NULL,
    size_bytes        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    added_by_user_id  INT NULL DEFAULT NULL,
    status            ENUM('pending','attached','dismissed') NOT NULL DEFAULT 'pending',
    target_module     VARCHAR(30) NULL DEFAULT NULL,
    target_record_id  INT NULL DEFAULT NULL,
    discovered_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at       DATETIME NULL DEFAULT NULL,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_family_path (family_id, remote_path(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

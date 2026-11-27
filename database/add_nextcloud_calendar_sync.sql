-- Synchronisation à sens unique FamilyBoard → Nextcloud (calendrier), par membre : un calendrier
-- "FamilyBoard" possédé par le compte technique Nextcloud (celui déjà configuré pour les
-- quotas, voir StorageSettings), partagé en LECTURE SEULE à chaque membre. Jamais l'inverse —
-- aucune écriture depuis Nextcloud ne doit revenir dans FamilyBoard.
--
-- nextcloud_calendar_items : état de réconciliation (quel item a été poussé pour quel membre,
-- avec quel contenu) — permet de détecter les changements/suppressions à chaque passage,
-- sans dépendre d'un hook par écriture (les occurrences récurrentes de garde alternée n'ont pas
-- de ligne DB stable, seulement un identifiant déterministe, voir App\Core\NextcloudCalendarSync).
INSERT INTO app_settings (`key`, `value`) VALUES
    ('nextcloud_calendar_sync_enabled', '0'),
    ('nextcloud_calendar_sync_last_run', '')
ON DUPLICATE KEY UPDATE `key` = `key`;

CREATE TABLE IF NOT EXISTS nextcloud_calendar_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    item_uid     VARCHAR(190) NOT NULL,
    content_hash CHAR(64) NOT NULL,
    synced_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_item (user_id, item_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

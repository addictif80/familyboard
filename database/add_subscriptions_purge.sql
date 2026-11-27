-- Rétention puis suppression définitive des données des modules premium après un impayé/essai
-- non converti, et suivi des relances email (voir PremiumDataPurge, cron.php).
ALTER TABLE family_subscriptions ADD COLUMN grace_started_at DATETIME NULL DEFAULT NULL;
ALTER TABLE family_subscriptions ADD COLUMN data_purged_at DATETIME NULL DEFAULT NULL;
ALTER TABLE family_subscriptions ADD COLUMN reminder_downgrade_sent_at DATETIME NULL DEFAULT NULL;
ALTER TABLE family_subscriptions ADD COLUMN reminder_midpoint_sent_at DATETIME NULL DEFAULT NULL;
ALTER TABLE family_subscriptions ADD COLUMN reminder_final_sent_at DATETIME NULL DEFAULT NULL;

-- Instantané HTML des données premium juste avant leur suppression définitive, consultable par
-- l'admin système en cas de réclamation (même principe que deleted_users.data_export_html).
CREATE TABLE IF NOT EXISTS premium_data_purges (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    family_id      INT NOT NULL,
    family_name    VARCHAR(100) NOT NULL,
    modules_purged TEXT NOT NULL,
    export_html    LONGTEXT NULL,
    purged_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

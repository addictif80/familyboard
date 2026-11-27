-- Journal d'activité de la garde partagée : dès qu'un co-parent invité se
-- connecte pour la première fois (nouveau compte OU compte existant relié à
-- un nouveau planning via une invitation), la journalisation de toutes les
-- actions liées à ce planning — côté parent utilisateur ET côté co-parent —
-- est activée automatiquement. Journal immuable (aucune modification/suppression).

ALTER TABLE custody_access
  ADD COLUMN IF NOT EXISTS first_login_at DATETIME NULL DEFAULT NULL;

-- Pas de FOREIGN KEY sur schedule_id (volontaire) : le journal doit survivre à
-- la suppression du planning lui-même, pour qu'une des deux parties ne puisse
-- pas effacer son historique en supprimant le planning de garde.
CREATE TABLE IF NOT EXISTS custody_activity_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id   INT          NOT NULL,
    family_id     INT          NOT NULL,
    child_name    VARCHAR(100) NULL DEFAULT NULL,
    user_id       INT          NULL DEFAULT NULL,
    action        VARCHAR(60)  NOT NULL,
    details       VARCHAR(500) NULL DEFAULT NULL,
    ip            VARCHAR(45)  NULL DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_schedule (schedule_id, created_at),
    INDEX idx_family (family_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

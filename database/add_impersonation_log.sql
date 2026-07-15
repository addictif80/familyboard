-- Journal d'audit de l'impersonation (admin système se connectant temporairement
-- en tant qu'un membre de famille, pour du support).

CREATE TABLE IF NOT EXISTS impersonation_log (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    target_user_id    INT          NOT NULL,
    target_family_id  INT          NOT NULL,
    admin_username    VARCHAR(150) NOT NULL,
    ip                VARCHAR(45)  NULL DEFAULT NULL,
    started_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    ended_at          DATETIME     NULL DEFAULT NULL,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_target (target_user_id),
    INDEX idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

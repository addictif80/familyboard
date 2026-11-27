-- Partage ponctuel et public (lien magique, sans compte) du carnet de santé d'UN sujet (enfant ou
-- membre) — pour le transmettre à un médecin avant un rendez-vous. Contrairement aux autres liens
-- publics de l'app (coffre-fort, listes...), celui-ci expire TOUJOURS (expires_at non nul) : des
-- données médicales ne doivent pas rester accessibles indéfiniment par un lien qui aurait fuité.
CREATE TABLE IF NOT EXISTS health_share_links (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    family_id    INT          NOT NULL,
    subject_type ENUM('user','child') NOT NULL,
    subject_id   INT          NOT NULL,
    token        VARCHAR(64)  NOT NULL UNIQUE,
    created_by   INT          NULL DEFAULT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    expires_at   DATETIME     NOT NULL,
    revoked_at   DATETIME     NULL DEFAULT NULL,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_family_subject (family_id, subject_type, subject_id),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

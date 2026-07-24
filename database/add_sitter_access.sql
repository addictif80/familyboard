-- Accès temporaire limité (mode baby-sitter) : un lien à durée de vie
-- limitée donnant une vue lecture seule du planning du jour, des tâches
-- ouvertes et des fiches urgence — sans connexion ni accès au reste
-- des données familiales.
CREATE TABLE IF NOT EXISTS sitter_links (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT          NOT NULL,
    label      VARCHAR(150) NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    created_by INT          NOT NULL,
    expires_at DATETIME     NOT NULL,
    revoked_at DATETIME     NULL DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token),
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

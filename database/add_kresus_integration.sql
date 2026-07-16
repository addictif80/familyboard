-- Connexion bancaire via Kresus (auto-hébergé, une instance par membre) et
-- alternative "saisie assistée" pour les membres qui ne veulent pas connecter
-- leur banque. Voir deploy/kresus/README.md pour le déploiement.

CREATE TABLE IF NOT EXISTS kresus_accounts (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    family_id        INT          NOT NULL,
    user_id          INT          NOT NULL,
    kresus_url       VARCHAR(255) NOT NULL COMMENT 'URL de l’instance Kresus du membre',
    kresus_username  VARCHAR(255) NULL DEFAULT NULL COMMENT 'Identifiant Basic Auth du reverse-proxy',
    kresus_password  VARCHAR(255) NULL DEFAULT NULL COMMENT 'Mot de passe Basic Auth du reverse-proxy',
    last_sync_at     DATETIME     NULL DEFAULT NULL,
    last_sync_status ENUM('ok','error') NULL DEFAULT NULL,
    last_sync_error  VARCHAR(500) NULL DEFAULT NULL,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user (user_id),
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE budget_transactions ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'manual | kresus';
ALTER TABLE budget_transactions ADD COLUMN IF NOT EXISTS external_id VARCHAR(120) NULL DEFAULT NULL COMMENT 'Identifiant de la transaction côté Kresus, pour éviter les doublons';
ALTER TABLE budget_transactions ADD CONSTRAINT uk_budget_external UNIQUE (family_id, external_id);

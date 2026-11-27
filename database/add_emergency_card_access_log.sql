-- Journal des consultations publiques d'une fiche d'urgence (RGPD : transparence sur qui a
-- consulté des données de santé exposées sans authentification via lien public).
CREATE TABLE IF NOT EXISTS emergency_card_access_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    card_id     INT          NOT NULL,
    ip          VARCHAR(45)  NULL DEFAULT NULL,
    accessed_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (card_id) REFERENCES emergency_cards(id) ON DELETE CASCADE,
    INDEX idx_card (card_id, accessed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Module "Bons plans" : annuaire privé à chaque famille (pas de partage entre familles,
-- contrairement au Portail de liens) de bonnes affaires — code promo, réduction, lien vers
-- l'offre, catégorie, date de validité optionnelle.
CREATE TABLE IF NOT EXISTS deals (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    family_id    INT NOT NULL,
    title        VARCHAR(150) NOT NULL,
    description  TEXT NULL DEFAULT NULL,
    url          VARCHAR(500) NULL DEFAULT NULL,
    promo_code   VARCHAR(100) NULL DEFAULT NULL,
    discount     VARCHAR(100) NULL DEFAULT NULL,
    category     VARCHAR(30) NOT NULL DEFAULT 'autre',
    expires_at   DATE NULL DEFAULT NULL,
    created_by   INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

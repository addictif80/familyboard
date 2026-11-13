-- Module "Cartes de fidélité" : cartes de magasins/enseignes dématérialisées, affichées sous
-- forme de code-barres/QR scannable en caisse (saisie manuelle ou scan caméra côté client).
CREATE TABLE IF NOT EXISTS loyalty_cards (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    family_id      INT NOT NULL,
    store_name     VARCHAR(100) NOT NULL,
    card_color     VARCHAR(7)  NOT NULL DEFAULT '#7C5CFF',
    barcode_value  VARCHAR(100) NOT NULL,
    barcode_format ENUM('ean13','code128','qrcode') NOT NULL DEFAULT 'code128',
    notes          VARCHAR(255) NULL DEFAULT NULL,
    created_by     INT NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

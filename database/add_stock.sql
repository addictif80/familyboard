-- Stock frigo/congélateur : suivi des quantités et des dates de péremption, avec des rappels à
-- J-3 et le jour même (voir cron.php::sendStockExpiryReminders()) — des seuils bien plus courts
-- que les garanties/documents (J-30/J-7, voir sendExpiryReminders()) car un produit alimentaire
-- se périme en jours, pas en mois.
CREATE TABLE IF NOT EXISTS stock_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT          NOT NULL,
    user_id     INT          NOT NULL,
    name        VARCHAR(150) NOT NULL,
    location    ENUM('fridge','freezer') NOT NULL DEFAULT 'fridge',
    category    VARCHAR(100) NULL DEFAULT NULL,
    quantity    INT          NOT NULL DEFAULT 1,
    unit        VARCHAR(30)  NULL DEFAULT NULL,
    expiry_date DATE         NULL DEFAULT NULL,
    notes       TEXT         NULL DEFAULT NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family_location (family_id, location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

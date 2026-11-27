-- Module "Compteurs & consommation" : relevés eau/électricité/gaz pour suivre la consommation
-- dans le temps (un compteur = un point de mesure, ex. "Compteur électrique principal").
CREATE TABLE IF NOT EXISTS meters (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    family_id    INT NOT NULL,
    name         VARCHAR(150) NOT NULL,
    meter_type   ENUM('eau','electricite','gaz','autre') NOT NULL DEFAULT 'autre',
    unit         VARCHAR(20)  NOT NULL DEFAULT 'kWh',
    provider     VARCHAR(150) NULL DEFAULT NULL,
    contract_ref VARCHAR(100) NULL DEFAULT NULL,
    created_by   INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meter_readings (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    meter_id    INT NOT NULL,
    reading_at  DATE NOT NULL,
    `value`     DECIMAL(12,2) NOT NULL,
    notes       VARCHAR(255) NULL DEFAULT NULL,
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id)   REFERENCES meters(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE CASCADE,
    INDEX idx_meter (meter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

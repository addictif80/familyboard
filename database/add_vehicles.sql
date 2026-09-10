-- Module "Véhicules" : entretien, contrôle technique, assurance, kilométrage.
CREATE TABLE IF NOT EXISTS vehicles (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    family_id                   INT NOT NULL,
    name                        VARCHAR(150) NOT NULL,
    brand                       VARCHAR(100) NULL DEFAULT NULL,
    model                       VARCHAR(100) NULL DEFAULT NULL,
    plate                       VARCHAR(20)  NULL DEFAULT NULL,
    purchase_date               DATE NULL DEFAULT NULL,
    mileage                     INT  NULL DEFAULT NULL,
    insurance_company           VARCHAR(150) NULL DEFAULT NULL,
    insurance_expiry            DATE NULL DEFAULT NULL,
    technical_control_expiry    DATE NULL DEFAULT NULL,
    notes                       TEXT NULL DEFAULT NULL,
    created_by                  INT NOT NULL,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historique d'entretien (vidange, pneus, révision...) par véhicule.
CREATE TABLE IF NOT EXISTS vehicle_maintenance (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id  INT NOT NULL,
    title       VARCHAR(150) NOT NULL,
    done_at     DATE NOT NULL,
    mileage     INT  NULL DEFAULT NULL,
    cost_cents  INT  NULL DEFAULT NULL,
    notes       VARCHAR(255) NULL DEFAULT NULL,
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id)  REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_vehicle (vehicle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

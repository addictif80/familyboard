-- Module "Voyages & réservations" : voyages familiaux et leurs réservations (transport,
-- hébergement, activité...).
CREATE TABLE IF NOT EXISTS travels (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT NOT NULL,
    title         VARCHAR(150) NOT NULL,
    destination   VARCHAR(150) NULL DEFAULT NULL,
    start_date    DATE NULL DEFAULT NULL,
    end_date      DATE NULL DEFAULT NULL,
    budget_cents  INT  NULL DEFAULT NULL,
    notes         TEXT NULL DEFAULT NULL,
    created_by    INT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réservations liées à un voyage (transport, hébergement, activité, autre).
CREATE TABLE IF NOT EXISTS travel_reservations (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    travel_id           INT NOT NULL,
    type                ENUM('transport','hebergement','activite','autre') NOT NULL DEFAULT 'autre',
    title               VARCHAR(150) NOT NULL,
    confirmation_number VARCHAR(100) NULL DEFAULT NULL,
    cost_cents          INT  NULL DEFAULT NULL,
    reservation_date     DATE NULL DEFAULT NULL,
    notes               VARCHAR(255) NULL DEFAULT NULL,
    created_by          INT NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (travel_id)  REFERENCES travels(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE CASCADE,
    INDEX idx_travel (travel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

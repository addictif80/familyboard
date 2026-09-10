-- Module "Congés familiaux" : qui pose quoi et quand (congés payés, RTT, sans solde), avec
-- détection de chevauchement entre membres (alerte informative, jamais bloquante).
CREATE TABLE IF NOT EXISTS leave_requests (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    user_id     INT NOT NULL,
    leave_type  ENUM('conges_payes','rtt','sans_solde','autre') NOT NULL DEFAULT 'conges_payes',
    title       VARCHAR(150) NULL DEFAULT NULL,
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    notes       VARCHAR(255) NULL DEFAULT NULL,
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family_dates (family_id, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

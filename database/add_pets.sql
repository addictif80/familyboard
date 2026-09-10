-- Module "Animaux de compagnie" : carnet de santé animal, vétérinaire, rappels vaccins/vermifuge.
CREATE TABLE IF NOT EXISTS pets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    species     VARCHAR(50)  NULL DEFAULT NULL,
    breed       VARCHAR(100) NULL DEFAULT NULL,
    birth_date  DATE NULL DEFAULT NULL,
    vet_name    VARCHAR(150) NULL DEFAULT NULL,
    vet_phone   VARCHAR(30)  NULL DEFAULT NULL,
    notes       TEXT NULL DEFAULT NULL,
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pet_care_entries (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    pet_id        INT NOT NULL,
    entry_type    ENUM('vaccin','vermifuge','veto','autre') NOT NULL DEFAULT 'autre',
    title         VARCHAR(150) NOT NULL,
    done_at       DATE NOT NULL,
    reminder_date DATE NULL DEFAULT NULL,
    notes         VARCHAR(255) NULL DEFAULT NULL,
    created_by    INT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pet_id)      REFERENCES pets(id)  ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pet (pet_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

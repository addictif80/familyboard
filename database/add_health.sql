-- Module "Santé" : carnet de santé (vaccins, allergies, traitements), courbes de croissance
-- des enfants, et carnet médical (médecins/spécialistes, rappels de renouvellement
-- d'ordonnance). subject_type/subject_id est polymorphe (comme les fiches urgence) : une
-- entrée concerne soit un membre du compte (users), soit un enfant du registre familial
-- (family_children) — pas de FK sur subject_id/child_id puisque la table cible varie selon
-- subject_type ; l'appartenance à la famille est vérifiée côté application.
CREATE TABLE IF NOT EXISTS health_entries (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT NOT NULL,
    subject_type  ENUM('user','child') NOT NULL,
    subject_id    INT NOT NULL,
    entry_type    ENUM('vaccination','allergie','traitement','antecedent','autre') NOT NULL DEFAULT 'autre',
    title         VARCHAR(150) NOT NULL,
    description   TEXT NULL DEFAULT NULL,
    entry_date    DATE NULL DEFAULT NULL,
    reminder_date DATE NULL DEFAULT NULL,
    created_by    INT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family_subject (family_id, subject_type, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- child_id référence family_children.id (registre partagé) sans FK formelle : cette migration
-- doit pouvoir s'appliquer quel que soit l'ordre relatif avec add_shared_children_registry.sql.
CREATE TABLE IF NOT EXISTS health_growth (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    child_id    INT NOT NULL,
    measured_at DATE NOT NULL,
    height_cm   DECIMAL(5,1) NULL DEFAULT NULL,
    weight_kg   DECIMAL(5,2) NULL DEFAULT NULL,
    notes       VARCHAR(255) NULL DEFAULT NULL,
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family_child (family_id, child_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS health_doctors (
    id                         INT AUTO_INCREMENT PRIMARY KEY,
    family_id                  INT NOT NULL,
    subject_type               ENUM('user','child') NOT NULL,
    subject_id                 INT NOT NULL,
    name                       VARCHAR(150) NOT NULL,
    specialty                  VARCHAR(100) NULL DEFAULT NULL,
    phone                      VARCHAR(30)  NULL DEFAULT NULL,
    address                    VARCHAR(255) NULL DEFAULT NULL,
    next_appointment           DATE NULL DEFAULT NULL,
    prescription_renewal_date  DATE NULL DEFAULT NULL,
    notes                      VARCHAR(255) NULL DEFAULT NULL,
    created_by                 INT NOT NULL,
    created_at                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family_subject (family_id, subject_type, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

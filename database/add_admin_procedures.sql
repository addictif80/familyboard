-- Module "Démarches administratives" : échéances CNI/passeport/CAF/mutuelle... avec rappels
-- avant expiration. subject_type/subject_id polymorphe (comme Santé), sans FK sur
-- family_children pour ne pas contraindre l'ordre des migrations.
CREATE TABLE IF NOT EXISTS admin_procedures (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    family_id      INT NOT NULL,
    subject_type   ENUM('user','child') NOT NULL,
    subject_id     INT NOT NULL,
    procedure_type ENUM('cni','passeport','permis','carte_vitale','caf','mutuelle','autre') NOT NULL DEFAULT 'autre',
    title          VARCHAR(150) NOT NULL,
    deadline_date  DATE NOT NULL,
    done           TINYINT(1) NOT NULL DEFAULT 0,
    notes          VARCHAR(255) NULL DEFAULT NULL,
    created_by     INT NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

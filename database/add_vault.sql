-- Module "Coffre-fort numérique / succession" : informations et documents importants (comptes,
-- contacts utiles, souhaits, documents) + personnes de confiance pouvant demander un accès
-- d'urgence (décès, incapacité), validé par un administrateur famille.
CREATE TABLE IF NOT EXISTS vault_entries (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT NOT NULL,
    category      ENUM('document','compte','contact','souhait','autre') NOT NULL DEFAULT 'autre',
    title         VARCHAR(150) NOT NULL,
    content       TEXT NULL DEFAULT NULL,
    file_path     VARCHAR(255) NULL DEFAULT NULL,
    file_original VARCHAR(255) NULL DEFAULT NULL,
    file_mime     VARCHAR(100) NULL DEFAULT NULL,
    created_by    INT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personne de confiance désignée par la famille, avec un lien magique personnel lui permettant
-- de demander un accès d'urgence au coffre-fort — accès qui ne s'ouvre qu'après validation
-- explicite d'un administrateur de la famille (jamais automatique).
CREATE TABLE IF NOT EXISTS vault_trustees (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT NOT NULL,
    name          VARCHAR(150) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    relationship  VARCHAR(100) NULL DEFAULT NULL,
    notes         VARCHAR(255) NULL DEFAULT NULL,
    access_token  VARCHAR(64) NOT NULL,
    status        ENUM('none','requested','approved','denied') NOT NULL DEFAULT 'none',
    requested_at  DATETIME NULL DEFAULT NULL,
    decided_at    DATETIME NULL DEFAULT NULL,
    decided_by    INT NULL DEFAULT NULL,
    created_by    INT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (decided_by) REFERENCES users(id)    ON DELETE SET NULL,
    UNIQUE KEY uq_access_token (access_token),
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

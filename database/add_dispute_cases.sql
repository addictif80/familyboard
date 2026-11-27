-- Module "Dossiers de litige" : suivi d'un litige (voisinage, consommation, employeur...) avec
-- pièces jointes, traçabilité des échanges (téléphone/e-mail/courrier) et partage public en
-- lecture seule (ex. transmission à un avocat ou une administration sans lui créer de compte).
CREATE TABLE IF NOT EXISTS dispute_cases (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    family_id      INT NOT NULL,
    created_by     INT NOT NULL,
    title          VARCHAR(255) NOT NULL,
    opposing_party VARCHAR(255) NOT NULL,
    start_date     DATE NOT NULL,
    details        LONGTEXT,
    status         ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pièces jointes : PDF, Word, Excel, images, e-mails (.eml) — voir OcrHelper::DISPUTE_DOC_MIMES.
CREATE TABLE IF NOT EXISTS dispute_documents (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    dispute_id    INT NOT NULL,
    uploaded_by   INT NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    file_original VARCHAR(255) NOT NULL,
    file_mime     VARCHAR(150) NOT NULL,
    uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispute_id)  REFERENCES dispute_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)         ON DELETE CASCADE,
    INDEX idx_dispute (dispute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Traçabilité des échanges : chaque contact avec la partie adverse (ou un tiers du dossier),
-- avec le numéro/adresse e-mail/adresse postale selon le canal utilisé.
CREATE TABLE IF NOT EXISTS dispute_exchanges (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    dispute_id    INT NOT NULL,
    created_by    INT NOT NULL,
    type          ENUM('telephone','email','courrier') NOT NULL,
    contact_info  VARCHAR(255) NOT NULL,
    exchange_date DATE NOT NULL,
    notes         TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispute_id) REFERENCES dispute_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)         ON DELETE CASCADE,
    INDEX idx_dispute (dispute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lien public (lecture seule, sans compte) — un seul lien actif par dossier, comme pour les
-- listes de tâches (voir task_list_shares) : le régénérer révoque l'ancien.
CREATE TABLE IF NOT EXISTS dispute_shares (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    dispute_id INT NOT NULL,
    token      VARCHAR(64) NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dispute (dispute_id),
    UNIQUE KEY uq_token (token),
    FOREIGN KEY (dispute_id) REFERENCES dispute_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des ouvertures du lien public — consultable par les administrateurs de la famille
-- uniquement (date/heure/IP), jamais purgé automatiquement (valeur probatoire pour le litige).
CREATE TABLE IF NOT EXISTS dispute_share_access_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    dispute_id  INT NOT NULL,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address  VARCHAR(45),
    FOREIGN KEY (dispute_id) REFERENCES dispute_cases(id) ON DELETE CASCADE,
    INDEX idx_dispute (dispute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

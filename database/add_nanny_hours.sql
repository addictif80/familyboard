-- Module "Suivi nounou" : heures de garde saisies manuellement (jour + durée), liées
-- optionnellement à un enfant de la famille, avec totaux mensuel/annuel et export PDF
-- (voir NannyHours, NannyController, templates/nanny/index.php).
CREATE TABLE IF NOT EXISTS nanny_children (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    name        VARCHAR(150) NOT NULL,
    color       VARCHAR(7) NOT NULL DEFAULT '#4A90D9',
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- child_id est nullable : une entrée peut concerner la garde sans viser un enfant précis
-- (ex. plusieurs enfants gardés ensemble la même journée), ou l'enfant a depuis été supprimé
-- du répertoire (ON DELETE SET NULL, l'historique d'heures est conservé).
CREATE TABLE IF NOT EXISTS nanny_hours_entries (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    family_id    INT NOT NULL,
    child_id     INT NULL,
    entry_date   DATE NOT NULL,
    hours        DECIMAL(4,2) NOT NULL,
    nanny_name   VARCHAR(150) DEFAULT '',
    notes        VARCHAR(255) DEFAULT '',
    created_by   INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id)       ON DELETE CASCADE,
    FOREIGN KEY (child_id)   REFERENCES nanny_children(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)          ON DELETE CASCADE,
    INDEX idx_family_date (family_id, entry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Checklist "à ne pas oublier" réutilisable à chaque transfert de garde (cartable, doudou,
-- médicaments...). Les coches sont datées (check_date) : un nouveau jour de transfert repart
-- automatiquement d'une checklist vierge sans qu'aucun job de reset ne soit nécessaire.
CREATE TABLE IF NOT EXISTS custody_checklist_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    schedule_id INT NULL, -- NULL = s'applique à tous les enfants/plannings de la famille
    label       VARCHAR(150) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)   REFERENCES families(id)         ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES custody_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)             ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custody_checklist_checks (
    item_id    INT NOT NULL,
    check_date DATE NOT NULL,
    checked_by INT NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id, check_date),
    FOREIGN KEY (item_id)    REFERENCES custody_checklist_items(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_by) REFERENCES users(id)                  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

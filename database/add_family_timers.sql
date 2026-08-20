-- Minuteurs définis par l'admin de famille (ex. "Machine à laver — 40 min"), affichables sur
-- l'écran mural / le kiosque, déclenchables d'un bouton, avec alarme sonore côté client à
-- échéance (calculée depuis ends_at, pas de statut serveur "alarming" séparé nécessaire).
CREATE TABLE IF NOT EXISTS family_timers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT NOT NULL,
    label VARCHAR(100) NOT NULL,
    duration_minutes INT NOT NULL,
    show_on_wall TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Un lancement (run) par déclenchement du minuteur ; au plus un run 'running' à la fois par
-- minuteur (imposé côté application, pas par contrainte SQL).
CREATE TABLE IF NOT EXISTS family_timer_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timer_id INT NOT NULL,
    family_id INT NOT NULL,
    started_by INT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ends_at TIMESTAMP NOT NULL,
    status ENUM('running','stopped') NOT NULL DEFAULT 'running',
    stopped_at TIMESTAMP NULL,
    FOREIGN KEY (timer_id) REFERENCES family_timers(id) ON DELETE CASCADE,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

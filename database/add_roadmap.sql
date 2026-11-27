-- Roadmap interne (idées de développement), visible uniquement par l'admin système
-- (jamais exposée aux familles). status_order sert au tri manuel dans un même statut.
CREATE TABLE IF NOT EXISTS roadmap_items (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200) NOT NULL,
    description TEXT NULL,
    status      ENUM('idea','in_progress','done') NOT NULL DEFAULT 'idea',
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

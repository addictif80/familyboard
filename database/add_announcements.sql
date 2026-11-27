-- Centre d'annonces système : nouveautés, maintenances programmées, avertissements, publiés par
-- un administrateur système et visibles de tous les membres (toutes familles confondues) — voir
-- App\Models\Announcement, AnnouncementController (page /announcements) et l'onglet
-- /admin?tab=announcements. Distinct de RoadmapItem (usage interne sysadmin uniquement) et de
-- Notification::broadcastToAll() (message ponctuel, non conservé sous forme de liste consultable).
CREATE TABLE IF NOT EXISTS announcements (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(200) NOT NULL,
    content       TEXT NOT NULL,
    type          ENUM('info','nouveaute','maintenance','avertissement') NOT NULL DEFAULT 'info',
    published_at  DATETIME NULL DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_published (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accusés de lecture par membre — sert uniquement au badge "non lu", jamais affiché à un tiers.
CREATE TABLE IF NOT EXISTS announcement_reads (
    announcement_id  INT NOT NULL,
    user_id          INT NOT NULL,
    read_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (announcement_id, user_id),
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

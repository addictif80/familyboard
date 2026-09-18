-- Catégories du menu Démarrer (interface bureau PC) : gérées par l'admin système, qui peut
-- créer/renommer/supprimer des catégories et réaffecter les modules entre elles.
CREATE TABLE IF NOT EXISTS nav_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(10) NOT NULL DEFAULT '📁',
    position INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS nav_category_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    module_slug VARCHAR(50) NOT NULL,
    position INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_module_slug (module_slug),
    FOREIGN KEY (category_id) REFERENCES nav_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO nav_categories (name, icon, position) VALUES
    ('Vie de famille', '🏡', 0),
    ('Garde partagée', '👶', 1),
    ('Organisation', '🗂️', 2),
    ('Suivi & sécurité', '🛡️', 3);

INSERT INTO nav_category_modules (category_id, module_slug, position)
SELECT id, m.slug, m.pos FROM nav_categories,
(SELECT 'wall' AS slug, 0 AS pos UNION ALL SELECT 'family-wall', 1 UNION ALL SELECT 'albums', 2
 UNION ALL SELECT 'chat', 3 UNION ALL SELECT 'calendar', 4 UNION ALL SELECT 'contacts', 5
 UNION ALL SELECT 'meals', 6 UNION ALL SELECT 'wishlist', 7 UNION ALL SELECT 'polls', 8) m
WHERE nav_categories.name = 'Vie de famille';

INSERT INTO nav_category_modules (category_id, module_slug, position)
SELECT id, m.slug, m.pos FROM nav_categories,
(SELECT 'custody' AS slug, 0 AS pos UNION ALL SELECT 'comm_log', 1) m
WHERE nav_categories.name = 'Garde partagée';

INSERT INTO nav_category_modules (category_id, module_slug, position)
SELECT id, m.slug, m.pos FROM nav_categories,
(SELECT 'tasks' AS slug, 0 AS pos UNION ALL SELECT 'projects', 1 UNION ALL SELECT 'budget', 2
 UNION ALL SELECT 'documents', 3 UNION ALL SELECT 'warranties', 4 UNION ALL SELECT 'links', 5
 UNION ALL SELECT 'letters', 6 UNION ALL SELECT 'disputes', 7 UNION ALL SELECT 'school', 8
 UNION ALL SELECT 'employment', 9 UNION ALL SELECT 'nanny', 10 UNION ALL SELECT 'health', 11
 UNION ALL SELECT 'vehicles', 12 UNION ALL SELECT 'pets', 13 UNION ALL SELECT 'meters', 14
 UNION ALL SELECT 'admin_procedures', 15 UNION ALL SELECT 'leave', 16 UNION ALL SELECT 'travels', 17
 UNION ALL SELECT 'vault', 18 UNION ALL SELECT 'ai_assistant', 19 UNION ALL SELECT 'deals', 20
 UNION ALL SELECT 'additions', 21) m
WHERE nav_categories.name = 'Organisation';

INSERT INTO nav_category_modules (category_id, module_slug, position)
SELECT id, m.slug, m.pos FROM nav_categories,
(SELECT 'baby' AS slug, 0 AS pos UNION ALL SELECT 'location', 1 UNION ALL SELECT 'emergency', 2) m
WHERE nav_categories.name = 'Suivi & sécurité';

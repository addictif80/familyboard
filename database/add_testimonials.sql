-- Témoignages de familles utilisatrices, affichés sur la page d'accueil publique après
-- modération par un administrateur système (voir App\Models\Testimonial et
-- /admin?tab=testimonials). Peut venir d'une soumission par une famille (family_id renseigné)
-- ou être saisi directement par l'administrateur (family_id NULL, ex. retour recueilli par e-mail).
CREATE TABLE IF NOT EXISTS testimonials (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT NULL DEFAULT NULL,
    author_name   VARCHAR(150) NOT NULL,
    author_role   VARCHAR(150) NULL DEFAULT NULL,
    content       TEXT NOT NULL,
    rating        TINYINT NULL DEFAULT NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    sort_order    INT NOT NULL DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE SET NULL,
    INDEX idx_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

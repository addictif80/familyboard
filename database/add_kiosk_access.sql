-- Accès "écran mural" (mode kiosque) : lien longue durée généré par
-- l'admin de la famille pour une tablette dédiée (pas de compte
-- utilisateur réel). Affiche tâches/courses (avec ajout + coche),
-- contacts, événements et repas, avec rafraîchissement automatique.
CREATE TABLE IF NOT EXISTS kiosk_links (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT          NOT NULL,
    label      VARCHAR(150) NOT NULL,
    token      VARCHAR(64)  NOT NULL,
    created_by INT          NOT NULL,
    revoked_at DATETIME     NULL DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token),
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

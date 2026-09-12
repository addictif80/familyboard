-- Liste de naissance : une par famille, partagée via un lien public (comme les liens
-- baby-sitter/écran mural) pour que des proches sans compte FamilyBoard puissent réserver un
-- cadeau et éviter les doublons — les parents (vue authentifiée) ne voient jamais le statut de
-- réservation, uniquement les personnes venues via le lien public (voir BirthListAccessController).
CREATE TABLE IF NOT EXISTS birth_lists (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    token       VARCHAR(64) NOT NULL,
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    UNIQUE KEY uq_family (family_id),
    UNIQUE KEY uq_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS birth_list_items (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    birth_list_id        INT NOT NULL,
    title                VARCHAR(200) NOT NULL,
    url                  VARCHAR(500) NULL DEFAULT NULL,
    image_path           VARCHAR(255) NULL DEFAULT NULL,
    price                DECIMAL(10,2) NULL DEFAULT NULL,
    notes                VARCHAR(255) NULL DEFAULT NULL,
    -- Réservé par un membre de la famille (reserved_by_user_id) OU par un proche externe sans
    -- compte (reserved_by_name saisi librement) — jamais les deux. reservation_token est rendu
    -- au navigateur du réservant externe pour lui permettre d'annuler sa propre réservation par
    -- la suite, sans compte ni identifiant.
    reserved_by_name     VARCHAR(150) NULL DEFAULT NULL,
    reserved_by_user_id  INT NULL DEFAULT NULL,
    reservation_token    VARCHAR(64) NULL DEFAULT NULL,
    reserved_at          DATETIME NULL DEFAULT NULL,
    created_by           INT NOT NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (birth_list_id)       REFERENCES birth_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (reserved_by_user_id) REFERENCES users(id)      ON DELETE SET NULL,
    FOREIGN KEY (created_by)          REFERENCES users(id)      ON DELETE CASCADE,
    INDEX idx_birth_list (birth_list_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liste de cadeaux "à la mesenvies.fr" pour un enfant de la famille sans compte propre
-- (FamilyChild) : un item peut désormais appartenir à un utilisateur (comme avant) OU à un
-- enfant du registre familial — jamais les deux. L'enfant n'ayant pas de session, sa liste est
-- gérée par les parents et partagée via un lien public à jeton (même principe que les listes de
-- naissance, voir add_birth_list.sql), avec réservation anonyme pour éviter les doublons.

ALTER TABLE wishlist_items
    MODIFY COLUMN user_id INT NULL,
    ADD COLUMN family_child_id INT NULL DEFAULT NULL AFTER user_id,
    ADD COLUMN image_path VARCHAR(255) NULL DEFAULT NULL AFTER url,
    ADD COLUMN reserved_by_name VARCHAR(150) NULL DEFAULT NULL AFTER reserved_by,
    ADD COLUMN reservation_token VARCHAR(64) NULL DEFAULT NULL AFTER reserved_by_name,
    ADD CONSTRAINT fk_wishlist_items_child FOREIGN KEY (family_child_id) REFERENCES family_children(id) ON DELETE CASCADE,
    ADD INDEX idx_family_child (family_child_id);

CREATE TABLE IF NOT EXISTS wishlist_child_links (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    family_id       INT          NOT NULL,
    family_child_id INT          NOT NULL,
    token           VARCHAR(64)  NOT NULL,
    created_by      INT          NOT NULL,
    created_at      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wishlist_child_links_child (family_child_id),
    UNIQUE KEY uq_wishlist_child_links_token (token),
    FOREIGN KEY (family_id)       REFERENCES families(id)       ON DELETE CASCADE,
    FOREIGN KEY (family_child_id) REFERENCES family_children(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)      REFERENCES users(id),
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

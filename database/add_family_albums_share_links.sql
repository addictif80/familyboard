-- Partage d'album via lien public (à durée illimitée, révocable), selon les
-- mêmes règles que le partage classique avec le co-parent : seul l'admin
-- peut générer/révoquer ce lien et activer l'ajout de photos par le
-- destinataire. Suit le même modèle que sitter_links/kiosk_links.
CREATE TABLE IF NOT EXISTS album_share_links (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    album_id     INT          NOT NULL,
    token        VARCHAR(64)  NOT NULL,
    allow_upload TINYINT(1)   NOT NULL DEFAULT 0,
    created_by   INT          NOT NULL,
    revoked_at   DATETIME     NULL DEFAULT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_token (token),
    FOREIGN KEY (album_id)   REFERENCES albums(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE CASCADE,
    INDEX idx_album (album_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Les photos ajoutées via un lien public n'ont pas de compte FamilyBoard
-- associé : user_id devient nullable, avec un nom déclaratif optionnel et
-- une référence au lien utilisé (pour modération/traçabilité).
ALTER TABLE album_photos MODIFY COLUMN user_id INT NULL;
ALTER TABLE album_photos ADD COLUMN IF NOT EXISTS uploader_name VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE album_photos ADD COLUMN IF NOT EXISTS share_link_id INT NULL DEFAULT NULL;
ALTER TABLE album_photos ADD CONSTRAINT fk_album_photos_share_link FOREIGN KEY (share_link_id) REFERENCES album_share_links(id) ON DELETE SET NULL;

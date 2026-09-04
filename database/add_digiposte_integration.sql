-- Import de documents depuis Digiposte (coffre-fort numérique La Poste/Docaposte), via l'API
-- partenaire OAuth2 (https://developer.laposte.fr, produit "Digiposte"). Chaque membre connecte
-- SON PROPRE compte Digiposte (personnel, jamais partagé au niveau famille) — voir
-- digiposte_connections, une ligne par utilisateur.
--
-- ⚠️ Les chemins d'API (colonnes digiposte_*_path) sont pré-remplis avec des valeurs par défaut
-- déduites du schéma OAuth2 REST habituel, PAS vérifiées contre la documentation technique
-- réelle de l'API Digiposte v3 (non accessible publiquement au moment de l'écriture) — à ajuster
-- depuis le panneau admin une fois la fiche technique/les identifiants obtenus sur
-- developer.laposte.fr, sans avoir besoin de toucher au code (voir App\Core\DigiposteClient).

INSERT INTO app_settings (`key`, `value`) VALUES
    ('digiposte_enabled', '0'),
    ('digiposte_client_id', ''),
    ('digiposte_client_secret', ''),
    ('digiposte_base_url', 'https://api.digiposte.fr'),
    ('digiposte_authorize_url', 'https://api.digiposte.fr/oauth/authorize'),
    ('digiposte_token_path', '/oauth/token'),
    ('digiposte_documents_list_path', '/documents'),
    ('digiposte_document_download_path', '/documents/{id}/content'),
    ('digiposte_scope', 'read'),
    ('digiposte_sync_interval_minutes', '360')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- Jetons OAuth2 d'un utilisateur, chiffrés au repos (voir App\Core\Crypto — même principe que
-- les secrets TOTP). Une ligne par utilisateur, jamais par famille.
CREATE TABLE IF NOT EXISTS digiposte_connections (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    user_id               INT NOT NULL UNIQUE,
    access_token_enc      TEXT NOT NULL,
    refresh_token_enc     TEXT NULL DEFAULT NULL,
    token_expires_at      DATETIME NULL DEFAULT NULL,
    account_label         VARCHAR(150) NULL DEFAULT NULL, -- affichage seulement (ex. email Digiposte), jamais utilisé pour l'auth
    last_synced_at        DATETIME NULL DEFAULT NULL,
    last_sync_error       VARCHAR(255) NULL DEFAULT NULL,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anti-doublon : un document Digiposte déjà importé ne doit jamais être réimporté au sync
-- suivant. document_id en CASCADE : si le document est supprimé côté FamilyBoard, il pourra être
-- réimporté plus tard (comportement voulu, pas une fuite de dédup).
CREATE TABLE IF NOT EXISTS digiposte_imported_documents (
    id                        INT AUTO_INCREMENT PRIMARY KEY,
    user_id                   INT NOT NULL,
    digiposte_document_id     VARCHAR(150) NOT NULL,
    document_id               INT NOT NULL,
    imported_at                DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_digiposte_doc (user_id, digiposte_document_id),
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Import automatique de documents (factures, etc.) depuis une instance Cozy Cloud dédiée par
-- famille — voir App\Core\CozyProvisioner (création/suppression d'instance via un petit relais
-- HTTP déployé sur le serveur Cozy, l'API admin de Cozy Stack n'exposant pas ces opérations),
-- App\Core\CozyOAuthClient (connexion OAuth2) et App\Core\CozyDocumentSync (import + suppression
-- côté Cozy une fois le document rapatrié — Cozy sert de sas de transit, jamais de second
-- entrepôt permanent).

CREATE TABLE IF NOT EXISTS family_cozy_connections (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    family_id         INT NOT NULL,
    cozy_domain       VARCHAR(190) NOT NULL,
    status            ENUM('provisioning','pending_onboarding','pending_oauth','connected','error') NOT NULL DEFAULT 'provisioning',
    -- Identifiants client OAuth (enregistrement dynamique, propre à chaque instance Cozy — voir
    -- CozyOAuthClient::registerClient) et jetons d'accès, chiffrés via App\Core\Crypto (jamais en
    -- clair en base, même exigence que les mots de passe LDAP/Synology — voir StorageSettings).
    client_id         VARCHAR(255) NULL DEFAULT NULL,
    client_secret     TEXT NULL DEFAULT NULL,
    access_token      TEXT NULL DEFAULT NULL,
    refresh_token     TEXT NULL DEFAULT NULL,
    token_expires_at  DATETIME NULL DEFAULT NULL,
    last_sync_at      DATETIME NULL DEFAULT NULL,
    last_error        TEXT NULL DEFAULT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_family (family_id),
    UNIQUE KEY uq_domain (cozy_domain),
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Déduplication : un fichier Cozy déjà importé ne doit jamais être réimporté (le fichier est
-- normalement supprimé de Cozy juste après, mais cette table protège aussi contre une suppression
-- qui aurait échoué/pas encore été constatée au prochain passage du cron).
CREATE TABLE IF NOT EXISTS cozy_imported_files (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT NOT NULL,
    cozy_file_id  VARCHAR(100) NOT NULL,
    document_id   INT NULL DEFAULT NULL,
    imported_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_family_file (family_id, cozy_file_id),
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

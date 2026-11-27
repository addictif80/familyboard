-- Filet de sécurité : add_family_sharing.sql contenait une instruction ALTER ... ADD CONSTRAINT
-- non idempotente qui a échoué en production, empêchant la création de event_shares et
-- event_share_changes (statements suivants dans le même fichier, jamais atteints). Ce fichier
-- recrée ces deux tables de façon totalement idempotente (CREATE TABLE IF NOT EXISTS),
-- indépendamment du sort du fichier d'origine — s'il a fini par réussir entre-temps, ces
-- instructions n'ont simplement aucun effet.
CREATE TABLE IF NOT EXISTS event_shares (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    origin_event_id    INT NULL DEFAULT NULL,
    origin_family_id   INT NOT NULL,
    target_family_id   INT NOT NULL,
    status             ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    forked_event_id    INT NULL DEFAULT NULL,
    invited_by         INT NOT NULL,
    origin_deleted_at  DATETIME NULL DEFAULT NULL,
    ended_at           DATETIME NULL DEFAULT NULL,
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    responded_at       DATETIME NULL DEFAULT NULL,
    FOREIGN KEY (origin_event_id)  REFERENCES events(id)   ON DELETE SET NULL,
    FOREIGN KEY (origin_family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (target_family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (forked_event_id)  REFERENCES events(id)   ON DELETE SET NULL,
    FOREIGN KEY (invited_by)       REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_origin (origin_event_id),
    INDEX idx_target (target_family_id, status),
    INDEX idx_forked (forked_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_share_changes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    event_share_id  INT NOT NULL,
    change_type     ENUM('update','delete') NOT NULL,
    payload         JSON NULL DEFAULT NULL,
    status          ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    decline_reason  VARCHAR(500) NULL DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at     DATETIME NULL DEFAULT NULL,
    FOREIGN KEY (event_share_id) REFERENCES event_shares(id) ON DELETE CASCADE,
    INDEX idx_share (event_share_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Également nécessaire à cette fonctionnalité, au cas où le CREATE TABLE family_friends
-- (avant l'instruction fautive dans le fichier d'origine) n'aurait lui non plus jamais abouti.
CREATE TABLE IF NOT EXISTS family_friends (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    requester_family_id  INT NOT NULL,
    target_family_id     INT NOT NULL,
    status               ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    requested_by         INT NOT NULL,
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    responded_at         DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uq_pair (requester_family_id, target_family_id),
    FOREIGN KEY (requester_family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (target_family_id)    REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by)        REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_target (target_family_id, status),
    INDEX idx_requester (requester_family_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE events ADD COLUMN IF NOT EXISTS shared_from_family_id INT NULL DEFAULT NULL;

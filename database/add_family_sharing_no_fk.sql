-- Deuxième filet de sécurité : malgré add_family_sharing_tables_fix.sql (déjà idempotent),
-- l'erreur "Table 'event_shares' doesn't exist" persistait en production après déploiement.
-- Deux causes possibles, aucune vérifiable sans accès direct à la base :
--  1) Le suivi des migrations se fait par NOM DE FICHIER (table _migrations) : si
--     add_family_sharing.sql avait déjà été marqué "appliqué" lors d'une révision antérieure
--     du fichier (avant l'ajout de l'ADD CONSTRAINT fautif), toute modification ultérieure de
--     ce même fichier ne sera plus jamais rejouée automatiquement.
--  2) Les CREATE TABLE avec FOREIGN KEY échouent en bloc (erreur 150) si le type exact des
--     colonnes référencées (families.id, users.id, events.id) diverge de schema.sql en
--     production — impossible à vérifier depuis cet environnement.
-- Ce fichier recrée les mêmes tables SANS aucune contrainte de clé étrangère (l'intégrité
-- référentielle est déjà gérée côté PHP pour ces tables), afin qu'un mismatch de type ou un
-- nom de fichier déjà marqué "appliqué" ne puisse plus jamais bloquer leur création.
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
    INDEX idx_share (event_share_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS family_friends (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    requester_family_id  INT NOT NULL,
    target_family_id     INT NOT NULL,
    status               ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    requested_by         INT NOT NULL,
    created_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    responded_at         DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uq_pair (requester_family_id, target_family_id),
    INDEX idx_target (target_family_id, status),
    INDEX idx_requester (requester_family_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE events ADD COLUMN IF NOT EXISTS shared_from_family_id INT NULL DEFAULT NULL;

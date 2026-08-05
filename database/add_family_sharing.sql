-- Partage inter-familles : une famille peut devenir "amie" d'une autre via son code famille
-- (acceptation obligatoire), puis inviter cette famille amie à participer à ses événements de
-- calendrier (à nouveau avec acceptation obligatoire à chaque invitation). Une fois acceptée,
-- une invitation d'événement crée une copie ("fork") indépendante dans la famille invitée :
-- elle peut la modifier ou l'annuler pour elle-même sans toucher à l'original. Si la famille
-- source modifie ou supprime l'original, chaque famille participante reçoit une alerte à
-- résoudre individuellement (accepter/refuser les modifications, accepter/refuser la
-- suppression) plutôt qu'une répercussion automatique.

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

-- Marque un événement comme étant la copie ("fork") d'un événement partagé par une autre
-- famille — purement informatif (affichage "Partagé par la famille X"), sans lien de
-- synchronisation directe : toute la logique de propagation passe par event_shares /
-- event_share_changes ci-dessous, jamais par une mise à jour directe de cette copie. Pas de
-- contrainte de clé étrangère ici volontairement : ADD CONSTRAINT n'est pas idempotent (pas
-- d'équivalent "IF NOT EXISTS" en MySQL), et une tentative précédente a échoué en production —
-- comme cette instruction est en plein milieu du fichier, son échec empêchait toutes les
-- instructions suivantes (création de event_shares/event_share_changes) de s'exécuter, à
-- chaque nouvelle tentative de migration automatique. La colonne seule (nullable, sans FK)
-- suffit à l'usage qui en est fait.
ALTER TABLE events ADD COLUMN IF NOT EXISTS shared_from_family_id INT NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS event_shares (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    -- NULL une fois l'événement source supprimé (origin_deleted_at renseigné) : la ligne
    -- survit pour l'historique et pour que target_family puisse encore résoudre l'alerte de
    -- suppression, même après coup.
    origin_event_id    INT NULL DEFAULT NULL,
    origin_family_id   INT NOT NULL,
    target_family_id   INT NOT NULL,
    status             ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    -- Copie indépendante appartenant à target_family, créée à l'acceptation.
    forked_event_id    INT NULL DEFAULT NULL,
    invited_by         INT NOT NULL,
    origin_deleted_at  DATETIME NULL DEFAULT NULL,
    -- Renseigné quand le lien de synchronisation prend fin : suppression de l'original
    -- acceptée (la copie a aussi été supprimée) ou refusée (la copie devient indépendante,
    -- plus aucune alerte à venir). Un partage encore actif a ended_at NULL.
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

-- Une modification ou suppression de l'événement source, à résoudre individuellement par
-- chaque famille ayant accepté le partage — jamais répercutée automatiquement sur sa copie.
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

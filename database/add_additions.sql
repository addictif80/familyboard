-- Module Additions : partage de frais entre plusieurs personnes (restaurant, cadeau commun,
-- activité...), répartition en pourcentage ou montant libre. Les participants peuvent être des
-- membres de la famille créatrice, un co-parent, un membre d'une famille amie (voir
-- family_friends), ou une personne sans compte invitée par e-mail (voir addition_guests) — dans
-- tous les cas, chaque participant doit accepter individuellement l'invitation.
--
-- La cagnotte (addition avec is_cagnotte=1) est une variante gratuite, sans processeur de
-- paiement : un objectif optionnel, des moyens de paiement déclarés (espèces, virement, Wero),
-- et un registre de perceptions saisies manuellement par un admin famille (addition_payments).

CREATE TABLE IF NOT EXISTS additions (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    family_id           INT           NOT NULL,
    created_by          INT           NULL DEFAULT NULL,
    title               VARCHAR(150)  NOT NULL,
    description         VARCHAR(500)  NULL DEFAULT NULL,
    total_amount        DECIMAL(10,2) NOT NULL,
    split_mode          ENUM('percentage','amount') NOT NULL DEFAULT 'percentage',
    is_cagnotte         TINYINT(1)    NOT NULL DEFAULT 0,
    cagnotte_goal       DECIMAL(10,2) NULL DEFAULT NULL,
    -- CSV parmi 'cash','transfer','wero'.
    cagnotte_methods    VARCHAR(100)  NULL DEFAULT NULL,
    cagnotte_iban       VARCHAR(50)   NULL DEFAULT NULL,
    cagnotte_wero_phone VARCHAR(30)   NULL DEFAULT NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Identité stable d'une personne invitée sans compte FamilyBoard, à travers toutes les
-- additions où elle est conviée (un seul lien magique donne accès à tout son "espace
-- additions", pas juste une addition précise). linked_user_id se renseigne automatiquement si
-- cette personne crée un jour un compte avec la même adresse e-mail (voir AuthController) —
-- elle retrouve alors les mêmes éléments directement dans son compte.
CREATE TABLE IF NOT EXISTS addition_guests (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    email          VARCHAR(150) NOT NULL,
    name           VARCHAR(100) NULL DEFAULT NULL,
    access_token   VARCHAR(64)  NOT NULL,
    linked_user_id INT NULL DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email (email),
    UNIQUE KEY uq_token (access_token),
    FOREIGN KEY (linked_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS addition_participants (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    addition_id  INT NOT NULL,
    -- Exactement l'un des deux : user_id (compte FamilyBoard) ou guest_id (invité externe).
    user_id      INT NULL DEFAULT NULL,
    guest_id     INT NULL DEFAULT NULL,
    -- Pourcentage (0-100) ou montant, selon additions.split_mode.
    share_value  DECIMAL(10,2) NOT NULL,
    status       ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    invited_by   INT NULL DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL DEFAULT NULL,
    FOREIGN KEY (addition_id) REFERENCES additions(id)       ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)            ON DELETE SET NULL,
    FOREIGN KEY (guest_id)    REFERENCES addition_guests(id)  ON DELETE CASCADE,
    FOREIGN KEY (invited_by)  REFERENCES users(id)            ON DELETE SET NULL,
    INDEX idx_addition (addition_id),
    INDEX idx_user (user_id),
    INDEX idx_guest (guest_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registre de perceptions (cagnotte) saisi manuellement par un admin famille — aucun
-- processeur de paiement, purement déclaratif/traçabilité.
CREATE TABLE IF NOT EXISTS addition_payments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    addition_id    INT NOT NULL,
    participant_id INT NULL DEFAULT NULL,
    payer_name     VARCHAR(100) NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    method         ENUM('cash','transfer','wero') NOT NULL,
    note           VARCHAR(255) NULL DEFAULT NULL,
    recorded_by    INT NULL DEFAULT NULL,
    paid_at        DATE NOT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (addition_id)    REFERENCES additions(id)             ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES addition_participants(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by)    REFERENCES users(id)                 ON DELETE SET NULL,
    INDEX idx_addition (addition_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Générateur de courriers postaux formels — inspiré du module "courriers" du dépôt
-- addictif80/ce (CRM d'un conseiller bancaire), adapté au multi-foyer FamilyBoard : courriers
-- et modèles partagés au sein d'une même famille (comme Documents, Garanties...) plutôt que
-- propriété exclusive d'un utilisateur isolé comme dans le CRM d'origine.
CREATE TABLE IF NOT EXISTS letters (
    id                            INT AUTO_INCREMENT PRIMARY KEY,
    family_id                     INT NOT NULL,
    user_id                       INT NOT NULL,
    civility                      VARCHAR(20)  DEFAULT '',
    recipient_last_name           VARCHAR(255) DEFAULT '',
    recipient_first_name          VARCHAR(255) DEFAULT '',
    recipient_display_name        VARCHAR(255) NOT NULL,
    recipient_complement          VARCHAR(255) DEFAULT '',
    recipient_address             VARCHAR(255) NOT NULL,
    recipient_address_complement  VARCHAR(255) DEFAULT '',
    recipient_postal_city         VARCHAR(255) NOT NULL,
    recipient_email                VARCHAR(255) DEFAULT '',
    place                         VARCHAR(255) DEFAULT '',
    letter_date                   DATETIME DEFAULT CURRENT_TIMESTAMP,
    subject                       VARCHAR(500) NOT NULL,
    body                          LONGTEXT,
    created_at                    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modèles réutilisables (avec variables {{...}}), visibles et modifiables par toute la famille
-- dès leur création — pas de workflow d'approbation admin comme dans le CRM d'origine, non
-- pertinent à l'échelle d'un foyer.
CREATE TABLE IF NOT EXISTS letter_templates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    created_by  INT NOT NULL,
    name        VARCHAR(255) NOT NULL,
    subject     VARCHAR(500) DEFAULT '',
    body        LONGTEXT,
    variables   JSON DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des envois par e-mail (PDF joint) — plusieurs envois possibles pour un même courrier.
CREATE TABLE IF NOT EXISTS letter_sends (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    letter_id  INT NOT NULL,
    user_id    INT NOT NULL,
    sent_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (letter_id) REFERENCES letters(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adresse postale du foyer : expéditeur fixe pour tous les courriers de la famille (le nom et
-- prénom de l'expéditeur, eux, viennent du profil de l'utilisateur qui génère le courrier).
ALTER TABLE families ADD COLUMN sender_address VARCHAR(255) NULL;
ALTER TABLE families ADD COLUMN sender_postal_city VARCHAR(255) NULL;

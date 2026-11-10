-- Stockage individuel payant (quota perso par membre, au-delà du quota familial partagé) +
-- provisioning LDAP/Synology. Tant que storage_billing_enabled=0 (valeur par défaut), rien
-- n'est restreint ni facturé : comme pour l'abonnement familial (voir add_subscriptions.sql),
-- le lancement de cette fonctionnalité ne doit jamais verrouiller rétroactivement des familles
-- déjà en place. Les abonnements de stockage perso vivent sur le Customer Stripe du PAYEUR
-- (users.stripe_customer_id), jamais sur la famille ni sur family_subscriptions.

ALTER TABLE users ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(100) NULL DEFAULT NULL AFTER birthday;
ALTER TABLE users ADD COLUMN IF NOT EXISTS storage_addon_cap_gb INT NULL DEFAULT NULL; -- NULL = utilise storage_member_addon_cap_gb (réglage admin)
ALTER TABLE users ADD COLUMN IF NOT EXISTS ldap_username VARCHAR(60) NULL DEFAULT NULL UNIQUE;

-- Catalogue des paliers de stockage additionnel (prix + volume), synchronisés vers Stripe
-- comme les paliers familiaux (un Produit Stripe par palier, voir StripeGateway::syncPlanPrices).
CREATE TABLE IF NOT EXISTS storage_products (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    code                 VARCHAR(40)  NOT NULL UNIQUE,
    name                 VARCHAR(100) NOT NULL,
    quota_gb             INT          NOT NULL,
    price_monthly_cents  INT          NOT NULL DEFAULT 0,
    stripe_product_id    VARCHAR(100) NULL DEFAULT NULL,
    stripe_price_id_monthly VARCHAR(100) NULL DEFAULT NULL,
    sort_order           INT          NOT NULL DEFAULT 0,
    active               TINYINT(1)   NOT NULL DEFAULT 1,
    created_at           DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO storage_products (code, name, quota_gb, price_monthly_cents, sort_order) VALUES
    ('storage_100gb', '+100 Go', 100,  199, 1),
    ('storage_250gb', '+250 Go', 250,  399, 2),
    ('storage_500gb', '+500 Go', 500,  599, 3),
    ('storage_1tb',   '+1 To',   1024, 999, 4)
ON DUPLICATE KEY UPDATE code = code;

-- Demande d'achat de stockage pour un bénéficiaire mineur, en attente de validation + paiement
-- par un adulte de la famille. Pour un bénéficiaire majeur payant lui-même, aucune ligne ici :
-- le Checkout Stripe est lancé directement (voir Phase 2).
CREATE TABLE IF NOT EXISTS storage_requests (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    beneficiary_id    INT NOT NULL,
    storage_product_id INT NOT NULL,
    status            ENUM('pending','approved','rejected','canceled') NOT NULL DEFAULT 'pending',
    payer_id          INT NULL DEFAULT NULL, -- renseigné à l'approbation (l'adulte qui paie)
    decided_by        INT NULL DEFAULT NULL,
    decided_at        DATETIME NULL DEFAULT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (beneficiary_id)     REFERENCES users(id)            ON DELETE CASCADE,
    FOREIGN KEY (storage_product_id) REFERENCES storage_products(id) ON DELETE CASCADE,
    FOREIGN KEY (payer_id)           REFERENCES users(id)            ON DELETE SET NULL,
    FOREIGN KEY (decided_by)         REFERENCES users(id)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Un item d'abonnement de stockage additionnel = un palier souscrit par un payeur pour un
-- bénéficiaire. Plusieurs items actifs d'un même bénéficiaire s'additionnent (quota cumulé,
-- voir ProvisioningService::totalQuotaForGroup côté ABPanel, à reproduire dans le modèle PHP).
CREATE TABLE IF NOT EXISTS member_storage_subscriptions (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    beneficiary_id       INT NOT NULL,
    payer_id             INT NOT NULL,
    storage_product_id   INT NOT NULL,
    status               ENUM('active','past_due','grace','canceled') NOT NULL DEFAULT 'active',
    stripe_subscription_id VARCHAR(100) NULL DEFAULT NULL,
    current_period_end   DATETIME NULL DEFAULT NULL,
    grace_ends_at         DATETIME NULL DEFAULT NULL,
    canceled_at           DATETIME NULL DEFAULT NULL,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (beneficiary_id)     REFERENCES users(id)            ON DELETE CASCADE,
    FOREIGN KEY (payer_id)           REFERENCES users(id)            ON DELETE CASCADE,
    FOREIGN KEY (storage_product_id) REFERENCES storage_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File de jobs de provisioning LDAP/Synology, rejouable (cron-driven, voir ABPanel
-- ProvisionLdapAccountJob / SyncSynologyQuotaJob pour la logique à porter). payload JSON
-- libre selon le type (ex. {"quota_gb":150} pour un sync_quota).
CREATE TABLE IF NOT EXISTS provisioning_jobs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    job_type      ENUM('provision_ldap','sync_quota','deprovision') NOT NULL,
    payload       TEXT NULL DEFAULT NULL,
    status        ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
    attempts      INT NOT NULL DEFAULT 0,
    last_error    TEXT NULL DEFAULT NULL,
    run_after     DATETIME NULL DEFAULT NULL, -- backoff : ne pas retenter avant cette date
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings (`key`, `value`) VALUES
    ('storage_billing_enabled', '0'),
    ('storage_free_quota_gb', '5'),
    ('storage_family_quota_gb', '50'),
    ('storage_member_addon_cap_gb', '1024'),
    ('storage_grace_days', '30'),
    ('storage_capacity_alert_threshold_pct', '70'),
    ('storage_ldap_host', ''),
    ('storage_ldap_bind_dn', ''),
    ('storage_ldap_bind_password', ''),
    ('storage_ldap_base_dn', ''),
    ('storage_synology_url', ''),
    ('storage_synology_username', ''),
    ('storage_synology_password', ''),
    ('storage_synology_shared_folder', '')
ON DUPLICATE KEY UPDATE `key` = `key`;

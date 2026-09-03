-- Abonnements payants (Stripe Billing). Tous les paramètres (activation, prix, paliers, essai,
-- délai de grâce, liste des modules gratuits, clés Stripe) sont pilotés depuis le panneau
-- admin système — rien n'est en dur côté code, voir AdminController::updateSubscriptionSettings
-- et Plan::save(). Tant que sub_billing_enabled=0 (valeur par défaut), aucun module n'est
-- restreint : le lancement de cette fonctionnalité ne doit jamais verrouiller rétroactivement
-- des familles déjà en place sans action explicite de l'admin.

CREATE TABLE IF NOT EXISTS plans (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    code                     VARCHAR(40)  NOT NULL UNIQUE,
    name                     VARCHAR(100) NOT NULL,
    member_limit             INT          NULL DEFAULT NULL, -- NULL = illimité
    price_monthly_cents      INT          NOT NULL DEFAULT 0,
    price_yearly_cents       INT          NOT NULL DEFAULT 0,
    stripe_price_id_monthly  VARCHAR(100) NULL DEFAULT NULL,
    stripe_price_id_yearly   VARCHAR(100) NULL DEFAULT NULL,
    sort_order               INT          NOT NULL DEFAULT 0,
    active                   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at               DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plans (code, name, member_limit, price_monthly_cents, price_yearly_cents, sort_order) VALUES
    ('premium_4',   'Premium',        4,    499,  4790, 1),
    ('premium_8',   'Premium',        8,    799,  7670, 2),
    ('premium_max', 'Premium',        NULL, 1199, 11510, 3)
ON DUPLICATE KEY UPDATE code = code;

CREATE TABLE IF NOT EXISTS family_subscriptions (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    family_id            INT NOT NULL UNIQUE,
    plan_id              INT NULL,
    status               ENUM('none','trialing','active','past_due','canceled') NOT NULL DEFAULT 'none',
    billing_interval     ENUM('monthly','yearly') NULL DEFAULT NULL,
    stripe_customer_id   VARCHAR(100) NULL DEFAULT NULL,
    stripe_subscription_id VARCHAR(100) NULL DEFAULT NULL,
    trial_ends_at        DATETIME NULL DEFAULT NULL,
    current_period_end   DATETIME NULL DEFAULT NULL,
    grace_ends_at         DATETIME NULL DEFAULT NULL,
    trial_used            TINYINT(1) NOT NULL DEFAULT 0, -- empêche de renouveler l'essai gratuit en resouscrivant
    manual                TINYINT(1) NOT NULL DEFAULT 0, -- attribué à la main par l'admin, hors Stripe (geste commercial, support...)
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id)   REFERENCES plans(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings (`key`, `value`) VALUES
    ('sub_billing_enabled', '0'),
    ('sub_trial_days', '14'),
    ('sub_grace_days', '30'),
    ('sub_annual_discount_pct', '20'),
    ('sub_free_modules', 'calendar,tasks,wall,chat,contacts'),
    ('stripe_publishable_key', ''),
    ('stripe_secret_key', ''),
    ('stripe_webhook_secret', '')
ON DUPLICATE KEY UPDATE `key` = `key`;

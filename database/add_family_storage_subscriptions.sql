-- Abonnements de stockage additionnel FAMILIAL (par opposition aux abonnements perso par
-- membre, voir member_storage_subscriptions) — un palier storage_products (scope='family')
-- souscrit par un payeur (toujours l'admin de la famille, qui reçoit un rôle de payeur comme
-- tout autre achat Stripe dans cette appli, voir users.stripe_customer_id). Plusieurs
-- abonnements actifs s'additionnent au-dessus de storage_family_quota_gb — même principe que
-- member_storage_subscriptions pour le quota perso.
CREATE TABLE IF NOT EXISTS family_storage_subscriptions (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    family_id              INT NOT NULL,
    payer_id               INT NOT NULL,
    storage_product_id    INT NOT NULL,
    status                 ENUM('active','past_due','grace','canceled') NOT NULL DEFAULT 'active',
    stripe_subscription_id VARCHAR(100) NULL DEFAULT NULL,
    grace_ends_at          DATETIME NULL DEFAULT NULL,
    canceled_at            DATETIME NULL DEFAULT NULL,
    created_at             DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)          REFERENCES families(id)         ON DELETE CASCADE,
    FOREIGN KEY (payer_id)           REFERENCES users(id)            ON DELETE CASCADE,
    FOREIGN KEY (storage_product_id) REFERENCES storage_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

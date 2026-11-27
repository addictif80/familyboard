-- Programme de parrainage : chaque famille a un code de parrainage stable, partagé via un lien
-- (voir Family::ensureReferralCode()). Quand une NOUVELLE famille s'inscrit via ce lien, la
-- relation est tracée ici et une récompense (jours d'un palier premium offerts) peut être
-- accordée automatiquement au parrain — voir Referral::create() et
-- FamilySubscription::extendManualDays().
ALTER TABLE families
    ADD COLUMN referral_code VARCHAR(12) NULL DEFAULT NULL,
    ADD UNIQUE KEY uq_referral_code (referral_code);

CREATE TABLE IF NOT EXISTS referrals (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    referrer_family_id  INT NOT NULL,
    referred_family_id  INT NOT NULL,
    reward_granted_at   DATETIME NULL DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_family_id) REFERENCES families(id) ON DELETE CASCADE,
    UNIQUE KEY uq_referred (referred_family_id),
    INDEX idx_referrer (referrer_family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

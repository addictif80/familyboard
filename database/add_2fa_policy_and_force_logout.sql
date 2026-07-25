-- Horodatage du premier constat "2FA obligatoire mais non configurée" pour cet utilisateur —
-- démarre son délai de grâce individuel (voir TwoFactorAuth::getPolicyStatus).
ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_required_notice_at DATETIME NULL DEFAULT NULL;

-- Déconnexion globale ("déconnecter tous les appareils") : toute session ou jeton "se souvenir
-- de moi" émis avant cette date est invalidé au prochain contrôle d'authentification.
ALTER TABLE users ADD COLUMN IF NOT EXISTS force_logout_at DATETIME NULL DEFAULT NULL;

ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_method ENUM('totp','email') NULL DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL DEFAULT NULL;

-- Codes à usage unique envoyés par email (méthode de repli si aucune application
-- d'authentification n'est enregistrée). Un seul code actif par utilisateur à la fois.
CREATE TABLE IF NOT EXISTS two_factor_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    code_hash  VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

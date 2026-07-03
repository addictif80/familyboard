-- Long-lived login tokens so PWA-installed sessions survive PHP session
-- expiry/cleanup and keep receiving push notifications without re-login.
CREATE TABLE IF NOT EXISTS auth_tokens (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT          NOT NULL,
    selector       VARCHAR(32)  NOT NULL,
    validator_hash VARCHAR(255) NOT NULL,
    expires_at     DATETIME     NOT NULL,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_selector (selector),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add blocked_at, blocked_reason, is_super_admin to users
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS blocked_at     DATETIME     NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS blocked_reason VARCHAR(500) NULL DEFAULT NULL;

-- Blocked IPs
CREATE TABLE IF NOT EXISTS blocked_ips (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(50)  NOT NULL UNIQUE,
    reason     VARCHAR(500) NULL DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Web Push subscriptions
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    endpoint   TEXT         NOT NULL,
    p256dh     VARCHAR(700) NOT NULL,
    auth_key   VARCHAR(255) NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- App-level settings (VAPID keys, etc.)
CREATE TABLE IF NOT EXISTS app_settings (
    `key`       VARCHAR(100) PRIMARY KEY,
    `value`     MEDIUMTEXT   NOT NULL,
    updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Support tickets
CREATE TABLE IF NOT EXISTS support_tickets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT          NOT NULL,
    user_id    INT          NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    status     ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Support messages (thread)
CREATE TABLE IF NOT EXISTS support_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT          NOT NULL,
    user_id    INT          NULL DEFAULT NULL,
    is_admin   TINYINT(1)   NOT NULL DEFAULT 0,
    message    TEXT         NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

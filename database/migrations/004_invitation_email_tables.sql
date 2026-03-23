-- Migration 004: invitation tokens, email logs, email templates
-- Run: mysql -u user -p familyboard < database/migrations/004_invitation_email_tables.sql

CREATE TABLE IF NOT EXISTS invitation_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT NOT NULL,
    invited_by INT NOT NULL,
    email      VARCHAR(255) NOT NULL,
    token      VARCHAR(64)  NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token  (token),
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT           NOT NULL,
    sent_by       INT           NULL,
    to_email      VARCHAR(255)  NOT NULL,
    to_name       VARCHAR(255)  NULL,
    subject       VARCHAR(500)  NOT NULL,
    body          LONGTEXT      NOT NULL,
    type          VARCHAR(50)   NOT NULL DEFAULT 'manual',
    status        ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    error_message TEXT          NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_family  (family_id),
    INDEX idx_type    (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_templates (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT          NOT NULL,
    type       VARCHAR(50)  NOT NULL,
    subject    VARCHAR(500) NOT NULL,
    body       LONGTEXT     NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_family_type (family_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

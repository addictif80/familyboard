-- Limitation des tentatives de connexion (protection brute-force) sur les
-- formulaires de connexion famille et super-admin.
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    scope      VARCHAR(20)  NOT NULL DEFAULT 'user',
    ip         VARCHAR(45)  NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_scope_ip_date (scope, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

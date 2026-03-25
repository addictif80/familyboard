CREATE TABLE IF NOT EXISTS contacts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT          NOT NULL,
    user_id    INT          NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name  VARCHAR(100) NOT NULL DEFAULT '',
    email      VARCHAR(255) NULL DEFAULT NULL,
    phone      VARCHAR(50)  NULL DEFAULT NULL,
    phone2     VARCHAR(50)  NULL DEFAULT NULL,
    address    TEXT         NULL DEFAULT NULL,
    birthday   DATE         NULL DEFAULT NULL,
    notes      TEXT         NULL DEFAULT NULL,
    avatar     VARCHAR(500) NULL DEFAULT NULL,
    color      VARCHAR(7)   NOT NULL DEFAULT '#4A90D9',
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

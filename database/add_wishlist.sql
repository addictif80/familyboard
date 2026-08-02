CREATE TABLE IF NOT EXISTS wishlist_items (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    family_id    INT          NOT NULL,
    user_id      INT          NOT NULL,
    title        VARCHAR(255) NOT NULL,
    description  TEXT         NULL DEFAULT NULL,
    url          VARCHAR(500) NULL DEFAULT NULL,
    price        DECIMAL(10,2) NULL DEFAULT NULL,
    priority     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    is_purchased TINYINT(1)   NOT NULL DEFAULT 0,
    reserved_by  INT          NULL DEFAULT NULL,
    reserved_at  DATETIME     NULL DEFAULT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)   REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (reserved_by) REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_family (family_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 006: recurring budget items (fixed income & expenses)
CREATE TABLE IF NOT EXISTS budget_recurring (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    family_id        INT            NOT NULL,
    user_id          INT            NOT NULL,
    title            VARCHAR(255)   NOT NULL,
    amount           DECIMAL(10,2)  NOT NULL,
    type             ENUM('income','expense') NOT NULL DEFAULT 'expense',
    category_id      INT            DEFAULT NULL,
    day_of_month     TINYINT        NOT NULL DEFAULT 1 COMMENT 'Day 1-28',
    alert_days_before TINYINT       NOT NULL DEFAULT 3 COMMENT '0 = no alert',
    last_alert_sent  DATE           DEFAULT NULL,
    is_active        TINYINT(1)     NOT NULL DEFAULT 1,
    notes            TEXT           DEFAULT NULL,
    created_at       DATETIME       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)   REFERENCES families(id)           ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)              ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES budget_categories(id)  ON DELETE SET NULL,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

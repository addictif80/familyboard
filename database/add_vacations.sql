-- Personal vacation periods for family members
CREATE TABLE IF NOT EXISTS vacations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT          NOT NULL,
    user_id     INT          NOT NULL,
    title       VARCHAR(255) NOT NULL DEFAULT 'Congé',
    start_date  DATE         NOT NULL,
    end_date    DATE         NOT NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family_dates (family_id, start_date, end_date)
);

-- School zone + holidays cache
ALTER TABLE families
    ADD COLUMN IF NOT EXISTS school_zone CHAR(1) NULL DEFAULT NULL
        COMMENT 'Zone scolaire : A, B ou C';

CREATE TABLE IF NOT EXISTS school_holidays (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    zone        CHAR(1)      NOT NULL,
    description VARCHAR(255) NOT NULL,
    start_date  DATE         NOT NULL,
    end_date    DATE         NOT NULL,
    synced_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone_dates (zone, start_date, end_date)
);

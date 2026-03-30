CREATE TABLE IF NOT EXISTS school_holidays (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    zone        CHAR(1)      NOT NULL,
    description VARCHAR(255) NOT NULL,
    start_date  DATE         NOT NULL,
    end_date    DATE         NOT NULL,
    synced_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone_dates (zone, start_date, end_date)
);

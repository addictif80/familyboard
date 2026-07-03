-- Position sharing: opt-in, one-tap "check-in" (no continuous background
-- tracking — unreliable in a PWA, especially on iOS). Members save a few
-- favorite places, then share their current position for a limited time;
-- the check-in auto-expires so nothing lingers.

CREATE TABLE IF NOT EXISTS saved_places (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT          NOT NULL,
    name       VARCHAR(100) NOT NULL,
    lat        DECIMAL(10,7) NOT NULL,
    lng        DECIMAL(10,7) NOT NULL,
    radius_m   INT          NOT NULL DEFAULT 150,
    created_by INT          NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS location_checkins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    family_id  INT          NOT NULL,
    lat        DECIMAL(10,7) NOT NULL,
    lng        DECIMAL(10,7) NOT NULL,
    place_id   INT          NULL DEFAULT NULL,
    note       VARCHAR(100) NULL DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME     NOT NULL,
    FOREIGN KEY (user_id)   REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (family_id) REFERENCES families(id)      ON DELETE CASCADE,
    FOREIGN KEY (place_id)  REFERENCES saved_places(id)  ON DELETE SET NULL,
    INDEX idx_family_expiry (family_id, expires_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

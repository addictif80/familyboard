-- Albums photo familiaux (« family album »), partageables avec le co-parent
-- d'un planning de garde alternée. Suit le même schéma de partage que
-- documents/events/comm_log_messages : un album non NULL en custody_schedule_id
-- est visible (et alimentable) par tout co-parent ayant accès à ce planning.

CREATE TABLE IF NOT EXISTS albums (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    family_id           INT          NOT NULL,
    user_id             INT          NOT NULL,
    title               VARCHAR(150) NOT NULL,
    description         TEXT         NULL DEFAULT NULL,
    custody_schedule_id INT          NULL DEFAULT NULL,
    created_at          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)           REFERENCES families(id)          ON DELETE CASCADE,
    FOREIGN KEY (user_id)             REFERENCES users(id)             ON DELETE CASCADE,
    FOREIGN KEY (custody_schedule_id) REFERENCES custody_schedules(id) ON DELETE SET NULL,
    INDEX idx_family (family_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS album_photos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    album_id   INT          NOT NULL,
    user_id    INT          NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    caption    VARCHAR(255) NULL DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE,
    INDEX idx_album (album_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Module Bébé — Tables
-- =============================================================

CREATE TABLE IF NOT EXISTS babies (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT          NOT NULL,
    name          VARCHAR(100) NOT NULL,
    birth_date    DATE         NULL DEFAULT NULL,
    birth_time    TIME         NULL DEFAULT NULL,
    birth_weight  DECIMAL(5,2) NULL DEFAULT NULL,
    birth_height  DECIMAL(5,2) NULL DEFAULT NULL,
    birth_details TEXT         NULL DEFAULT NULL,
    avatar        VARCHAR(255) NULL DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    INDEX idx_baby_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pregnancies (
    id         INT  AUTO_INCREMENT PRIMARY KEY,
    family_id  INT  NOT NULL,
    baby_id    INT  NULL DEFAULT NULL,
    lmp_date   DATE NULL DEFAULT NULL,
    due_date   DATE NULL DEFAULT NULL,
    notes      TEXT NULL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (baby_id)   REFERENCES babies(id)   ON DELETE SET NULL,
    INDEX idx_preg_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pregnancy_consultations (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    pregnancy_id   INT          NOT NULL,
    family_id      INT          NOT NULL,
    practitioner   VARCHAR(150) NULL DEFAULT NULL,
    consult_date   DATETIME     NOT NULL,
    details        TEXT         NULL DEFAULT NULL,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pregnancy_id) REFERENCES pregnancies(id) ON DELETE CASCADE,
    INDEX idx_pc_pregnancy (pregnancy_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pregnancy_images (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    consultation_id   INT          NOT NULL,
    family_id         INT          NOT NULL,
    file_path         VARCHAR(255) NOT NULL,
    original_name     VARCHAR(255) NULL DEFAULT NULL,
    created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (consultation_id) REFERENCES pregnancy_consultations(id) ON DELETE CASCADE,
    INDEX idx_pi_consult (consultation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS baby_events (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    baby_id    INT          NOT NULL,
    family_id  INT          NOT NULL,
    type       ENUM('feeding','stool','urine','diaper','bath') NOT NULL,
    event_at   DATETIME     NOT NULL,
    quantity   DECIMAL(6,1) NULL DEFAULT NULL,
    notes      TEXT         NULL DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (baby_id) REFERENCES babies(id) ON DELETE CASCADE,
    INDEX idx_be_baby_type (baby_id, type),
    INDEX idx_be_baby_at   (baby_id, event_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS baby_sleeps (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    baby_id    INT      NOT NULL,
    family_id  INT      NOT NULL,
    start_at   DATETIME NOT NULL,
    end_at     DATETIME NULL DEFAULT NULL,
    notes      TEXT     NULL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (baby_id) REFERENCES babies(id) ON DELETE CASCADE,
    INDEX idx_bs_baby (baby_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS baby_consultations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    baby_id       INT          NOT NULL,
    family_id     INT          NOT NULL,
    practitioner  VARCHAR(150) NULL DEFAULT NULL,
    consult_at    DATETIME     NOT NULL,
    details       TEXT         NULL DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (baby_id) REFERENCES babies(id) ON DELETE CASCADE,
    INDEX idx_bc_baby (baby_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

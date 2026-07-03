-- =============================================================
-- FamilyBoard — Schéma complet de la base de données
-- =============================================================
-- • Idempotent : utilise CREATE TABLE IF NOT EXISTS et
--   ALTER TABLE … ADD COLUMN IF NOT EXISTS partout.
-- • Compatible installation fraîche et mise à jour d'une base
--   existante (toutes versions confondues).
--
-- Import manuel (installation fraîche) :
--   mysql -u root -p familyboard < database/schema.sql
--
-- Sur une installation existante le schéma est appliqué
-- automatiquement au démarrage via Database::autoMigrate().
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Familles ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS families (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100)  NOT NULL,
    invite_code      VARCHAR(20)   NOT NULL UNIQUE,
    timezone         VARCHAR(50)   NOT NULL DEFAULT 'Europe/Paris',
    weather_city     VARCHAR(100)  NULL DEFAULT NULL,
    go2rtc_url       VARCHAR(255)  NULL DEFAULT NULL,
    strix_url        VARCHAR(255)  NULL DEFAULT NULL,
    disabled_modules        JSON          NULL DEFAULT NULL,
    caldav_sync_interval    SMALLINT      NULL DEFAULT NULL,
    created_at              DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invite (invite_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Utilisateurs ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT          NOT NULL,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('admin','member') NOT NULL DEFAULT 'member',
    avatar     VARCHAR(255) NULL DEFAULT NULL,
    color      VARCHAR(7)   NOT NULL DEFAULT '#4A90D9',
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note : les identifiants SMTP sont globaux (configurés par l'administrateur
-- système dans le panneau /admin, stockés dans app_settings) et non plus
-- propres à chaque famille — voir database/add_global_smtp.sql.

-- ── Sources CalDAV ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS caldav_sources (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT          NOT NULL,
    user_id    INT          NOT NULL,
    name       VARCHAR(100) NOT NULL,
    url        VARCHAR(500) NOT NULL,
    username   VARCHAR(255) NULL DEFAULT NULL,
    password   VARCHAR(255) NULL DEFAULT NULL,
    color      VARCHAR(7)   NOT NULL DEFAULT '#4A90D9',
    last_sync  DATETIME     NULL DEFAULT NULL,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Événements calendrier ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS events (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    family_id        INT          NOT NULL,
    user_id          INT          NOT NULL,
    title            VARCHAR(255) NOT NULL,
    description      TEXT         NULL DEFAULT NULL,
    start_datetime   DATETIME     NOT NULL,
    end_datetime     DATETIME     NOT NULL,
    is_all_day       TINYINT(1)   NOT NULL DEFAULT 0,
    color            VARCHAR(7)   NOT NULL DEFAULT '#4A90D9',
    recurrence       VARCHAR(50)  NULL DEFAULT NULL,
    recurrence_end   DATE         NULL DEFAULT NULL,
    caldav_uid       VARCHAR(255) NULL DEFAULT NULL,
    caldav_source_id INT          NULL DEFAULT NULL,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)        REFERENCES families(id)       ON DELETE CASCADE,
    FOREIGN KEY (user_id)          REFERENCES users(id)          ON DELETE CASCADE,
    FOREIGN KEY (caldav_source_id) REFERENCES caldav_sources(id) ON DELETE SET NULL,
    INDEX idx_family_dates (family_id, start_datetime, end_datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Mur familial ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS posts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT       NOT NULL,
    user_id    INT       NOT NULL,
    content    TEXT      NULL DEFAULT NULL,
    image_path VARCHAR(500) NULL DEFAULT NULL,
    created_at DATETIME  DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT      NOT NULL,
    user_id    INT      NOT NULL,
    content    TEXT     NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_reactions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT        NOT NULL,
    user_id    INT        NOT NULL,
    emoji      VARCHAR(10) NOT NULL DEFAULT '❤️',
    UNIQUE KEY uk_reaction (post_id, user_id),
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tâches & Courses ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS task_lists (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT         NOT NULL,
    user_id    INT         NOT NULL,
    name       VARCHAR(100) NOT NULL,
    type       ENUM('tasks','shopping') NOT NULL DEFAULT 'tasks',
    color      VARCHAR(7)  NOT NULL DEFAULT '#4A90D9',
    created_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    list_id      INT         NOT NULL,
    user_id      INT         NOT NULL,
    assigned_to  INT         NULL DEFAULT NULL,
    title        VARCHAR(255) NOT NULL,
    notes        TEXT         NULL DEFAULT NULL,
    is_completed TINYINT(1)  NOT NULL DEFAULT 0,
    due_date     DATE         NULL DEFAULT NULL,
    priority     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    completed_at DATETIME    NULL DEFAULT NULL,
    created_at   DATETIME    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (list_id)     REFERENCES task_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Chat familial ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT      NOT NULL,
    user_id    INT      NOT NULL,
    content    TEXT     NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Budget ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS budget_categories (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    family_id INT         NOT NULL,
    name      VARCHAR(100) NOT NULL,
    color     VARCHAR(7)  NOT NULL DEFAULT '#4A90D9',
    icon      VARCHAR(50) NOT NULL DEFAULT '💰',
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budget_transactions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT            NOT NULL,
    user_id     INT            NOT NULL,
    category_id INT            NULL DEFAULT NULL,
    title       VARCHAR(255)   NOT NULL,
    amount      DECIMAL(10,2)  NOT NULL,
    type        ENUM('income','expense') NOT NULL DEFAULT 'expense',
    date        DATE           NOT NULL,
    notes       TEXT           NULL DEFAULT NULL,
    created_at  DATETIME       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)   REFERENCES families(id)           ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)              ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES budget_categories(id)  ON DELETE SET NULL,
    INDEX idx_family_date (family_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budget_goals (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    family_id      INT           NOT NULL,
    name           VARCHAR(100)  NOT NULL,
    target_amount  DECIMAL(10,2) NOT NULL DEFAULT 0,
    current_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    deadline       DATE          NULL DEFAULT NULL,
    color          VARCHAR(7)    NOT NULL DEFAULT '#4A90D9',
    created_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budget_recurring (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    family_id         INT            NOT NULL,
    user_id           INT            NOT NULL,
    title             VARCHAR(255)   NOT NULL,
    amount            DECIMAL(10,2)  NOT NULL,
    type              ENUM('income','expense') NOT NULL DEFAULT 'expense',
    category_id       INT            NULL DEFAULT NULL,
    day_of_month      TINYINT        NOT NULL DEFAULT 1 COMMENT 'Jour 1-28',
    alert_days_before TINYINT        NOT NULL DEFAULT 3 COMMENT '0 = pas d\'alerte',
    last_alert_sent   DATE           NULL DEFAULT NULL,
    is_active         TINYINT(1)     NOT NULL DEFAULT 1,
    notes             TEXT           NULL DEFAULT NULL,
    created_at        DATETIME       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)   REFERENCES families(id)           ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)              ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES budget_categories(id)  ON DELETE SET NULL,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Garde alternée ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS custody_schedules (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    family_id                INT          NOT NULL,
    child_name               VARCHAR(100) NOT NULL,
    color                    VARCHAR(7)   NOT NULL DEFAULT '#E67E22',
    notes                    TEXT         NULL DEFAULT NULL,
    recurrence_type          ENUM('none','every_other_day','every_other_week','every_2weeks','every_month')
                                          NOT NULL DEFAULT 'none',
    recurrence_start         DATE         NULL DEFAULT NULL,
    recurrence_parent1_id    INT          NULL DEFAULT NULL,
    recurrence_parent2_id    INT          NULL DEFAULT NULL,
    recurrence_parent1_label VARCHAR(100) NULL DEFAULT NULL,
    recurrence_parent1_color VARCHAR(7)   NOT NULL DEFAULT '#4A90D9',
    recurrence_parent2_label VARCHAR(100) NULL DEFAULT NULL,
    recurrence_parent2_color VARCHAR(7)   NOT NULL DEFAULT '#E74C3C',
    created_at               DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)             REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (recurrence_parent1_id) REFERENCES users(id)    ON DELETE SET NULL,
    FOREIGN KEY (recurrence_parent2_id) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custody_events (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id    INT     NOT NULL,
    parent_user_id INT     NULL DEFAULT NULL,
    start_date     DATE    NOT NULL,
    end_date       DATE    NOT NULL,
    arrival_time   TIME    NULL DEFAULT NULL,
    departure_time TIME    NULL DEFAULT NULL,
    notes          TEXT    NULL DEFAULT NULL,
    FOREIGN KEY (schedule_id)    REFERENCES custody_schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_user_id) REFERENCES users(id)             ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Projets ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT           NOT NULL,
    user_id     INT           NOT NULL,
    name        VARCHAR(150)  NOT NULL,
    description TEXT          NULL DEFAULT NULL,
    status      ENUM('active','completed','archived') NOT NULL DEFAULT 'active',
    budget      DECIMAL(10,2) NULL DEFAULT NULL,
    deadline    DATE          NULL DEFAULT NULL,
    color       VARCHAR(7)    NOT NULL DEFAULT '#4A90D9',
    created_at  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_tasks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT          NOT NULL,
    user_id     INT          NOT NULL,
    assigned_to INT          NULL DEFAULT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT         NULL DEFAULT NULL,
    status      ENUM('todo','in_progress','done') NOT NULL DEFAULT 'todo',
    priority    ENUM('low','medium','high')        NOT NULL DEFAULT 'medium',
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_expenses (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT           NOT NULL,
    user_id    INT           NOT NULL,
    title      VARCHAR(255)  NOT NULL,
    amount     DECIMAL(10,2) NOT NULL,
    date       DATE          NOT NULL,
    notes      TEXT          NULL DEFAULT NULL,
    created_at DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_materials (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    project_id   INT           NOT NULL,
    user_id      INT           NOT NULL,
    name         VARCHAR(255)  NOT NULL,
    url          TEXT          NULL DEFAULT NULL,
    price        DECIMAL(10,2) NULL DEFAULT NULL,
    quantity     DECIMAL(10,3) NOT NULL DEFAULT 1,
    unit         VARCHAR(50)   NULL DEFAULT NULL,
    notes        TEXT          NULL DEFAULT NULL,
    is_purchased TINYINT(1)    NOT NULL DEFAULT 0,
    created_at   DATETIME      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Garanties ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS warranties (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    family_id         INT            NOT NULL,
    user_id           INT            NOT NULL,
    title             VARCHAR(255)   NOT NULL,
    brand             VARCHAR(100)   NULL DEFAULT NULL,
    model             VARCHAR(100)   NULL DEFAULT NULL,
    serial_number     VARCHAR(100)   NULL DEFAULT NULL,
    store             VARCHAR(150)   NULL DEFAULT NULL,
    purchase_date     DATE           NULL DEFAULT NULL,
    warranty_months   SMALLINT       NOT NULL DEFAULT 24,
    warranty_end_date DATE           NULL DEFAULT NULL,
    price             DECIMAL(10,2)  NULL DEFAULT NULL,
    notes             TEXT           NULL DEFAULT NULL,
    file_path         VARCHAR(500)   NULL DEFAULT NULL,
    file_original     VARCHAR(255)   NULL DEFAULT NULL,
    file_mime         VARCHAR(100)   NULL DEFAULT NULL,
    ocr_text          MEDIUMTEXT     NULL DEFAULT NULL,
    created_at        DATETIME       DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FULLTEXT KEY ft_warranties (title, brand, model, serial_number, store, ocr_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Documents ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS documents (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT          NOT NULL,
    user_id       INT          NOT NULL,
    title         VARCHAR(255) NOT NULL,
    doc_type      VARCHAR(50)  NOT NULL DEFAULT 'other',
    issuer        VARCHAR(150) NULL DEFAULT NULL,
    issue_date    DATE         NULL DEFAULT NULL,
    expiry_date   DATE         NULL DEFAULT NULL,
    tags          VARCHAR(500) NULL DEFAULT NULL,
    file_path     VARCHAR(500) NULL DEFAULT NULL,
    file_original VARCHAR(255) NULL DEFAULT NULL,
    file_mime     VARCHAR(100) NULL DEFAULT NULL,
    ocr_text      MEDIUMTEXT   NULL DEFAULT NULL,
    notes         TEXT         NULL DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    FULLTEXT KEY ft_documents (title, doc_type, issuer, tags, ocr_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_members (
    document_id INT NOT NULL,
    user_id     INT NOT NULL,
    PRIMARY KEY (document_id, user_id),
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rattache les documents existants à leur propriétaire
INSERT IGNORE INTO document_members (document_id, user_id)
SELECT id, user_id FROM documents WHERE user_id IS NOT NULL;

-- ── Caméras IP ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cameras (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT          NOT NULL,
    user_id     INT          NOT NULL,
    name        VARCHAR(150) NOT NULL,
    host        VARCHAR(255) NOT NULL,
    stream_url  TEXT         NULL DEFAULT NULL,
    stream_type ENUM('mjpeg','snapshot','hls','rtsp','other') NOT NULL DEFAULT 'other',
    username    VARCHAR(100) NULL DEFAULT NULL,
    password    VARCHAR(255) NULL DEFAULT NULL,
    model       VARCHAR(150) NULL DEFAULT NULL,
    notes       TEXT         NULL DEFAULT NULL,
    sort_order  INT          NOT NULL DEFAULT 0,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Invitations & e-mails ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS invitation_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT          NOT NULL,
    invited_by INT          NOT NULL,
    email      VARCHAR(255) NOT NULL,
    token      VARCHAR(64)  NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     NULL DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token  (token),
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT           NOT NULL,
    sent_by       INT           NULL DEFAULT NULL,
    to_email      VARCHAR(255)  NOT NULL,
    to_name       VARCHAR(255)  NULL DEFAULT NULL,
    subject       VARCHAR(500)  NOT NULL,
    body          LONGTEXT      NOT NULL,
    type          VARCHAR(50)   NOT NULL DEFAULT 'manual',
    status        ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    error_message TEXT          NULL DEFAULT NULL,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_family  (family_id),
    INDEX idx_type    (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT          NOT NULL,
    type       VARCHAR(50)  NOT NULL,
    subject    VARCHAR(500) NOT NULL,
    body       LONGTEXT     NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_family_type (family_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    type       VARCHAR(50)  NOT NULL DEFAULT 'info',
    title      VARCHAR(255) NOT NULL,
    message    TEXT         NULL DEFAULT NULL,
    link       VARCHAR(500) NULL DEFAULT NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- ALTER TABLE — idempotents pour bases existantes
-- Colonnes ajoutées au fil des versions ; sans effet sur une
-- installation fraîche (CREATE TABLE ci-dessus les inclut déjà).
-- =============================================================
ALTER TABLE families ADD COLUMN IF NOT EXISTS timezone         VARCHAR(50)  NOT NULL DEFAULT 'Europe/Paris';
ALTER TABLE families ADD COLUMN IF NOT EXISTS weather_city     VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE families ADD COLUMN IF NOT EXISTS strix_url        VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE families ADD COLUMN IF NOT EXISTS go2rtc_url       VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE families ADD COLUMN IF NOT EXISTS disabled_modules     JSON     NULL DEFAULT NULL;
ALTER TABLE families ADD COLUMN IF NOT EXISTS caldav_sync_interval SMALLINT NULL DEFAULT NULL;

ALTER TABLE custody_schedules ADD COLUMN IF NOT EXISTS recurrence_type          ENUM('none','every_other_day','every_other_week','every_2weeks','every_month') NOT NULL DEFAULT 'none';
ALTER TABLE custody_schedules ADD COLUMN IF NOT EXISTS recurrence_start         DATE         NULL DEFAULT NULL;
ALTER TABLE custody_schedules ADD COLUMN IF NOT EXISTS recurrence_parent1_id    INT          NULL DEFAULT NULL;
ALTER TABLE custody_schedules ADD COLUMN IF NOT EXISTS recurrence_parent2_id    INT          NULL DEFAULT NULL;
ALTER TABLE custody_schedules ADD COLUMN IF NOT EXISTS recurrence_parent1_label VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE custody_schedules ADD COLUMN IF NOT EXISTS recurrence_parent1_color VARCHAR(7)   NOT NULL DEFAULT '#4A90D9';
ALTER TABLE custody_schedules ADD COLUMN IF NOT EXISTS recurrence_parent2_label VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE custody_schedules ADD COLUMN IF NOT EXISTS recurrence_parent2_color VARCHAR(7)   NOT NULL DEFAULT '#E74C3C';

SET FOREIGN_KEY_CHECKS = 1;

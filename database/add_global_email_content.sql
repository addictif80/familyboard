-- Global (system-admin) customizable subject + message text for automatic
-- emails. Scoped by type only (not per-family) — edited from /admin, the
-- graphical style itself is fixed in code and never stored here.
CREATE TABLE IF NOT EXISTS email_content (
    type       VARCHAR(50)  NOT NULL PRIMARY KEY,
    subject    VARCHAR(500) NOT NULL,
    message    TEXT         NOT NULL,
    updated_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

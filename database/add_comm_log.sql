-- Journal de communication parental : messages immuables (jamais modifiés
-- ni supprimés une fois envoyés) avec accusé de lecture horodaté — utile
-- comme trace de communication entre parents séparés.
CREATE TABLE IF NOT EXISTS comm_log_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    family_id  INT      NOT NULL,
    user_id    INT      NOT NULL,
    content    TEXT     NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comm_log_reads (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT      NOT NULL,
    user_id    INT      NOT NULL,
    read_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_read (message_id, user_id),
    FOREIGN KEY (message_id) REFERENCES comm_log_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)              ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

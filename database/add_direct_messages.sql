-- Messagerie privée 1-à-1 entre membres qui se suivent mutuellement (distincte du Chat
-- familial, qui reste un chat de groupe). user_a_id est toujours le plus petit id des deux
-- participants — convention qui garantit qu'un même couple n'a jamais qu'un seul thread,
-- sans avoir à tester les deux ordres à chaque requête.
CREATE TABLE IF NOT EXISTS dm_threads (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_a_id   INT      NOT NULL,
    user_b_id   INT      NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pair (user_a_id, user_b_id),
    FOREIGN KEY (user_a_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (user_b_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dm_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    thread_id   INT      NOT NULL,
    sender_id   INT      NOT NULL,
    content     TEXT     NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES dm_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id)      ON DELETE CASCADE,
    INDEX idx_thread (thread_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assistant IA (Ollama auto-hébergé, configuré par l'administrateur système) : conversation
-- partagée par famille (comme le chat familial) + journal des actions effectuées par l'assistant
-- (jamais une boîte noire — chaque création est tracée et visible de tous les membres).
CREATE TABLE IF NOT EXISTS ai_assistant_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    user_id     INT NULL DEFAULT NULL,
    role        ENUM('user','assistant') NOT NULL,
    content     TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_family (family_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- target_table/target_id pointent vers la ligne réellement créée (tasks/events/...) — permet de
-- vérifier après coup ce que l'assistant a fait, sans dupliquer les données ici.
CREATE TABLE IF NOT EXISTS ai_assistant_actions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    family_id     INT NOT NULL,
    user_id       INT NOT NULL,
    action_type   VARCHAR(50)  NOT NULL,
    summary       VARCHAR(255) NOT NULL,
    target_table  VARCHAR(50)  NOT NULL,
    target_id     INT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

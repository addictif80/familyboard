-- Materiaux de projet : liens vers des articles à acheter
CREATE TABLE IF NOT EXISTS project_materials (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    user_id     INT NOT NULL,
    name        VARCHAR(255) NOT NULL,
    url         TEXT NULL,
    price       DECIMAL(10,2) NULL,
    quantity    DECIMAL(10,3) DEFAULT 1,
    unit        VARCHAR(50) NULL,
    notes       TEXT NULL,
    is_purchased TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)
);

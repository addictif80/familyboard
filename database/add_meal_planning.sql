-- Planification de repas : recettes simples + planning hebdomadaire.
CREATE TABLE IF NOT EXISTS recipes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    family_id    INT          NOT NULL,
    title        VARCHAR(150) NOT NULL,
    ingredients  TEXT         NOT NULL,
    instructions TEXT         NULL DEFAULT NULL,
    servings     INT          NOT NULL DEFAULT 4,
    created_by   INT          NOT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meal_plans (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    family_id    INT          NOT NULL,
    date         DATE         NOT NULL,
    meal_type    ENUM('breakfast','lunch','dinner','snack') NOT NULL,
    recipe_id    INT          NULL DEFAULT NULL,
    custom_title VARCHAR(255) NULL DEFAULT NULL,
    created_by   INT          NOT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slot (family_id, date, meal_type),
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (recipe_id)  REFERENCES recipes(id)  ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

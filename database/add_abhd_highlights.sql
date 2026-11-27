-- Mises en avant des autres services ABHD (éditeur de FamilyBoard) — gérées uniquement par
-- l'administrateur système, jamais par un admin de famille. Volontairement nommé "highlights"
-- plutôt que "ads"/"pub" partout dans le code et l'interface : voir la politique de
-- confidentialité, section dédiée.
CREATE TABLE IF NOT EXISTS abhd_highlights (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    title              VARCHAR(150)  NOT NULL,
    description        VARCHAR(300)  NULL DEFAULT NULL,
    url                VARCHAR(500)  NOT NULL,
    image_path         VARCHAR(500)  NULL DEFAULT NULL,
    show_dashboard     TINYINT(1)    NOT NULL DEFAULT 1,
    show_module_pages  TINYINT(1)    NOT NULL DEFAULT 1,
    show_email         TINYINT(1)    NOT NULL DEFAULT 1,
    show_coparent      TINYINT(1)    NOT NULL DEFAULT 1,
    is_active          TINYINT(1)    NOT NULL DEFAULT 1,
    click_count        INT           NOT NULL DEFAULT 0,
    created_at         DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

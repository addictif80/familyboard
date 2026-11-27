-- Fêtes des prénoms ajoutées/corrigées par un administrateur système, en complément du
-- calendrier intégré (App\Models\NameDay) — prioritaires sur celui-ci en cas de conflit.
CREATE TABLE IF NOT EXISTS custom_name_days (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_day CHAR(5) NOT NULL COMMENT 'MM-DD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_custom_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

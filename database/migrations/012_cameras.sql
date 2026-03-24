-- Caméras IP : stockage des caméras et de leurs flux
CREATE TABLE IF NOT EXISTS cameras (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    user_id     INT NOT NULL,
    name        VARCHAR(150) NOT NULL,
    host        VARCHAR(255) NOT NULL,
    stream_url  TEXT NULL,
    stream_type ENUM('mjpeg','snapshot','hls','rtsp','other') DEFAULT 'other',
    username    VARCHAR(100) NULL,
    password    VARCHAR(255) NULL,
    model       VARCHAR(150) NULL,
    notes       TEXT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)
);

-- URL de l'instance Strix pour la découverte automatique des flux
ALTER TABLE families ADD COLUMN IF NOT EXISTS strix_url VARCHAR(255) NULL DEFAULT NULL;

-- Mode sombre programmé pour l'écran mural / le kiosque (jamais pour l'app normale, qui a déjà
-- son propre bouton clair/sombre par utilisateur) : horaires fixes ou coucher/lever du soleil
-- (calculé depuis le domicile — voir add_home_tracking.sql).
ALTER TABLE families ADD COLUMN IF NOT EXISTS dark_mode_type ENUM('off','fixed','sunset') NOT NULL DEFAULT 'off';
ALTER TABLE families ADD COLUMN IF NOT EXISTS dark_mode_start TIME NULL;
ALTER TABLE families ADD COLUMN IF NOT EXISTS dark_mode_end TIME NULL;

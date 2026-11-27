-- Domicile de la famille (position + rayon) pour la fonction "quelqu'un est-il à la maison ?"
-- des minuteurs (alerte différée + notification push). Renseigné par un admin de famille,
-- inactif tant qu'il n'est pas défini (home_lat/home_lng restent NULL).
ALTER TABLE families ADD COLUMN IF NOT EXISTS home_lat DECIMAL(10,7) NULL;
ALTER TABLE families ADD COLUMN IF NOT EXISTS home_lng DECIMAL(10,7) NULL;
ALTER TABLE families ADD COLUMN IF NOT EXISTS home_radius_m INT NOT NULL DEFAULT 150;

-- Dernière position connue d'un membre — jamais un historique (minimisation RGPD, comme les
-- check-ins ponctuels de LocationCheckin) : une seule ligne par utilisateur, écrasée à chaque
-- envoi, effacée dès que le suivi est désactivé. location_tracking_enabled est un opt-out
-- explicite ("Ne plus me suivre") réglable depuis le profil.
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_lat DECIMAL(10,7) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_lng DECIMAL(10,7) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_location_at DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS location_tracking_enabled TINYINT(1) NOT NULL DEFAULT 1;

-- held_for_return : un minuteur arrivé à échéance sans personne à la maison est mis en attente
-- (pas d'alarme sonore/visuelle tant que personne n'est là pour l'entendre) — voir cron.php.
ALTER TABLE family_timer_runs ADD COLUMN IF NOT EXISTS held_for_return TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE family_timer_runs ADD COLUMN IF NOT EXISTS away_notified_at DATETIME NULL;

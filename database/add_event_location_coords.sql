-- Coordonnées de l'adresse d'un événement, pour affichage carte et
-- ouverture dans Google Maps / Waze.
ALTER TABLE events
  ADD COLUMN IF NOT EXISTS location_lat DECIMAL(10,7) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS location_lng DECIMAL(10,7) NULL DEFAULT NULL;

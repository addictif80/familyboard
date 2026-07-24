-- Champs optionnels "Plus d'infos" sur les événements du calendrier :
-- nom du professionnel (médecin, artisan…) et adresse du rendez-vous.
ALTER TABLE events
  ADD COLUMN IF NOT EXISTS professional_name VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS location VARCHAR(500) NULL DEFAULT NULL;

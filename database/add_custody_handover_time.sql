-- Heure de passage unique par planning de garde : les blocs de garde (récurrence, weekends,
-- vacances, événements manuels) ne basculent plus à minuit mais à cette heure précise — ex.
-- "vendredi 18h" plutôt que "samedi 00h00", pour coller à un vrai passage en fin de journée
-- d'école plutôt qu'à la limite calendaire du jour.
ALTER TABLE custody_schedules ADD COLUMN IF NOT EXISTS handover_time TIME NOT NULL DEFAULT '18:00:00';

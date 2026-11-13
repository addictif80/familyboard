-- Visites guidées "Comment ça marche" vues par l'utilisateur (globale + une par module)
ALTER TABLE users ADD COLUMN IF NOT EXISTS tours_seen JSON NULL DEFAULT NULL;

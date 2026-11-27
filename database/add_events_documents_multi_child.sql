-- Sélection de plusieurs enfants (même principe que le portail de liens et le partage
-- d'album) pour les événements et les documents partagés avec un accès co-parent.
ALTER TABLE events    ADD COLUMN IF NOT EXISTS custody_schedule_ids VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS custody_schedule_ids VARCHAR(255) NULL DEFAULT NULL;

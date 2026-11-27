-- Sélection de plusieurs enfants (plutôt qu'une case à cocher unique ou un seul enfant) pour
-- la visibilité co-parent : portail de liens (par famille) et partage d'album.
ALTER TABLE portal_links ADD COLUMN IF NOT EXISTS coparent_schedule_ids VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE albums       ADD COLUMN IF NOT EXISTS custody_schedule_ids  VARCHAR(255) NULL DEFAULT NULL;

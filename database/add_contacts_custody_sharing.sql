-- Partage sélectif d'un contact (médecin, professionnel de santé...) avec un accès co-parent —
-- même mécanisme que Documents/Calendrier/Santé (custody_schedule_ids : CSV des plannings de
-- garde auxquels le contact est visible).
ALTER TABLE contacts ADD COLUMN IF NOT EXISTS custody_schedule_ids VARCHAR(255) NULL DEFAULT NULL;

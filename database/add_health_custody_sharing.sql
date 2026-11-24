-- Partage du carnet de santé (entrées) et du carnet médical (médecins/professionnels de santé)
-- avec un accès co-parent, sur le même principe que les documents et le calendrier
-- (custody_schedule_ids : CSV des plannings de garde auxquels la fiche est visible).
ALTER TABLE health_entries ADD COLUMN IF NOT EXISTS custody_schedule_ids VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE health_doctors ADD COLUMN IF NOT EXISTS custody_schedule_ids VARCHAR(255) NULL DEFAULT NULL;

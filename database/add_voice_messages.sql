-- Messages vocaux : chat familial + journal de communication parental
-- (garde alternée). Un message peut être texte, vocal, ou les deux
-- (une légende texte accompagnant l'enregistrement).

ALTER TABLE messages
  ADD COLUMN IF NOT EXISTS audio_path     VARCHAR(500) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS audio_original VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS audio_mime     VARCHAR(100) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS audio_duration SMALLINT     NULL DEFAULT NULL;

ALTER TABLE comm_log_messages
  ADD COLUMN IF NOT EXISTS audio_path     VARCHAR(500) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS audio_original VARCHAR(255) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS audio_mime     VARCHAR(100) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS audio_duration SMALLINT     NULL DEFAULT NULL;

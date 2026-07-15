-- Garde alternée : "1 weekend sur 2 + un jour fixe en semaine" (ex: un
-- weekend sur deux + le mercredi), un schéma très courant en France en
-- dehors du weekend alterné pur — jusqu'ici seule la peinture manuelle
-- jour par jour permettait de le représenter.

ALTER TABLE custody_schedules
  ADD COLUMN IF NOT EXISTS extra_weekday TINYINT NULL DEFAULT NULL;

ALTER TABLE custody_schedules
MODIFY COLUMN recurrence_type
ENUM('none','every_other_day','every_other_week','every_2weeks','every_month','weekends_every_2','weekends_monthly','weekends_every_2_plus_weekday')
NOT NULL DEFAULT 'none';

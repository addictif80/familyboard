-- Add weekend-only recurrence types to custody schedules
ALTER TABLE custody_schedules
MODIFY COLUMN recurrence_type
ENUM('none','every_other_day','every_other_week','every_2weeks','every_month','weekends_every_2','weekends_monthly')
NOT NULL DEFAULT 'none';

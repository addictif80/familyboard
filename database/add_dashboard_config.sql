-- Dashboard widget config per user
ALTER TABLE users ADD COLUMN IF NOT EXISTS dashboard_config JSON NULL DEFAULT NULL;

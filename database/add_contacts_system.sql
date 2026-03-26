-- Add is_system flag to contacts for auto-seeded entries (e.g. emergency numbers)
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS is_system TINYINT(1) NOT NULL DEFAULT 0;

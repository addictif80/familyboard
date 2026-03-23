-- Migration 005: add timezone to families table
ALTER TABLE families
    ADD COLUMN timezone VARCHAR(50) NOT NULL DEFAULT 'Europe/Paris' AFTER name;

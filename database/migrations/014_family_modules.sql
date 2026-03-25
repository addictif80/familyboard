-- Migration 014: per-family module activation
-- Stores a JSON array of disabled module slugs (e.g. ["custody","cameras"])
-- NULL / empty array means all modules are enabled (default).
ALTER TABLE families ADD COLUMN IF NOT EXISTS disabled_modules JSON NULL DEFAULT NULL;

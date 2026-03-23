-- Migration: noms libres pour les parents dans la garde alternée
-- À exécuter après migration_custody_recurrence.sql

ALTER TABLE custody_schedules
    ADD COLUMN recurrence_parent1_label VARCHAR(100) DEFAULT NULL AFTER recurrence_parent2_id,
    ADD COLUMN recurrence_parent1_color VARCHAR(7) DEFAULT '#4A90D9' AFTER recurrence_parent1_label,
    ADD COLUMN recurrence_parent2_label VARCHAR(100) DEFAULT NULL AFTER recurrence_parent1_color,
    ADD COLUMN recurrence_parent2_color VARCHAR(7) DEFAULT '#E74C3C' AFTER recurrence_parent2_label;

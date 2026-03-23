-- Migration: ajout de la périodicité sur les plannings de garde
-- À exécuter dans phpMyAdmin sur la base existante

ALTER TABLE custody_schedules
    ADD COLUMN recurrence_type ENUM('none','every_other_day','every_other_week','every_2weeks','every_month') DEFAULT 'none' AFTER notes,
    ADD COLUMN recurrence_start DATE DEFAULT NULL AFTER recurrence_type,
    ADD COLUMN recurrence_parent1_id INT DEFAULT NULL AFTER recurrence_start,
    ADD COLUMN recurrence_parent2_id INT DEFAULT NULL AFTER recurrence_parent1_id,
    ADD CONSTRAINT fk_custody_p1 FOREIGN KEY (recurrence_parent1_id) REFERENCES users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_custody_p2 FOREIGN KEY (recurrence_parent2_id) REFERENCES users(id) ON DELETE SET NULL;

-- Nettoyage : nanny_children est remplacée par le registre central family_children (voir
-- add_shared_children_registry.sql) — les entrées d'heures de nounou utilisent désormais
-- family_child_id. Supprime la table et l'ancienne colonne de liaison devenues inutiles.
SET @fk := (
    SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'nanny_hours_entries'
      AND COLUMN_NAME = 'child_id' AND REFERENCED_TABLE_NAME = 'nanny_children'
    LIMIT 1
);
SET @sql := IF(@fk IS NOT NULL, CONCAT('ALTER TABLE nanny_hours_entries DROP FOREIGN KEY ', @fk), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE nanny_hours_entries DROP COLUMN IF EXISTS child_id;
DROP TABLE IF EXISTS nanny_children;

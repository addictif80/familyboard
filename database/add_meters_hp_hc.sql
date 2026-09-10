-- Compteurs électriques à double index (heures pleines / heures creuses) : un compteur peut être
-- marqué has_hp_hc, auquel cas ses relevés utilisent value_hp/value_hc au lieu de l'index unique
-- `value` (deux compteurs cumulatifs indépendants, pas juste une répartition d'un même total).
ALTER TABLE meters
    ADD COLUMN has_hp_hc TINYINT(1) NOT NULL DEFAULT 0 AFTER meter_type;

ALTER TABLE meter_readings
    MODIFY COLUMN `value` DECIMAL(12,2) NULL DEFAULT NULL,
    ADD COLUMN value_hp DECIMAL(12,2) NULL DEFAULT NULL AFTER `value`,
    ADD COLUMN value_hc DECIMAL(12,2) NULL DEFAULT NULL AFTER value_hp;

-- Nettoyage des résidus de l'intégration Kresus (voir l'ancienne
-- remove_kresus_integration.sql, retirée du dépôt car systématiquement en échec en
-- prod pour une raison non reproductible — ce fichier reprend le même nettoyage sous
-- un nouveau nom, pour repartir sur une entrée propre dans _migrations).
DROP TABLE IF EXISTS kresus_accounts;

ALTER TABLE budget_transactions DROP INDEX IF EXISTS uk_budget_external;
ALTER TABLE budget_transactions DROP COLUMN IF EXISTS external_id;
ALTER TABLE budget_transactions DROP COLUMN IF EXISTS source;

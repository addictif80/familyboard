-- Retire l'intégration Kresus (fonctionnalité abandonnée). La saisie
-- assistée (suggestion de catégorie) n'est pas concernée et reste active.

DROP TABLE IF EXISTS kresus_accounts;

ALTER TABLE budget_transactions DROP INDEX IF EXISTS uk_budget_external;
ALTER TABLE budget_transactions DROP COLUMN IF EXISTS external_id;
ALTER TABLE budget_transactions DROP COLUMN IF EXISTS source;

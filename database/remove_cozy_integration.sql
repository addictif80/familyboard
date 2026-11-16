-- Retrait de l'intégration Cozy Cloud (import automatique de factures) : Konnectors
-- communautaires trop instables (sites tiers changeants, anti-bot) pour un usage fiable,
-- abandonné au profit de l'upload manuel de documents.
DROP TABLE IF EXISTS cozy_imported_files;
DROP TABLE IF EXISTS family_cozy_connections;
DELETE FROM app_settings WHERE `key` IN ('cozy_stack_domain', 'cozy_wrapper_url', 'cozy_wrapper_token', 'cozy_sync_last_run');

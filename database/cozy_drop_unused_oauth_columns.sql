-- Le jeton d'accès Cozy est désormais généré directement côté serveur (cozy-stack instances
-- token-oauth, voir App\Core\CozyProvisioner::mintToken()) plutôt que via le parcours OAuth
-- navigateur habituel — client_secret et refresh_token ne sont donc jamais renseignés.
ALTER TABLE family_cozy_connections DROP COLUMN IF EXISTS client_secret;
ALTER TABLE family_cozy_connections DROP COLUMN IF EXISTS refresh_token;

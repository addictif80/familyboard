-- Intégration Vaultwarden (coffre-fort de mots de passe/2FA auto-hébergé) : trace uniquement
-- si FamilyBoard a déjà déclenché une invitation pour ce compte, jamais si l'utilisateur a
-- effectivement terminé la création de son coffre côté Vaultwarden (opaque depuis FamilyBoard,
-- qui ne connaît jamais le mot de passe maître — voir App\Core\Vaultwarden).
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS vault_invited_at DATETIME NULL DEFAULT NULL;

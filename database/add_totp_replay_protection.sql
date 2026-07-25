-- Empêche le rejeu d'un même code TOTP intercepté tant qu'il reste dans sa fenêtre de validité
-- (~90s) : on ne réaccepte jamais un pas de temps déjà consommé pour cet utilisateur.
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_totp_counter BIGINT NULL DEFAULT NULL;

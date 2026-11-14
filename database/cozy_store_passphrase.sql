-- Le mot de passe aléatoire généré à la création de l'instance (voir App\Core\CozyProvisioner)
-- était jusqu'ici jeté immédiatement — mais certains Konnectors (EDF...) n'acceptent d'être
-- configurés que depuis l'application mobile Twake, qui exige ce mot de passe pour s'y
-- connecter (la connexion par lien magique ne couvre que le web). Stocké chiffré via
-- App\Core\Crypto, affiché à la demande de la famille (bouton dédié), jamais ailleurs.
ALTER TABLE family_cozy_connections ADD COLUMN IF NOT EXISTS instance_passphrase TEXT NULL DEFAULT NULL AFTER access_token;

-- Date/heure de dernière connexion d'un utilisateur, affichée dans la liste des utilisateurs
-- (admin famille) — voir App\Core\Session::login(), mise à jour à chaque authentification
-- réussie (connexion directe, 2FA, "se souvenir de moi").
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL DEFAULT NULL;

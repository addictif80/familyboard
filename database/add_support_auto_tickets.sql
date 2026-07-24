-- Remontée automatique de problèmes techniques : un ticket de support peut
-- désormais être créé automatiquement (au nom de l'utilisateur concerné)
-- lorsqu'une erreur technique est détectée pendant sa navigation (voir
-- App\Core\ErrorReporter). error_hash permet de retrouver un ticket récent
-- pour la même erreur/utilisateur plutôt que d'en recréer un à chaque fois.
ALTER TABLE support_tickets
  ADD COLUMN IF NOT EXISTS source     ENUM('user','auto') NOT NULL DEFAULT 'user',
  ADD COLUMN IF NOT EXISTS error_hash VARCHAR(32) NULL DEFAULT NULL,
  ADD INDEX IF NOT EXISTS idx_error_hash (user_id, error_hash);

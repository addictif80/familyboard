-- Permet plusieurs administrateurs par famille (ex. les deux parents) : un admin peut
-- promouvoir un membre ou rétrograder un autre admin. L'administrateur fondateur (le premier
-- admin de la famille, is_founder=1) est protégé : un admin promu ne peut ni le rétrograder ni
-- le retirer de la famille — seul lui-même peut quitter son propre rôle (via la suppression de
-- compte existante, qui gère déjà le transfert de rôle).
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_founder TINYINT(1) NOT NULL DEFAULT 0;

-- Rétro-application : marque comme fondateur le premier admin (par id, donc par ordre de
-- création) de chaque famille existante.
UPDATE users u
JOIN (SELECT family_id, MIN(id) as min_id FROM users WHERE role='admin' GROUP BY family_id) f
  ON u.id = f.min_id
SET u.is_founder = 1;

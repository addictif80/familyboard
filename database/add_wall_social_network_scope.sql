-- Étend le mur social à 3 portées : "personal" (Amis — abonnés via `follows`, désormais
-- utilisable entre deux familles amies, plus seulement à l'intérieur d'une même famille),
-- "family" (ma famille uniquement, inchangé) et la nouvelle "network" (toutes les familles
-- amies de la mienne, voir App\Models\FamilyFriend — publication immédiate, sans validation).
ALTER TABLE posts
  MODIFY COLUMN post_type ENUM('personal','family','network') NOT NULL DEFAULT 'personal';

-- Un album peut être mis en avant dans l'onglet "Photos" du mur, avec la même portée qu'une
-- publication (NULL = non affiché sur le mur, comportement actuel inchangé par défaut).
ALTER TABLE albums
  ADD COLUMN IF NOT EXISTS wall_scope ENUM('personal','family','network') NULL DEFAULT NULL;

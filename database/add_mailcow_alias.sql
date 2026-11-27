-- Alias e-mail par famille (<slug>@<domaine configuré>) redirigeant vers les adresses des
-- membres, géré via l'API d'administration d'une instance Mailcow auto-hébergée — voir
-- App\Core\Mailcow. Le slug est généré une seule fois à la création de la famille (dérivé de
-- son nom) puis figé : le renommage ultérieur de la famille ne doit jamais casser un alias déjà
-- communiqué à des tiers.
ALTER TABLE families ADD COLUMN mail_alias_slug VARCHAR(80) NULL UNIQUE AFTER invite_code;

-- Fonctionnalité d'alias e-mail familial (Mailcow) retirée : jamais utilisée par les
-- utilisateurs en pratique, et son remplacement (grommunio) se heurtait à une limite de
-- licence non viable (voir add_mailcow_alias.sql pour l'historique).
ALTER TABLE families DROP COLUMN IF EXISTS mail_alias_slug;

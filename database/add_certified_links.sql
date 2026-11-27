-- Liens "certifiés" ajoutés par l'administrateur système, diffusés dans le portail de liens
-- de TOUTES les familles (family_id NULL = portée globale plutôt qu'une famille précise).
-- submitted_by/reviewed_by deviennent nullable : l'admin système n'a pas de ligne dans `users`
-- (son compte est distinct, voir AppSetting admin_username/admin_password_hash).
ALTER TABLE portal_links
  MODIFY COLUMN family_id INT NULL DEFAULT NULL,
  MODIFY COLUMN submitted_by INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS certified TINYINT(1) NOT NULL DEFAULT 0;

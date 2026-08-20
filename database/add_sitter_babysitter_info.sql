-- Téléphone de chaque parent (affiché à la baby-sitter, jamais ailleurs de façon automatique) et
-- consignes libres saisies à la création d'un accès baby-sitter.
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL;
ALTER TABLE sitter_links ADD COLUMN IF NOT EXISTS instructions TEXT NULL;

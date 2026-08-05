-- Quand un accès co-parent est supprimé (par l'administrateur de la famille ou par le
-- co-parent lui-même), le contenu qu'il a créé doit rester — jamais de suppression en
-- cascade. On détache donc le compte au lieu de le laisser cascader (ON DELETE CASCADE),
-- en gardant son nom en clair au moment du détachement (même principe que
-- album_photos.uploader_name, déjà utilisé pour les ajouts via lien public).
ALTER TABLE documents MODIFY COLUMN user_id INT NULL;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS former_user_name VARCHAR(100) NULL DEFAULT NULL;

ALTER TABLE events MODIFY COLUMN user_id INT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS former_user_name VARCHAR(100) NULL DEFAULT NULL;

ALTER TABLE comm_log_messages MODIFY COLUMN user_id INT NULL;
ALTER TABLE comm_log_messages ADD COLUMN IF NOT EXISTS former_user_name VARCHAR(100) NULL DEFAULT NULL;

ALTER TABLE portal_links MODIFY COLUMN submitted_by INT NULL;
ALTER TABLE portal_links ADD COLUMN IF NOT EXISTS former_submitted_by_name VARCHAR(100) NULL DEFAULT NULL;

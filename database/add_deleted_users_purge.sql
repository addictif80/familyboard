-- Trace de tout compte supprimé (membre classique, co-parent, ou admin ayant transféré son
-- rôle) — quel que soit qui a initié la suppression (le compte lui-même, l'administrateur de
-- la famille, ou un administrateur système). Le contenu du compte est conservé (voir les
-- colonnes former_*_id ci-dessous) jusqu'à ce qu'un administrateur système décide de le
-- purger définitivement depuis /admin.
CREATE TABLE IF NOT EXISTS deleted_users (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    original_user_id INT          NOT NULL,
    family_id        INT          NOT NULL,
    name             VARCHAR(100) NOT NULL,
    email            VARCHAR(150) NOT NULL,
    role             VARCHAR(20)  NOT NULL,
    deleted_by       ENUM('self','family_admin','system_admin') NOT NULL,
    deleted_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    purged_at        DATETIME     NULL DEFAULT NULL,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    INDEX idx_family (family_id),
    INDEX idx_original_user (original_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Identifiant brut (sans contrainte FK : le compte référencé n'existe plus) permettant de
-- cibler précisément les lignes d'un compte supprimé donné lors d'une purge — plus fiable
-- qu'un rapprochement par nom, qui peut être ambigu en cas d'homonymie.
ALTER TABLE documents ADD COLUMN IF NOT EXISTS former_user_id INT NULL DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS former_user_id INT NULL DEFAULT NULL;
ALTER TABLE comm_log_messages ADD COLUMN IF NOT EXISTS former_user_id INT NULL DEFAULT NULL;
ALTER TABLE portal_links ADD COLUMN IF NOT EXISTS former_submitted_by_id INT NULL DEFAULT NULL;
ALTER TABLE album_photos ADD COLUMN IF NOT EXISTS former_user_id INT NULL DEFAULT NULL;

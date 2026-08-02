-- Transforme le mur familial en réseau social interne : chaque publication est soit
-- personnelle (visible seulement par ses abonnés acceptés + l'auteur), soit "au nom de la
-- famille" (visible par tous, soumise à validation de l'admin de famille).
ALTER TABLE posts
  ADD COLUMN IF NOT EXISTS post_type ENUM('personal','family') NOT NULL DEFAULT 'personal',
  ADD COLUMN IF NOT EXISTS status ENUM('published','pending','rejected') NOT NULL DEFAULT 'published',
  ADD COLUMN IF NOT EXISTS reviewed_by INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL DEFAULT NULL;

-- Les publications déjà existantes avant cette migration restent visibles par toute la
-- famille comme elles l'ont toujours été (pas de rupture pour l'historique) : elles sont
-- donc requalifiées "famille" plutôt que rattachées à leur auteur comme une publication
-- personnelle nouvellement soumise aux règles de suivi.
UPDATE posts SET post_type='family', status='published' WHERE post_type='personal';

CREATE TABLE IF NOT EXISTS follows (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    follower_id   INT      NOT NULL,
    followee_id   INT      NOT NULL,
    status        ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    responded_at  DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uq_follow (follower_id, followee_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followee_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_followee_status (followee_id, status),
    INDEX idx_follower_status (follower_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

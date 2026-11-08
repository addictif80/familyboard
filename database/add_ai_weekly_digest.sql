-- Digest hebdomadaire généré par l'IA (distinct du résumé email "weekly_digest" de cron.php) :
-- un court texte pour la semaine à venir, affiché en popup modal au prochain login/lancement
-- de la PWA. Un seul digest par famille et par semaine (tout le monde voit le même texte) ;
-- chaque utilisateur a sa propre marque "vu" pour ne pas le réafficher en boucle.
CREATE TABLE IF NOT EXISTS ai_weekly_digests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    family_id    INT      NOT NULL,
    week_start   DATE     NOT NULL,
    content      TEXT     NOT NULL,
    generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_family_week (family_id, week_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS last_digest_week_seen DATE NULL DEFAULT NULL;

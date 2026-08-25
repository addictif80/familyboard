-- Partage public (lien, sans compte) d'une liste de tâches/courses : l'invité peut cocher/
-- décocher un élément depuis ce lien, ce qui coche/décoche la même ligne que celle vue par les
-- membres connectés de la famille (une seule source de vérité : la table `tasks`). Un seul lien
-- actif par liste à la fois — le régénérer révoque l'ancien, comme pour les fiches urgence.
CREATE TABLE IF NOT EXISTS task_list_shares (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    list_id     INT NOT NULL,
    token       VARCHAR(64) NOT NULL,
    created_by  INT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_list (list_id),
    UNIQUE KEY uq_token (token),
    FOREIGN KEY (list_id)    REFERENCES task_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

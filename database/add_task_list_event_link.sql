-- Checklist récurrente liée à un événement : une liste de tâches peut être rattachée à un
-- événement du calendrier. À chaque fois qu'elle est rattachée à un événement différent de
-- celui déjà mémorisé (ex. une nouvelle occurrence de "Vacances d'été" créée pour l'année
-- suivante), ses tâches sont automatiquement décochées — pratique pour une checklist de
-- voyage réutilisée d'une fois sur l'autre sans avoir à tout recréer.
ALTER TABLE task_lists
    ADD COLUMN linked_event_id INT NULL DEFAULT NULL AFTER type,
    ADD COLUMN event_reset_at DATETIME NULL DEFAULT NULL AFTER linked_event_id,
    ADD CONSTRAINT fk_task_lists_linked_event FOREIGN KEY (linked_event_id) REFERENCES events(id) ON DELETE SET NULL;

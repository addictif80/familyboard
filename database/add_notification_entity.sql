-- Permet au clic sur une notification d'ouvrir une prévisualisation lecture seule de
-- l'enregistrement concerné (voir NotificationController::preview()), plutôt que de renvoyer
-- systématiquement vers la page du module où il faut chercher soi-même l'élément. `link` reste
-- le lien "page complète" de secours, affiché dans la modal.
ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS entity_type VARCHAR(30) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS entity_id   INT          NULL DEFAULT NULL;

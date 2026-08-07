-- Historique/versioning des documents : à l'ajout d'un document dont le titre ressemble à un
-- document déjà existant (même famille, même type), l'utilisateur peut choisir de le
-- remplacer — l'ancien est alors archivé (retiré de la liste par défaut) plutôt que supprimé,
-- et garde une trace de son remplaçant.
ALTER TABLE documents
    ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN replaced_by_id INT NULL DEFAULT NULL,
    ADD CONSTRAINT fk_documents_replaced_by FOREIGN KEY (replaced_by_id) REFERENCES documents(id) ON DELETE SET NULL;

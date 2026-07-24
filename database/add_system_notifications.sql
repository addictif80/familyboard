-- Contenu détaillé des notifications système envoyées par l'administrateur (titre,
-- texte court affiché dans la cloche/push, et contenu long saisi via l'éditeur WYSIWYG).
-- Table séparée de `notifications` (qui reste une ligne par destinataire) car le
-- contenu long est identique pour tous les destinataires d'une même diffusion.
CREATE TABLE IF NOT EXISTS system_notifications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255) NOT NULL,
    short_text   VARCHAR(500) NOT NULL,
    content_html LONGTEXT     NOT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

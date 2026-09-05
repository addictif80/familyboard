-- Écran de présentation affiché une fois à un compte co-parent nouvellement créé (voir
-- CoparentController::welcome(), InvitationController::accept()) : pas un configurateur, juste
-- un tour de ce qu'il peut faire avec son accès restreint. Ce indicateur évite de le montrer à
-- nouveau automatiquement, sans empêcher de le revoir depuis les réglages.
ALTER TABLE users ADD COLUMN IF NOT EXISTS coparent_welcome_seen_at DATETIME NULL DEFAULT NULL;

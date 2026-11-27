-- Jeton personnel (un par utilisateur) permettant à une app calendrier de smartphone de
-- s'abonner au calendrier global FamilyBoard (événements, garde alternée, congés, calendriers
-- CalDAV déjà synchronisés) sans session ni mot de passe — l'app calendrier interroge ce lien
-- périodiquement, en arrière-plan, ce qu'aucune authentification par cookie ne permettrait.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS calendar_feed_token VARCHAR(64) NULL DEFAULT NULL,
    ADD UNIQUE KEY IF NOT EXISTS uniq_calendar_feed_token (calendar_feed_token);

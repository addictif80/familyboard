-- Les alertes officielles n'écrivent plus dans la table notifications (voir
-- OfficialAlertFeed::notifyConcernedFamilies) : elles ont désormais leur
-- propre page dédiée par catégorie (/alertes/:category), la notification
-- push n'a plus besoin de passer par la cloche. Purge des entrées créées
-- avant ce changement, qui ne seraient sinon jamais nettoyées.
DELETE FROM notifications WHERE type = 'official_alert';

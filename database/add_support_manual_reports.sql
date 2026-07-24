-- Un utilisateur (même sans connaissances techniques) peut signaler
-- lui-même un problème en un clic via le bouton "Signaler un problème" —
-- un diagnostic (navigateur, état du cache/service worker, page…) est
-- alors joint automatiquement au ticket. Distinct de 'auto' (détection
-- automatique d'une erreur) pour que l'admin distingue les deux dans la liste.
ALTER TABLE support_tickets
  MODIFY COLUMN source ENUM('user','auto','manual') NOT NULL DEFAULT 'user';

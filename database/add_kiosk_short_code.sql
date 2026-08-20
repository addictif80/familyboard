-- Code court (6 chiffres) permettant de rattacher une TV connectée à un accès kiosque
-- existant depuis une page dédiée (board.abhd.fr/tv), sans avoir à saisir l'URL complète
-- au clavier/télécommande. Nullable : les liens créés avant cette migration se voient
-- attribuer un code à la volée (KioskLink::ensureShortCode) plutôt que par un backfill SQL.
ALTER TABLE kiosk_links ADD COLUMN short_code VARCHAR(6) NULL UNIQUE AFTER token;

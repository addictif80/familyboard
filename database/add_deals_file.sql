-- Pièce jointe optionnelle (image ou PDF) sur un bon plan — flyer, capture d'écran d'un code
-- promo, bon de réduction scanné... Un seul fichier par bon plan, comme sur le module Garanties.
ALTER TABLE deals ADD COLUMN IF NOT EXISTS file_path     VARCHAR(500) NULL DEFAULT NULL;
ALTER TABLE deals ADD COLUMN IF NOT EXISTS file_original VARCHAR(255) NULL DEFAULT NULL;
ALTER TABLE deals ADD COLUMN IF NOT EXISTS file_mime     VARCHAR(100) NULL DEFAULT NULL;

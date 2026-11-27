-- Le générateur de courriers est un outil de courrier POSTAL (impression), pas d'envoi par
-- e-mail : retrait de la piste d'envoi (colonne + journal), ajoutée puis retirée avant toute
-- mise en production.
DROP TABLE IF EXISTS letter_sends;
ALTER TABLE letters DROP COLUMN recipient_email;

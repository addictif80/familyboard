-- Permet de renvoyer le rapport de suppression (motif + page de données) après coup, aussi bien
-- depuis l'admin système que via la demande en libre-service d'un ex-titulaire de compte (voir
-- App\Controllers\DeletedAccountController) — le motif et l'export sont capturés une seule fois
-- au moment de la suppression (AccountDeletion::deleteUser()), avant toute anonymisation ou
-- purge du contenu, pour rester disponibles même après une purge définitive.
ALTER TABLE deleted_users
  ADD COLUMN IF NOT EXISTS reason VARCHAR(1000) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS data_export_html LONGTEXT NULL DEFAULT NULL;

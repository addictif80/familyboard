-- Partage de dépenses entre co-parents : réutilise les additions existantes (le modèle de
-- partage, "le participant doit X % / X € au créateur", couvre déjà exactement un partage
-- 50/50) plutôt qu'un système séparé. Deux manques comblés ici :
-- 1) un co-parent (accès via custody_access, pas forcément "famille amie") n'était pas une
--    cible d'invitation valide — voir AdditionController::inviteParticipant() ;
-- 2) une addition classique (hors cagnotte) n'avait aucun suivi de règlement individuel.
ALTER TABLE addition_participants
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL DEFAULT NULL;

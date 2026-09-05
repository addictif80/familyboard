-- Configurateur familial affiché après inscription (enfants, invitations, scolarité,
-- activité pro, budget) — voir App\Controllers\OnboardingController. Passable à tout moment ;
-- ce indicateur permet de ne plus le proposer automatiquement une fois terminé/passé, sans
-- empêcher de le relancer manuellement depuis les réglages.
ALTER TABLE families ADD COLUMN IF NOT EXISTS onboarding_completed_at DATETIME NULL DEFAULT NULL;

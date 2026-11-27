-- Suivi des abonnements récurrents : étend les dépenses/revenus récurrents existants
-- (budget_recurring) plutôt que de créer un module séparé — un abonnement EST une dépense
-- récurrente, juste pas toujours mensuelle (assurances, cotisations annuelles...).
-- frequency/renewal_month permettent une récurrence annuelle en plus du mensuel existant ;
-- provider est un champ d'affichage libre ("Netflix", "MAIF"...), distinct de la catégorie
-- budgétaire générique.
ALTER TABLE budget_recurring
  ADD COLUMN IF NOT EXISTS frequency ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
  ADD COLUMN IF NOT EXISTS renewal_month TINYINT NULL DEFAULT NULL COMMENT '1-12, utilisé seulement si frequency=yearly',
  ADD COLUMN IF NOT EXISTS provider VARCHAR(150) NULL DEFAULT NULL;

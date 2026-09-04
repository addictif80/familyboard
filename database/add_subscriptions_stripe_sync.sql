-- Synchronisation automatique des Produits/Prix Stripe pour les paliers (voir
-- StripeGateway::syncPlanPrices(), appelé depuis AdminController::savePlan()) : l'admin
-- ne saisit plus les ID Stripe à la main, ils sont créés/mis à jour automatiquement à
-- chaque enregistrement d'un palier. Un seul Produit Stripe par palier, avec un Prix par
-- fréquence (mensuel/annuel) — un Prix Stripe étant immuable, un changement de tarif crée
-- un nouveau Prix et archive l'ancien.

ALTER TABLE plans ADD COLUMN IF NOT EXISTS stripe_product_id VARCHAR(100) NULL DEFAULT NULL AFTER price_yearly_cents;

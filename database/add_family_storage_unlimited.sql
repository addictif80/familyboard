-- Quota familial illimité PAR FAMILLE (en plus du réglage global storage_family_quota_unlimited
-- qui s'applique à toutes les familles à la fois) — réservé à l'admin système, même pattern que
-- users.storage_quota_unlimited (quota perso par membre). Par défaut 0 : aucun changement pour
-- une famille déjà en place.
ALTER TABLE families ADD COLUMN IF NOT EXISTS storage_quota_unlimited TINYINT(1) NOT NULL DEFAULT 0;

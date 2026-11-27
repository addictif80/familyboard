-- Correction assistée par IA pour les erreurs techniques auto-détectées (voir
-- App\Core\AiCodeFixer / App\Core\GitHubPublisher) : error_file/error_line permettent de
-- retrouver le fichier/ligne en cause sans parser le texte libre du premier message ;
-- ai_fix_* trace la proposition de correctif en cours (une seule à la fois par ticket —
-- une nouvelle analyse remplace la précédente tant qu'aucune Pull Request n'est ouverte).
ALTER TABLE support_tickets
  ADD COLUMN IF NOT EXISTS error_file VARCHAR(500) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS error_line INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_fix_status ENUM('none','proposed','pr_open','error') NOT NULL DEFAULT 'none',
  ADD COLUMN IF NOT EXISTS ai_fix_diagnosis TEXT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_fix_relative_path VARCHAR(500) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_fix_original_content MEDIUMTEXT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_fix_proposed_content MEDIUMTEXT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_fix_pr_url VARCHAR(500) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_fix_error TEXT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS ai_fix_updated_at DATETIME NULL DEFAULT NULL;

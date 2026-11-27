-- Veille informationnelle : alertes détectées par mots-clés dans des flux RSS d'actualité
-- français (aucun flux officiel temps réel n'existe pour toutes les catégories visées —
-- FR-Alert n'a pas d'API, l'alerte enlèvement n'a pas de flux RSS actif, Météo-France
-- Vigilance nécessite une clé API — voir App\Core\OfficialAlertFeed). Cette table stocke
-- les correspondances trouvées, une ligne par (article, ville concernée) pour les
-- catégories géolocalisées (city_match NULL = alerte nationale).
CREATE TABLE IF NOT EXISTS official_alerts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category      ENUM('enlevement','canicule','inondation','feu_foret','climatique','industrielle') NOT NULL,
    title         VARCHAR(500) NOT NULL,
    summary       TEXT         NULL DEFAULT NULL,
    source_name   VARCHAR(150) NOT NULL,
    source_url    VARCHAR(1000) NOT NULL,
    city_match    VARCHAR(100) NULL DEFAULT NULL,
    dedupe_key    VARCHAR(64)  NOT NULL,
    published_at  DATETIME     NULL DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_dedupe (dedupe_key),
    INDEX idx_category (category),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registre central "Enfants de la famille" (voir App\Models\FamilyChild) : jusqu'ici, un
-- enfant devait être recréé indépendamment dans chaque module (Suivi scolaire, Suivi nounou,
-- Garde alternée, Bébé). family_children devient la fiche d'identité unique (nom, couleur,
-- date de naissance), réutilisable partout via un family_child_id optionnel — chaque module
-- garde ses propres données spécifiques (notes scolaires, planning de garde, agenda bébé...),
-- il ne fait plus que s'y rattacher.
--
-- Migration automatique des fiches déjà existantes : school_students amorce le registre (une
-- fiche élève = une fiche enfant, rattachement exact via une clé de migration temporaire), puis
-- nanny_children, babies et les enfants de custody_schedules sont rattachés à une fiche existante
-- du même nom (insensible à la casse, au sein de la même famille) si elle existe déjà, sinon une
-- nouvelle fiche est créée — best-effort : deux enfants distincts portant exactement le même
-- prénom dans la même famille seront fusionnés en une seule fiche (cas limite, à séparer
-- manuellement depuis le registre si besoin).

CREATE TABLE IF NOT EXISTS family_children (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    name        VARCHAR(100) NOT NULL,
    color       VARCHAR(7) NOT NULL DEFAULT '#4A90D9',
    birth_date  DATE NULL DEFAULT NULL,
    avatar      VARCHAR(255) NULL DEFAULT NULL,
    created_by  INT NULL DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colonne de corrélation utilisée uniquement le temps de cette migration (retirée à la fin).
ALTER TABLE family_children ADD COLUMN IF NOT EXISTS _migrate_key VARCHAR(20) NULL DEFAULT NULL;

-- ── 1) Suivi scolaire : amorce le registre (une fiche élève = une fiche enfant) ────────────
ALTER TABLE school_students ADD COLUMN IF NOT EXISTS family_child_id INT NULL DEFAULT NULL;

INSERT INTO family_children (family_id, name, color, created_by, created_at, _migrate_key)
SELECT ss.family_id, ss.name, ss.color, ss.created_by, ss.created_at, CONCAT('ss:', ss.id)
FROM school_students ss
WHERE ss.family_child_id IS NULL;

UPDATE school_students ss
JOIN family_children fc ON fc._migrate_key = CONCAT('ss:', ss.id)
SET ss.family_child_id = fc.id
WHERE ss.family_child_id IS NULL;

ALTER TABLE school_students
  ADD CONSTRAINT fk_school_students_family_child FOREIGN KEY (family_child_id) REFERENCES family_children(id) ON DELETE SET NULL;

-- ── 2) Suivi nounou : dédoublonnage par nom, puis rattachement des entrées d'heures ────────
INSERT INTO family_children (family_id, name, color, created_by, created_at)
SELECT nc.family_id, nc.name, nc.color, nc.created_by, nc.created_at
FROM nanny_children nc
WHERE NOT EXISTS (
    SELECT 1 FROM family_children fc
    WHERE fc.family_id = nc.family_id AND LOWER(TRIM(fc.name)) = LOWER(TRIM(nc.name))
);

ALTER TABLE nanny_hours_entries ADD COLUMN IF NOT EXISTS family_child_id INT NULL DEFAULT NULL;

UPDATE nanny_hours_entries nhe
JOIN nanny_children nc  ON nc.id = nhe.child_id
JOIN family_children fc ON fc.family_id = nc.family_id AND LOWER(TRIM(fc.name)) = LOWER(TRIM(nc.name))
SET nhe.family_child_id = fc.id
WHERE nhe.child_id IS NOT NULL;

ALTER TABLE nanny_hours_entries
  ADD CONSTRAINT fk_nanny_hours_family_child FOREIGN KEY (family_child_id) REFERENCES family_children(id) ON DELETE SET NULL;

-- ── 3) Bébé : dédoublonnage par nom (utile pour un enfant déjà suivi ailleurs) ─────────────
INSERT INTO family_children (family_id, name, color, birth_date, avatar, created_at)
SELECT b.family_id, b.name, '#4A90D9', b.birth_date, b.avatar, b.created_at
FROM babies b
WHERE NOT EXISTS (
    SELECT 1 FROM family_children fc
    WHERE fc.family_id = b.family_id AND LOWER(TRIM(fc.name)) = LOWER(TRIM(b.name))
);

ALTER TABLE babies ADD COLUMN IF NOT EXISTS family_child_id INT NULL DEFAULT NULL;

UPDATE babies b
JOIN family_children fc ON fc.family_id = b.family_id AND LOWER(TRIM(fc.name)) = LOWER(TRIM(b.name))
SET b.family_child_id = fc.id;

ALTER TABLE babies
  ADD CONSTRAINT fk_babies_family_child FOREIGN KEY (family_child_id) REFERENCES family_children(id) ON DELETE SET NULL;

-- ── 4) Garde alternée : dédoublonnage par nom (le planning existant reste inchangé, seul le
--      rattachement est ajouté — child_name/color restent la source d'affichage du planning) ──
INSERT INTO family_children (family_id, name, color, created_at)
SELECT cs.family_id, cs.child_name, MIN(cs.color), MIN(cs.created_at)
FROM custody_schedules cs
WHERE NOT EXISTS (
    SELECT 1 FROM family_children fc
    WHERE fc.family_id = cs.family_id AND LOWER(TRIM(fc.name)) = LOWER(TRIM(cs.child_name))
)
GROUP BY cs.family_id, cs.child_name;

ALTER TABLE custody_schedules ADD COLUMN IF NOT EXISTS family_child_id INT NULL DEFAULT NULL;

UPDATE custody_schedules cs
JOIN family_children fc ON fc.family_id = cs.family_id AND LOWER(TRIM(fc.name)) = LOWER(TRIM(cs.child_name))
SET cs.family_child_id = fc.id;

ALTER TABLE custody_schedules
  ADD CONSTRAINT fk_custody_schedules_family_child FOREIGN KEY (family_child_id) REFERENCES family_children(id) ON DELETE SET NULL;

ALTER TABLE family_children DROP COLUMN _migrate_key;

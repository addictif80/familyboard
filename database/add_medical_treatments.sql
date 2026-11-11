-- Module "Traitement médical" (voir App\Controllers\MedicalTreatmentController) — distinct du
-- module "Santé" existant (health_entries, partagé à toute la famille) : ici, chaque traitement
-- est privé au membre concerné (owner_type='user') ou, pour un enfant du registre partagé
-- (owner_type='child'), géré par un admin de la famille au nom de l'enfant — jamais visible des
-- autres membres adultes.
CREATE TABLE IF NOT EXISTS medical_treatments (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    family_id        INT NOT NULL,
    owner_type       ENUM('user','child') NOT NULL,
    owner_user_id    INT NULL DEFAULT NULL,
    owner_child_id   INT NULL DEFAULT NULL,
    created_by       INT NOT NULL,
    name             VARCHAR(150) NOT NULL,
    dosage           VARCHAR(150) NOT NULL,
    notes            TEXT NULL DEFAULT NULL,
    start_date       DATE NOT NULL,
    end_date         DATE NULL DEFAULT NULL,
    -- 'fixed' : une ou plusieurs heures précises par jour (fixed_times, ex. ["08:00","20:00"]).
    -- 'interval' : rappel toutes les interval_hours heures à partir de interval_anchor.
    schedule_type    ENUM('fixed','interval') NOT NULL DEFAULT 'fixed',
    fixed_times      JSON NULL DEFAULT NULL,
    interval_hours   INT NULL DEFAULT NULL,
    interval_anchor  DATETIME NULL DEFAULT NULL,
    active           TINYINT NOT NULL DEFAULT 1,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)      REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_user_id)  REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (owner_child_id) REFERENCES family_children(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)     REFERENCES users(id)     ON DELETE CASCADE,
    INDEX idx_family (family_id),
    INDEX idx_owner_user (owner_user_id),
    INDEX idx_owner_child (owner_child_id),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des rappels déjà envoyés — purement pour l'idempotence du cron (voir cron.php,
-- sendMedicalTreatmentReminders) : empêche un double envoi du même créneau le même jour si le
-- cron repasse dans sa fenêtre de tolérance. slot_key = l'heure du créneau ("08:00"), calculée
-- aussi bien pour un horaire fixe que pour un rappel par intervalle.
CREATE TABLE IF NOT EXISTS medical_treatment_reminders (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    treatment_id   INT NOT NULL,
    slot_key       VARCHAR(10) NOT NULL,
    reminded_date  DATE NOT NULL,
    reminded_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (treatment_id) REFERENCES medical_treatments(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_slot (treatment_id, slot_key, reminded_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Garde alternée v2 : jour de passation, répartition dédiée pendant les
-- vacances, et accès restreint pour le parent qui n'a pas de compte
-- FamilyBoard (ou qui a le sien, dans une autre famille).

ALTER TABLE custody_schedules
  ADD COLUMN IF NOT EXISTS handover_weekday TINYINT NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS custody_vacation_periods (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id       INT          NOT NULL,
    label             VARCHAR(100) NULL DEFAULT NULL,
    start_date        DATE         NOT NULL,
    end_date          DATE         NOT NULL,
    distribution_type ENUM('1week_2','2weeks_4','odd_even_weeks') NOT NULL DEFAULT '1week_2',
    starting_parent   TINYINT      NOT NULL DEFAULT 1,
    created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES custody_schedules(id) ON DELETE CASCADE,
    INDEX idx_schedule (schedule_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
  MODIFY COLUMN role ENUM('admin','member','coparent') NOT NULL DEFAULT 'member';

-- Table de liaison indépendante des familles : un utilisateur (qu'il ait ou
-- non sa propre famille FamilyBoard) peut se voir accorder l'accès à un ou
-- plusieurs plannings de garde appartenant à une AUTRE famille.
CREATE TABLE IF NOT EXISTS custody_access (
    user_id     INT      NOT NULL,
    schedule_id INT      NOT NULL,
    granted_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, schedule_id),
    FOREIGN KEY (user_id)     REFERENCES users(id)             ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES custody_schedules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tagging d'un enfant "garde alternée" sur documents, événements calendrier
-- et journal parental, pour scoper la vue du co-parent restreint.
ALTER TABLE documents ADD COLUMN IF NOT EXISTS custody_schedule_id INT NULL DEFAULT NULL;
ALTER TABLE events    ADD COLUMN IF NOT EXISTS custody_schedule_id INT NULL DEFAULT NULL;
ALTER TABLE comm_log_messages ADD COLUMN IF NOT EXISTS custody_schedule_id INT NULL DEFAULT NULL;

ALTER TABLE documents
  ADD CONSTRAINT fk_documents_custody FOREIGN KEY (custody_schedule_id) REFERENCES custody_schedules(id) ON DELETE SET NULL;
ALTER TABLE events
  ADD CONSTRAINT fk_events_custody FOREIGN KEY (custody_schedule_id) REFERENCES custody_schedules(id) ON DELETE SET NULL;
ALTER TABLE comm_log_messages
  ADD CONSTRAINT fk_commlog_custody FOREIGN KEY (custody_schedule_id) REFERENCES custody_schedules(id) ON DELETE SET NULL;

-- Invitations restreintes (co-parent)
ALTER TABLE invitation_tokens
  ADD COLUMN IF NOT EXISTS invite_role VARCHAR(20) NOT NULL DEFAULT 'member',
  ADD COLUMN IF NOT EXISTS custody_schedule_ids VARCHAR(255) NULL DEFAULT NULL;

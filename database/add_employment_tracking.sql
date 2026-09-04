-- Suivi salarié : suivi de l'EMPLOI d'un membre de la famille (l'employeur est une entreprise,
-- recherchée par SIREN — voir App\Core\SirenLookup), pas un salarié employé par la famille.
-- Tous les montants sont estimés (taux de cotisation/prélèvement à la source paramétrables par
-- l'utilisateur), jamais un substitut à un vrai bulletin de paie — voir l'avertissement affiché
-- dans templates/employment/index.php.

CREATE TABLE IF NOT EXISTS employment_profiles (
    id                          INT AUTO_INCREMENT PRIMARY KEY,
    family_id                   INT          NOT NULL,
    user_id                     INT          NOT NULL, -- membre de la famille concerné
    employer_siren              VARCHAR(9)   NULL DEFAULT NULL,
    employer_name               VARCHAR(255) NULL DEFAULT NULL,
    employer_address            VARCHAR(255) NULL DEFAULT NULL,
    job_title                   VARCHAR(150) NULL DEFAULT NULL,
    contract_type               ENUM('cdi','cdd','temps_partiel','apprentissage','autre') NOT NULL DEFAULT 'cdi',
    hire_date                   DATE         NULL DEFAULT NULL,
    trial_period_end            DATE         NULL DEFAULT NULL,
    color                       VARCHAR(7)   NOT NULL DEFAULT '#4A90D9',

    -- Rémunération : taux horaire toujours renseigné (sert aussi de référence pour les heures
    -- sup even en mode mensualisé) ; salaire mensuel fixe optionnel qui remplace le calcul sur
    -- heures pour les heures "normales" du mois.
    pay_mode                    ENUM('hourly','monthly') NOT NULL DEFAULT 'hourly',
    hourly_rate_cents           INT          NULL DEFAULT NULL,
    monthly_gross_cents         INT          NULL DEFAULT NULL,
    contractual_weekly_hours    DECIMAL(5,2) NOT NULL DEFAULT 35,
    overtime_threshold_hours    DECIMAL(5,2) NOT NULL DEFAULT 8, -- heures sup/semaine avant la 2e tranche
    overtime_rate1_pct          DECIMAL(5,2) NOT NULL DEFAULT 25,
    overtime_rate2_pct          DECIMAL(5,2) NOT NULL DEFAULT 50,

    -- Congés payés / RTT : ancre annuelle (jour+mois) de remise à zéro, taux d'acquisition
    -- mensuel des congés, dotation annuelle de RTT (prorata mensuel appliqué au calcul).
    leave_reset_month           TINYINT      NOT NULL DEFAULT 6,
    leave_reset_day             TINYINT      NOT NULL DEFAULT 1,
    leave_accrual_days_per_month DECIMAL(4,2) NOT NULL DEFAULT 2.5,
    rtt_days_per_year           DECIMAL(5,2) NOT NULL DEFAULT 0,

    -- Estimation de paie.
    cotisation_rate_pct         DECIMAL(5,2) NULL DEFAULT NULL, -- brut -> net social
    pas_rate_pct                DECIMAL(5,2) NULL DEFAULT NULL, -- net social -> net à verser (PAS)

    created_by                  INT          NOT NULL,
    created_at                  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id) REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Planning récurrent hebdomadaire (gabarit par défaut).
CREATE TABLE IF NOT EXISTS employment_work_schedule (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    profile_id    INT NOT NULL,
    day_of_week   TINYINT NOT NULL, -- 1=lundi ... 7=dimanche
    start_time    TIME NOT NULL,
    end_time      TIME NOT NULL,
    break_minutes INT NOT NULL DEFAULT 0,
    FOREIGN KEY (profile_id) REFERENCES employment_profiles(id) ON DELETE CASCADE,
    INDEX idx_profile (profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Correctifs jour par jour : remplace le gabarit récurrent pour une date précise (heures
-- différentes, ou hours_worked=0 pour marquer un jour normalement travaillé comme non travaillé
-- ce jour-là, sans passer par une absence/congé).
CREATE TABLE IF NOT EXISTS employment_schedule_exceptions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    profile_id     INT NOT NULL,
    exception_date DATE NOT NULL,
    hours_worked   DECIMAL(5,2) NOT NULL DEFAULT 0,
    note           VARCHAR(255) NULL DEFAULT NULL,
    UNIQUE KEY uq_profile_date (profile_id, exception_date),
    FOREIGN KEY (profile_id) REFERENCES employment_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Corrections manuelles des compteurs congés/RTT (report d'une période précédente,
-- régularisation...).
CREATE TABLE IF NOT EXISTS employment_leave_adjustments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    profile_id      INT NOT NULL,
    leave_type      ENUM('paid_leave','rtt') NOT NULL,
    adjustment_date DATE NOT NULL,
    days            DECIMAL(5,2) NOT NULL, -- positif ou négatif
    note            VARCHAR(255) NULL DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES employment_profiles(id) ON DELETE CASCADE,
    INDEX idx_profile (profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Primes ponctuelles rattachées à un mois de paie (13e mois, prime exceptionnelle...).
CREATE TABLE IF NOT EXISTS employment_primes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    profile_id   INT NOT NULL,
    period_year  SMALLINT NOT NULL,
    period_month TINYINT NOT NULL,
    label        VARCHAR(150) NOT NULL,
    amount_cents INT NOT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES employment_profiles(id) ON DELETE CASCADE,
    INDEX idx_profile_period (profile_id, period_year, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estimation de paie mensuelle calculée (historisée — un recalcul écrase le mois concerné).
CREATE TABLE IF NOT EXISTS employment_payslips (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    profile_id            INT NOT NULL,
    period_year           SMALLINT NOT NULL,
    period_month          TINYINT NOT NULL,
    worked_hours          DECIMAL(6,2) NOT NULL DEFAULT 0,
    overtime_tier1_hours  DECIMAL(6,2) NOT NULL DEFAULT 0,
    overtime_tier2_hours  DECIMAL(6,2) NOT NULL DEFAULT 0,
    base_gross_cents      INT NOT NULL DEFAULT 0,
    overtime_gross_cents  INT NOT NULL DEFAULT 0,
    primes_cents          INT NOT NULL DEFAULT 0,
    gross_total_cents     INT NOT NULL DEFAULT 0,
    cotisation_rate_pct   DECIMAL(5,2) NULL DEFAULT NULL,
    net_social_cents      INT NOT NULL DEFAULT 0,
    pas_rate_pct          DECIMAL(5,2) NULL DEFAULT NULL,
    net_a_verser_cents    INT NOT NULL DEFAULT 0,
    computed_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_profile_period (profile_id, period_year, period_month),
    FOREIGN KEY (profile_id) REFERENCES employment_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arrêts de travail (maladie), avec indemnités journalières saisies manuellement (montant
-- communiqué par l'Assurance Maladie — trop de cas particuliers pour un calcul automatique
-- fiable : carence, subrogation, complément employeur...).
CREATE TABLE IF NOT EXISTS employment_sick_leaves (
    id                       INT AUTO_INCREMENT PRIMARY KEY,
    profile_id               INT NOT NULL,
    start_date                DATE NOT NULL,
    end_date                  DATE NOT NULL,
    reason                     VARCHAR(255) NULL DEFAULT NULL,
    ijss_total_cents           INT NULL DEFAULT NULL,
    employer_complement_cents  INT NULL DEFAULT NULL,
    notes                      TEXT NULL DEFAULT NULL,
    created_by                 INT NOT NULL,
    created_at                 DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (profile_id) REFERENCES employment_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_profile (profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents liés (module Documents existant, même principe que school_student_documents) :
-- bulletins de paie scannés, arrêts de travail, contrat de travail...
CREATE TABLE IF NOT EXISTS employment_documents (
    profile_id  INT NOT NULL,
    document_id INT NOT NULL,
    PRIMARY KEY (profile_id, document_id),
    FOREIGN KEY (profile_id)  REFERENCES employment_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tag d'un événement du Calendrier familial comme congé payé / RTT / absence non payée d'un
-- profil salarié — réutilise le calendrier existant plutôt que d'en dupliquer un, sur le même
-- principe que custody_schedule_id (voir add_custody_coparent.sql).
ALTER TABLE events ADD COLUMN IF NOT EXISTS employment_profile_id INT NULL DEFAULT NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS employment_leave_type ENUM('paid_leave','rtt','unpaid') NULL DEFAULT NULL;
ALTER TABLE events ADD CONSTRAINT fk_events_employment_profile
    FOREIGN KEY (employment_profile_id) REFERENCES employment_profiles(id) ON DELETE SET NULL;

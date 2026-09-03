-- Module "Suivi scolaire" : élèves de la famille (indépendant de la garde alternée — fonctionne
-- aussi pour les familles à foyer unique), avec emploi du temps, matières/professeurs, notes,
-- absences, activités extra-scolaires et documents (bulletins compris).
CREATE TABLE IF NOT EXISTS school_students (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    family_id   INT NOT NULL,
    name        VARCHAR(150) NOT NULL,
    school_name VARCHAR(255) DEFAULT '',
    class_name  VARCHAR(100) DEFAULT '',
    color       VARCHAR(7) NOT NULL DEFAULT '#4A90D9',
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (family_id)  REFERENCES families(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE,
    INDEX idx_family (family_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Matière + nom du professeur (le sport/EPS est une matière comme les autres, pas de section
-- séparée) — propre à chaque élève, deux enfants dans la même classe peuvent avoir des
-- professeurs différents selon les options.
CREATE TABLE IF NOT EXISTS school_subjects (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    name         VARCHAR(150) NOT NULL,
    teacher_name VARCHAR(150) DEFAULT '',
    color        VARCHAR(7) NOT NULL DEFAULT '#8E44AD',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES school_students(id) ON DELETE CASCADE,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emploi du temps hebdomadaire (récurrent, pas daté) : un créneau = un jour de semaine + horaire.
CREATE TABLE IF NOT EXISTS school_timetable_slots (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    subject_id  INT NOT NULL,
    day_of_week TINYINT NOT NULL, -- 1=lundi ... 7=dimanche
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    room        VARCHAR(100) DEFAULT '',
    FOREIGN KEY (student_id) REFERENCES school_students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES school_subjects(id) ON DELETE CASCADE,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_grades (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    subject_id  INT NOT NULL,
    title       VARCHAR(255) DEFAULT '',
    grade_value DECIMAL(5,2) NOT NULL,
    grade_max   DECIMAL(5,2) NOT NULL DEFAULT 20,
    grade_date  DATE NOT NULL,
    comment     VARCHAR(255) DEFAULT '',
    created_by  INT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES school_students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES school_subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)           ON DELETE CASCADE,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_absences (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    subject_id   INT NULL,
    absence_date DATE NOT NULL,
    duration     VARCHAR(50) DEFAULT '',
    reason       VARCHAR(255) DEFAULT '',
    justified    TINYINT(1) NOT NULL DEFAULT 0,
    created_by   INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES school_students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES school_subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)           ON DELETE CASCADE,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_activities (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    name          VARCHAR(150) NOT NULL,
    location      VARCHAR(255) DEFAULT '',
    schedule_info VARCHAR(255) DEFAULT '',
    contact_info  VARCHAR(255) DEFAULT '',
    notes         TEXT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES school_students(id) ON DELETE CASCADE,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents (bulletins compris) — même schéma minimal que dispute_documents.
CREATE TABLE IF NOT EXISTS school_documents (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    uploaded_by   INT NOT NULL,
    title         VARCHAR(255) NOT NULL,
    doc_type      ENUM('bulletin','other') NOT NULL DEFAULT 'other',
    file_path     VARCHAR(500) NOT NULL,
    file_original VARCHAR(255) NOT NULL,
    file_mime     VARCHAR(150) NOT NULL,
    uploaded_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)  REFERENCES school_students(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)           ON DELETE CASCADE,
    INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

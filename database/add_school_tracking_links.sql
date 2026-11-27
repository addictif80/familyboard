-- Rattache une fiche élève à : un compte membre de la famille (l'élève lui-même, s'il a son
-- propre compte), un compte co-parent, une liste de tâches/courses existante, et un ou
-- plusieurs documents du module Documents.
--
-- Le compte membre lié garde tous ses droits normaux dans FamilyBoard, mais voit SES PROPRES
-- notes/absences/bulletins en lecture seule (jamais les siens en écriture, jamais ceux d'un
-- autre enfant de la fratrie). Le compte co-parent lié, lui, n'a accès qu'à cette seule fiche
-- élève et uniquement en lecture — appliqué côté contrôleur (SchoolController), pas ici.
ALTER TABLE school_students ADD COLUMN linked_user_id INT NULL AFTER family_id;
ALTER TABLE school_students ADD COLUMN linked_coparent_id INT NULL AFTER linked_user_id;
ALTER TABLE school_students ADD COLUMN linked_task_list_id INT NULL AFTER linked_coparent_id;

ALTER TABLE school_students ADD CONSTRAINT fk_school_students_linked_user
    FOREIGN KEY (linked_user_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE school_students ADD CONSTRAINT fk_school_students_linked_coparent
    FOREIGN KEY (linked_coparent_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE school_students ADD CONSTRAINT fk_school_students_linked_tasklist
    FOREIGN KEY (linked_task_list_id) REFERENCES task_lists(id) ON DELETE SET NULL;

-- Documents liés (plusieurs possibles par élève, ex. certificat de scolarité + bulletin scanné
-- déjà présent dans le module Documents plutôt que réuploadé ici).
CREATE TABLE IF NOT EXISTS school_student_documents (
    student_id  INT NOT NULL,
    document_id INT NOT NULL,
    PRIMARY KEY (student_id, document_id),
    FOREIGN KEY (student_id)  REFERENCES school_students(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

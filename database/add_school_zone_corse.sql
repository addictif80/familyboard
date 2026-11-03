-- Ajoute le support de l'académie de Corse aux vacances scolaires : zone CHAR(1) ('A'/'B'/'C')
-- ne peut pas stocker "Corse", d'où l'élargissement à VARCHAR(10).
ALTER TABLE families
    MODIFY COLUMN school_zone VARCHAR(10) NULL DEFAULT NULL
        COMMENT 'Zone scolaire : A, B, C ou Corse';

ALTER TABLE school_holidays
    MODIFY COLUMN zone VARCHAR(10) NOT NULL;

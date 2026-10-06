-- ==========================================================
-- EduSuivi — Migration v9 : Évaluations et Maxima par Période
-- ==========================================================

-- 1. Table des évaluations élémentaires (Devoirs, Interrogations, Exercices, Examens)
CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    evaluation_type ENUM('devoir', 'interrogation', 'exercice', 'examen') NOT NULL,
    title VARCHAR(100) DEFAULT NULL,
    period VARCHAR(50) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    max_score DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    eval_date DATE DEFAULT CURRENT_DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 2. Table de configuration du maxima par période et matière pour chaque classe
CREATE TABLE IF NOT EXISTS period_maxima (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    period VARCHAR(50) NOT NULL,
    max_grade DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    UNIQUE KEY unique_class_sub_period (class_id, subject_id, period),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

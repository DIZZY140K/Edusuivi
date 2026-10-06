-- ==========================================================
-- EduSuivi — Schéma de Base de Données Unifié & Consolidé
-- À importer directement dans phpMyAdmin (ou exécuté via setup.php)
-- ==========================================================

CREATE DATABASE IF NOT EXISTS edusuivi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edusuivi;

-- 1. Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher','parent') NOT NULL,
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Table des classes
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    level VARCHAR(50) NOT NULL,
    teacher_id INT DEFAULT NULL,
    max_students INT DEFAULT 40,
    room VARCHAR(50) DEFAULT NULL,
    school_year VARCHAR(20) DEFAULT '2024-2025',
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3. Table des matières
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- 4. Table des élèves
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricule VARCHAR(20) DEFAULT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    gender ENUM('M','F') DEFAULT 'M',
    option_filiere VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    school_year VARCHAR(20) DEFAULT '2024-2025',
    class_id INT,
    parent_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. Affectation enseignant -> classe/matière
CREATE TABLE IF NOT EXISTS teacher_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE(teacher_id, class_id, subject_id)
) ENGINE=InnoDB;

-- 6. Table des notes
CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    grade DECIMAL(5,2) NOT NULL,
    max_grade DECIMAL(5,2) DEFAULT 20,
    period VARCHAR(30) DEFAULT '1er Trimestre',
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_grades_student (student_id),
    INDEX idx_grades_period (period)
) ENGINE=InnoDB;

-- 7. Table des absences
CREATE TABLE IF NOT EXISTS absences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT DEFAULT NULL,
    date DATE NOT NULL,
    justified TINYINT(1) DEFAULT 0,
    reason TEXT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_absences_student (student_id),
    INDEX idx_absences_date (date)
) ENGINE=InnoDB;

-- 8. Table des annonces
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    target ENUM('all','teachers','parents','class') DEFAULT 'all',
    target_class_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 9. Messagerie interne
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_deleted_sender TINYINT(1) DEFAULT 0,
    is_deleted_receiver TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_messages_receiver (receiver_id, is_read)
) ENGINE=InnoDB;

-- 10. Agenda partagé
CREATE TABLE IF NOT EXISTS agenda_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    event_type ENUM('reunion','examen','activite','conge','autre') DEFAULT 'autre',
    event_date DATE NOT NULL,
    event_time TIME DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    target ENUM('all','teachers','parents','class') DEFAULT 'all',
    target_class_id INT DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 11. Frais scolaires (Types de frais)
CREATE TABLE IF NOT EXISTS fee_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    school_year VARCHAR(20) DEFAULT '2024-2025',
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- 12. Frais scolaires (Dettes par élève)
CREATE TABLE IF NOT EXISTS student_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    school_year VARCHAR(20) DEFAULT '2024-2025',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 13. Frais scolaires (Paiements enregistrés)
CREATE TABLE IF NOT EXISTS fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_fee_id INT NOT NULL,
    student_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash','orange_money','mpesa','airtel_money','cheque','virement') DEFAULT 'cash',
    transaction_ref VARCHAR(100) DEFAULT NULL,
    payment_date DATE NOT NULL,
    receipt_number VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_payments_student (student_id)
) ENGINE=InnoDB;

-- 14. Rappels de paiement
CREATE TABLE IF NOT EXISTS payment_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    student_fee_id INT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_by INT NOT NULL,
    method ENUM('notification','message') DEFAULT 'notification',
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 15. Classements des élèves
CREATE TABLE IF NOT EXISTS student_rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    period VARCHAR(30) NOT NULL,
    school_year VARCHAR(20) DEFAULT '2024-2025',
    average DECIMAL(5,2) DEFAULT 0,
    rank_position INT DEFAULT 0,
    computed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rank (student_id, period, school_year),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 16. Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('message','absence','note','fee','event','alert') DEFAULT 'alert',
    title VARCHAR(200) NOT NULL,
    body TEXT,
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user (user_id, is_read)
) ENGINE=InnoDB;

-- 17. Préférences utilisateurs
CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INT PRIMARY KEY,
    lang VARCHAR(5) DEFAULT 'fr',
    theme VARCHAR(20) DEFAULT 'dark',
    notifications_enabled TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 18. Alertes intelligentes
CREATE TABLE IF NOT EXISTS smart_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('grade_drop','repeated_absence','unpaid_fees','event_reminder') NOT NULL,
    threshold_value DECIMAL(5,2) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 19. Activités / Logs de sécurité
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==========================================================
-- 20. Données de Démonstration Initiales
-- ==========================================================

-- Comptes de démo (mots de passe hashés avec bcrypt — valides)
-- admin@edusuivi.cd  -> admin123
-- ngozi@edusuivi.cd  -> prof123
-- marie@edusuivi.cd  -> parent123
INSERT INTO users (name, email, password, role) VALUES
('Directeur Kalumba',   'admin@edusuivi.cd', '$2y$10$.uA8fXaa2WPbNS/bvuF3XecnY0J7XjGkK7.SwFVUBXA0CrA6RbcxO', 'admin'),
('Prof. Ngozi Mutombo', 'ngozi@edusuivi.cd', '$2y$10$NafXRI6qYfpJa.EMVznj3eVvbDg/Is16yM1gjXmCzlHZKWv8qQCwq', 'teacher'),
('Marie Kabongo',        'marie@edusuivi.cd', '$2y$10$EV91dwN3yd6zbQ0XgIA2.ub.bIMexvFkSSyANvLNvNZmAkGBDtZ/m', 'parent')
ON DUPLICATE KEY UPDATE password=VALUES(password);

-- Classes
INSERT INTO classes (name, level, teacher_id) VALUES
('6ème A', 'Secondaire', 2),
('5ème B', 'Secondaire', NULL),
('4ème A', 'Secondaire', NULL)
ON DUPLICATE KEY UPDATE id=id;

-- Matières
INSERT INTO subjects (name) VALUES
('Mathématiques'), ('Français'), ('Sciences'), ('Histoire-Géographie'), ('Anglais'), ('Éducation Civique')
ON DUPLICATE KEY UPDATE id=id;

-- Élèves
INSERT INTO students (first_name, last_name, date_of_birth, gender, class_id, parent_id, matricule, option_filiere) VALUES
('Jean', 'Kabongo', '2010-03-15', 'M', 1, 3, 'MAT-2025-0001', 'Sciences'),
('Sarah', 'Kabongo', '2012-07-22', 'F', 2, 3, 'MAT-2025-0002', 'Commerce'),
('Pierre', 'Mwamba', '2011-01-10', 'M', 1, NULL, 'MAT-2025-0003', 'Sciences'),
('Grace', 'Lukusa', '2010-11-05', 'F', 1, NULL, 'MAT-2025-0004', 'Lettres'),
('David', 'Tshimanga', '2011-06-18', 'M', 2, NULL, 'MAT-2025-0005', 'Sciences')
ON DUPLICATE KEY UPDATE id=id;

-- Affectation enseignant
INSERT INTO teacher_assignments (teacher_id, class_id, subject_id) VALUES
(2, 1, 1), (2, 1, 3), (2, 2, 1)
ON DUPLICATE KEY UPDATE id=id;

-- Notes
INSERT INTO grades (student_id, subject_id, teacher_id, grade, max_grade, period, comment) VALUES
(1, 1, 2, 16.00, 20.00, '1er Trimestre', 'Très bon travail'),
(1, 3, 2, 14.00, 20.00, '1er Trimestre', 'Bon effort'),
(1, 2, 2, 12.00, 20.00, '1er Trimestre', 'Peut mieux faire'),
(2, 1, 2, 18.00, 20.00, '1er Trimestre', 'Excellent'),
(3, 1, 2, 10.00, 20.00, '1er Trimestre', 'Doit travailler plus'),
(4, 1, 2, 15.00, 20.00, '1er Trimestre', 'Bien'),
(1, 1, 2, 14.00, 20.00, '2ème Trimestre', 'Légère baisse'),
(1, 3, 2, 15.00, 20.00, '2ème Trimestre', 'En progression'),
(1, 2, 2, 11.00, 20.00, '2ème Trimestre', 'Efforts nécessaires'),
(2, 1, 2, 17.00, 20.00, '2ème Trimestre', 'Toujours excellent'),
(3, 1, 2, 12.00, 20.00, '2ème Trimestre', 'Amélioration visible'),
(4, 1, 2, 14.00, 20.00, '2ème Trimestre', 'Stable'),
(1, 1, 2, 17.00, 20.00, '3ème Trimestre', 'Très belle progression'),
(1, 3, 2, 16.00, 20.00, '3ème Trimestre', 'Excellent'),
(2, 1, 2, 19.00, 20.00, '3ème Trimestre', 'Parfait'),
(3, 1, 2, 13.00, 20.00, '3ème Trimestre', 'Continue ainsi')
ON DUPLICATE KEY UPDATE id=id;

-- Absences
INSERT INTO absences (student_id, subject_id, date, justified, reason, recorded_by) VALUES
(1, NULL, '2025-01-15', 1, 'Maladie', 2),
(1, NULL, '2025-02-03', 0, NULL, 2),
(3, NULL, '2025-01-20', 0, NULL, 2),
(5, NULL, '2025-02-10', 1, 'Raison familiale', 2)
ON DUPLICATE KEY UPDATE id=id;

-- Annonces
INSERT INTO announcements (title, content, author_id, target) VALUES
('Rentrée scolaire 2025', 'La rentrée est prévue pour le 5 septembre 2025. Tous les élèves doivent se présenter en uniforme.', 1, 'all'),
('Réunion parents-enseignants', 'Une réunion est programmée le samedi 20 janvier à 10h dans la salle polyvalente.', 1, 'parents'),
('Examens du 1er trimestre', 'Les examens débuteront le 15 mars. Prière de bien préparer vos élèves.', 1, 'teachers')
ON DUPLICATE KEY UPDATE id=id;

-- Frais scolaires prédéfinis
INSERT INTO fee_types (name, amount, description, school_year) VALUES
('Minerval annuel', 150000.00, 'Frais de scolarité annuels', '2024-2025'),
('Frais d\'inscription', 25000.00, 'Inscription scolaire', '2024-2025'),
('Frais de bibliothèque', 10000.00, 'Accès bibliothèque', '2024-2025'),
('Frais d\'examens', 20000.00, 'Organisation des examens', '2024-2025')
ON DUPLICATE KEY UPDATE id=id;

-- Assigner les frais aux élèves
INSERT INTO student_fees (student_id, fee_type_id, amount_due, school_year) VALUES
(1, 1, 150000.00, '2024-2025'), (1, 2, 25000.00, '2024-2025'),
(2, 1, 150000.00, '2024-2025'), (2, 2, 25000.00, '2024-2025'),
(3, 1, 150000.00, '2024-2025'), (3, 2, 25000.00, '2024-2025'),
(4, 1, 150000.00, '2024-2025'), (5, 1, 150000.00, '2024-2025')
ON DUPLICATE KEY UPDATE id=id;

-- Paiements initiaux
INSERT INTO fee_payments (student_fee_id, student_id, amount_paid, payment_method, payment_date, receipt_number, recorded_by) VALUES
(1, 1, 100000.00, 'orange_money', CURRENT_DATE, 'REC-2025-00001', 1),
(3, 2, 150000.00, 'cash', CURRENT_DATE, 'REC-2025-00002', 1)
ON DUPLICATE KEY UPDATE id=id;

-- Agenda initial
INSERT INTO agenda_events (title, description, event_type, event_date, event_time, target, created_by) VALUES
('Réunion parents-enseignants', 'Réunion trimestrielle obligatoire', 'reunion', DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY), '10:00:00', 'parents', 1),
('Examens du 2ème trimestre', 'Début des examens trimestriels', 'examen', DATE_ADD(CURRENT_DATE, INTERVAL 14 DAY), '08:00:00', 'all', 1),
('Fête de l\'école', 'Journée culturelle annuelle', 'activite', DATE_ADD(CURRENT_DATE, INTERVAL 21 DAY), '09:00:00', 'all', 1)
ON DUPLICATE KEY UPDATE id=id;

-- Configuration alertes intelligentes
INSERT INTO smart_alerts (alert_type, threshold_value, is_active) VALUES
('grade_drop', 20.00, 1),
('repeated_absence', 3.00, 1),
('unpaid_fees', NULL, 1),
('event_reminder', 2.00, 1)
ON DUPLICATE KEY UPDATE id=id;

-- ============================================================
-- EduSuivi — Migration v5
-- Nouvelle fonctionnalité : Gestion Multi-École
-- À importer dans phpMyAdmin (exécuter UNE SEULE FOIS)
-- ============================================================

USE edusuivi;

-- ─── 1. Table des écoles ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS schools (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    address     TEXT,
    phone       VARCHAR(30)  DEFAULT NULL,
    email       VARCHAR(100) DEFAULT NULL,
    logo        VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ─── 2. Modifier l'ENUM role dans users ──────────────────────
-- Ajoute le rôle 'school' en première position
ALTER TABLE users
    MODIFY COLUMN role ENUM('school','admin','teacher','parent') NOT NULL;

-- ─── 3. Ajouter school_id dans users ─────────────────────────
-- Lie un utilisateur admin/school à une école
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS school_id INT DEFAULT NULL AFTER role;

ALTER TABLE users
    ADD CONSTRAINT fk_users_school
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL;

-- ─── 4. Compte école de démonstration ────────────────────────
INSERT INTO schools (name, address, phone, email) VALUES
('École Primaire Saint-Joseph', 'Avenue Kasa-Vubu, Kinshasa-Gombe', '+243 81 234 5678', 'ecole@edusuivi.cd')
ON DUPLICATE KEY UPDATE id=id;

-- Compte connexion pour l'école (mot de passe : ecole123)
-- Hash bcrypt généré pour 'ecole123'
INSERT INTO users (name, email, password, role, school_id) VALUES
('École Saint-Joseph', 'ecole@edusuivi.cd',
 '$2y$10$CyA4FzTcEUDhmVNBhOBA6ugAVZXqDaBIU8wus2zYZ9Siupsd6Kz8u',
 'school', 1)
ON DUPLICATE KEY UPDATE school_id=1;

-- ─── 5. Lier l'admin existant à l'école de démo ──────────────
-- L'admin existant (Directeur Kalumba) est associé à l'école 1
UPDATE users SET school_id = 1 WHERE email = 'admin@edusuivi.cd' AND role = 'admin';

-- ─── 6. Vérification ─────────────────────────────────────────
SELECT id, name, email, role, school_id FROM users ORDER BY role, id;
SELECT * FROM schools;

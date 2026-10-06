-- ============================================================
-- EduSuivi — Migration v6 : Absences par matière (école secondaire)
-- À exécuter une seule fois dans phpMyAdmin
-- ============================================================

-- Ajouter subject_id s'il n'existe pas encore (normalement déjà présent)
ALTER TABLE absences
  MODIFY COLUMN subject_id INT DEFAULT NULL;

-- Empêcher qu'un élève soit marqué absent deux fois
-- pour la même matière le même jour (même séance)
ALTER TABLE absences
  ADD UNIQUE KEY IF NOT EXISTS unique_absence_per_seance (student_id, subject_id, date);

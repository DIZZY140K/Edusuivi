-- ==========================================================
-- EduSuivi — Migration v8 : Modes Primaire / Secondaire
-- ==========================================================

-- 1. Ajouter la colonne school_level à la table classes
ALTER TABLE classes ADD COLUMN school_level ENUM('primaire','secondaire') DEFAULT 'secondaire' AFTER level;

-- 2. Catégoriser les classes existantes selon leur niveau
UPDATE classes SET school_level = 'secondaire' WHERE level LIKE '%7ème%' OR level LIKE '%8ème%' OR level LIKE '%Humanités%' OR level LIKE '%Secondaire%';
UPDATE classes SET school_level = 'primaire' WHERE level LIKE '%1ère année%' OR level LIKE '%2ème année%' OR level LIKE '%3ème année%' OR level LIKE '%4ème année%' OR level LIKE '%5ème année%' OR level LIKE '%6ème année%' OR level LIKE '%Primaire%';

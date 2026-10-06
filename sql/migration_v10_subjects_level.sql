-- Migration v10 : Gestion des cours par cycle scolaire (Primaire et Secondaire)
ALTER TABLE `subjects` 
ADD COLUMN `school_level` ENUM('primaire', 'secondaire', 'les_deux') NOT NULL DEFAULT 'secondaire' AFTER `name`,
ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'Général' AFTER `school_level`;

-- Mettre à jour les matières existantes
UPDATE `subjects` SET `school_level` = 'les_deux', `category` = 'Sciences Exactes' WHERE `name` LIKE '%Math%';
UPDATE `subjects` SET `school_level` = 'les_deux', `category` = 'Langues' WHERE `name` LIKE '%Français%';
UPDATE `subjects` SET `school_level` = 'secondaire', `category` = 'Sciences' WHERE `name` LIKE '%Sciences%';
UPDATE `subjects` SET `school_level` = 'secondaire', `category` = 'Sciences Humaines' WHERE `name` LIKE '%Histoire%';
UPDATE `subjects` SET `school_level` = 'secondaire', `category` = 'Langues' WHERE `name` LIKE '%Anglais%';
UPDATE `subjects` SET `school_level` = 'les_deux', `category` = 'Éducation Civique & Morale' WHERE `name` LIKE '%Civique%';

-- Insérer des cours standards du primaire RDC si non existants
INSERT INTO `subjects` (`name`, `school_level`, `category`) 
SELECT 'Langues Nationales & Traditions', 'primaire', 'Langues'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `name` = 'Langues Nationales & Traditions');

INSERT INTO `subjects` (`name`, `school_level`, `category`) 
SELECT 'Sciences & Éveil', 'primaire', 'Sciences'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `name` = 'Sciences & Éveil');

INSERT INTO `subjects` (`name`, `school_level`, `category`) 
SELECT 'Éducation Physique & Santé', 'primaire', 'Sport & Santé'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `name` = 'Éducation Physique & Santé');

INSERT INTO `subjects` (`name`, `school_level`, `category`) 
SELECT 'Arts Plastiques & Musique', 'primaire', 'Arts & Culture'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `name` = 'Arts Plastiques & Musique');

-- Insérer des cours standards du secondaire RDC si non existants
INSERT INTO `subjects` (`name`, `school_level`, `category`) 
SELECT 'Physique', 'secondaire', 'Sciences Exactes'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `name` = 'Physique');

INSERT INTO `subjects` (`name`, `school_level`, `category`) 
SELECT 'Chimie', 'secondaire', 'Sciences Exactes'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `name` = 'Chimie');

INSERT INTO `subjects` (`name`, `school_level`, `category`) 
SELECT 'Biologie & Sciences de la Vie', 'secondaire', 'Sciences Naturelles'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `name` = 'Biologie & Sciences de la Vie');

INSERT INTO `subjects` (`name`, `school_level`, `category`) 
SELECT 'Informatique & TIC', 'secondaire', 'Technologies'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `name` = 'Informatique & TIC');

INSERT INTO `subjects` (`name`, `school_level`, `category`) 
SELECT 'Philosophie', 'secondaire', 'Sciences Humaines'
WHERE NOT EXISTS (SELECT 1 FROM `subjects` WHERE `name` = 'Philosophie');

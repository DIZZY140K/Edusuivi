-- ============================================================
-- EduSuivi — Migration v4
-- Nouvelles fonctionnalités : reset mot de passe + conversations
-- À importer dans phpMyAdmin (exécuter une seule fois)
-- ============================================================

USE edusuivi;

-- Ajout des colonnes pour la réinitialisation de mot de passe
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME DEFAULT NULL;

-- Index pour les tokens (performance)
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_reset_token (reset_token);

-- Ajout d'un champ conversation_id pour grouper les messages entre 2 utilisateurs
-- (optionnel, pour optimisation future — la logique de conversation sera gérée en PHP)

-- Vérification : afficher la structure mise à jour
DESCRIBE users;

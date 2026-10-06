================================================================
  EduSuivi - Application de Suivi Parental Scolaire
  Pour XAMPP (PHP + MySQL)
================================================================

INSTALLATION :
--------------

1. Installer XAMPP : https://www.apachefriends.org/
2. Démarrer Apache et MySQL dans le panneau XAMPP
3. Copier le dossier "edusuivi" dans C:\xampp\htdocs\
4. Ouvrir le navigateur et aller à :
   http://localhost/edusuivi/setup.php
   
   Cela va créer la base de données et les données de démo.

5. Aller à http://localhost/edusuivi/login.php

COMPTES DE DÉMONSTRATION :
--------------------------
- Admin :     admin@edusuivi.cd   / admin123
- Enseignant: ngozi@edusuivi.cd   / prof123
- Parent :    marie@edusuivi.cd   / parent123

STRUCTURE DU PROJET :
---------------------
edusuivi/
├── sql/database.sql      -- Script SQL de la base de données
├── includes/
│   ├── config.php        -- Configuration et connexion BDD
│   ├── header.php        -- En-tête et sidebar communs
│   └── footer.php        -- Pied de page commun
├── css/style.css         -- Feuille de style
├── js/app.js             -- JavaScript (interactions)
├── login.php             -- Page de connexion
├── logout.php            -- Déconnexion
├── setup.php             -- Script d'installation (à exécuter 1 fois)
├── admin/                -- Pages administrateur
│   ├── index.php         -- Tableau de bord admin
│   ├── students.php      -- Gestion des élèves (CRUD)
│   ├── teachers.php      -- Gestion des enseignants
│   ├── classes.php       -- Gestion des classes
│   └── announcements.php -- Gestion des annonces
├── teacher/              -- Pages enseignant
│   ├── index.php         -- Tableau de bord enseignant
│   ├── classes.php       -- Mes classes
│   ├── grades.php        -- Saisie des notes
│   └── absences.php      -- Signalement des absences
├── docs/                 -- Documentation et diagrammes UML
│   ├── diagrams.html     -- Visualisation & export interactif des diagrammes
│   ├── Diagrammes_UML_EduSuivi.docx -- Rapport Word contenant les diagrammes
│   └── diagrams/         -- Codes sources Mermaid (.mmd) de chaque diagramme
└── parent/               -- Pages parent
    ├── index.php         -- Tableau de bord parent
    ├── grades.php        -- Consultation des notes
    ├── absences.php      -- Historique des absences
    └── announcements.php -- Annonces de l'école

TECHNOLOGIES :
--------------
- HTML5, CSS3, JavaScript (vanilla)
- PHP 8+ (avec PDO pour MySQL)
- MySQL / MariaDB
- Serveur : Apache (XAMPP)

© 2025 EduSuivi - Projet de Fin de Licence

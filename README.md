# 🎓 EduSuivi — Plateforme de Suivi Scolaire & Parental

[![PHP Version](https://img.shields.io/badge/PHP-8.x-777bb4.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL-8.0%20%2F%20MariaDB-4479a1.svg)](https://www.mysql.com/)
[![Web Server](https://img.shields.io/badge/Apache-XAMPP-d4930d.svg)](https://www.apachefriends.org/)
[![Documentation PDF](https://img.shields.io/badge/Documentation-PDF%20Généré-10b981.svg)](docs/EduSuivi_Guide_Complet.pdf)

> **EduSuivi** est une application web intégrée de gestion scolaire, de suivi pédagogique en temps réel et de gestion comptable adaptée aux réalités éducatives de la **République Démocratique du Congo (RDC)**.

---

## 📄 Documentation PDF Complète

Un **Guide Complet & Manuel d'Utilisation Officiel (10 pages)** a été généré en format PDF haute fidélité :
- 📥 **[Télécharger le Guide Complet (docs/EduSuivi_Guide_Complet.pdf)](docs/EduSuivi_Guide_Complet.pdf)**
- 📥 **[Accéder à la copie racine (README_EduSuivi.pdf)](README_EduSuivi.pdf)**

Ce document inclut le sommaire interactif, les procédures d'installation pas à pas, la description détaillée des 3 espaces (Admin, Enseignant, Parent), le fonctionnement du module financier bidevise (FC & USD), le schéma complet des 16 tables de la base de données, ainsi que les bonnes pratiques de sécurité.

---

## 🚀 Installation Rapide (Sous XAMPP)

1. **Démarrer XAMPP :**
   Lancez le Panneau de Contrôle XAMPP et démarrez les modules **Apache** et **MySQL**.

2. **Emplacement du projet :**
   Placez le dossier du projet dans votre répertoire web local :
   ```text
   C:\xampp\htdocs\edusuivi
   ```

3. **Initialisation automatique de la base de données :**
   Ouvrez votre navigateur et rendez-vous sur :
   ```text
   http://localhost/edusuivi/setup.php
   ```
   *Ce script crée automatiquement la base de données `edusuivi`, l'ensemble des 16 tables relationnelles et injecte un jeu complet de données de test.*

4. **Connexion à l'application :**
   ```text
   http://localhost/edusuivi/login.php
   ```

---

## 🔑 Comptes de Démonstration Préconfigurés

| Rôle | Adresse Email | Mot de passe | Périmètre d'Accès |
| :--- | :--- | :--- | :--- |
| **Administrateur** | `admin@edusuivi.cd` | `admin123` | Gestion globale : élèves, profs, classes, comptabilité, BDD, statistiques. |
| **Enseignant** | `ngozi@edusuivi.cd` | `prof123` | Saisie des notes par classe/matière assignée, pointage des absences. |
| **Parent d'Élève** | `marie@edusuivi.cd` | `parent123` | Suivi des enfants (notes, bulletins, absences, solde des frais, reçus). |

---

## 🌟 Fonctionnalités Principales

### 🏫 1. Espace Administrateur (Direction)
- **Gestion des Élèves & Inscriptions :** Création, matricule automatique, photo, rattachement de classe et de parent tuteur.
- **Gestion du Personnel Enseignant :** Création des comptes, attribution de classes et de matières (`teacher_assignments`).
- **Comptabilité & Frais Scolaires bidevise (FC & USD) :**
  - Configuration de frais découpés en **tranches d'acomptes paramétrables**.
  - Encaissement en **Francs Congolais (FC)** ou en **Dollars Américains (USD)** avec taux de change ajustable en temps réel.
  - Conversion automatique et émission instantanée d'un **reçu de caisse normalisé** avec référence infalsifiable `REC-YYYY-XXXXX`.
  - Notification automatique transmise au parent tuteur dès la validation de l'encaissement.
  - Barre de défilement fluide pour une ergonomie optimale sur tout écran.
- **Bulletins Périodiques & Palmarès :** Calcul automatique des moyennes, des totaux et des classements par période.
- **Sauvegarde de la BDD :** Exportation en 1 clic d'un dump SQL compressé.

### 📚 2. Espace Enseignant
- **Grille de Saisie Interactive des Notes :** Saisie en masse rapide sur 20 avec calcul automatique et appréciations.
- **Gestion de l'Assiduité :** Appel journalier, déclaration des absences avec statut (justifiée ou non) et motifs.
- **Mes Classes & Matières :** Consultation des programmes et des effectifs assignés.

### 👨‍👩‍👧 3. Espace Parent d'Élève
- **Tableau de Bord Famille :** Vue d'ensemble sur tous les enfants inscrits dans l'établissement.
- **Bulletins & Notes en Direct :** Suivi instantané des évaluations et téléchargement des bulletins scolaires.
- **Assiduité :** Relevé des absences pour prévenir le décrochage scolaire.
- **État Financier & Reçus :** Suivi de la dette, des tranches réglées et consultation des reçus officiels.

### 💬 4. Modules Transversaux
- **Messagerie Interne :** Communication bidirectionnelle sécurisée entre parents, enseignants et direction.
- **Notifications en Temps Réel :** Badges et alertes automatiques.
- **Agenda Partagé :** Calendrier des événements, examens, congés et réunions.
- **Multilingue (4 langues) :** Français (`fr`), Lingala (`ln`), Swahili (`sw`), Kikongo (`kg`).
- **Thèmes Visuels :** Mode Sombre (Dark Mode) et Mode Clair.

---

## 📁 Architecture du Code Source

```text
edusuivi/
├── admin/                  # Contrôleurs et vues de l'administration
│   ├── index.php           # Tableau de bord avec statistiques
│   ├── students.php        # Gestion CRUD des élèves
│   ├── teachers.php        # Gestion des enseignants
│   ├── classes.php         # Gestion des classes et assignations
│   ├── fees.php            # Gestion des frais et paiements (FC/USD)
│   ├── bulletin.php        # Génération des bulletins scolaires
│   ├── agenda.php          # Calendrier des événements
│   ├── announcements.php   # Diffusion des avis et annonces
│   └── backup.php          # Sauvegarde / Restauration de la BDD
├── teacher/                # Interface réservée aux enseignants
│   ├── index.php           # Tableau de bord enseignant
│   ├── classes.php         # Classes et matières assignées
│   ├── grades.php          # Saisie en masse des notes
│   └── absences.php        # Appel et gestion des absences
├── parent/                 # Portail dédié aux familles
│   ├── index.php           # Synthèse du suivi des enfants
│   ├── grades.php          # Relevé de notes et bulletins
│   ├── absences.php        # Historique des absences
│   ├── fees.php            # Suivi financier et reçus de paiement
│   └── agenda.php          # Agenda scolaire de l'établissement
├── includes/               # Fichiers communs et logiques partagées
│   ├── config.php          # Connexion PDO, sécurité et langues
│   ├── header.php          # En-tête HTML et navigation latérale
│   └── footer.php          # Pied de page et scripts
├── lang/                   # Dictionnaires de traduction (fr, ln, sw, kg)
├── sql/database.sql        # Script DDL de la base de données
├── docs/                   # Documentation technique et livrables
│   ├── EduSuivi_Guide_Complet.pdf # Manuel officiel au format PDF
│   ├── generate_pdf_readme.py     # Script ReportLab du générateur PDF
│   ├── diagrams.html              # Visualisation interactive UML
│   └── diagrams/                  # Diagrammes d'architecture
├── login.php / logout.php  # Authentification sécurisée BCRYPT
└── setup.php               # Script d'installation en 1 clic
```

---

## 🛡️ Sécurité & Bonnes Pratiques

- **Requêtes Préparées PDO :** Protection absolue contre les injections SQL sur toutes les entrées.
- **Mots de Passe Chiffrés :** Utilisation de l'algorithme standard BCRYPT via `password_hash()`.
- **Neutralisation XSS :** Échappement systématique des données dynamiques via `escape()`.
- **Contrôle d'Accès par Rôles :** Fonction de vérification stricte `requireRole('admin'|'teacher'|'parent')` en tête de chaque script.

---

© 2026 EduSuivi — Tous droits réservés.

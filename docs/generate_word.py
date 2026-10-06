import base64
import urllib.request
import os
import sys

# Installer python-docx si absent (sécurité supplémentaire dans le script)
try:
    import docx
except ImportError:
    print("Erreur: La bibliothèque 'python-docx' est manquante. Veuillez d'abord l'installer avec pip.")
    sys.exit(1)

from docx import Document
from docx.shared import Inches, Pt
from docx.enum.text import WD_ALIGN_PARAGRAPH

# Définition des diagrammes Mermaid
diagrams = {
    "cas_utilisation": {
        "title": "1. Diagramme de Cas d'Utilisation (Use Case)",
        "desc": "Ce diagramme modélise les rôles et permissions des acteurs de l'application EduSuivi : l'Administrateur, l'Enseignant et le Parent. Toutes leurs actions requièrent d'être identifié (héritage de l'Utilisateur Connecté).",
        "code": """graph LR
    Admin((Administrateur))
    Teacher((Enseignant))
    Parent((Parent))
    UserBase((Utilisateur Connecté))

    Admin --> UserBase
    Teacher --> UserBase
    Parent --> UserBase

    subgraph Base ["Fonctionnalités Communes"]
        UC_Login([Se connecter / Réinitialiser MDP])
        UC_Logout([Se déconnecter])
        UC_Pref([Gérer son profil / Langue & Thème])
        UC_Msg([Utiliser la messagerie interne])
        UC_Notif([Consulter les notifications])
    end

    subgraph AdminActions ["Espace Administration"]
        UC_GStudents([Gérer les élèves CRUD])
        UC_GTeachers([Gérer les enseignants CRUD])
        UC_GParents([Gérer les parents CRUD])
        UC_GClasses([Gérer les classes & Assignations])
        UC_GFees([Gérer les frais & Enregistrer les paiements])
        UC_GAnnouncements([Diffuser des annonces globales])
        UC_GAgenda([Gérer l'agenda scolaire])
        UC_Backup([Sauvegarder / Restaurer la BDD])
        UC_Stats([Consulter les statistiques & Rapports])
    end

    subgraph TeacherActions ["Espace Enseignant"]
        UC_GradesInput([Saisir et modifier les notes en masse])
        UC_AbsencesInput([Signaler et justifier les absences])
        UC_ConsultClasses([Consulter ses classes & matières])
    end

    subgraph ParentActions ["Espace Parent (Suivi)"]
        UC_ConsultGrades([Consulter les notes & Bulletins de ses enfants])
        UC_ConsultAbsences([Consulter les absences de ses enfants])
        UC_ConsultFees([Suivre les frais scolaires & Voir les reçus])
        UC_ConsultAnnouncements([Consulter les annonces de l'école])
        UC_ConsultAgenda([Consulter l'agenda scolaire])
    end

    UserBase --> UC_Login
    UserBase --> UC_Logout
    UserBase --> UC_Pref
    UserBase --> UC_Msg
    UserBase --> UC_Notif

    Admin --> UC_GStudents
    Admin --> UC_GTeachers
    Admin --> UC_GParents
    Admin --> UC_GClasses
    Admin --> UC_GFees
    Admin --> UC_GAnnouncements
    Admin --> UC_GAgenda
    Admin --> UC_Backup
    Admin --> UC_Stats

    Teacher --> UC_GradesInput
    Teacher --> UC_AbsencesInput
    Teacher --> UC_ConsultClasses

    Parent --> UC_ConsultGrades
    Parent --> UC_ConsultAbsences
    Parent --> UC_ConsultFees
    Parent --> UC_ConsultAnnouncements
    Parent --> UC_ConsultAgenda"""
    },

    "classes": {
        "title": "2. Diagramme de Classes (Class Diagram)",
        "desc": "Ce diagramme modélise l'architecture de données de l'application. Bien qu'il s'agisse d'un projet PHP procédural avec accès SQL direct, il représente la structure objet conceptuelle pour la gestion des utilisateurs (User/UserPreference), de la structure scolaire (Class, Student, Subject, TeacherAssignment), de la scolarité (Grade, Absence, StudentRanking) et de la comptabilité (FeeType, StudentFee, FeePayment, PaymentReminder).",
        "code": """classDiagram
    class User {
        +int id
        +string name
        +string email
        +string password
        +string role
        +string reset_token
        +datetime reset_token_expiry
        +timestamp created_at
        +login()
        +logout()
        +resetPassword()
    }

    class UserPreference {
        +int user_id
        +string lang
        +string theme
        +bool notifications_enabled
        +timestamp updated_at
    }

    class Student {
        +int id
        +string matricule
        +string first_name
        +string last_name
        +date date_of_birth
        +char gender
        +string option_filiere
        +string phone
        +string address
        +string photo
        +string school_year
        +int class_id
        +int parent_id
        +timestamp created_at
        +timestamp updated_at
    }

    class Class {
        +int id
        +string name
        +string level
        +int teacher_id
        +int max_students
        +string room
        +string school_year
    }

    class Subject {
        +int id
        +string name
    }

    class TeacherAssignment {
        +int id
        +int teacher_id
        +int class_id
        +int subject_id
    }

    class Grade {
        +int id
        +int student_id
        +int subject_id
        +int teacher_id
        +decimal grade
        +decimal max_grade
        +string period
        +string comment
        +timestamp created_at
    }

    class Absence {
        +int id
        +int student_id
        +int subject_id
        +date date
        +bool justified
        +string reason
        +int recorded_by
        +timestamp created_at
    }

    class Announcement {
        +int id
        +string title
        +string content
        +int author_id
        +string target
        +int target_class_id
        +timestamp created_at
    }

    class Message {
        +int id
        +int sender_id
        +int receiver_id
        +string subject
        +string body
        +bool is_read
        +bool is_deleted_sender
        +bool is_deleted_receiver
        +timestamp created_at
    }

    class AgendaEvent {
        +int id
        +string title
        +string description
        +string event_type
        +date event_date
        +time event_time
        +date end_date
        +string target
        +int target_class_id
        +int created_by
        +timestamp created_at
    }

    class FeeType {
        +int id
        +string name
        +decimal amount
        +string description
        +string school_year
        +bool is_active
    }

    class StudentFee {
        +int id
        +int student_id
        +int fee_type_id
        +decimal amount_due
        +string school_year
        +timestamp created_at
    }

    class FeePayment {
        +int id
        +int student_fee_id
        +int student_id
        +decimal amount_paid
        +string payment_method
        +string transaction_ref
        +date payment_date
        +string receipt_number
        +string notes
        +int recorded_by
        +timestamp created_at
    }

    class PaymentReminder {
        +int id
        +int student_id
        +int student_fee_id
        +timestamp sent_at
        +int sent_by
        +string method
    }

    class StudentRanking {
        +int id
        +int student_id
        +int class_id
        +string period
        +string school_year
        +decimal average
        +int rank_position
        +timestamp computed_at
    }

    class Notification {
        +int id
        +int user_id
        +string type
        +string title
        +string body
        +bool is_read
        +string link
        +timestamp created_at
    }

    class SmartAlert {
        +int id
        +string alert_type
        +decimal threshold_value
        +bool is_active
        +timestamp created_at
    }

    class ActivityLog {
        +int id
        +int user_id
        +string action
        +string details
        +string ip_address
        +timestamp created_at
    }

    User "1" -- "1" UserPreference : possède
    User "1" -- "*" Student : est le parent de (si role='parent')
    User "1" -- "*" Class : est le titulaire de (si role='teacher')
    
    Student "*" -- "1" Class : appartient à
    Student "*" -- "1" User : a pour parent
    
    TeacherAssignment "*" -- "1" User : assigne Enseignant
    TeacherAssignment "*" -- "1" Class : assigne Classe
    TeacherAssignment "*" -- "1" Subject : assigne Matière
    
    Grade "*" -- "1" Student : concerne
    Grade "*" -- "1" Subject : dans la matière
    Grade "*" -- "1" User : attribuée par Enseignant
    
    Absence "*" -- "1" Student : concerne
    Absence "*" -- "0..1" Subject : dans la matière (optionnel)
    Absence "*" -- "1" User : enregistrée par (Enseignant/Admin)
    
    Announcement "*" -- "1" User : rédigée par
    Announcement "*" -- "0..1" Class : ciblée pour (optionnel)
    
    Message "*" -- "1" User : envoyé par (sender)
    Message "*" -- "1" User : reçu par (receiver)
    
    AgendaEvent "*" -- "1" User : créé par
    AgendaEvent "*" -- "0..1" Class : ciblé pour
    
    StudentFee "*" -- "1" Student : assigné à
    StudentFee "*" -- "1" FeeType : type de frais
    
    FeePayment "*" -- "1" StudentFee : paye la dette
    FeePayment "*" -- "1" Student : pour l'élève
    FeePayment "*" -- "1" User : enregistré par l'administrateur
    
    PaymentReminder "*" -- "1" Student : notifie
    PaymentReminder "*" -- "1" StudentFee : concerne les frais
    PaymentReminder "*" -- "1" User : envoyé par l'administrateur
    
    StudentRanking "*" -- "1" Student : concerne
    StudentRanking "*" -- "1" Class : dans la classe
    
    Notification "*" -- "1" User : destinée à
    ActivityLog "*" -- "0..1" User : déclenché par"""
    },

    "sequence_notes": {
        "title": "3. Diagramme de Séquence : Saisie des Notes",
        "desc": "Ce diagramme montre la séquence dynamique de la saisie en masse des notes par un enseignant (via teacher/grades.php). Il illustre la persistance conditionnelle (UPDATE ou INSERT) selon la présence d'une note préexistante en BDD.",
        "code": """sequenceDiagram
    actor Teacher as Enseignant
    participant Browser as Navigateur Client
    participant Server as Serveur PHP (teacher/grades.php)
    participant DB as Base de Données (MySQL)
    
    Teacher->>Browser: Sélectionne Classe, Matière et Période
    Browser->>Server: HTTP GET /teacher/grades.php?class_id=X&subject_id=Y
    Server->>DB: Récupère la liste des élèves de la classe
    DB-->>Server: Données élèves (ID, nom, matricule)
    Server->>DB: Récupère les notes existantes si saisies
    DB-->>Server: Liste des notes
    Server-->>Browser: Affiche la grille de saisie en masse
    
    Teacher->>Browser: Remplit les notes et commentaires
    Teacher->>Browser: Clique sur "Enregistrer toutes les notes"
    
    Browser->>Server: HTTP POST /teacher/grades.php (grades[student_id], comments...)
    Note over Server: requireRole('teacher') & validation des données
    
    loop Pour chaque élève
        Server->>DB: SELECT id FROM grades WHERE student_id=? AND subject_id=? AND period=?
        DB-->>Server: Retourne l'id (s'il existe)
        
        alt La note existe déjà
            Server->>DB: UPDATE grades SET grade=?, comment=? WHERE id=?
        else Nouvelle note
            Server->>DB: INSERT INTO grades (student_id, subject_id, teacher_id, grade, comment...)
        end
        DB-->>Server: Confirmation SQL
    end
    
    Server-->>Browser: HTTP 200 OK (Redirection + Message de succès)
    Browser-->>Teacher: Affiche "X note(s) enregistrée(s) avec succès." """
    },

    "sequence_paiements": {
        "title": "4. Diagramme de Séquence : Enregistrement de Paiement",
        "desc": "Ce diagramme modélise l'encaissement d'un paiement de frais scolaires par l'administrateur, menant à l'insertion de l'historique et au déclenchement d'une notification push destinée au parent de l'élève.",
        "code": """sequenceDiagram
    actor Admin as Administrateur
    participant Browser as Navigateur (Admin)
    participant Server as Serveur PHP (admin/fees.php)
    participant DB as Base de Données (MySQL)
    actor Parent as Parent (Notification)
    
    Admin->>Browser: Sélectionne l'élève & Clique sur "Enregistrer paiement"
    Browser-->>Admin: Ouvre le modal de paiement
    Admin->>Browser: Remplit le type de frais, montant, mode (Cash/M-Pesa...)
    Admin->>Browser: Soumet le formulaire
    
    Browser->>Server: HTTP POST /admin/fees.php (action=pay, student_fee_id, amount_paid...)
    Note over Server: requireRole('admin') & Validation du montant
    
    Server->>DB: INSERT INTO fee_payments (student_fee_id, student_id, amount_paid, payment_method, recorder_by...)
    DB-->>Server: Confirmation (Paiement inséré)
    
    Server->>DB: SELECT parent_id FROM students WHERE id = student_id
    DB-->>Server: ID Parent (ex: 3)
    
    alt Le parent est associé
        Server->>DB: INSERT INTO notifications (user_id, type, title, body, link)
        DB-->>Server: Confirmation
    end
    
    Server-->>Browser: HTTP 200 OK + Message ("Paiement enregistré")
    Browser-->>Admin: Affiche le reçu de paiement généré
    
    Parent->>Browser: Se connecte ou actualise sa page (parent/index.php)
    Browser->>Server: HTTP GET /api/notifications.php
    Server->>DB: SELECT * FROM notifications WHERE user_id=? AND is_read=0
    DB-->>Server: Liste des notifications (incluant le paiement)
    Server-->>Browser: Réponse JSON / HTML
    Browser-->>Parent: Affiche une alerte/badge rouge + Détail: "Paiement enregistré: Reçu REC-..." """
    },

    "activites": {
        "title": "5. Diagramme d'Activités",
        "desc": "Ce diagramme d'activités modélise les flux de travail principaux de l'application EduSuivi selon les trois rôles : l'Administrateur, l'Enseignant et le Parent. Il présente l'enchaînement des actions depuis la connexion jusqu'à la déconnexion.",
        "code": r"""flowchart TD
    START([🚀 Démarrage Application]) --> LOGIN

    subgraph AUTH ["🔐 Authentification"]
        LOGIN[Accéder à la page de connexion] --> CREDS[Saisir Email + Mot de passe]
        CREDS --> CHECK_CREDS{Identifiants\nvalides ?}
        CHECK_CREDS -- Non --> ERR_AUTH[Afficher erreur de connexion]
        ERR_AUTH --> CREDS
        CHECK_CREDS -- Oui --> DETECT_ROLE{Quel rôle\nutilisateur ?}
    end

    DETECT_ROLE -- Admin --> ADMIN_DASH
    DETECT_ROLE -- Enseignant --> TEACH_DASH
    DETECT_ROLE -- Parent --> PARENT_DASH

    subgraph ADMIN ["🏫 Espace Administrateur"]
        ADMIN_DASH([Tableau de bord Admin]) --> ADMIN_ACTION{Choisir une\naction}

        ADMIN_ACTION -- Gérer élèves --> STUD_LIST[Voir la liste des élèves]
        STUD_LIST --> STUD_CHOICE{Action sur\nles élèves}
        STUD_CHOICE -- Ajouter --> STUD_FORM[Remplir le formulaire élève]
        STUD_FORM --> STUD_VALID{Formulaire\nvalide ?}
        STUD_VALID -- Non --> STUD_ERR[Afficher erreurs de validation]
        STUD_ERR --> STUD_FORM
        STUD_VALID -- Oui --> STUD_SAVE[Enregistrer en base de données]
        STUD_SAVE --> STUD_CONF[Afficher matricule généré]
        STUD_CHOICE -- Modifier --> EDIT_STUD[Charger données existantes]
        EDIT_STUD --> STUD_FORM
        STUD_CHOICE -- Supprimer --> DEL_STUD[Confirmer suppression]
        DEL_STUD --> STUD_LIST

        ADMIN_ACTION -- Gérer classes --> CLASS_LIST[Voir les classes]
        CLASS_LIST --> CLASS_CHOICE{Action}
        CLASS_CHOICE -- Créer --> CLASS_FORM[Remplir nom / niveau / titulaire]
        CLASS_FORM --> CLASS_SAVE[Créer la classe]
        CLASS_SAVE --> CLASS_LIST
        CLASS_CHOICE -- Affecter enseignant --> ASSIGN[Choisir Enseignant + Matière + Classe]
        ASSIGN --> ASSIGN_SAVE[Enregistrer l'affectation]
        ASSIGN_SAVE --> CLASS_LIST

        ADMIN_ACTION -- Gérer frais --> FEE_LIST[Voir les frais scolaires]
        FEE_LIST --> FEE_PAY[Enregistrer un paiement]
        FEE_PAY --> FEE_RECEIPT[Générer le reçu REC-YYYY-XXXXX]
        FEE_RECEIPT --> NOTIFY_PARENT[Envoyer notification au parent]
        NOTIFY_PARENT --> FEE_LIST

        ADMIN_ACTION -- Statistiques --> STATS[Afficher tableaux de bord statistiques]
        ADMIN_ACTION -- Sauvegarder --> BACKUP[Exporter la base de données]
    end

    subgraph TEACH ["📚 Espace Enseignant"]
        TEACH_DASH([Tableau de bord Enseignant]) --> TEACH_ACTION{Choisir une\naction}

        TEACH_ACTION -- Saisir les notes --> GRADE_SEL[Sélectionner Classe + Matière + Période]
        GRADE_SEL --> GRADE_GRID[Afficher grille de saisie en masse]
        GRADE_GRID --> GRADE_FILL[Remplir les notes et commentaires]
        GRADE_FILL --> GRADE_SAVE{Pour chaque élève :\nINSERT ou UPDATE ?}
        GRADE_SAVE -- Note existante --> UPDATE_GRADE[UPDATE grades ...]
        GRADE_SAVE -- Nouvelle note --> INSERT_GRADE[INSERT INTO grades ...]
        UPDATE_GRADE --> GRADE_OK[Confirmer : X notes enregistrées]
        INSERT_GRADE --> GRADE_OK

        TEACH_ACTION -- Signaler absences --> ABS_SEL[Sélectionner Classe + Date]
        ABS_SEL --> ABS_LIST[Cocher les élèves absents]
        ABS_SAVE --> ABS_JUSTIFY{Justifier\nune absence ?}
        ABS_JUSTIFY -- Oui --> ABS_REASON[Saisir le motif]
        ABS_REASON --> ABS_SAVE
        ABS_JUSTIFY -- Non --> TEACH_DASH

        TEACH_ACTION -- Consulter classes --> VIEW_CLASS[Voir ses classes et matières assignées]
    end

    subgraph PARENT ["👨‍👩‍👧 Espace Parent"]
        PARENT_DASH([Tableau de bord Parent]) --> PARENT_ACTION{Consulter}

        PARENT_ACTION -- Notes & Bulletins --> GRADES_VIEW[Voir les notes par période]
        GRADES_VIEW --> BULLETIN[Télécharger le bulletin PDF]

        PARENT_ACTION -- Absences --> ABS_VIEW[Voir les absences de l'enfant]

        PARENT_ACTION -- Frais scolaires --> FEE_VIEW[Voir l'état des paiements]
        FEE_VIEW --> RECEIPT_DL[Télécharger le reçu]

        PARENT_ACTION -- Annonces --> ANN_VIEW[Lire les annonces scolaires]

        PARENT_ACTION -- Agenda --> AGENDA_VIEW[Consulter les événements]
    end

    subgraph COMMON ["💬 Actions communes à tous les rôles"]
        MSG[Messagerie interne] --> SEND_MSG[Envoyer un message]
        NOTIF[Consulter les notifications] --> MARK_READ[Marquer comme lues]
        PROFILE[Gérer son profil] --> LANG[Changer la langue]
        PROFILE --> THEME[Changer le thème clair/sombre]
    end

    ADMIN_DASH --> MSG
    TEACH_DASH --> MSG
    PARENT_DASH --> MSG

    ADMIN_DASH --> NOTIF
    TEACH_DASH --> NOTIF
    PARENT_DASH --> NOTIF

    ADMIN_DASH --> PROFILE
    TEACH_DASH --> PROFILE
    PARENT_DASH --> PROFILE

    ADMIN_DASH --> LOGOUT
    TEACH_DASH --> LOGOUT
    PARENT_DASH --> LOGOUT
    LOGOUT([🔚 Déconnexion → retour login])"""
    },

    "deploiement": {
        "title": "6. Diagramme de Déploiement",
        "desc": "Ce diagramme modélise l'architecture système sous XAMPP (Apache, PHP 8 et MySQL) et comment l'environnement interagit avec les postes clients.",
        "code": """flowchart TB
    subgraph Client [Poste Client]
        Browser[Navigateur Web]
        UI[Interface HTML5 / CSS]
        Browser --- UI
    end

    subgraph Serveur [Serveur local XAMPP]
        subgraph Web [Serveur Apache]
            Apache[Processus Apache]
            Config[Configuration Apache]
            Apache --- Config
        end
        
        subgraph App [Moteur PHP]
            Engine[PHP Engine]
            PDO[Extension PDO]
            Code[PHP Code]
            Engine --- PDO
            Engine --- Code
        end

        subgraph DB [MySQL]
            MySQL[MySQL Server]
            Schema[Database Schema]
            MySQL --- Schema
        end
    end

    Browser --> Apache
    Apache --> Engine
    PDO --> MySQL"""
    }
}

# Initialiser le document Word
doc = Document()

# Styles & Marges
section = doc.sections[0]
section.top_margin = Inches(1)
section.bottom_margin = Inches(1)
section.left_margin = Inches(1)
section.right_margin = Inches(1)

# Style de police général
style = doc.styles['Normal']
font = style.font
font.name = 'Arial'
font.size = Pt(11)

# Titre Principal
title = doc.add_paragraph()
title_run = title.add_run("Rapport d'Architecture & Diagrammes UML — EduSuivi")
title_run.bold = True
title_run.font.size = Pt(22)
title.alignment = WD_ALIGN_PARAGRAPH.CENTER
doc.add_paragraph("\nCe document présente la modélisation UML complète de l'application de suivi scolaire parental EduSuivi.\n").alignment = WD_ALIGN_PARAGRAPH.CENTER

# Parcourir et télécharger/intégrer chaque diagramme
from docx.enum.section import WD_ORIENT, WD_SECTION
import shutil

current_is_landscape = False

for key, diag in diagrams.items():
    print(f"Traitement de : {diag['title']}...")
    
    # Déterminer si ce diagramme doit être en paysage (largeur)
    should_landscape = key in ["classes", "activites"]
    
    if should_landscape and not current_is_landscape:
        # Créer une nouvelle section paysage
        new_section = doc.add_section(WD_SECTION.NEW_PAGE)
        new_section.orientation = WD_ORIENT.LANDSCAPE
        new_section.page_width = Inches(11)
        new_section.page_height = Inches(8.5)
        new_section.top_margin = Inches(0.5)
        new_section.bottom_margin = Inches(0.5)
        new_section.left_margin = Inches(0.5)
        new_section.right_margin = Inches(0.5)
        current_is_landscape = True
    elif not should_landscape and current_is_landscape:
        # Revenir en portrait
        new_section = doc.add_section(WD_SECTION.NEW_PAGE)
        new_section.orientation = WD_ORIENT.PORTRAIT
        new_section.page_width = Inches(8.5)
        new_section.page_height = Inches(11)
        new_section.top_margin = Inches(1)
        new_section.bottom_margin = Inches(1)
        new_section.left_margin = Inches(1)
        new_section.right_margin = Inches(1)
        current_is_landscape = False
    
    # 1. Ajouter le titre de section
    heading = doc.add_heading(diag['title'], level=1)
    heading.style.font.color.rgb = docx.shared.RGBColor(212, 147, 13) # Couleur dorée EduSuivi
    
    # 2. Ajouter la description
    doc.add_paragraph(diag['desc'])
    
    # 3. Télécharger le diagramme PNG depuis l'API de rendu en ligne mermaid.ink
    # Encodage URL-safe Base64
    code_bytes = diag['code'].encode('utf-8')
    b64_bytes = base64.urlsafe_b64encode(code_bytes)
    b64_str = b64_bytes.decode('ascii')
    
    img_url = f"https://mermaid.ink/img/{b64_str}"
    temp_img_path = f"temp_{key}.png"
    
    try:
        # Télécharger et enregistrer localement l'image temporaire
        req = urllib.request.Request(
            img_url, 
            headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'}
        )
        with urllib.request.urlopen(req) as response, open(temp_img_path, 'wb') as out_file:
            out_file.write(response.read())
        
        # Copier l'image vers docs/diagrams/ pour la mettre à jour dans le dépôt
        dest_png_path = f"docs/diagrams/{key}.png"
        try:
            shutil.copy(temp_img_path, dest_png_path)
            print(f"Image sauvegardée dans {dest_png_path}")
        except Exception as copy_err:
            print(f"Alerte : Impossible de sauvegarder dans {dest_png_path} ({copy_err})")

        # 4. Insérer l'image dans Word
        p_img = doc.add_paragraph()
        p_img.alignment = WD_ALIGN_PARAGRAPH.CENTER
        run_img = p_img.add_run()
        # On définit la largeur de l'image (10.0 pouces pour paysage, 6.0 pour portrait)
        img_width = Inches(10.0) if should_landscape else Inches(6.0)
        run_img.add_picture(temp_img_path, width=img_width)
        
        # Légende
        caption = doc.add_paragraph()
        caption_run = caption.add_run(f"Figure — {diag['title']}")
        caption_run.italic = True
        caption_run.font.size = Pt(9)
        caption.alignment = WD_ALIGN_PARAGRAPH.CENTER
        
        # Supprimer le fichier image temporaire
        os.remove(temp_img_path)
        
    except Exception as e:
        print(f"Alerte : Impossible de télécharger l'image pour {key} ({e}). Le code brut sera inséré à la place.")
        p_err = doc.add_paragraph()
        p_err.add_run("[Image non disponible en raison d'une erreur réseau ou de taille de diagramme]").italic = True
        
        # Si échec, insérer le code brut dans le document pour référence
        code_p = doc.add_paragraph()
        code_run = code_p.add_run(diag['code'])
        code_run.font.name = 'Courier New'
        code_run.font.size = Pt(9.5)
        
    doc.add_paragraph("\n") # Espace

# Sauvegarder le fichier Word
output_path = "docs/Diagrammes_UML_EduSuivi.docx"
doc.save(output_path)
print(f"\nDocument Word créé avec succès à l'emplacement : {output_path}")

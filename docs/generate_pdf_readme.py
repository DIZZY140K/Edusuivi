# -*- coding: utf-8 -*-
"""
Générateur du Guide Complet & README en PDF pour EduSuivi
Utilise ReportLab avec numérotation 'Page X sur Y' et suivi automatique
des pages du sommaire à deux passes.
"""

import os
import sys
from datetime import datetime

from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import cm, mm
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    KeepTogether, HRFlowable, PageBreak
)
from reportlab.pdfgen import canvas

# Palette de couleurs officielles EduSuivi
PRIMARY_GOLD = colors.HexColor("#d4930d")
SECONDARY_AMBER = colors.HexColor("#f59e0b")
DARK_NAVY = colors.HexColor("#0f172a")
SLATE_BLUE = colors.HexColor("#1e293b")
TEXT_MAIN = colors.HexColor("#1e293b")
TEXT_MUTED = colors.HexColor("#64748b")
BG_LIGHT = colors.HexColor("#f8fafc")
BORDER_COLOR = colors.HexColor("#e2e8f0")
ACCENT_GREEN = colors.HexColor("#10b981")
ACCENT_RED = colors.HexColor("#ef4444")
ACCENT_BLUE = colors.HexColor("#3b82f6")

# Dictionnaire global pour stocker la page exacte de chaque section
TRACKED_SECTIONS = {}

class TrackedHeading(Paragraph):
    """
    Paragraphe personnalisé enregistrant automatiquement son numéro de page
    lors de l'étape de tracé ReportLab pour le sommaire.
    """
    def __init__(self, text, style, section_id):
        super().__init__(text, style)
        self.section_id = section_id

    def draw(self):
        super().draw()
        TRACKED_SECTIONS[self.section_id] = self.canv._pageNumber


class NumberedCanvas(canvas.Canvas):
    """
    Canvas personnalisé à deux passes permettant de calculer et
    d'afficher le nombre total de pages ('Page X sur Y').
    Ajoute également les en-têtes et pieds de page stylisés.
    """
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self._saved_page_states = []

    def showPage(self):
        self._saved_page_states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        num_pages = len(self._saved_page_states)
        for state in self._saved_page_states:
            self.__dict__.update(state)
            self.draw_page_decorations(num_pages)
            super().showPage()
        super().save()

    def draw_page_decorations(self, page_count):
        if self._pageNumber == 1:
            # Pas d'en-tête ni de pied de page sur la couverture
            return

        self.saveState()
        self.setFont("Helvetica", 8)
        self.setFillColor(TEXT_MUTED)

        # En-tête courant (Running Header)
        self.drawString(1.8 * cm, 28.5 * cm, "EduSuivi — Portail de Suivi Scolaire & Parental")
        self.drawRightString(19.2 * cm, 28.5 * cm, "Manuel Officiel & Documentation Technique")
        
        self.setStrokeColor(BORDER_COLOR)
        self.setLineWidth(0.6)
        self.line(1.8 * cm, 28.3 * cm, 19.2 * cm, 28.3 * cm)

        # Pied de page (Running Footer)
        self.line(1.8 * cm, 1.6 * cm, 19.2 * cm, 1.6 * cm)
        self.drawString(1.8 * cm, 1.2 * cm, "© EduSuivi · Système Intégré de Gestion Éducative · RDC")
        page_text = f"Page {self._pageNumber} sur {page_count}"
        self.drawRightString(19.2 * cm, 1.2 * cm, page_text)

        self.restoreState()


def generate_story(page_map=None):
    """
    Génère la liste ordonnée des flowables (éléments graphiques) du document.
    Si page_map est fourni, il est utilisé pour renseigner les pages du sommaire.
    """
    if page_map is None:
        page_map = {}

    styles = getSampleStyleSheet()

    # Typographie & Styles
    style_cover_title = ParagraphStyle(
        'CoverTitle',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=30,
        leading=36,
        textColor=PRIMARY_GOLD,
        alignment=1,
        spaceAfter=12
    )

    style_cover_subtitle = ParagraphStyle(
        'CoverSubtitle',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=13,
        leading=18,
        textColor=DARK_NAVY,
        alignment=1,
        spaceAfter=22
    )

    style_cover_badge = ParagraphStyle(
        'CoverBadge',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=9.5,
        leading=12.5,
        textColor=colors.white,
        alignment=1
    )

    style_h1 = ParagraphStyle(
        'Header1',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=14,
        leading=18,
        textColor=DARK_NAVY,
        spaceBefore=14,
        spaceAfter=7,
        keepWithNext=True
    )

    style_h2 = ParagraphStyle(
        'Header2',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=11,
        leading=15,
        textColor=PRIMARY_GOLD,
        spaceBefore=9,
        spaceAfter=4,
        keepWithNext=True
    )

    style_h3 = ParagraphStyle(
        'Header3',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=9.5,
        leading=13,
        textColor=SLATE_BLUE,
        spaceBefore=6,
        spaceAfter=3,
        keepWithNext=True
    )

    style_body = ParagraphStyle(
        'BodyMain',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=9,
        leading=13,
        textColor=TEXT_MAIN,
        spaceAfter=5
    )

    style_table_cell = ParagraphStyle(
        'TableCell',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=8.5,
        leading=11.5,
        textColor=TEXT_MAIN
    )

    style_table_header = ParagraphStyle(
        'TableHeader',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=8.5,
        leading=11.5,
        textColor=colors.white
    )

    story = []

    # =========================================================================
    # PAGE 1 : COUVERTURE
    # =========================================================================
    story.append(Spacer(1, 2.2 * cm))

    # Badge en-tête couverture
    badge_data = [[
        Paragraph("PORTAIL ÉDUCATIF & ADMINISTRATIF UNIFIÉ", style_cover_badge)
    ]]
    badge_table = Table(badge_data, colWidths=[12.5 * cm])
    badge_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), PRIMARY_GOLD),
        ('ALIGN', (0, 0), (-1, -1), 'CENTER'),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ]))
    story.append(badge_table)
    story.append(Spacer(1, 1.0 * cm))

    # Titre & Sous-titre
    story.append(Paragraph("EduSuivi", style_cover_title))
    story.append(Paragraph(
        "Plateforme Complète de Suivi Scolaire, Pédagogique & Financier Parental",
        style_cover_subtitle
    ))

    story.append(HRFlowable(width="60%", thickness=2, color=PRIMARY_GOLD, spaceBefore=4, spaceAfter=22))

    # Boîte de présentation
    intro_box = [
        [Paragraph(
            "<b>Manuel de Référence Officiel & README Technique</b><br/><br/>"
            "Ce document détaille l'ensemble des modules, spécifications d'architecture, "
            "procédures de déploiement sous XAMPP, protocoles de sécurité, gestion des devises (FC & USD), "
            "et guides opérationnels pour les trois profils d'utilisateurs : <b>Administrateurs</b>, "
            "<b>Enseignants</b> et <b>Parents d'élèves</b>.",
            style_body
        )]
    ]
    box_table = Table(intro_box, colWidths=[15 * cm])
    box_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), BG_LIGHT),
        ('BOX', (0, 0), (-1, -1), 1, BORDER_COLOR),
        ('LEFTPADDING', (0, 0), (-1, -1), 16),
        ('RIGHTPADDING', (0, 0), (-1, -1), 16),
        ('TOPPADDING', (0, 0), (-1, -1), 12),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 12),
    ]))
    story.append(box_table)

    story.append(Spacer(1, 3.2 * cm))

    # Métadonnées en bas de couverture
    meta_data = [
        [Paragraph("<b>Version du Système :</b>", style_table_cell), Paragraph("2.5 (Édition Complète Multilingue)", style_table_cell)],
        [Paragraph("<b>Environnement Cible :</b>", style_table_cell), Paragraph("PHP 8.x / MySQL 8.x (Pile Apache XAMPP)", style_table_cell)],
        [Paragraph("<b>Cadre Éducatif :</b>", style_table_cell), Paragraph("Système Éducatif RDC (Kinshasa & Provinces)", style_table_cell)],
        [Paragraph("<b>Date de Génération :</b>", style_table_cell), Paragraph(datetime.now().strftime("%d/%m/%Y"), style_table_cell)],
        [Paragraph("<b>État de Déploiement :</b>", style_table_cell), Paragraph("<font color='#10b981'><b>Prêt pour Production & Démonstration</b></font>", style_table_cell)]
    ]
    meta_table = Table(meta_data, colWidths=[5 * cm, 10 * cm])
    meta_table.setStyle(TableStyle([
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
        ('TOPPADDING', (0, 0), (-1, -1), 3),
        ('LEFTPADDING', (0, 0), (-1, -1), 0),
    ]))
    story.append(meta_table)

    story.append(PageBreak())

    # =========================================================================
    # PAGE 2 : SOMMAIRE GÉNÉRAL
    # =========================================================================
    story.append(Paragraph("Sommaire Général", style_h1))
    story.append(HRFlowable(width="100%", thickness=1, color=PRIMARY_GOLD, spaceBefore=4, spaceAfter=14))

    toc_items = [
        ("sec1", "1. Vue d'Ensemble & Mission d'EduSuivi"),
        ("sec2", "2. Architecture & Pile Technologique"),
        ("sec3", "3. Guide d'Installation & Déploiement (XAMPP)"),
        ("sec4", "4. Comptes de Démonstration & Accès Préconfigurés"),
        ("sec5", "5. Structure Complète des Répertoires & Fichiers"),
        ("sec6", "6. Manuel Opérationnel : Espace Administrateur"),
        ("sec7", "7. Manuel Opérationnel : Espace Enseignant"),
        ("sec8", "8. Manuel Opérationnel : Espace Parent"),
        ("sec9", "9. Module Financier : Tranches & Paiement FC / USD"),
        ("sec10", "10. Fonctionnalités Transversales (Messagerie, Langues, Thèmes)"),
        ("sec11", "11. Schéma Relationnel de la Base de Données"),
        ("sec12", "12. Sécurité, Performance & Dépannage Courant"),
    ]

    toc_data = []
    for key, title in toc_items:
        page_val = page_map.get(key, "-")
        toc_data.append([
            Paragraph(f"<b>{title}</b>", style_body),
            Paragraph(f"<b>Page {page_val}</b>", style_table_cell)
        ])
    toc_table = Table(toc_data, colWidths=[14.2 * cm, 3.0 * cm])
    toc_table.setStyle(TableStyle([
        ('LINEBELOW', (0, 0), (-1, -1), 0.5, BORDER_COLOR),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
        ('ALIGN', (1, 0), (1, -1), 'RIGHT'),
    ]))
    story.append(toc_table)

    story.append(Spacer(1, 0.8 * cm))
    story.append(Paragraph(
        "<i>Note d'organisation : Ce document a été structuré pour servir à la fois de manuel de "
        "formation pour les utilisateurs finaux (direction, enseignants, parents) et de guide technique "
        "d'exploitation pour les administrateurs système et développeurs.</i>",
        style_table_cell
    ))

    story.append(PageBreak())

    # =========================================================================
    # SECTION 1 & 2
    # =========================================================================
    story.append(TrackedHeading("1. Vue d'Ensemble & Mission d'EduSuivi", style_h1, "sec1"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))
    
    story.append(Paragraph(
        "<b>EduSuivi</b> est une plateforme web moderne conçue pour combler le fossé de communication "
        "entre les établissements scolaires et les parents d'élèves en République Démocratique du Congo. "
        "Elle centralise la gestion administrative, le suivi pédagogique en temps réel (notes, moyennes, "
        "bulletins périodiques, pointage des présences) ainsi que la comptabilité des frais scolaires avec "
        "gestion des devises mixtes (Francs Congolais et Dollars Américains).",
        style_body
    ))
    story.append(Paragraph("<b>Objectifs Piliers de la Plateforme :</b>", style_h3))
    story.append(Paragraph(
        "• <b>Transparence Administrative & Financière :</b> Élimination des contestations grâce à des états "
        "de compte clairs, des tranches d'acomptes paramétrables, et l'émission instantanée de reçus imprimables "
        "portant une référence infalsifiable.<br/>"
        "• <b>Implication Parentale Accrue :</b> Consultation instantanée des relevés de notes, des absences journalières, "
        "des devoirs et de l'agenda scolaire sans attendre la remise physique des bulletins de fin de période.<br/>"
        "• <b>Gain de Temps pour le Corps Enseignant :</b> Grille interactive de saisie en masse des notes, "
        "calcul instantané des totaux et pointage rapide de l'assiduité par cours.<br/>"
        "• <b>Inclusion Nationale :</b> Support intégral du Français et des 3 langues nationales majeures "
        "(Lingala, Swahili, Kikongo) ainsi qu'un mode sombre (Dark Mode) pour le confort visuel.",
        style_body
    ))

    story.append(Spacer(1, 0.4 * cm))
    story.append(TrackedHeading("2. Architecture & Pile Technologique", style_h1, "sec2"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    tech_data = [
        [Paragraph("Composant", style_table_header), Paragraph("Technologies", style_table_header), Paragraph("Rôle & Avantages", style_table_header)],
        [Paragraph("<b>Moteur Backend</b>", style_table_cell), Paragraph("PHP 8.x (sans framework lourd)", style_table_cell), Paragraph("Exécution rapide, zéro dépendance complexe, requêtes préparées PDO pour l'intégrité.", style_table_cell)],
        [Paragraph("<b>Base de Données</b>", style_table_cell), Paragraph("MySQL 8.0 / MariaDB", style_table_cell), Paragraph("Stockage relationnel UTF8MB4 avec clés étrangères CASCADE/SET NULL et indexation.", style_table_cell)],
        [Paragraph("<b>Serveur Web</b>", style_table_cell), Paragraph("Apache HTTP Server 2.4", style_table_cell), Paragraph("Standard de l'industrie, distribution facilitée par la suite XAMPP.", style_table_cell)],
        [Paragraph("<b>Frontend & UI</b>", style_table_cell), Paragraph("HTML5, CSS3 Moderne, Vanilla JS", style_table_cell), Paragraph("Design épuré 'glassmorphism', responsive design (PC, tablettes, smartphones).", style_table_cell)],
        [Paragraph("<b>Sécurité</b>", style_table_cell), Paragraph("BCRYPT, CSRF Tokens, Escape XSS", style_table_cell), Paragraph("Mots de passe hachés avec cost factor élevé, contrôle de rôle strict sur chaque page.", style_table_cell)],
    ]
    tech_table = Table(tech_data, colWidths=[3.2 * cm, 4.8 * cm, 9.2 * cm])
    tech_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), DARK_NAVY),
        ('GRID', (0, 0), (-1, -1), 0.5, BORDER_COLOR),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, BG_LIGHT]),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ]))
    story.append(tech_table)

    story.append(PageBreak())

    # =========================================================================
    # SECTION 3 & 4
    # =========================================================================
    story.append(TrackedHeading("3. Guide d'Installation & Déploiement (XAMPP)", style_h1, "sec3"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    story.append(Paragraph(
        "L'application est conçue pour être déployée localement en moins de 3 minutes sur n'importe quel poste "
        "équipé de XAMPP sous Windows, Linux ou macOS.",
        style_body
    ))

    install_steps = [
        ("Étape 1 : Téléchargement et Lancement de XAMPP",
         "Télécharger et installer XAMPP avec PHP 8.x depuis le site officiel <i>apachefriends.org</i>. "
         "Ouvrir le <b>Panneau de Contrôle XAMPP</b> et cliquer sur <b>Start</b> pour les modules <b>Apache</b> et <b>MySQL</b>."),
        ("Étape 2 : Positionnement des Fichiers",
         "Copier l'intégralité du dossier du projet <code>edusuivi</code> à la racine du serveur web local :<br/>"
         "<code>C:\\xampp\\htdocs\\edusuivi</code>"),
        ("Étape 3 : Initialisation Automatisée (setup.php)",
         "Ouvrir un navigateur web moderne (Chrome, Edge, Firefox) et se rendre à l'adresse :<br/>"
         "<b><code>http://localhost/edusuivi/setup.php</code></b><br/>"
         "Ce script intelligent vérifie la connexion, crée la base de données <code>edusuivi</code>, génère "
         "l'ensemble des 16 tables relationnelles et injecte un jeu complet de données de test (élèves, classes, notes, frais, paiements)."),
        ("Étape 4 : Accès et Connexion",
         "Une fois l'installation confirmée, accéder au portail d'authentification :<br/>"
         "<b><code>http://localhost/edusuivi/login.php</code></b>")
    ]

    for title, desc in install_steps:
        story.append(Paragraph(f"<b>{title}</b>", style_h3))
        story.append(Paragraph(desc, style_body))
        story.append(Spacer(1, 0.15 * cm))

    story.append(Spacer(1, 0.4 * cm))
    story.append(TrackedHeading("4. Comptes de Démonstration & Accès Préconfigurés", style_h1, "sec4"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    demo_data = [
        [Paragraph("Rôle", style_table_header), Paragraph("Identifiant (Email)", style_table_header), Paragraph("Mot de passe", style_table_header), Paragraph("Périmètre & Permissions", style_table_header)],
        [Paragraph("<b>Administrateur</b>", style_table_cell), Paragraph("<code>admin@edusuivi.cd</code>", style_table_cell), Paragraph("<code>admin123</code>", style_table_cell), Paragraph("Accès complet : élèves, profs, classes, comptabilité, BDD, statistiques.", style_table_cell)],
        [Paragraph("<b>Enseignant</b>", style_table_cell), Paragraph("<code>ngozi@edusuivi.cd</code>", style_table_cell), Paragraph("<code>prof123</code>", style_table_cell), Paragraph("Saisie des notes par classe/matière assignée, pointage des absences.", style_table_cell)],
        [Paragraph("<b>Parent d'Élève</b>", style_table_cell), Paragraph("<code>marie@edusuivi.cd</code>", style_table_cell), Paragraph("<code>parent123</code>", style_table_cell), Paragraph("Suivi de ses enfants (notes, bulletins, absences, solde des frais & reçus).", style_table_cell)],
    ]
    demo_table = Table(demo_data, colWidths=[3.2 * cm, 4.5 * cm, 2.8 * cm, 6.7 * cm])
    demo_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), DARK_NAVY),
        ('GRID', (0, 0), (-1, -1), 0.5, BORDER_COLOR),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, BG_LIGHT]),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ('TOPPADDING', (0, 0), (-1, -1), 5),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
    ]))
    story.append(demo_table)

    story.append(PageBreak())

    # =========================================================================
    # SECTION 5 : STRUCTURE DU PROJET
    # =========================================================================
    story.append(TrackedHeading("5. Structure Complète des Répertoires & Fichiers", style_h1, "sec5"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    struct_data = [
        [Paragraph("Dossier / Fichier", style_table_header), Paragraph("Description & Rôle Fonctionnel", style_table_header)],
        [Paragraph("<b><code>login.php / logout.php</code></b>", style_table_cell), Paragraph("Authentification sécurisée avec sessions PHP et gestion de déconnexion propre.", style_table_cell)],
        [Paragraph("<b><code>setup.php</code></b>", style_table_cell), Paragraph("Script d'auto-installation avec création automatique de la BDD et injection de démo.", style_table_cell)],
        [Paragraph("<b><code>register_school.php</code></b>", style_table_cell), Paragraph("Formulaire d'inscription d'un nouvel établissement scolaire (multi-écoles).", style_table_cell)],
        [Paragraph("<b><code>includes/config.php</code></b>", style_table_cell), Paragraph("Connexion PDO MySQL, helper de rôles (<code>requireRole()</code>), gestion multilingue.", style_table_cell)],
        [Paragraph("<b><code>includes/header.php & footer.php</code></b>", style_table_cell), Paragraph("Structure UI globale, barre de navigation, barre latérale (sidebar), badges alertes.", style_table_cell)],
        [Paragraph("<b><code>admin/</code></b>", style_table_cell), Paragraph("Espace Direction : gestion des élèves, enseignants, cours, finances, sauvegardes.", style_table_cell)],
        [Paragraph("<b><code>teacher/</code></b>", style_table_cell), Paragraph("Espace Enseignant : grille interactive des notes, gestion des absences journalières.", style_table_cell)],
        [Paragraph("<b><code>parent/</code></b>", style_table_cell), Paragraph("Espace Famille : consultation bulletins, historique paiements, reçu et agenda.", style_table_cell)],
        [Paragraph("<b><code>api/</code></b>", style_table_cell), Paragraph("Endpoints JSON pour les requêtes asynchrones AJAX (notifications, détails frais).", style_table_cell)],
        [Paragraph("<b><code>sql/database.sql</code></b>", style_table_cell), Paragraph("Schéma DDL complet avec contraintes d'intégrité, tables et jeux d'essai.", style_table_cell)],
        [Paragraph("<b><code>lang/</code> (fr, ln, sw, kg)</b>", style_table_cell), Paragraph("Dictionnaires de traduction pour Français, Lingala, Swahili et Kikongo.", style_table_cell)],
        [Paragraph("<b><code>css/ & js/</code></b>", style_table_cell), Paragraph("Feuilles de style (thèmes clair/sombre) et scripts pour les interactions asynchrones.", style_table_cell)],
        [Paragraph("<b><code>docs/</code></b>", style_table_cell), Paragraph("Documentation UML (diagrammes de classes, séquences, activités) et guides.", style_table_cell)],
    ]
    struct_table = Table(struct_data, colWidths=[5.5 * cm, 11.7 * cm])
    struct_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), DARK_NAVY),
        ('GRID', (0, 0), (-1, -1), 0.5, BORDER_COLOR),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, BG_LIGHT]),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
    ]))
    story.append(struct_table)

    story.append(PageBreak())

    # =========================================================================
    # SECTION 6 : ESPACE ADMINISTRATEUR
    # =========================================================================
    story.append(TrackedHeading("6. Manuel Opérationnel : Espace Administrateur", style_h1, "sec6"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    story.append(Paragraph(
        "L'administrateur dispose des prérogatives les plus étendues pour piloter l'établissement :",
        style_body
    ))
    
    admin_features = [
        ("Tableau de Bord Exécutif (admin/index.php)",
         "Affiche les compteurs globaux en temps réel : nombre total d'élèves, enseignants, classes enregistrées, "
         "taux de recouvrement des frais scolaires (pourcentage recouvré vs dette), alertes d'absences du jour."),
        ("Gestion des Élèves (admin/students.php)",
         "Création, consultation, modification et radiation des élèves. Chaque élève reçoit un matricule unique, "
         "une photo d'identité, une classe de rattachement et est associé au compte utilisateur de son parent tuteur."),
        ("Gestion des Enseignants & Assignations (admin/teachers.php & classes.php)",
         "Enregistrement du corps professoral, désignation des enseignants titulaires de classe et attribution "
         "des matières enseignées par classe via la table <code>teacher_assignments</code>."),
        ("Comptabilité & Frais Scolaires (admin/fees.php)",
         "Configuration des types de frais (ex: Minerval annuel, Frais d'inscription), découpage en tranches d'acompte, "
         "encaissement en Francs Congolais ou Dollars, émission de reçu PDF/impression et notification automatique."),
        ("Bulletins & Palmarès (admin/bulletin.php)",
         "Compilation des moyennes pondérées par trimestre, calcul des classements/rangs et génération des bulletins scolaires imprimables."),
        ("Sauvegarde & Sécurité de la Base de Données (admin/backup.php)",
         "Exportation instantanée d'un dump SQL compressé de l'ensemble de la base de données pour archivage externe.")
    ]

    for title, desc in admin_features:
        story.append(Paragraph(f"• <b>{title} :</b> {desc}", style_body))

    story.append(PageBreak())

    # =========================================================================
    # SECTION 7 & 8 : ENSEIGNANT & PARENT
    # =========================================================================
    story.append(TrackedHeading("7. Manuel Opérationnel : Espace Enseignant", style_h1, "sec7"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    teacher_features = [
        ("Mes Classes & Matières (teacher/classes.php)",
         "Affiche la liste exacte des classes et cours où l'enseignant est mandaté, avec le nombre d'élèves par groupe."),
        ("Saisie en Masse des Notes (teacher/grades.php)",
         "Grille interactive permettant de saisir simultanément les notes sur 20 et appréciations de tous les élèves "
         "d'une classe pour une période donnée (1er, 2ème ou 3ème Trimestre). Le système exécute une requête "
         "conditionnelle intelligente (INSERT pour nouvelle note, UPDATE si la note existe déjà)."),
        ("Signalement & Justification des Absences (teacher/absences.php)",
         "Pointage journalier rapide de la présence des élèves, avec possibilité d'indiquer si l'absence est justifiée "
         "ou non et de consigner un motif médical ou familial. Les alertes remontent instantanément aux parents.")
    ]

    for title, desc in teacher_features:
        story.append(Paragraph(f"• <b>{title} :</b> {desc}", style_body))

    story.append(Spacer(1, 0.4 * cm))
    story.append(TrackedHeading("8. Manuel Opérationnel : Espace Parent", style_h1, "sec8"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    parent_features = [
        ("Tableau de Bord Famille (parent/index.php)",
         "Aperçu synthétique pour chaque enfant de la fratrie : dernière moyenne générale, dernières absences, "
         "et solde financier en attente."),
        ("Consultation des Notes & Bulletins (parent/grades.php)",
         "Détail des notes par matière, comparaison avec la moyenne de la classe et téléchargement/impression du bulletin scolaire."),
        ("Suivi de l'Assiduité (parent/absences.php)",
         "Historique complet des dates d'absence et motifs enregistrés pour prévenir le décrochage scolaire."),
        ("Suivi des Frais & Reçus (parent/fees.php)",
         "Tableau de bord financier : total dû, montant déjà payé, solde restant, et accès à l'historique complet "
         "des reçus de paiement émis avec leur référence officielle <code>REC-YYYY-XXXXX</code>.")
    ]

    for title, desc in parent_features:
        story.append(Paragraph(f"• <b>{title} :</b> {desc}", style_body))

    story.append(PageBreak())

    # =========================================================================
    # SECTION 9 & 10 : MODULE FINANCIER & TRANSVERSAL
    # =========================================================================
    story.append(TrackedHeading("9. Module Financier : Tranches & Paiement FC / USD", style_h1, "sec9"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    fee_points = [
        ("Découpage Paramétrable en Tranches d'Acompte",
         "L'administrateur peut créer des types de frais configurés en plusieurs tranches distinctes "
         "(ex: <i>Acompte initial</i>, <i>2ème tranche</i>, <i>3ème tranche</i>). Chaque tranche dispose de son "
         "intitulé et de son montant, et le montant total du frais est calculé automatiquement."),
        ("Saisie & Sélection Interactive lors de l'Encaissement",
         "Dans le modal de paiement, l'administrateur choisit l'élève et le frais. L'interface affiche l'état "
         "de chaque tranche (Payé, Partiel, À payer) sous forme de badges cliquables qui pré-remplissent le montant exact restant."),
        ("Double Devise (FC / USD) avec Taux Ajustable en Temps Réel",
         "L'opérateur peut basculer en un clic entre <b>🪙 FC</b> et <b>💵 USD</b>. Un champ permet d'ajuster "
         "le taux de change officiel (ex: 1 USD = 2 800 FC). Si le paiement est reçu en dollars (ex: $20 USD), "
         "l'application affiche l'équivalence exacte en FC et convertit automatiquement la valeur en Francs Congolais "
         "dans la comptabilité générale pour préserver l'uniformité des comptes."),
        ("Émission Instantanée de Reçus de Caisse Normalisés",
         "Chaque encaissement génère automatiquement un reçu officiel portant un numéro d'ordre unique "
         "<code>REC-YYYY-XXXXX</code>, la date, l'identité de l'élève, le mode (Espèces, M-Pesa, Orange Money, Airtel Money, etc.) "
         "et le nom du caissier responsable."),
        ("Notification Push Automatique vers le Compte Parent",
         "Dès validation du paiement, le système envoie une notification interne au parent tuteur avec la référence "
         "du reçu et le montant acquitté.")
    ]

    for title, desc in fee_points:
        story.append(Paragraph(f"• <b>{title} :</b> {desc}", style_body))

    story.append(Spacer(1, 0.4 * cm))
    story.append(TrackedHeading("10. Fonctionnalités Transversales", style_h1, "sec10"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    trans_data = [
        [Paragraph("Fonctionnalité", style_table_header), Paragraph("Description & Valeur Ajoutée", style_table_header)],
        [Paragraph("<b>Messagerie Interne</b>", style_table_cell), Paragraph("Boîte de communication sécurisée entre les utilisateurs sans exposer les numéros personnels.", style_table_cell)],
        [Paragraph("<b>Notifications Contextuelles</b>", style_table_cell), Paragraph("Système d'alertes en temps réel avec badge compteur non-lu pour les notes, absences et paiements.", style_table_cell)],
        [Paragraph("<b>Agenda Scolaire Partagé</b>", style_table_cell), Paragraph("Calendrier d'événements (examens, congés, réunions des parents) avec ciblage global ou par classe.", style_table_cell)],
        [Paragraph("<b>Support Multilingue</b>", style_table_cell), Paragraph("Prise en charge intégrale de 4 langues : Français (fr), Lingala (ln), Swahili (sw) et Kikongo (kg).", style_table_cell)],
        [Paragraph("<b>Mode Sombre / Mode Clair</b>", style_table_cell), Paragraph("Bascule de thème d'affichage persistée dans les préférences de l'utilisateur.", style_table_cell)],
    ]
    trans_table = Table(trans_data, colWidths=[5.5 * cm, 11.7 * cm])
    trans_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), DARK_NAVY),
        ('GRID', (0, 0), (-1, -1), 0.5, BORDER_COLOR),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, BG_LIGHT]),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 4),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 4),
    ]))
    story.append(trans_table)

    story.append(PageBreak())

    # =========================================================================
    # SECTION 11 : BASE DE DONNÉES
    # =========================================================================
    story.append(TrackedHeading("11. Schéma Relationnel de la Base de Données", style_h1, "sec11"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    story.append(Paragraph(
        "La base de données <code>edusuivi</code> comprend 16 tables relationnelles articulées autour de 4 modules majeurs :",
        style_body
    ))

    db_tables = [
        ("users", "Comptes utilisateurs avec rôle (admin, teacher, parent), email unique et mot de passe chiffré BCRYPT."),
        ("schools", "Informations de l'établissement scolaire (nom, adresse, téléphone, email, logo) pour support multi-écoles."),
        ("classes", "Classes organisées par niveau (ex: 7ème EB, 1ère Humanités) et rattachées à un enseignant titulaire."),
        ("subjects", "Référentiel des matières enseignées (Mathématiques, Français, Sciences, Histoire, etc.)."),
        ("students", "Dossiers des élèves avec matricule, filière, photo, lien vers la classe et vers le compte parent."),
        ("teacher_assignments", "Table d'affectation liant un enseignant, une classe et une matière spécifique."),
        ("grades", "Notes enregistrées sur 20 par élève, matière, enseignant et période trimestrielle."),
        ("absences", "Registres journaliers des absences avec mention de justification et motifs."),
        ("fee_types", "Types de frais scolaires avec montant global, description et découpage en tranches."),
        ("student_fees", "Lignes de dette rattachant chaque élève à ses obligations financières annuelles."),
        ("fee_payments", "Historique de chaque paiement encaissé avec devise (FC/USD), taux, référence reçu et caissier."),
        ("payment_reminders", "Journal des relances et rappels de paiement transmis aux parents."),
        ("announcements", "Avis et communications générales ou ciblées par classe émis par la direction."),
        ("messages", "Messages internes bidirectionnels entre utilisateurs avec états de lecture."),
        ("agenda_events", "Événements du calendrier scolaire (examens, réunions, congés, rentrée)."),
        ("notifications", "Alertes système automatiques (notes publiées, paiement validé, absence signalée)."),
        ("user_preferences", "Préférences de profil utilisateur (code langue 'fr'/'ln'/'sw'/'kg', thème sombre).")
    ]

    db_data = [[Paragraph("Table", style_table_header), Paragraph("Description & Clés Principales", style_table_header)]]
    for tbl, desc in db_tables:
        db_data.append([
            Paragraph(f"<b><code>{tbl}</code></b>", style_table_cell),
            Paragraph(desc, style_table_cell)
        ])
    db_table = Table(db_data, colWidths=[4.5 * cm, 12.7 * cm])
    db_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), DARK_NAVY),
        ('GRID', (0, 0), (-1, -1), 0.5, BORDER_COLOR),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, BG_LIGHT]),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
        ('TOPPADDING', (0, 0), (-1, -1), 3),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 3),
    ]))
    story.append(db_table)

    story.append(PageBreak())

    # =========================================================================
    # SECTION 12 : SÉCURITÉ & DÉPANNAGE
    # =========================================================================
    story.append(TrackedHeading("12. Sécurité, Performance & Dépannage Courant", style_h1, "sec12"))
    story.append(HRFlowable(width="100%", thickness=0.8, color=BORDER_COLOR, spaceBefore=2, spaceAfter=8))

    faq_items = [
        ("Problème : Erreur de connexion 'Access denied for user root@localhost'",
         "<b>Cause :</b> MySQL possède un mot de passe ou le service est éteint.<br/>"
         "<b>Solution :</b> Démarrer le service MySQL dans le panneau XAMPP. Si un mot de passe a été défini sur root, "
         "l'ajuster dans <code>includes/config.php</code> à la ligne <code>define('DB_PASS', 'votre_mdp');</code>."),
        ("Problème : La table ou les colonnes récentes n'existent pas",
         "<b>Cause :</b> Base de données issue d'une version antérieure.<br/>"
         "<b>Solution :</b> Lancer <code>setup.php</code> dans le navigateur ou importer le script <code>sql/database.sql</code> "
         "directement dans phpMyAdmin (<code>http://localhost/phpmyadmin</code>). Des auto-migrations intégrées dans le code "
         "créent automatiquement les nouvelles colonnes si elles sont manquantes."),
        ("Sécurité : Protection contre les injections SQL",
         "Toutes les interactions avec la base de données transitent exclusivement via des requêtes préparées PDO "
         "avec liaison de paramètres (<code>$stmt->execute([...])</code>)."),
        ("Sécurité : Protection contre les attaques XSS",
         "Toutes les données dynamiques provenant des saisies utilisateurs sont systématiquement filtrées "
         "via la fonction utilitaire <code>escape($str)</code> (équivalent à <code>htmlspecialchars(..., ENT_QUOTES, 'UTF-8')</code>).")
    ]

    for q, ans in faq_items:
        story.append(Paragraph(f"• <b>{q}</b>", style_h3))
        story.append(Paragraph(ans, style_body))
        story.append(Spacer(1, 0.1 * cm))

    # Bloc final de clôture
    story.append(Spacer(1, 0.4 * cm))
    conclusion_box = [
        [Paragraph(
            "<b>🎉 Félicitations ! Votre instance EduSuivi est prête pour une utilisation intensive.</b><br/>"
            "Pour toute question ou extension fonctionnelle (intégration SMS, paiement en ligne par API bancaire, "
            "bulletins ministériels personnalisés), consultez la documentation technique dans le dossier <code>docs/</code>.",
            style_body
        )]
    ]
    concl_table = Table(conclusion_box, colWidths=[17.2 * cm])
    concl_table.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), colors.HexColor("#f0fdf4")),
        ('BOX', (0, 0), (-1, -1), 1, ACCENT_GREEN),
        ('LEFTPADDING', (0, 0), (-1, -1), 14),
        ('RIGHTPADDING', (0, 0), (-1, -1), 14),
        ('TOPPADDING', (0, 0), (-1, -1), 8),
        ('BOTTOMPADDING', (0, 0), (-1, -1), 8),
    ]))
    story.append(concl_table)

    return story


def build_pdf(filename="docs/EduSuivi_Guide_Complet.pdf"):
    """
    Exécute une passe préliminaire pour collecter les pages exactes des sections,
    puis produit le PDF final avec un sommaire parfait.
    """
    temp_target = filename + ".tmp.pdf"
    
    # --- PASSE 1 : Collecte des numéros de page réels ---
    doc_pass1 = SimpleDocTemplate(
        temp_target,
        pagesize=A4,
        leftMargin=1.8 * cm,
        rightMargin=1.8 * cm,
        topMargin=2.0 * cm,
        bottomMargin=2.0 * cm
    )
    story_pass1 = generate_story(page_map={})
    doc_pass1.build(story_pass1, canvasmaker=NumberedCanvas)
    
    # Nettoyage fichier temporaire
    if os.path.exists(temp_target):
        try:
            os.remove(temp_target)
        except Exception:
            pass

    # Copie du dictionnaire de pages
    exact_pages = dict(TRACKED_SECTIONS)

    # --- PASSE 2 : Génération finale avec numérotation exacte ---
    doc_pass2 = SimpleDocTemplate(
        filename,
        pagesize=A4,
        leftMargin=1.8 * cm,
        rightMargin=1.8 * cm,
        topMargin=2.0 * cm,
        bottomMargin=2.0 * cm
    )
    story_pass2 = generate_story(page_map=exact_pages)
    doc_pass2.build(story_pass2, canvasmaker=NumberedCanvas)

    print(f"Document PDF généré avec succès : {filename} ({len(exact_pages)} sections indexées)")


if __name__ == "__main__":
    out_file = "docs/EduSuivi_Guide_Complet.pdf"
    if len(sys.argv) > 1:
        out_file = sys.argv[1]
    build_pdf(out_file)

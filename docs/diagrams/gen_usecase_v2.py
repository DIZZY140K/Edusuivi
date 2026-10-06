"""
EduSuivi - Diagramme de cas d'utilisation UML CORRIGÉ
Corrections :
  - Séparation stricte des espaces fonctionnels (packages UML)
  - Suppression des libellés CRUD exposés => sous-cas inclus via <<include>>
  - Fonctionnalités communes rattachées à chaque acteur via <<include>> (pas via un acteur abstrait)
  - Héritage d'acteur supprimé (remplacé par généralisations propres)
  - Relations <<extend>> pour les cas optionnels
"""

from pathlib import Path
from html import escape

OUT = Path(__file__).resolve().parent

# ──────────────────────────────────────────────
# SVG helpers
# ──────────────────────────────────────────────

def e(s: str) -> str:
    return escape(str(s), quote=True)

def svg_text(x, y, text, size=14, weight="normal", fill="#1e293b",
             anchor="middle", italic=False):
    style = f"font-size:{size}px;font-weight:{weight};font-family:'Segoe UI',Arial,sans-serif;"
    if italic:
        style += "font-style:italic;"
    return (f'<text x="{x}" y="{y}" fill="{fill}" text-anchor="{anchor}" '
            f'style="{style}">{e(text)}</text>')

def ellipse(cx, cy, rx, ry, fill="#eef2ff", stroke="#6366f1", sw=2):
    return (f'<ellipse cx="{cx}" cy="{cy}" rx="{rx}" ry="{ry}" '
            f'fill="{fill}" stroke="{stroke}" stroke-width="{sw}"/>')

def rect_r(x, y, w, h, fill, stroke, sw=2, rx=12):
    return (f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" '
            f'fill="{fill}" stroke="{stroke}" stroke-width="{sw}"/>')

def line(x1, y1, x2, y2, color="#64748b", sw=1.8, dashed=False):
    dash = ' stroke-dasharray="8 5"' if dashed else ''
    return (f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" '
            f'stroke="{color}" stroke-width="{sw}"{dash}/>')

def actor(cx, cy, label_text, color="#1e293b"):
    """UML stick figure actor"""
    r = 14
    head = f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="white" stroke="{color}" stroke-width="2"/>'
    body = f'<line x1="{cx}" y1="{cy+r}" x2="{cx}" y2="{cy+r+32}" stroke="{color}" stroke-width="2"/>'
    arms = f'<line x1="{cx-22}" y1="{cy+r+14}" x2="{cx+22}" y2="{cy+r+14}" stroke="{color}" stroke-width="2"/>'
    legs = (f'<line x1="{cx}" y1="{cy+r+32}" x2="{cx-18}" y2="{cy+r+54}" stroke="{color}" stroke-width="2"/>'
            f'<line x1="{cx}" y1="{cy+r+32}" x2="{cx+18}" y2="{cy+r+54}" stroke="{color}" stroke-width="2"/>')
    name = svg_text(cx, cy+r+72, label_text, size=15, weight="700", fill=color, anchor="middle")
    return head + body + arms + legs + name

def use_case(cx, cy, rx, ry, text_lines_list, fill="#eef2ff", stroke="#6366f1", sw=2):
    """UML ellipse use case with multi-line text"""
    el = ellipse(cx, cy, rx, ry, fill, stroke, sw)
    total = len(text_lines_list)
    step = 17
    start_y = cy - (total - 1) * step / 2 + 5
    texts = ""
    for i, t in enumerate(text_lines_list):
        texts += svg_text(cx, start_y + i * step, t, size=13, weight="600",
                          fill="#1e293b", anchor="middle")
    return el + texts

def package_box(x, y, w, h, title, fill, stroke, title_fill="#fff",
                tab_w=None, tab_h=28):
    """UML package (dossier) avec onglet titre"""
    tw = tab_w or min(len(title) * 9 + 20, w - 20)
    tab = (f'<rect x="{x}" y="{y}" width="{tw}" height="{tab_h}" rx="8" '
           f'fill="{stroke}" stroke="{stroke}" stroke-width="0"/>')
    body = rect_r(x, y + tab_h - 4, w, h, fill, stroke, sw=2.5, rx=0)
    title_t = svg_text(x + tw / 2, y + tab_h - 9, title,
                       size=14, weight="800", fill=title_fill, anchor="middle")
    return tab + body + title_t

def arrow_line(x1, y1, x2, y2, color="#64748b", sw=1.8, dashed=False,
               label_txt="", label_italic=True):
    """Flèche avec pointe ouverte et label optionnel"""
    import math
    dash = ' stroke-dasharray="8 5"' if dashed else ''
    dx, dy = x2 - x1, y2 - y1
    length = math.hypot(dx, dy) or 1
    ux, uy = dx / length, dy / length

    # Arrow head (open)
    ah = 12
    ax = ah * (ux * 0.866 - uy * 0.5)
    ay = ah * (ux * 0.5 + uy * 0.866)
    bx = ah * (ux * 0.866 + uy * 0.5)
    by = ah * (-ux * 0.5 + uy * 0.866)

    path = (f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" '
            f'stroke="{color}" stroke-width="{sw}"{dash}/>'
            f'<line x1="{x2}" y1="{y2}" x2="{x2-ax:.1f}" y2="{y2-ay:.1f}" '
            f'stroke="{color}" stroke-width="{sw}"/>'
            f'<line x1="{x2}" y1="{y2}" x2="{x2-bx:.1f}" y2="{y2-by:.1f}" '
            f'stroke="{color}" stroke-width="{sw}"/>')

    if label_txt:
        mx, my = (x1 + x2) / 2, (y1 + y2) / 2
        label_bg = (f'<rect x="{mx-38}" y="{my-12}" width="76" height="20" '
                    f'rx="4" fill="white" opacity="0.9"/>')
        label_t = svg_text(mx, my + 2, label_txt, size=11, fill=color,
                           anchor="middle", italic=label_italic)
        path += label_bg + label_t
    return path


# ──────────────────────────────────────────────
# Construction du diagramme
# ──────────────────────────────────────────────

def build_use_case_diagram() -> str:
    W, H = 1800, 1550

    parts = []

    # ── Fond ──
    parts.append(f'<rect width="{W}" height="{H}" fill="#f8fafc"/>')

    # ── Defs (marqueur flèche généralisation = triangle fermé) ──
    parts.append("""
    <defs>
      <marker id="gen_arrow" markerWidth="12" markerHeight="12" refX="10" refY="6" orient="auto">
        <polygon points="0,0 12,6 0,12" fill="white" stroke="#475569" stroke-width="1.5"/>
      </marker>
      <marker id="assoc_arrow" markerWidth="10" markerHeight="10" refX="9" refY="5" orient="auto">
        <polyline points="0,0 9,5 0,10" fill="none" stroke="#64748b" stroke-width="1.8"/>
      </marker>
      <filter id="sh" x="-15%" y="-15%" width="130%" height="130%">
        <feDropShadow dx="0" dy="3" stdDeviation="6" flood-color="#0f172a" flood-opacity="0.10"/>
      </filter>
    </defs>
    """)

    # ── Titre ──
    parts.append(svg_text(W//2, 46, "EduSuivi — Diagramme de cas d'utilisation",
                          size=26, weight="800", fill="#0f172a"))
    parts.append(svg_text(W//2, 72,
                          "Système de gestion scolaire  ·  Acteurs : Administrateur, Enseignant, Parent",
                          size=14, fill="#64748b"))

    # ═══════════════════════════════════════════════════════════
    # SYSTÈME (bordure principale)
    # ═══════════════════════════════════════════════════════════
    SYS_X, SYS_Y, SYS_W, SYS_H = 230, 95, 1360, 1390
    parts.append(
        f'<rect x="{SYS_X}" y="{SYS_Y}" width="{SYS_W}" height="{SYS_H}" '
        f'rx="4" fill="#ffffff" stroke="#94a3b8" stroke-width="2.5" '
        f'stroke-dasharray="6 4"/>'
    )
    parts.append(svg_text(SYS_X + SYS_W // 2, SYS_Y + 22,
                          "«système»  EduSuivi",
                          size=15, weight="700", fill="#475569"))

    # ═══════════════════════════════════════════════════════════
    # PACKAGE 1 — Fonctionnalités communes (shared across actors)
    # ═══════════════════════════════════════════════════════════
    P0X, P0Y, P0W, P0H = 580, 115, 820, 140
    parts.append(package_box(P0X, P0Y, P0W, P0H,
                              "Fonctionnalités communes (partagées)",
                              fill="#f1f5f9", stroke="#64748b", title_fill="#fff"))

    # UC communs
    uc_auth   = (990, 170);  parts.append(use_case(*uc_auth,  80, 26, ["S'authentifier"],          fill="#e2e8f0", stroke="#64748b"))
    uc_logout = (860, 195);  parts.append(use_case(*uc_logout,75, 26, ["Se déconnecter"],           fill="#e2e8f0", stroke="#64748b"))
    uc_profil = (710, 195);  parts.append(use_case(*uc_profil,75, 26, ["Gérer son profil"],          fill="#e2e8f0", stroke="#64748b"))
    uc_msg    = (1120, 195); parts.append(use_case(*uc_msg,   75, 26, ["Messagerie interne"],        fill="#e2e8f0", stroke="#64748b"))
    uc_notif  = (1240, 170); parts.append(use_case(*uc_notif, 75, 26, ["Notifications"],             fill="#e2e8f0", stroke="#64748b"))

    # Sous-cas de S'authentifier (<<include>>)
    uc_reset = (1090, 116);
    parts.append(use_case(*uc_reset, 68, 22, ["Réinitialiser", "mot de passe"],
                           fill="#f8fafc", stroke="#94a3b8", sw=1.5))
    parts.append(arrow_line(1090, 138, *uc_auth, dashed=True, color="#94a3b8", label_txt="«extend»"))

    # ═══════════════════════════════════════════════════════════
    # PACKAGE 2 — Espace Administrateur
    # ═══════════════════════════════════════════════════════════
    P1X, P1Y, P1W, P1H = 245, 290, 500, 1150
    parts.append(package_box(P1X, P1Y, P1W, P1H,
                              "Espace Administrateur",
                              fill="#f0fdf4", stroke="#16a34a"))

    # UC admin — niveaux fonctionnels (sans CRUD)
    uc_a = [
        (490, 365,  ["Gérer les élèves"],                "#dcfce7", "#16a34a"),
        (490, 445,  ["Gérer les enseignants"],           "#dcfce7", "#16a34a"),
        (490, 525,  ["Gérer les parents"],               "#dcfce7", "#16a34a"),
        (490, 615,  ["Gérer les classes"],               "#dcfce7", "#16a34a"),
        (490, 705,  ["Gérer les frais scolaires"],       "#dcfce7", "#16a34a"),
        (490, 795,  ["Enregistrer un paiement"],         "#dcfce7", "#16a34a"),
        (490, 885,  ["Diffuser une annonce"],            "#dcfce7", "#16a34a"),
        (490, 965,  ["Gérer l'agenda scolaire"],         "#dcfce7", "#16a34a"),
        (490, 1045, ["Consulter statistiques/rapports"], "#dcfce7", "#16a34a"),
        (490, 1125, ["Sauvegarder la base de données"],  "#dcfce7", "#16a34a"),
        (490, 1205, ["Restaurer la base de données"],    "#dcfce7", "#16a34a"),
        (490, 1295, ["Gérer les utilisateurs"],          "#dcfce7", "#16a34a"),
        (490, 1375, ["Affecter enseignant à classe"],    "#dcfce7", "#16a34a"),
    ]
    for cx, cy, lines_, fill_, stroke_ in uc_a:
        parts.append(use_case(cx, cy, 105, 26, lines_, fill_, stroke_))

    # Sous-cas inclus pour "Gérer les élèves"
    sub_elv_x = 330
    for i, sub in enumerate(["Ajouter", "Modifier", "Archiver"]):
        sy = 335 + i * 45
        parts.append(use_case(sub_elv_x, sy, 60, 20, [sub],
                               fill="#f0fdf4", stroke="#86efac", sw=1.3))
        parts.append(arrow_line(sub_elv_x + 60, sy, 490 - 105, 365,
                                dashed=True, color="#86efac", sw=1.2,
                                label_txt="«include»" if i == 1 else ""))

    # Sous-cas "Gérer les frais" → Enregistrer paiement (<<include>>)
    parts.append(arrow_line(490, 740, 490, 770,
                            dashed=True, color="#86efac", sw=1.4,
                            label_txt="«include»"))

    # ═══════════════════════════════════════════════════════════
    # PACKAGE 3 — Espace Enseignant
    # ═══════════════════════════════════════════════════════════
    P2X, P2Y, P2W, P2H = 770, 290, 460, 670
    parts.append(package_box(P2X, P2Y, P2W, P2H,
                              "Espace Enseignant",
                              fill="#faf5ff", stroke="#7c3aed"))

    uc_t = [
        (1010, 365, ["Saisir les notes"],                   "#ede9fe", "#7c3aed"),
        (1010, 460, ["Modifier les notes"],                 "#ede9fe", "#7c3aed"),
        (1010, 555, ["Signaler une absence"],               "#ede9fe", "#7c3aed"),
        (1010, 650, ["Justifier une absence"],              "#ede9fe", "#7c3aed"),
        (1010, 750, ["Consulter ses classes"],              "#ede9fe", "#7c3aed"),
        (1010, 840, ["Consulter ses matières assignées"],   "#ede9fe", "#7c3aed"),
    ]
    for cx, cy, lines_, fill_, stroke_ in uc_t:
        parts.append(use_case(cx, cy, 105, 26, lines_, fill_, stroke_))

    # Saisir notes <<include>> Modifier notes
    parts.append(arrow_line(1010, 395, 1010, 435, dashed=True, color="#a78bfa",
                            sw=1.4, label_txt="«include»"))
    # Signaler absence <<include>> Justifier
    parts.append(arrow_line(1010, 585, 1010, 625, dashed=True, color="#a78bfa",
                            sw=1.4, label_txt="«include»"))
    # Consulter classes <<include>> Consulter matières
    parts.append(arrow_line(1010, 780, 1010, 815, dashed=True, color="#a78bfa",
                            sw=1.4, label_txt="«include»"))

    # ═══════════════════════════════════════════════════════════
    # PACKAGE 4 — Espace Parent
    # ═══════════════════════════════════════════════════════════
    P3X, P3Y, P3W, P3H = 770, 995, 460, 490
    parts.append(package_box(P3X, P3Y, P3W, P3H,
                              "Espace Parent (Consultation)",
                              fill="#fff7ed", stroke="#d97706"))

    uc_p = [
        (1010, 1060, ["Consulter les notes",   "et bulletins"],       "#fef3c7", "#d97706"),
        (1010, 1155, ["Consulter les absences"],                       "#fef3c7", "#d97706"),
        (1010, 1250, ["Suivre les frais scolaires"],                   "#fef3c7", "#d97706"),
        (1010, 1340, ["Payer via Mobile Money"],                       "#fef3c7", "#d97706"),
        (1010, 1430, ["Consulter les annonces", "et l'agenda"],        "#fef3c7", "#d97706"),
    ]
    for cx, cy, lines_, fill_, stroke_ in uc_p:
        parts.append(use_case(cx, cy, 105, 30, lines_, fill_, stroke_))

    # Suivre frais <<extend>> Payer Mobile Money
    parts.append(arrow_line(1010, 1280, 1010, 1310, dashed=True, color="#f59e0b",
                            sw=1.4, label_txt="«extend»"))

    # ═══════════════════════════════════════════════════════════
    # ACTEURS (en dehors du système)
    # ═══════════════════════════════════════════════════════════
    # Administrateur
    ACT_ADM = (95, 800)
    parts.append(actor(*ACT_ADM, "Administrateur", color="#16a34a"))

    # Enseignant
    ACT_TCH = (1700, 560)
    parts.append(actor(*ACT_TCH, "Enseignant", color="#7c3aed"))

    # Parent
    ACT_PAR = (1700, 1230)
    parts.append(actor(*ACT_PAR, "Parent", color="#d97706"))

    # ═══════════════════════════════════════════════════════════
    # LIAISONS acteur → use cases (association simple)
    # ═══════════════════════════════════════════════════════════
    # Administrateur → tous ses UC
    for cx, cy, *_ in uc_a:
        parts.append(
            f'<line x1="{ACT_ADM[0]+28}" y1="{ACT_ADM[1]}" x2="{cx-105}" y2="{cy}" '
            f'stroke="#16a34a" stroke-width="1.6" opacity="0.6"/>'
        )

    # Admin → UC communs
    for uc in [uc_auth, uc_logout, uc_profil, uc_msg, uc_notif]:
        parts.append(
            f'<line x1="{ACT_ADM[0]+28}" y1="{ACT_ADM[1]}" x2="{uc[0]}" y2="{uc[1]+26}" '
            f'stroke="#64748b" stroke-width="1.3" stroke-dasharray="5 4" opacity="0.5"/>'
        )

    # Enseignant → UC enseignant
    for cx, cy, *_ in uc_t:
        parts.append(
            f'<line x1="{ACT_TCH[0]-28}" y1="{ACT_TCH[1]}" x2="{cx+105}" y2="{cy}" '
            f'stroke="#7c3aed" stroke-width="1.6" opacity="0.6"/>'
        )
    # Enseignant → UC communs
    for uc in [uc_auth, uc_logout, uc_profil, uc_msg, uc_notif]:
        parts.append(
            f'<line x1="{ACT_TCH[0]-28}" y1="{ACT_TCH[1]}" x2="{uc[0]}" y2="{uc[1]+26}" '
            f'stroke="#64748b" stroke-width="1.3" stroke-dasharray="5 4" opacity="0.5"/>'
        )

    # Parent → UC parent
    for cx, cy, *_ in uc_p:
        parts.append(
            f'<line x1="{ACT_PAR[0]-28}" y1="{ACT_PAR[1]}" x2="{cx+105}" y2="{cy}" '
            f'stroke="#d97706" stroke-width="1.6" opacity="0.6"/>'
        )
    # Parent → UC communs
    for uc in [uc_auth, uc_logout, uc_profil, uc_msg, uc_notif]:
        parts.append(
            f'<line x1="{ACT_PAR[0]-28}" y1="{ACT_PAR[1]}" x2="{uc[0]}" y2="{uc[1]+26}" '
            f'stroke="#64748b" stroke-width="1.3" stroke-dasharray="5 4" opacity="0.5"/>'
        )

    # ═══════════════════════════════════════════════════════════
    # LÉGENDE
    # ═══════════════════════════════════════════════════════════
    LX, LY = 250, 1480
    parts.append(rect_r(LX, LY, 1310, 100, "#f1f5f9", "#cbd5e1", sw=2, rx=10))
    parts.append(svg_text(LX + 655, LY + 20, "Légende", size=13, weight="800", fill="#475569"))

    legend_items = [
        (LX + 50,  "——————", "#64748b",  "Association acteur → UC"),
        (LX + 350, "- - - - -", "#64748b", "«include» : sous-cas obligatoire"),
        (LX + 680, "- - - - -", "#94a3b8", "«extend» : cas optionnel"),
        (LX + 1000,"────▷", "#475569",   "Association directe"),
    ]
    for x, sym, col, desc in legend_items:
        parts.append(svg_text(x, LY + 60, sym, size=12, fill=col, anchor="start"))
        parts.append(svg_text(x, LY + 78, desc, size=11, fill="#475569", anchor="start"))

    # ═══════════════════════════════════════════════════════════
    # Assemblage SVG
    # ═══════════════════════════════════════════════════════════
    body = "\n".join(parts)
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" '
        f'viewBox="0 0 {W} {H}" role="img" '
        f'aria-label="EduSuivi Diagramme de cas d\'utilisation UML">\n'
        f'{body}\n</svg>\n'
    )


if __name__ == "__main__":
    svg = build_use_case_diagram()
    out = OUT / "cas_utilisation_v2.svg"
    out.write_text(svg, encoding="utf-8")
    print("OK - Genere : " + str(out))

from __future__ import annotations

from html import escape
from pathlib import Path


OUT_DIR = Path(__file__).resolve().parent


def attrs(**items: object) -> str:
    parts = []
    for key, value in items.items():
        if value is None:
            continue
        key = key.replace("_", "-")
        parts.append(f'{key}="{escape(str(value), quote=True)}"')
    return " ".join(parts)


def tag(name: str, content: str = "", **items: object) -> str:
    attr_text = attrs(**items)
    if content:
        return f"<{name} {attr_text}>{content}</{name}>" if attr_text else f"<{name}>{content}</{name}>"
    return f"<{name} {attr_text}/>" if attr_text else f"<{name}/>"


def text_lines(x: float, y: float, lines: list[str], size: int = 22, weight: int = 400,
               fill: str = "#0f172a", anchor: str = "start", line_height: int | None = None) -> str:
    line_height = line_height or int(size * 1.35)
    spans = []
    for index, line in enumerate(lines):
        dy = 0 if index == 0 else line_height
        spans.append(tag("tspan", escape(line), x=x, dy=dy if index else 0))
    return tag(
        "text",
        "".join(spans),
        x=x,
        y=y,
        fill=fill,
        **{
            "font-size": size,
            "font-weight": weight,
            "font-family": "Segoe UI, Arial, sans-serif",
            "text-anchor": anchor,
        },
    )


def wrap_text(value: str, max_chars: int) -> list[str]:
    words = value.split()
    lines: list[str] = []
    current = ""
    for word in words:
        candidate = f"{current} {word}".strip()
        if len(candidate) <= max_chars:
            current = candidate
        else:
            if current:
                lines.append(current)
            current = word
    if current:
        lines.append(current)
    return lines or [value]


def rect(x: float, y: float, w: float, h: float, fill: str, stroke: str = "#cbd5e1",
         sw: float = 2, rx: float = 12, opacity: float | None = None) -> str:
    return tag("rect", x=x, y=y, width=w, height=h, rx=rx, fill=fill, stroke=stroke,
               **{"stroke-width": sw, "fill-opacity": opacity})


def line_path(points: list[tuple[float, float]], color: str = "#475569", width: float = 3,
              dashed: bool = False, arrow: bool = True) -> str:
    commands = [f"M {points[0][0]} {points[0][1]}"]
    commands.extend(f"L {x} {y}" for x, y in points[1:])
    return tag(
        "path",
        d=" ".join(commands),
        fill="none",
        stroke=color,
        **{
            "stroke-width": width,
            "stroke-linecap": "round",
            "stroke-linejoin": "round",
            "stroke-dasharray": "10 10" if dashed else None,
            "marker-end": "url(#arrow)" if arrow else None,
        },
    )


def label(x: float, y: float, value: str, color: str = "#334155") -> str:
    w = max(80, len(value) * 10 + 24)
    return (
        rect(x - w / 2, y - 19, w, 32, "#ffffff", "#e2e8f0", sw=1, rx=8)
        + text_lines(x, y + 3, [value], size=16, weight=700, fill=color, anchor="middle")
    )


def class_box(x: float, y: float, w: float, title: str, fields: list[str], accent: str) -> tuple[str, float]:
    row_h = 29
    header_h = 52
    h = header_h + 24 + len(fields) * row_h
    parts = [
        rect(x, y, w, h, "#ffffff", "#cbd5e1", sw=2.5, rx=14),
        rect(x, y, w, header_h, accent, accent, sw=0, rx=14),
        tag("path", d=f"M {x} {y + header_h - 12} H {x + w} V {y + header_h} H {x} Z", fill=accent),
        text_lines(x + 20, y + 34, [title], size=24, weight=800, fill="#ffffff"),
    ]
    current_y = y + header_h + 32
    for field in fields:
        parts.append(text_lines(x + 22, current_y, [field], size=19, fill="#1e293b"))
        current_y += row_h
    return "".join(parts), h


def flow_node(x: float, y: float, w: float, h: float, value: str, fill: str = "#ffffff",
              stroke: str = "#94a3b8", color: str = "#0f172a", radius: int = 16) -> str:
    lines = wrap_text(value, max(12, int(w / 14)))
    start_y = y + h / 2 - (len(lines) - 1) * 13 + 7
    return (
        rect(x, y, w, h, fill, stroke, sw=2.5, rx=radius)
        + text_lines(x + w / 2, start_y, lines, size=20, weight=700, fill=color, anchor="middle", line_height=26)
    )


def decision(x: float, y: float, w: float, h: float, value: str, fill: str = "#fff7ed",
             stroke: str = "#f97316") -> str:
    points = [
        (x + w / 2, y),
        (x + w, y + h / 2),
        (x + w / 2, y + h),
        (x, y + h / 2),
    ]
    polygon = " ".join(f"{px},{py}" for px, py in points)
    lines = wrap_text(value, max(10, int(w / 15)))
    start_y = y + h / 2 - (len(lines) - 1) * 12 + 7
    return (
        tag("polygon", points=polygon, fill=fill, stroke=stroke, **{"stroke-width": 2.5})
        + text_lines(x + w / 2, start_y, lines, size=19, weight=800, fill="#7c2d12", anchor="middle", line_height=24)
    )


def lane(x: float, y: float, w: float, h: float, title: str, accent: str, fill: str) -> str:
    return (
        rect(x, y, w, h, fill, accent, sw=3, rx=18, opacity=0.95)
        + rect(x, y, w, 62, accent, accent, sw=0, rx=18)
        + tag("path", d=f"M {x} {y + 48} H {x + w} V {y + 62} H {x} Z", fill=accent)
        + text_lines(x + 26, y + 39, [title], size=25, weight=850, fill="#ffffff")
    )


def svg_shell(width: int, height: int, title: str, subtitle: str, body: str) -> str:
    defs = """
    <defs>
        <marker id="arrow" markerWidth="12" markerHeight="12" refX="10" refY="6" orient="auto" markerUnits="strokeWidth">
            <path d="M 0 0 L 12 6 L 0 12 z" fill="#475569"/>
        </marker>
        <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="8" stdDeviation="10" flood-color="#0f172a" flood-opacity="0.12"/>
        </filter>
    </defs>
    """
    header = (
        rect(0, 0, width, height, "#f8fafc", "none", sw=0, rx=0)
        + text_lines(80, 62, [title], size=42, weight=850, fill="#0f172a")
        + text_lines(82, 102, [subtitle], size=22, weight=500, fill="#475569")
    )
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" '
        f'viewBox="0 0 {width} {height}" role="img" aria-label="{escape(title, quote=True)}">\n'
        f"{defs}\n{header}\n{body}\n</svg>\n"
    )


def build_class_diagram() -> str:
    width, height = 2600, 1900
    colors = {
        "user": "#2563eb",
        "school": "#0f766e",
        "academic": "#16a34a",
        "fees": "#7c3aed",
        "comm": "#d97706",
        "system": "#64748b",
    }
    boxes = {
        "ActivityLog": (650, 150, 330, "JournalActivite", ["PK id", "FK user_id", "action", "details", "created_at"], colors["system"]),
        "User": (1060, 150, 330, "User", ["PK id", "name", "email", "role", "password/reset"], colors["user"]),
        "UserPreference": (1470, 150, 330, "PreferenceUser", ["PK/FK user_id", "lang", "theme", "notifications"], colors["user"]),
        "Student": (150, 520, 350, "Student", ["PK id", "matricule", "nom + prenom", "FK class_id", "FK parent_id"], colors["school"]),
        "Class": (560, 520, 350, "Class", ["PK id", "name", "level", "FK teacher_id", "school_year"], colors["school"]),
        "Subject": (970, 520, 350, "Subject", ["PK id", "name"], colors["school"]),
        "TeacherAssignment": (1380, 520, 390, "TeacherAssignment", ["PK id", "FK teacher_id", "FK class_id", "FK subject_id"], colors["school"]),
        "Announcement": (2070, 430, 360, "Announcement", ["PK id", "FK author_id", "target", "FK target_class_id"], colors["comm"]),
        "Message": (2070, 690, 360, "Message", ["PK id", "FK sender_id", "FK receiver_id", "subject", "is_read"], colors["comm"]),
        "AgendaEvent": (2070, 950, 360, "AgendaEvent", ["PK id", "title", "event_date", "FK created_by", "FK target_class_id"], colors["comm"]),
        "Notification": (2070, 1210, 360, "Notification", ["PK id", "FK user_id", "type", "title", "is_read"], colors["comm"]),
        "Grade": (150, 910, 350, "Grade", ["PK id", "FK student_id", "FK subject_id", "FK teacher_id", "grade / period"], colors["academic"]),
        "Absence": (560, 910, 350, "Absence", ["PK id", "FK student_id", "FK subject_id", "date", "justified"], colors["academic"]),
        "StudentRanking": (970, 910, 350, "StudentRanking", ["PK id", "FK student_id", "FK class_id", "average", "rank_position"], colors["academic"]),
        "SmartAlert": (1380, 910, 390, "SmartAlert", ["PK id", "alert_type", "threshold_value", "is_active"], colors["system"]),
        "FeeType": (150, 1300, 350, "FeeType", ["PK id", "name", "amount", "school_year", "is_active"], colors["fees"]),
        "StudentFee": (560, 1300, 350, "StudentFee", ["PK id", "FK student_id", "FK fee_type_id", "amount_due"], colors["fees"]),
        "FeePayment": (970, 1300, 350, "FeePayment", ["PK id", "FK student_fee_id", "FK student_id", "amount_paid", "receipt_number"], colors["fees"]),
        "PaymentReminder": (1380, 1300, 390, "PaymentReminder", ["PK id", "FK student_id", "FK student_fee_id", "FK sent_by", "sent_at"], colors["fees"]),
    }

    parts: list[str] = []
    for x, y, w, title, fields, accent in boxes.values():
        box_svg, _ = class_box(x, y, w, title, fields, accent)
        parts.append(tag("g", box_svg, filter="url(#softShadow)"))

    def center(name: str) -> tuple[float, float]:
        x, y, w, _title, fields, _accent = boxes[name]
        h = 52 + 24 + len(fields) * 29
        return x + w / 2, y + h / 2

    def side(name: str, side_name: str) -> tuple[float, float]:
        x, y, w, _title, fields, _accent = boxes[name]
        h = 52 + 24 + len(fields) * 29
        if side_name == "left":
            return x, y + h / 2
        if side_name == "right":
            return x + w, y + h / 2
        if side_name == "top":
            return x + w / 2, y
        return x + w / 2, y + h

    rels = [
        (side("User", "right"), side("UserPreference", "left"), "1 - 1", colors["user"]),
        (side("ActivityLog", "right"), side("User", "left"), "0..1 user", colors["system"]),
        (side("Student", "top"), (325, 390), (1060, 390), side("User", "bottom"), "parent", colors["user"]),
        (side("Class", "top"), (735, 430), (1225, 430), side("User", "bottom"), "titulaire", colors["user"]),
        (side("Student", "right"), side("Class", "left"), "* - 1", colors["school"]),
        (side("TeacherAssignment", "left"), side("Subject", "right"), "matiere", colors["school"]),
        (side("TeacherAssignment", "top"), (1575, 455), (735, 455), side("Class", "top"), "classe", colors["school"]),
        (side("TeacherAssignment", "top"), (1575, 380), (1225, 380), side("User", "bottom"), "enseignant", colors["user"]),
        (side("Grade", "top"), side("Student", "bottom"), "eleve", colors["academic"]),
        (side("Grade", "right"), (760, 1018), side("Absence", "left"), "suivi", colors["academic"]),
        (side("Absence", "top"), (735, 840), (1145, 840), side("Subject", "bottom"), "matiere", colors["academic"]),
        (side("StudentRanking", "top"), side("Subject", "bottom"), "classement", colors["academic"]),
        (side("StudentFee", "left"), side("FeeType", "right"), "type", colors["fees"]),
        (side("StudentFee", "top"), (735, 1215), (325, 1215), side("Student", "bottom"), "eleve", colors["fees"]),
        (side("FeePayment", "left"), side("StudentFee", "right"), "paiement", colors["fees"]),
        (side("PaymentReminder", "left"), side("FeePayment", "right"), "rappel", colors["fees"]),
        (side("Announcement", "left"), (1875, 560), (1225, 390), side("User", "bottom"), "auteur", colors["comm"]),
        (side("Announcement", "left"), (1875, 595), (735, 595), side("Class", "right"), "classe cible", colors["comm"]),
        (side("Message", "left"), (1875, 820), (1390, 280), side("User", "right"), "sender/receiver", colors["comm"]),
        (side("AgendaEvent", "left"), (1875, 1075), (1225, 410), side("User", "bottom"), "cree par", colors["comm"]),
        (side("Notification", "left"), (1875, 1335), (1390, 330), side("User", "right"), "destinataire", colors["comm"]),
    ]

    for rel in rels:
        *points, rel_label, color = rel
        pts = [p for p in points if isinstance(p, tuple)]
        parts.append(line_path(pts, color=color, width=3))
        a = pts[len(pts) // 2 - 1]
        b = pts[len(pts) // 2]
        parts.append(label((a[0] + b[0]) / 2, (a[1] + b[1]) / 2 - 8, rel_label, color))

    legend_y = 1710
    parts.append(rect(80, legend_y, 2440, 110, "#ffffff", "#e2e8f0", sw=2, rx=18))
    parts.append(text_lines(115, legend_y + 43, ["Lecture rapide"], size=23, weight=850, fill="#0f172a"))
    legend_items = [
        ("PK", "cle primaire"),
        ("FK", "cle etrangere"),
        ("1 - *", "relation un-vers-plusieurs"),
        ("0..1", "relation optionnelle"),
    ]
    x = 430
    for code, desc in legend_items:
        parts.append(rect(x, legend_y + 25, 68, 44, "#f1f5f9", "#cbd5e1", sw=2, rx=10))
        parts.append(text_lines(x + 34, legend_y + 54, [code], size=18, weight=850, fill="#0f172a", anchor="middle"))
        parts.append(text_lines(x + 86, legend_y + 54, [desc], size=19, weight=600, fill="#334155"))
        x += 470

    return svg_shell(
        width,
        height,
        "EduSuivi - Diagramme de classes",
        "Version lisible avec les entites principales, les champs cles et les relations majeures.",
        "".join(parts),
    )


def build_activity_diagram() -> str:
    width, height = 2600, 1900
    colors = {
        "auth": "#2563eb",
        "admin": "#0f766e",
        "teacher": "#7c3aed",
        "parent": "#d97706",
        "common": "#475569",
        "decision": "#f97316",
    }
    parts: list[str] = []

    parts.append(lane(600, 140, 1400, 420, "Authentification", colors["auth"], "#eff6ff"))
    parts.append(flow_node(850, 230, 260, 72, "Demarrage", "#dbeafe", "#2563eb"))
    parts.append(flow_node(1190, 230, 320, 72, "Page de connexion", "#ffffff", "#93c5fd"))
    parts.append(flow_node(1190, 340, 320, 72, "Email + mot de passe", "#ffffff", "#93c5fd"))
    parts.append(decision(1155, 445, 390, 92, "Identifiants valides ?"))
    parts.append(flow_node(1640, 455, 270, 72, "Erreur puis nouvelle saisie", "#fee2e2", "#ef4444"))
    parts.append(decision(850, 445, 250, 92, "Role utilisateur ?"))
    parts.append(line_path([(1110, 266), (1190, 266)], colors["auth"], 3))
    parts.append(line_path([(1350, 302), (1350, 340)], colors["auth"], 3))
    parts.append(line_path([(1350, 412), (1350, 445)], colors["auth"], 3))
    parts.append(line_path([(1545, 491), (1640, 491)], "#ef4444", 3))
    parts.append(label(1600, 468, "Non", "#b91c1c"))
    parts.append(line_path([(1775, 455), (1775, 380), (1510, 380)], "#ef4444", 3))
    parts.append(line_path([(1155, 491), (1100, 491)], colors["auth"], 3))
    parts.append(label(1125, 468, "Oui", colors["auth"]))

    lane_y, lane_h = 650, 830
    parts.append(lane(80, lane_y, 760, lane_h, "Espace administrateur", colors["admin"], "#ecfdf5"))
    parts.append(lane(920, lane_y, 760, lane_h, "Espace enseignant", colors["teacher"], "#f5f3ff"))
    parts.append(lane(1760, lane_y, 760, lane_h, "Espace parent", colors["parent"], "#fff7ed"))

    parts.append(line_path([(975, 537), (460, 650)], colors["auth"], 3))
    parts.append(line_path([(975, 537), (1300, 650)], colors["auth"], 3))
    parts.append(line_path([(975, 537), (2140, 650)], colors["auth"], 3))
    parts.append(label(720, 602, "Admin", colors["admin"]))
    parts.append(label(1210, 600, "Enseignant", colors["teacher"]))
    parts.append(label(1680, 600, "Parent", colors["parent"]))

    # Admin lane
    parts.append(flow_node(300, 745, 320, 70, "Tableau de bord", "#ffffff", "#5eead4"))
    parts.append(decision(300, 850, 320, 96, "Choisir une action", "#ccfbf1", colors["admin"]))
    admin_nodes = [
        (125, 1015, "Gestion eleves", "Ajouter / modifier / supprimer"),
        (455, 1015, "Classes", "Creer classe + affecter enseignant"),
        (125, 1195, "Frais scolaires", "Paiement, recu, notification"),
        (455, 1195, "Statistiques", "Rapports et sauvegarde BDD"),
    ]
    for x, y, title, desc in admin_nodes:
        parts.append(flow_node(x, y, 300, 72, title, "#ffffff", "#5eead4"))
        parts.append(flow_node(x, y + 88, 300, 80, desc, "#f8fafc", "#99f6e4"))
    parts.append(line_path([(460, 815), (460, 850)], colors["admin"], 3))
    for target in [(275, 1015), (605, 1015), (275, 1195), (605, 1195)]:
        parts.append(line_path([(460, 946), (460, 980), target, target], colors["admin"], 3))
    parts.append(decision(190, 1390, 470, 86, "Enregistrer et revenir au tableau de bord", "#ccfbf1", colors["admin"]))

    # Teacher lane
    parts.append(flow_node(1140, 745, 320, 70, "Tableau de bord", "#ffffff", "#c4b5fd"))
    parts.append(decision(1140, 850, 320, 96, "Choisir une action", "#ede9fe", colors["teacher"]))
    teacher_left = [
        (1000, 1015, "Saisir les notes"),
        (1000, 1110, "Classe + matiere + periode"),
        (1000, 1205, "Grille de saisie en masse"),
        (1000, 1300, "INSERT ou UPDATE puis confirmation"),
    ]
    teacher_right = [
        (1305, 1015, "Signaler absences"),
        (1305, 1110, "Classe + date"),
        (1305, 1205, "Cocher les eleves absents"),
        (1305, 1300, "Justifier si besoin"),
    ]
    for x, y, value in teacher_left + teacher_right:
        parts.append(flow_node(x, y, 290, 72, value, "#ffffff", "#c4b5fd"))
    parts.append(line_path([(1300, 815), (1300, 850)], colors["teacher"], 3))
    parts.append(line_path([(1300, 946), (1145, 1015)], colors["teacher"], 3))
    parts.append(line_path([(1300, 946), (1450, 1015)], colors["teacher"], 3))
    for x in [1145, 1450]:
        parts.append(line_path([(x, 1087), (x, 1110)], colors["teacher"], 3))
        parts.append(line_path([(x, 1182), (x, 1205)], colors["teacher"], 3))
        parts.append(line_path([(x, 1277), (x, 1300)], colors["teacher"], 3))
    parts.append(flow_node(1120, 1390, 360, 72, "Consulter classes et matieres assignees", "#ffffff", "#c4b5fd"))

    # Parent lane
    parts.append(flow_node(1980, 745, 320, 70, "Tableau de bord", "#ffffff", "#fdba74"))
    parts.append(decision(1980, 850, 320, 96, "Consulter", "#ffedd5", colors["parent"]))
    parent_nodes = [
        (1815, 1015, "Notes et bulletins", "Voir notes par periode"),
        (2145, 1015, "Absences", "Voir historique"),
        (1815, 1195, "Frais scolaires", "Voir paiements et recus"),
        (2145, 1195, "Annonces et agenda", "Lire les informations"),
    ]
    for x, y, title, desc in parent_nodes:
        parts.append(flow_node(x, y, 300, 72, title, "#ffffff", "#fdba74"))
        parts.append(flow_node(x, y + 88, 300, 80, desc, "#fffaf0", "#fed7aa"))
    parts.append(line_path([(2140, 815), (2140, 850)], colors["parent"], 3))
    for target in [(1965, 1015), (2295, 1015), (1965, 1195), (2295, 1195)]:
        parts.append(line_path([(2140, 946), (2140, 980), target, target], colors["parent"], 3))

    # Common actions
    common_y = 1570
    parts.append(lane(160, common_y, 2280, 210, "Actions communes et sortie", colors["common"], "#f1f5f9"))
    common_nodes = [
        (310, common_y + 95, "Messagerie interne"),
        (800, common_y + 95, "Notifications"),
        (1290, common_y + 95, "Profil, langue, theme"),
        (1780, common_y + 95, "Deconnexion"),
    ]
    for x, y, value in common_nodes:
        parts.append(flow_node(x, y, 350, 72, value, "#ffffff", "#94a3b8"))
    for x in [460, 1300, 2140]:
        parts.append(line_path([(x, 1480), (x, common_y + 95)], "#64748b", 2.5, dashed=True))

    return svg_shell(
        width,
        height,
        "EduSuivi - Diagramme d'activites",
        "Flux principal par role : authentification, actions metier, consultations et fonctions communes.",
        "".join(parts),
    )


def main() -> None:
    outputs = {
        "classes_clair.svg": build_class_diagram(),
        "activites_clair.svg": build_activity_diagram(),
    }
    for filename, svg in outputs.items():
        (OUT_DIR / filename).write_text(svg, encoding="utf-8")
        print(f"generated {OUT_DIR / filename}")


if __name__ == "__main__":
    main()

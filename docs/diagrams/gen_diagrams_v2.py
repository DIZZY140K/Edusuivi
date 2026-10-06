"""
EduSuivi — Génération des diagrammes UML corrigés v2
Corrections selon les remarques du professeur :
  1. Diagramme de classes  : trop proche d'un MLD — supprimer PK/FK, ajouter méthodes métier
  2. Diagramme de séquence : SQL exposé + paiement par Admin → refaire avec Parent + Mobile Money
  3. Diagramme d'activités : surchargé → simplifier, 3 couloirs, sans SQL
  4. Diagramme de déploiement : XAMPP only → montrer prod + hébergeur
"""

from __future__ import annotations
from pathlib import Path
from html import escape as _esc
import math

OUT = Path(__file__).resolve().parent


# ─────────────────────────────────────────────
# Helpers SVG génériques
# ─────────────────────────────────────────────

def esc(s): return _esc(str(s), quote=True)

def tx(x, y, text, size=13, weight="normal", fill="#1e293b",
        anchor="middle", italic=False, family="'Segoe UI',Arial,sans-serif"):
    st = f"font-size:{size}px;font-weight:{weight};font-family:{family};"
    if italic: st += "font-style:italic;"
    return f'<text x="{x}" y="{y}" fill="{fill}" text-anchor="{anchor}" style="{st}">{esc(text)}</text>'

def rx_rect(x, y, w, h, fill, stroke, sw=1.5, rx=10):
    return (f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" '
            f'fill="{fill}" stroke="{stroke}" stroke-width="{sw}"/>')

def line(x1,y1,x2,y2,col="#64748b",sw=1.5,dash=""):
    d = f' stroke-dasharray="{dash}"' if dash else ""
    return f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{col}" stroke-width="{sw}"{d}/>'

def arrow(x1,y1,x2,y2,col="#475569",sw=1.5,dash="",label="",label_italic=True):
    dx,dy = x2-x1, y2-y1
    L = math.hypot(dx,dy) or 1
    ux,uy = dx/L, dy/L
    ah=11
    ax=ah*(ux*.866-uy*.5); ay=ah*(ux*.5+uy*.866)
    bx=ah*(ux*.866+uy*.5); by=ah*(-ux*.5+uy*.866)
    d2 = f' stroke-dasharray="{dash}"' if dash else ""
    out = (f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" '
           f'stroke="{col}" stroke-width="{sw}"{d2}/>'
           f'<line x1="{x2}" y1="{y2}" x2="{x2-ax:.1f}" y2="{y2-ay:.1f}" stroke="{col}" stroke-width="{sw}"/>'
           f'<line x1="{x2}" y1="{y2}" x2="{x2-bx:.1f}" y2="{y2-by:.1f}" stroke="{col}" stroke-width="{sw}"/>')
    if label:
        mx,my = (x1+x2)/2,(y1+y2)/2
        out += (f'<rect x="{mx-36}" y="{my-10}" width="72" height="18" rx="4" fill="white" opacity="0.92"/>'
                + tx(mx,my+4,label,size=10,fill=col,italic=label_italic))
    return out

def wrap(text, max_ch=22):
    words=text.split(); lines=[]; cur=""
    for w in words:
        c=(cur+" "+w).strip()
        if len(c)<=max_ch: cur=c
        else:
            if cur: lines.append(cur)
            cur=w
    if cur: lines.append(cur)
    return lines or [text]

def multiline_text(cx, cy, text, size=12, fill="#1e293b", weight="normal",
                   anchor="middle", line_h=16, max_ch=22):
    ls=wrap(text,max_ch)
    n=len(ls)
    out=""
    for i,l in enumerate(ls):
        dy = (i-(n-1)/2)*line_h
        out+=tx(cx,cy+dy,l,size,weight,fill,anchor)
    return out

def svg_wrap(w,h,body,title=""):
    t = f'<title>{esc(title)}</title>' if title else ""
    return (f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" '
            f'viewBox="0 0 {w} {h}">\n{t}\n{body}\n</svg>')


# ═══════════════════════════════════════════════════════════════════════════
# 1. DIAGRAMME DE CLASSES — Corrigé (UML pur, méthodes métier, pas de PK/FK)
# ═══════════════════════════════════════════════════════════════════════════

def class_box(x, y, w, name, attrs, methods, color, fill):
    """UML class box: name / attributes / methods"""
    rh = 17      # row height
    pad = 8
    header_h = 36
    attr_h = max(len(attrs), 1) * rh + pad*2
    meth_h = max(len(methods), 1) * rh + pad*2
    total_h = header_h + attr_h + meth_h

    out = rx_rect(x, y, w, total_h, fill, color, sw=2, rx=6)
    # Header
    out += rx_rect(x, y, w, header_h, color, color, sw=0, rx=6)
    out += f'<rect x="{x}" y="{y+header_h-6}" width="{w}" height="6" fill="{color}"/>'
    out += tx(x+w/2, y+header_h-10, name, size=13, weight="800", fill="white")
    # Attributes section
    ay = y + header_h + pad + 3
    for a in attrs:
        out += tx(x+8, ay, a, size=11, fill="#334155", anchor="start")
        ay += rh
    # Divider
    div_y = y + header_h + attr_h
    out += line(x, div_y, x+w, div_y, col=color, sw=1.2, dash="4 3")
    # Methods section
    my = div_y + pad + 3
    for m in methods:
        out += tx(x+8, my, m, size=11, fill="#1e3a5f", anchor="start", italic=True)
        my += rh
    return out, total_h

def assoc_line(x1,y1,x2,y2,label="",col="#475569",style="solid",arrow_end=True,
               mult_start="",mult_end=""):
    dash="6 4" if style=="dashed" else ""
    out=arrow(x1,y1,x2,y2,col,sw=1.5,dash=dash) if arrow_end else line(x1,y1,x2,y2,col,sw=1.5,dash=dash)
    mx,my=(x1+x2)/2,(y1+y2)/2
    if label:
        out+=(f'<rect x="{mx-34}" y="{my-9}" width="68" height="16" rx="4" fill="white" opacity="0.88"/>'
              + tx(mx,my+4,label,size=10,fill=col))
    if mult_start:
        out+=tx(x1+14,y1-6,mult_start,size=10,fill=col)
    if mult_end:
        out+=tx(x2-14,y2-6,mult_end,size=10,fill=col)
    return out

def build_class_diagram():
    W,H = 1700,1100
    C = {
        "user":    ("#2563eb","#eff6ff"),
        "school":  ("#0f766e","#f0fdfa"),
        "acad":    ("#15803d","#f0fdf4"),
        "fee":     ("#7c3aed","#faf5ff"),
        "comm":    ("#b45309","#fffbeb"),
        "sys":     ("#475569","#f8fafc"),
    }
    parts=[f'<rect width="{W}" height="{H}" fill="#f8fafc"/>']
    parts.append(tx(W//2,38,"EduSuivi — Diagramme de classes (UML)",
                    size=24,weight="800",fill="#0f172a"))
    parts.append(tx(W//2,62,
        "Modélisation orientée objet — attributs métier et comportements principaux",
        size=13,fill="#64748b"))

    boxes={}
    def add(key,x,y,w,name,attrs,methods,c_key):
        col,fill=C[c_key]
        svg,h=class_box(x,y,w,name,attrs,methods,col,fill)
        parts.append(svg)
        boxes[key]=(x,y,w,h)

    # ── Colonne 1 : Utilisateur
    add("User",30,90,240,"Utilisateur",
        ["- nom : String","- email : String","- motDePasse : String","- role : Enum"],
        ["+ seConnecter()","+ seDeconnecter()","+ reinitialiserMDP()","+ modifierProfil()"],
        "user")

    add("Pref",30,370,240,"PreferenceUtilisateur",
        ["- langue : String","- theme : String","- notifActives : Bool"],
        ["+ changerLangue()","+ changerTheme()"],
        "user")

    add("Log",30,600,240,"JournalActivite",
        ["- action : String","- detail : String","- adresseIP : String"],
        ["+ enregistrer()"],
        "sys")

    # ── Colonne 2 : École
    add("Class",310,90,230,"Classe",
        ["- nom : String","- niveau : String","- anneeScolaire : String"],
        ["+ listerEleves()","+ getEnseignantTitulaire()"],
        "school")

    add("Subject",310,320,230,"Matiere",
        ["- nom : String","- coefficient : Float"],
        ["+ getMoyenneClasse()"],
        "school")

    add("Assign",310,490,230,"AffectationEnseignant",
        ["- anneeScolaire : String"],
        ["+ valider()"],
        "school")

    # ── Colonne 3 : Élève
    add("Student",580,90,240,"Eleve",
        ["- matricule : String","- nom : String","- prenom : String","- dateNaissance : Date"],
        ["+ calculerMoyenne()","+ getClassement()","+ getBulletin()"],
        "school")

    add("Grade",580,370,240,"Note",
        ["- valeur : Float","- noteMax : Float","- periode : String","- commentaire : String"],
        ["+ getPourcentage()"],
        "acad")

    add("Absence",580,570,240,"Absence",
        ["- date : Date","- justifiee : Bool","- motif : String"],
        ["+ justifier()","+ getStatut()"],
        "acad")

    add("Ranking",580,770,240,"Classement",
        ["- moyenne : Float","- rang : Int","- periode : String"],
        ["+ calculer()"],
        "acad")

    # ── Colonne 4 : Frais
    add("FeeType",870,90,230,"TypeFrais",
        ["- libelle : String","- montant : Float","- anneeScolaire : String"],
        ["+ estActif() : Bool"],
        "fee")

    add("StudentFee",870,300,230,"FraisEleve",
        ["- montantDu : Float","- anneeScolaire : String"],
        ["+ getSolde() : Float","+ estSolde() : Bool"],
        "fee")

    add("Payment",870,510,230,"Paiement",
        ["- montantPaye : Float","- modePaiement : String","- dateOperation : Date","- refTransaction : String"],
        ["+ genererRecu() : String","+ confirmer()"],
        "fee")

    add("MobilePayment",870,760,230,"PaiementMobileMoney",
        ["- operateur : String","- numeroPaye : String","- statut : String"],
        ["+ initierPaiement()","+ verifierStatut()","+ annuler()"],
        "fee")

    # ── Colonne 5 : Communication
    add("Notif",1170,90,240,"Notification",
        ["- type : String","- titre : String","- corps : String","- lue : Bool"],
        ["+ marquerLue()","+ envoyer()"],
        "comm")

    add("Msg",1170,330,240,"Message",
        ["- sujet : String","- corps : String","- lu : Bool"],
        ["+ envoyer()","+ supprimer()","+ marquerLu()"],
        "comm")

    add("Agenda",1170,560,240,"EvenementAgenda",
        ["- titre : String","- dateEvenement : Date","- type : String","- cible : String"],
        ["+ notifierConcernes()"],
        "comm")

    add("Annonce",1170,770,240,"Annonce",
        ["- titre : String","- contenu : String","- cible : String"],
        ["+ diffuser()","+ archiver()"],
        "comm")

    # ── Relations
    def mid_right(k): x,y,w,h=boxes[k]; return x+w,y+h//2
    def mid_left(k):  x,y,w,h=boxes[k]; return x,y+h//2
    def mid_bot(k):   x,y,w,h=boxes[k]; return x+w//2,y+h
    def mid_top(k):   x,y,w,h=boxes[k]; return x+w//2,y

    rels=[
        # User → Pref (composition 1-1)
        (mid_bot("User"),mid_top("Pref"),  "possède","#2563eb","solid","1","1"),
        # User → Log
        (mid_bot("Pref"),mid_top("Log"),   "génère","#475569","solid","1","*"),
        # Classe → Subject (via Assign)
        (mid_bot("Class"),mid_top("Subject"),"enseigne","#0f766e","solid","1","*"),
        (mid_bot("Subject"),mid_top("Assign"),"affectation","#0f766e","solid","1","*"),
        # Student
        (mid_right("Class"),mid_left("Student"),"contient","#0f766e","solid","1","*"),
        (mid_bot("Student"),mid_top("Grade"),"reçoit","#15803d","solid","1","*"),
        (mid_bot("Grade"),mid_top("Absence"),"","#15803d","solid","",""),
        (mid_bot("Absence"),mid_top("Ranking"),"","#15803d","solid","",""),
        # Fee
        (mid_right("FeeType"),mid_left("StudentFee"),"génère","#7c3aed","solid","1","*"),
        (mid_bot("StudentFee"),mid_top("Payment"),"règlement","#7c3aed","solid","1","*"),
        (mid_bot("Payment"),mid_top("MobilePayment"),"spécialise","#7c3aed","dashed","",""),
        # Student → Fee
        (mid_right("Student"),mid_left("StudentFee"),"frais","#7c3aed","solid","1","*"),
        # Notif
        (mid_right("Student"),mid_left("Notif"),"notifié","#b45309","dashed","",""),
        (mid_bot("Notif"),mid_top("Msg"),"","#b45309","solid","",""),
        (mid_bot("Msg"),mid_top("Agenda"),"","#b45309","solid","",""),
        (mid_bot("Agenda"),mid_top("Annonce"),"","#b45309","solid","",""),
    ]
    for pts in rels:
        if len(pts)==7:
            (x1,y1),(x2,y2),lbl,col,st,ms,me=pts
            parts.append(assoc_line(x1,y1,x2,y2,lbl,col,st,True,ms,me))
        else:
            (x1,y1),(x2,y2),lbl,col,st=pts
            parts.append(assoc_line(x1,y1,x2,y2,lbl,col,st))

    # Légende
    LY=H-55
    parts.append(rx_rect(30,LY,1640,45,"white","#e2e8f0",rx=8))
    legends=[
        (60,"── flèche pleine","Association / Composition"),
        (380,"── flèche tirets","Héritage / Spécialisation"),
        (720,"1..*","Multiplicité (un à plusieurs)"),
        (1000,"1","Multiplicité (un à un)"),
        (1250,"italique","Méthodes comportementales"),
    ]
    for lx,sym,desc in legends:
        parts.append(tx(lx,LY+18,sym,size=11,fill="#475569",anchor="start",weight="700"))
        parts.append(tx(lx,LY+34,desc,size=10,fill="#64748b",anchor="start"))

    return svg_wrap(W,H,"\n".join(parts),"EduSuivi Diagramme de classes UML")


# ═══════════════════════════════════════════════════════════════════════════
# 2. DIAGRAMME DE SÉQUENCE (paiement Mobile Money par le Parent)
# ═══════════════════════════════════════════════════════════════════════════

def build_sequence_diagram():
    W,H=1300,920
    parts=[f'<rect width="{W}" height="{H}" fill="#f8fafc"/>']
    parts.append(tx(W//2,36,"EduSuivi — Diagramme de séquence : Paiement Mobile Money",
                    size=20,weight="800",fill="#0f172a"))
    parts.append(tx(W//2,58,
        "Scénario : le Parent initie un paiement via Mobile Money depuis son espace",
        size=12,fill="#64748b"))

    COLORS={
        "Parent":"#d97706","App":"#2563eb","API":"#16a34a","Operateur":"#7c3aed","DB":"#475569"
    }
    actors=[
        ("Parent",    110, COLORS["Parent"]),
        ("Application\nEduSuivi", 360, COLORS["App"]),
        ("Service\nMobile Money", 630, COLORS["API"]),
        ("Opérateur\n(ex: M-Pesa)", 880, COLORS["Operateur"]),
        ("Base de\nDonnées",       1130, COLORS["DB"]),
    ]

    LIFE_TOP=110; LIFE_BOT=H-60
    # Draw actors + lifelines
    for name,cx,col in actors:
        ls=name.split("\n")
        bw=120; bh=44+len(ls)*4
        parts.append(rx_rect(cx-bw//2,LIFE_TOP-bh,bw,bh,col,"none",rx=8))
        for i,l in enumerate(ls):
            dy=(i-(len(ls)-1)/2)*15
            parts.append(tx(cx,LIFE_TOP-bh//2+dy+4,l,size=12,weight="700",fill="white"))
        parts.append(line(cx,LIFE_TOP,cx,LIFE_BOT,col=col,sw=1.5,dash="6 4"))

    def msg(ya,yb,x_from,x_to,text,col="#475569",ret=False,act=False):
        d="4 3" if ret else ""
        fill_arr= col
        parts.append(arrow(x_from,ya,x_to,ya,col,sw=1.5,dash=d))
        off=8 if x_to>x_from else -8
        parts.append(tx((x_from+x_to)/2,ya-5,text,size=11,fill=col,
                        italic=ret,anchor="middle"))
        if act:
            aw=16; ah=yb-ya if yb else 30
            ax=x_to-aw//2
            parts.append(rx_rect(ax,ya,aw,ah,"white",col,sw=1.5,rx=3))

    def box(ya,yb,x_center,w,text,col,fill):
        parts.append(rx_rect(x_center-w//2,ya,w,yb-ya,fill,col,sw=1.2,rx=6))
        parts.append(tx(x_center,(ya+yb)/2,text,size=10,fill=col,
                        weight="600",anchor="middle"))

    def note_box(x,y,w,h,text,bg="#fffbeb",col="#b45309"):
        parts.append(rx_rect(x,y,w,h,bg,col,sw=1.2,rx=4))
        parts.append(multiline_text(x+w//2,y+h//2+4,text,size=10,fill=col,max_ch=28))

    PA,AP,SM,OP,DB = [a[1] for a in actors]

    # ── Séquence ──
    Y=140
    # 1. Parent consulte ses frais
    msg(Y,Y+20,PA,AP,"Consulter l'espace Frais scolaires",COLORS["Parent"])
    Y+=28
    parts.append(arrow(AP,Y,PA,Y,COLORS["App"],sw=1.2,dash="4 3"))
    parts.append(tx((PA+AP)/2,Y-5,"Affiche solde restant + bouton «Payer»",
                    size=10,fill=COLORS["App"],italic=True))
    Y+=35

    # 2. Choisit opérateur
    note_box(PA-55,Y,230,28,"Choisit M-Pesa / Airtel / Orange\net saisit son numéro + montant")
    Y+=45
    msg(Y,Y,PA,AP,"Soumettre demande de paiement (opérateur, numéro, montant)",COLORS["Parent"])
    Y+=30

    # 3. App → Service MM
    msg(Y,Y,AP,SM,"Initier transaction (ref, montant, numéro)",COLORS["App"])
    Y+=30
    msg(Y,Y,SM,OP,"Envoyer notification USSD/push au téléphone",COLORS["API"])
    Y+=30

    # 4. Opérateur → utilisateur
    parts.append(rx_rect(OP-60,Y,120,28,"#faf5ff","#7c3aed",rx=6))
    parts.append(tx(OP,Y+14,"PIN saisi par le parent",size=10,fill="#7c3aed",weight="600"))
    Y+=40

    msg(Y,Y,OP,SM,"Confirmation de paiement (statut OK)",COLORS["Operateur"])
    Y+=30
    msg(Y,Y,SM,AP,"Réponse : {statut:success, ref_transaction}",COLORS["API"])
    Y+=30

    # 5. App enregistre en DB
    msg(Y,Y,AP,DB,"Enregistrer paiement (montant, opérateur, ref, statut)",COLORS["App"])
    Y+=25
    parts.append(arrow(DB,Y,AP,Y,COLORS["DB"],sw=1.2,dash="4 3"))
    parts.append(tx((AP+DB)/2,Y-5,"ID paiement confirmé",size=10,fill=COLORS["DB"],italic=True))
    Y+=30

    # 6. Notification parent
    msg(Y,Y,AP,DB,"Créer notification «Paiement confirmé»",COLORS["App"])
    Y+=25
    parts.append(arrow(DB,Y,AP,Y,COLORS["DB"],sw=1.2,dash="4 3"))
    parts.append(tx((AP+DB)/2,Y-5,"OK",size=10,fill=COLORS["DB"],italic=True))
    Y+=30

    # 7. Réponse au parent
    parts.append(arrow(AP,Y,PA,Y,COLORS["App"],sw=1.5,dash="4 3"))
    parts.append(tx((PA+AP)/2,Y-5,"Afficher reçu + badge de confirmation",
                    size=10,fill=COLORS["App"],italic=True))
    Y+=30

    note_box(PA-60,Y,210,36,
             "Recu ref: EDU-XXXXX\nStatut: CONFIRME",bg="#f0fdf4",col="#16a34a")
    Y+=50

    # Fragment ALT (paiement échoué)
    parts.append(rx_rect(PA-70,Y,W-PA+60,80,"#fff7f7","#ef4444",sw=1,rx=6))
    parts.append(tx(PA-60,Y+14,"alt  [Paiement refusé / timeout]",
                    size=10,fill="#ef4444",weight="700",anchor="start"))
    parts.append(arrow(SM,Y+38,AP,Y+38,"#ef4444",sw=1.2,dash="4 3"))
    parts.append(tx((AP+SM)/2,Y+32,"Erreur : paiement non abouti",size=10,fill="#ef4444",italic=True))
    parts.append(arrow(AP,Y+58,PA,Y+58,"#ef4444",sw=1.2,dash="4 3"))
    parts.append(tx((PA+AP)/2,Y+52,"Message d'erreur — inviter à réessayer",
                    size=10,fill="#ef4444",italic=True))

    return svg_wrap(W,H,"\n".join(parts),"Sequence paiement Mobile Money")


# ═══════════════════════════════════════════════════════════════════════════
# 3. DIAGRAMME D'ACTIVITÉS — Simplifié (3 couloirs, sans SQL, flux métier)
# ═══════════════════════════════════════════════════════════════════════════

def build_activity_diagram():
    W,H=1500,1000
    parts=[f'<rect width="{W}" height="{H}" fill="#f8fafc"/>']
    parts.append(tx(W//2,36,"EduSuivi — Diagramme d'activités (simplifié)",
                    size=22,weight="800",fill="#0f172a"))
    parts.append(tx(W//2,58,
        "Flux métier principal par rôle — authentification, actions et consultation",
        size=12,fill="#64748b"))

    LANE_Y=80; LANE_H=H-100
    lanes=[
        (0,  420,"Administrateur","#f0fdf4","#16a34a"),
        (420,420,"Enseignant",    "#faf5ff","#7c3aed"),
        (840,420,"Parent",        "#fff7ed","#d97706"),
    ]
    # Draw lanes
    for lx,lw,title,bg,col in lanes:
        parts.append(rx_rect(lx,LANE_Y,lw,LANE_H,bg,col,sw=2,rx=0))
        parts.append(rx_rect(lx,LANE_Y,lw,34,col,col,sw=0,rx=0))
        parts.append(tx(lx+lw//2,LANE_Y+22,title,size=15,weight="800",fill="white"))

    def node(cx,cy,w,h,text,fill,col,shape="rect"):
        ls=wrap(text,max(8,w//10))
        n=len(ls); lh=16
        start_y=cy-((n-1)*lh/2)+5
        if shape=="round":
            out=rx_rect(cx-w//2,cy-h//2,w,h,fill,col,rx=h//2)
        elif shape=="diamond":
            hw,hh=w//2,h//2
            pts=f"{cx},{cy-hh} {cx+hw},{cy} {cx},{cy+hh} {cx-hw},{cy}"
            out=f'<polygon points="{pts}" fill="{fill}" stroke="{col}" stroke-width="1.5"/>'
        else:
            out=rx_rect(cx-w//2,cy-h//2,w,h,fill,col,rx=8)
        for i,l in enumerate(ls):
            dy=(i-(n-1)/2)*lh
            out+=tx(cx,cy+dy+4,l,size=11,fill="#1e293b",weight="600")
        return out

    # AUTH (shared, top center)
    auth_cx=W//2
    parts.append(tx(auth_cx,LANE_Y+60,"─── Authentification (commune) ───",
                    size=11,fill="#475569",weight="600"))
    # Start
    parts.append(f'<circle cx="{auth_cx}" cy="{LANE_Y+82}" r="10" fill="#0f172a"/>')
    parts.append(arrow(auth_cx,LANE_Y+92,auth_cx,LANE_Y+115,"#0f172a",sw=1.5))
    # Login
    parts.append(node(auth_cx,LANE_Y+138,240,36,"Se connecter (email + mot de passe)","white","#2563eb"))
    parts.append(arrow(auth_cx,LANE_Y+156,auth_cx,LANE_Y+178,"#2563eb",sw=1.5))
    # Decision
    parts.append(node(auth_cx,LANE_Y+196,200,38,"Identifiants valides ?","#eff6ff","#2563eb","diamond"))
    # Non
    parts.append(arrow(auth_cx+100,LANE_Y+196,auth_cx+220,LANE_Y+196,"#ef4444",sw=1.3))
    parts.append(node(auth_cx+310,LANE_Y+196,170,32,"Afficher erreur — réessayer","#fee2e2","#ef4444"))
    parts.append(arrow(auth_cx+220,LANE_Y+215,auth_cx+220,LANE_Y+245,"#ef4444",sw=1.2))
    parts.append(arrow(auth_cx+220,LANE_Y+245,auth_cx+120,LANE_Y+245,"#ef4444",sw=1.2))
    parts.append(arrow(auth_cx+120,LANE_Y+245,auth_cx+120,LANE_Y+160,"#ef4444",sw=1.2))
    # Oui → détection rôle
    parts.append(arrow(auth_cx,LANE_Y+215,auth_cx,LANE_Y+240,"#2563eb",sw=1.5))
    parts.append(node(auth_cx,LANE_Y+260,180,34,"Détecter le rôle","#eff6ff","#2563eb","diamond"))

    # Fork vers 3 espaces
    FY=LANE_Y+280
    ADMIN_CX,TEACH_CX,PAR_CX=210,630,1050

    parts.append(arrow(auth_cx,FY,ADMIN_CX,FY+45,"#16a34a",sw=1.4,label="Admin"))
    parts.append(arrow(auth_cx,FY,TEACH_CX,FY+45,"#7c3aed",sw=1.4,label="Enseignant"))
    parts.append(arrow(auth_cx,FY,PAR_CX,FY+45,"#d97706",sw=1.4,label="Parent"))

    Y0=FY+70

    # ── ADMIN COLUMN ──
    ay=Y0
    admin_nodes=[
        ("Tableau de bord","white","#16a34a"),
        ("Gérer les élèves\n(ajouter/modifier/archiver)","#dcfce7","#16a34a"),
        ("Gérer les frais\net paiements","#dcfce7","#16a34a"),
        ("Diffuser annonce\nou gérer agenda","#dcfce7","#16a34a"),
        ("Consulter rapports\net statistiques","#dcfce7","#16a34a"),
        ("Sauvegarder la base","#dcfce7","#16a34a"),
    ]
    for txt,f,c in admin_nodes:
        parts.append(node(ADMIN_CX,ay,340,42,txt,f,c))
        if txt!=admin_nodes[-1][0]:
            parts.append(arrow(ADMIN_CX,ay+21,ADMIN_CX,ay+50,"#16a34a",sw=1.3))
        ay+=60

    # ── TEACHER COLUMN ──
    ty=Y0
    teach_nodes=[
        ("Tableau de bord","white","#7c3aed"),
        ("Saisir / modifier\nles notes","#ede9fe","#7c3aed"),
        ("Signaler et justifier\nles absences","#ede9fe","#7c3aed"),
        ("Consulter ses classes\net matières assignées","#ede9fe","#7c3aed"),
    ]
    for txt,f,c in teach_nodes:
        parts.append(node(TEACH_CX,ty,340,42,txt,f,c))
        if txt!=teach_nodes[-1][0]:
            parts.append(arrow(TEACH_CX,ty+21,TEACH_CX,ty+50,"#7c3aed",sw=1.3))
        ty+=60

    # ── PARENT COLUMN ──
    py=Y0
    parent_nodes=[
        ("Tableau de bord","white","#d97706"),
        ("Consulter notes\net bulletins","#fef3c7","#d97706"),
        ("Consulter les absences","#fef3c7","#d97706"),
        ("Suivre les frais\nscolaires","#fef3c7","#d97706"),
        ("Payer via\nMobile Money","#fef3c7","#d97706"),
        ("Lire annonces\net agenda","#fef3c7","#d97706"),
    ]
    for txt,f,c in parent_nodes:
        parts.append(node(PAR_CX,py,340,42,txt,f,c))
        if txt!=parent_nodes[-1][0]:
            parts.append(arrow(PAR_CX,py+21,PAR_CX,py+50,"#d97706",sw=1.3))
        py+=60

    # ── COMMON ACTIONS ──
    BOTTOM=max(ay,ty,py)+30
    COMMON_Y=BOTTOM+10
    parts.append(rx_rect(30,COMMON_Y,1440,62,"#f1f5f9","#64748b",rx=8))
    parts.append(tx(W//2,COMMON_Y+16,"Actions communes : Messagerie · Notifications · Gérer profil / langue / thème · Se déconnecter",
                    size=12,fill="#334155",weight="600"))
    # End
    END_Y=COMMON_Y+82
    parts.append(f'<circle cx="{W//2}" cy="{END_Y}" r="14" fill="none" stroke="#0f172a" stroke-width="3"/>')
    parts.append(f'<circle cx="{W//2}" cy="{END_Y}" r="9" fill="#0f172a"/>')
    parts.append(tx(W//2,END_Y+26,"Fin",size=11,fill="#64748b"))

    return svg_wrap(W,H,"\n".join(parts),"EduSuivi Diagramme activites simplifie")


# ═══════════════════════════════════════════════════════════════════════════
# 4. DIAGRAMME DE DÉPLOIEMENT — Corrigé (dev local + hébergement production)
# ═══════════════════════════════════════════════════════════════════════════

def build_deployment_diagram():
    W,H=1500,920
    parts=[f'<rect width="{W}" height="{H}" fill="#f8fafc"/>']
    parts.append(tx(W//2,38,"EduSuivi — Diagramme de déploiement",
                    size=22,weight="800",fill="#0f172a"))
    parts.append(tx(W//2,60,
        "Environnement de développement local (XAMPP) et hébergement de production",
        size=12,fill="#64748b"))

    def node_box(x,y,w,h,title,subtitle,fill,col,icon=""):
        out=rx_rect(x,y,w,h,fill,col,sw=2,rx=10)
        out+=tx(x+w//2,y+26,f"{icon}  {title}" if icon else title,
                size=13,weight="800",fill=col)
        if subtitle:
            out+=tx(x+w//2,y+42,subtitle,size=10,fill="#64748b")
        return out

    def artifact(x,y,w,h,text,fill="#fff",col="#64748b"):
        out=rx_rect(x,y,w,h,fill,col,sw=1.2,rx=6)
        out+=tx(x+w//2,y+h//2+4,text,size=11,fill=col,weight="600")
        return out

    def node_device(x,y,w,h,label,fill,col):
        out=rx_rect(x,y,w,h,fill,col,sw=2.5,rx=12)
        tab_w=min(len(label)*9+16,w-10)
        out+=rx_rect(x,y-22,tab_w,24,col,col,sw=0,rx=6)
        out+=tx(x+tab_w//2,y-8,label,size=12,weight="800",fill="white")
        return out

    # ── ZONE 1 : Clients ──
    CL_X,CL_Y,CL_W,CL_H=30,90,300,400
    parts.append(node_device(CL_X,CL_Y,CL_W,CL_H,"Postes Clients","#eff6ff","#2563eb"))
    parts.append(tx(CL_X+CL_W//2,CL_Y+30,"Navigateur Web",size=12,fill="#1e3a5f",weight="700"))
    for i,(device,sub) in enumerate([
        ("Ordinateur","Chrome / Firefox / Edge"),
        ("Smartphone","Safari / Chrome Mobile"),
        ("Tablette","Accès complet responsive"),
    ]):
        yy=CL_Y+70+i*100
        parts.append(artifact(CL_X+20,yy,CL_W-40,58,f"{device}\n{sub}","#dbeafe","#2563eb"))
    parts.append(tx(CL_X+CL_W//2,CL_Y+CL_H-18,"HTML5 · CSS3 · JavaScript",size=10,fill="#64748b"))

    # ── ZONE 2 : Serveur développement (XAMPP) ──
    DEV_X,DEV_Y,DEV_W,DEV_H=380,90,450,400
    parts.append(node_device(DEV_X,DEV_Y,DEV_W,DEV_H,"Serveur Dev — XAMPP (Windows local)","#f0fdf4","#16a34a"))
    parts.append(tx(DEV_X+DEV_W//2,DEV_Y+28,"Machine de développement",size=11,fill="#14532d"))

    comp_dev=[
        ("Apache HTTP Server 2.4","Port 80 — serve PHP","#dcfce7","#16a34a"),
        ("PHP 8.x + PDO","Moteur d'application","#dcfce7","#16a34a"),
        ("MySQL / MariaDB","Port 3306 — Base edusuivi","#dcfce7","#16a34a"),
        ("phpMyAdmin","Administration locale BDD","#f8fafc","#64748b"),
    ]
    for i,(n,s,f,c) in enumerate(comp_dev):
        yy=DEV_Y+55+i*82
        parts.append(artifact(DEV_X+15,yy,DEV_W-30,58,f"{n}\n{s}",f,c))

    # ── ZONE 3 : Serveur Production (hébergeur) ──
    PROD_X,PROD_Y,PROD_W,PROD_H=890,90,560,720
    parts.append(node_device(PROD_X,PROD_Y,PROD_W,PROD_H,
                              "Serveur de Production — Hébergement Web","#faf5ff","#7c3aed"))
    parts.append(tx(PROD_X+PROD_W//2,PROD_Y+26,"ex : OVH / Hostinger / cPanel Linux",
                    size=11,fill="#4c1d95"))

    comp_prod=[
        ("Serveur Web Nginx / Apache","HTTPS · TLS 1.3 · Port 443","#ede9fe","#7c3aed"),
        ("PHP 8.x (FastCGI / PHP-FPM)","Moteur d'application optimisé","#ede9fe","#7c3aed"),
        ("MySQL / MariaDB","Base de données edusuivi","#ede9fe","#7c3aed"),
        ("Certificat SSL / TLS","Chiffrement HTTPS","#ede9fe","#7c3aed"),
        ("Système de fichiers","Sources EduSuivi + uploads","#f8fafc","#64748b"),
        ("Cron Jobs","Rappels de paiement automatiques","#f8fafc","#64748b"),
    ]
    for i,(n,s,f,c) in enumerate(comp_prod):
        yy=PROD_Y+52+i*106
        parts.append(artifact(PROD_X+15,yy,PROD_W-30,72,f"{n}\n{s}",f,c))

    # ── ZONE 4 : Services externes ──
    EXT_X,EXT_Y,EXT_W,EXT_H=890,840,560,62
    parts.append(rx_rect(EXT_X,EXT_Y,EXT_W,EXT_H,"#fff7ed","#d97706",sw=2,rx=10))
    parts.append(tx(EXT_X+EXT_W//2,EXT_Y+20,"APIs Mobile Money (externes)",
                    size=12,weight="800",fill="#92400e"))
    parts.append(tx(EXT_X+EXT_W//2,EXT_Y+42,"M-Pesa · Airtel Money · Orange Money · Afrimoney",
                    size=11,fill="#b45309"))

    # ── Connexions réseau ──
    # Client → Dev
    parts.append(arrow(CL_X+CL_W,CL_Y+200,DEV_X,DEV_Y+200,"#2563eb",sw=2,
                       label="HTTP (local)"))
    # Client → Prod
    parts.append(arrow(CL_X+CL_W//2,CL_Y+CL_H,PROD_X+PROD_W//4,PROD_Y,
                       "#7c3aed",sw=2,label="HTTPS (internet)"))
    # Dev sync → Prod (déploiement)
    parts.append(arrow(DEV_X+DEV_W,DEV_Y+200,PROD_X,PROD_Y+200,
                       "#16a34a",sw=2,dash="8 4",label="FTP / Git deploy"))
    # Prod → External API
    parts.append(arrow(PROD_X+PROD_W//2,PROD_Y+PROD_H,EXT_X+EXT_W//2,EXT_Y,
                       "#d97706",sw=1.8,dash="6 4",label="HTTPS API"))

    # ── Légende ──
    LY=H-42
    parts.append(rx_rect(30,LY,1440,35,"white","#e2e8f0",rx=8))
    parts.append(tx(W//2,LY+14,"Légende :  trait plein = flux de données  |  tirets = déploiement / appel API",
                    size=11,fill="#64748b"))

    return svg_wrap(W,H,"\n".join(parts),"EduSuivi Diagramme deploiement")


# ═══════════════════════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════════════════════

def main():
    diagrams={
        "classes_v2.svg":     build_class_diagram(),
        "sequence_paiement_mobile_money_v2.svg": build_sequence_diagram(),
        "activites_v2.svg":   build_activity_diagram(),
        "deploiement_v2.svg": build_deployment_diagram(),
    }
    for fname,svg in diagrams.items():
        p=OUT/fname
        p.write_text(svg,encoding="utf-8")
        print("OK : "+str(p))

if __name__=="__main__":
    main()

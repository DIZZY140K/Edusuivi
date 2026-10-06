<?php
require_once __DIR__ . '/includes/config.php';

// Déjà connecté → rediriger
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect("/edusuivi/" . ($role === 'school' ? 'admin' : $role) . "/");
}

$success = $error = '';
$step    = 'form'; // 'form' | 'done'

// ── Traitement du formulaire d'inscription ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $schoolName    = trim($_POST['school_name']    ?? '');
    $schoolAddress = trim($_POST['school_address'] ?? '');
    $schoolPhone   = trim($_POST['school_phone']   ?? '');
    $adminName     = trim($_POST['admin_name']     ?? '');
    $adminEmail    = trim($_POST['admin_email']    ?? '');
    $adminPassword = $_POST['admin_password']      ?? '';
    $adminConfirm  = $_POST['admin_confirm']       ?? '';

    // Validations
    if (!$schoolName || !$adminName || !$adminEmail || !$adminPassword) {
        $error = "Tous les champs obligatoires (*) doivent être remplis.";
    } elseif (strlen($schoolName) < 3) {
        $error = "Le nom de l'école doit contenir au moins 3 caractères.";
    } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email du directeur est invalide.";
    } elseif (strlen($adminPassword) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($adminPassword !== $adminConfirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier que l'email n'est pas déjà pris
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$adminEmail]);
        if ($check->fetch()) {
            $error = "Cette adresse email est déjà utilisée par un autre compte.";
        } else {
            // ── DDL hors transaction (MySQL auto-commit sur CREATE/ALTER) ────
            try {
                // 1. Créer la table schools si elle n'existe pas
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS schools (
                        id         INT AUTO_INCREMENT PRIMARY KEY,
                        name       VARCHAR(150) NOT NULL,
                        address    TEXT,
                        phone      VARCHAR(30)  DEFAULT NULL,
                        email      VARCHAR(100) DEFAULT NULL,
                        logo       VARCHAR(255) DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                ");
            } catch(Exception $e) { /* table déjà existante */ }

            try {
                // 2. Ajouter la colonne school_id si manquante
                $cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'school_id'")->fetchAll();
                if (empty($cols)) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN school_id INT DEFAULT NULL");
                }
            } catch(Exception $e) {}

            try {
                // 3. Étendre l'ENUM role si nécessaire
                $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('school','admin','teacher','parent') NOT NULL");
            } catch(Exception $e) { /* déjà à jour */ }

            // ── Transaction pour les INSERTs uniquement ──────────────────────
            try {
                $pdo->beginTransaction();

                // Insérer l'école
                $stmtSchool = $pdo->prepare("INSERT INTO schools (name, address, phone, email) VALUES (?, ?, ?, ?)");
                $stmtSchool->execute([$schoolName, $schoolAddress, $schoolPhone, $adminEmail]);
                $schoolId = $pdo->lastInsertId();

                // Créer le compte directeur (admin)
                $hashed = password_hash($adminPassword, PASSWORD_BCRYPT);
                $stmtAdmin = $pdo->prepare("
                    INSERT INTO users (name, email, password, role, school_id)
                    VALUES (?, ?, ?, 'admin', ?)
                ");
                $stmtAdmin->execute([$adminName, $adminEmail, $hashed, $schoolId]);
                $adminId = $pdo->lastInsertId();

                $pdo->commit();

                // Connexion automatique
                $_SESSION['user_id']    = $adminId;
                $_SESSION['user_name']  = $adminName;
                $_SESSION['user_email'] = $adminEmail;
                $_SESSION['user_role']  = 'admin';
                $_SESSION['school_id']  = $schoolId;

                $step    = 'done';
                $success = $schoolName;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Erreur lors de la création : " . $e->getMessage();
            }
        }
    }
}

// Redirection auto si succès (après 2s de splash)
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduSuivi — Créer une nouvelle école</title>
    <meta name="description" content="Enregistrez votre établissement scolaire sur EduSuivi et commencez la gestion dès aujourd'hui.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
    <link rel="stylesheet" href="/edusuivi/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if ($step === 'done'): ?>
    <meta http-equiv="refresh" content="3;url=/edusuivi/admin/">
    <?php endif; ?>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 0;
        }

        /* ── Layout Split ── */
        .register-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* ── Panneau gauche (branding) ── */
        .register-hero {
            flex: 0 0 420px;
            background: linear-gradient(145deg, #0f0f23 0%, #1a1035 40%, #0d1b2a 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px 48px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow: hidden;
        }

        .register-hero::before {
            content: '';
            position: absolute;
            top: -100px; left: -100px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(99,102,241,.2) 0%, transparent 70%);
            pointer-events: none;
        }

        .register-hero::after {
            content: '';
            position: absolute;
            bottom: -80px; right: -80px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(212,147,13,.15) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-logo {
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 52px;
        }

        .hero-logo-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            box-shadow: 0 8px 24px rgba(99,102,241,.4);
        }

        .hero-logo-text h1 {
            font-size: 22px; font-weight: 800;
            color: #f8fafc; margin: 0;
        }

        .hero-logo-text p {
            font-size: 12px; color: rgba(255,255,255,.45);
            margin: 3px 0 0; letter-spacing: 1px; text-transform: uppercase;
        }

        .hero-title {
            font-size: 34px; font-weight: 900;
            color: #f8fafc;
            line-height: 1.2;
            margin-bottom: 18px;
        }

        .hero-title span {
            background: linear-gradient(135deg, #6366f1, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            font-size: 15px; color: rgba(255,255,255,.55);
            line-height: 1.7;
            margin-bottom: 40px;
        }

        .hero-steps {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .hero-step {
            display: flex; align-items: flex-start; gap: 14px;
        }

        .hero-step-num {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: rgba(99,102,241,.2);
            border: 1px solid rgba(99,102,241,.4);
            color: #818cf8;
            font-size: 12px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .hero-step-text strong {
            font-size: 13px; font-weight: 600;
            color: rgba(255,255,255,.9);
            display: block;
        }

        .hero-step-text span {
            font-size: 12px; color: rgba(255,255,255,.45);
        }

        .hero-back {
            margin-top: 48px;
            display: inline-flex; align-items: center; gap: 8px;
            color: rgba(255,255,255,.4);
            text-decoration: none;
            font-size: 13px;
            transition: color .2s;
        }

        .hero-back:hover { color: rgba(255,255,255,.8); }

        /* ── Panneau droit (formulaire) ── */
        .register-form-side {
            flex: 1;
            overflow-y: auto;
            padding: 48px 56px;
            display: flex;
            flex-direction: column;
        }

        .register-form-side h2 {
            font-size: 26px; font-weight: 800;
            color: var(--text);
            margin-bottom: 6px;
        }

        .register-form-side > p {
            font-size: 14px; color: var(--text-muted);
            margin-bottom: 36px;
        }

        /* ── Section divider ── */
        .form-section {
            margin-bottom: 32px;
        }

        .section-header {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        .section-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }

        .section-icon.school { background: rgba(99,102,241,.15); }
        .section-icon.admin  { background: rgba(212,147,13,.12); }

        .section-title {
            font-size: 15px; font-weight: 700; color: var(--text);
        }

        .section-subtitle {
            font-size: 12px; color: var(--text-muted); margin-top: 2px;
        }

        /* ── Form fields ── */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-row.single { grid-template-columns: 1fr; }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field-group label {
            font-size: 13px; font-weight: 600;
            color: var(--text);
        }

        .field-group label .req { color: #ef4444; margin-left: 2px; }
        .field-group label .opt { color: var(--text-muted); font-weight: 400; font-size: 11px; margin-left: 6px; }

        .field-input {
            padding: 12px 16px;
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-size: 14px; font-family: 'Inter', sans-serif;
            transition: border-color .2s, box-shadow .2s;
            width: 100%;
        }

        .field-input:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }

        .field-input::placeholder { color: var(--text-muted); opacity: .6; }

        textarea.field-input {
            resize: vertical;
            min-height: 80px;
        }

        .field-pwd-wrapper {
            position: relative;
        }

        .field-pwd-wrapper .field-input { padding-right: 46px; }

        .toggle-pwd-btn {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            font-size: 17px; color: var(--text-muted);
            padding: 4px; line-height: 1;
            transition: color .2s;
        }

        .toggle-pwd-btn:hover { color: var(--text); }

        /* ── Error / Success ── */
        .alert-error {
            background: rgba(239,68,68,.08);
            border: 1px solid rgba(239,68,68,.25);
            color: #ef4444;
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 14px; font-weight: 500;
            margin-bottom: 24px;
            display: flex; gap: 10px; align-items: flex-start;
        }

        /* ── Submit button ── */
        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            color: #fff;
            border: none; border-radius: 12px;
            font-size: 16px; font-weight: 700;
            cursor: pointer;
            transition: all .2s;
            margin-top: 8px;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99,102,241,.4);
        }

        .btn-register:active { transform: translateY(0); }

        /* ── Password strength indicator ── */
        .pwd-strength {
            display: flex; gap: 4px; margin-top: 6px;
        }

        .pwd-bar {
            flex: 1; height: 3px; border-radius: 2px;
            background: var(--border);
            transition: background .3s;
        }

        .pwd-hint {
            font-size: 11px; color: var(--text-muted); margin-top: 4px;
        }

        /* ── Success splash ── */
        .success-splash {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            min-height: 100vh; text-align: center;
            padding: 48px;
        }

        .success-icon {
            width: 100px; height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(16,185,129,.2), rgba(16,185,129,.1));
            border: 2px solid rgba(16,185,129,.4);
            display: flex; align-items: center; justify-content: center;
            font-size: 48px;
            margin-bottom: 28px;
            animation: popIn .5s ease-out;
        }

        @keyframes popIn {
            from { transform: scale(0.5); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }

        .success-title {
            font-size: 28px; font-weight: 800;
            color: var(--text); margin-bottom: 10px;
        }

        .success-subtitle {
            font-size: 15px; color: var(--text-muted);
            margin-bottom: 32px; line-height: 1.6;
            max-width: 400px;
        }

        .success-loader {
            display: flex; gap: 6px; align-items: center;
            font-size: 13px; color: var(--text-muted);
        }

        .dot-anim {
            display: flex; gap: 4px;
        }

        .dot-anim span {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #6366f1;
            animation: dotBounce 1.2s ease-in-out infinite;
        }

        .dot-anim span:nth-child(2) { animation-delay: .2s; }
        .dot-anim span:nth-child(3) { animation-delay: .4s; }

        @keyframes dotBounce {
            0%, 80%, 100% { transform: translateY(0); opacity: .4; }
            40% { transform: translateY(-6px); opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .register-hero { display: none; }
            .register-form-side { padding: 32px 24px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php if ($step === 'done'): ?>
<!-- ══ SPLASH DE SUCCÈS ════════════════════════════════════ -->
<div class="success-splash">
    <div class="success-icon">✓</div>
    <h1 class="success-title">École créée avec succès !</h1>
    <p class="success-subtitle">
        <strong><?= escape($success) ?></strong> est maintenant enregistrée sur EduSuivi.<br>
        Vous êtes connecté en tant que directeur. Redirection en cours…
    </p>
    <div class="success-loader">
        <div class="dot-anim">
            <span></span><span></span><span></span>
        </div>
        &nbsp; Chargement de votre tableau de bord…
    </div>
</div>

<?php else: ?>
<!-- ══ FORMULAIRE D'INSCRIPTION ═══════════════════════════ -->
<div class="register-layout">

    <!-- Panneau gauche -->
    <div class="register-hero">
        <div class="hero-logo">
            <div class="hero-logo-icon">🏫</div>
            <div class="hero-logo-text">
                <h1>EduSuivi</h1>
                <p>Portail Scolaire — RDC</p>
            </div>
        </div>

        <h2 class="hero-title">
            Rejoignez le réseau<br>
            <span>scolaire numérique</span>
        </h2>

        <p class="hero-desc">
            Enregistrez votre établissement en quelques secondes et gérez tout depuis un seul endroit : élèves, professeurs, parents, notes, finances et communications.
        </p>

        <div class="hero-steps">
            <div class="hero-step">
                <div class="hero-step-num">1</div>
                <div class="hero-step-text">
                    <strong>Renseignez votre école</strong>
                    <span>Nom et adresse de l'établissement</span>
                </div>
            </div>
            <div class="hero-step">
                <div class="hero-step-num">2</div>
                <div class="hero-step-text">
                    <strong>Créez le compte directeur</strong>
                    <span>Email et mot de passe du responsable</span>
                </div>
            </div>
            <div class="hero-step">
                <div class="hero-step-num">3</div>
                <div class="hero-step-text">
                    <strong>Accédez au tableau de bord</strong>
                    <span>Gestion complète de l'établissement</span>
                </div>
            </div>
        </div>

        <a href="/edusuivi/login.php" class="hero-back">
            ← Retour à la connexion
        </a>
    </div>

    <!-- Panneau droit -->
    <div class="register-form-side">
        <h2>🏫 Nouvelle école</h2>
        <p>Créez votre établissement et votre compte directeur en une seule étape.</p>

        <?php if ($error): ?>
        <div class="alert-error">
            <span>⚠️</span>
            <span><?= escape($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" id="registerForm" novalidate>

            <!-- ── Section 1 : École ── -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon school">🏫</div>
                    <div>
                        <div class="section-title">Informations de l'école</div>
                        <div class="section-subtitle">Identité de votre établissement</div>
                    </div>
                </div>

                <div class="form-row single">
                    <div class="field-group">
                        <label>Nom de l'école <span class="req">*</span></label>
                        <input type="text" name="school_name" class="field-input"
                               placeholder="Ex: École Primaire Saint-Joseph"
                               value="<?= escape($_POST['school_name'] ?? '') ?>"
                               required maxlength="150" id="schoolName">
                    </div>
                </div>

                <div class="form-row single">
                    <div class="field-group">
                        <label>Adresse de l'école <span class="req">*</span></label>
                        <textarea name="school_address" class="field-input"
                                  placeholder="Ex: Avenue Kasa-Vubu, Kinshasa-Gombe, RDC"
                                  required><?= escape($_POST['school_address'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="field-group">
                        <label>Téléphone <span class="opt">(optionnel)</span></label>
                        <input type="tel" name="school_phone" class="field-input"
                               placeholder="+243 81 234 5678"
                               value="<?= escape($_POST['school_phone'] ?? '') ?>">
                    </div>
                    <div class="field-group">
                        <label>Année scolaire <span class="opt">(optionnel)</span></label>
                        <input type="text" name="school_year" class="field-input"
                               placeholder="2024-2025"
                               value="<?= escape($_POST['school_year'] ?? date('Y') . '-' . (date('Y')+1)) ?>">
                    </div>
                </div>
            </div>

            <!-- ── Section 2 : Directeur ── -->
            <div class="form-section">
                <div class="section-header">
                    <div class="section-icon admin">👤</div>
                    <div>
                        <div class="section-title">Compte du directeur</div>
                        <div class="section-subtitle">Il aura le contrôle total de l'école</div>
                    </div>
                </div>

                <div class="form-row single">
                    <div class="field-group">
                        <label>Nom complet du directeur <span class="req">*</span></label>
                        <input type="text" name="admin_name" class="field-input"
                               placeholder="Ex: Directeur Jean Kalumba"
                               value="<?= escape($_POST['admin_name'] ?? '') ?>"
                               required>
                    </div>
                </div>

                <div class="form-row single">
                    <div class="field-group">
                        <label>Email de connexion <span class="req">*</span></label>
                        <input type="email" name="admin_email" class="field-input"
                               placeholder="directeur@votreecole.cd"
                               value="<?= escape($_POST['admin_email'] ?? '') ?>"
                               required id="adminEmail">
                    </div>
                </div>

                <div class="form-row">
                    <div class="field-group">
                        <label>Mot de passe <span class="req">*</span></label>
                        <div class="field-pwd-wrapper">
                            <input type="password" name="admin_password" class="field-input"
                                   placeholder="••••••••" required minlength="6"
                                   id="adminPwd" oninput="checkStrength(this.value)">
                            <button type="button" class="toggle-pwd-btn"
                                    onclick="toggleField('adminPwd')">👁</button>
                        </div>
                        <div class="pwd-strength">
                            <div class="pwd-bar" id="bar1"></div>
                            <div class="pwd-bar" id="bar2"></div>
                            <div class="pwd-bar" id="bar3"></div>
                            <div class="pwd-bar" id="bar4"></div>
                        </div>
                        <div class="pwd-hint" id="pwdHint">Minimum 6 caractères</div>
                    </div>
                    <div class="field-group">
                        <label>Confirmer le mot de passe <span class="req">*</span></label>
                        <div class="field-pwd-wrapper">
                            <input type="password" name="admin_confirm" class="field-input"
                                   placeholder="••••••••" required minlength="6"
                                   id="adminConfirm">
                            <button type="button" class="toggle-pwd-btn"
                                    onclick="toggleField('adminConfirm')">👁</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-register" id="submitBtn">
                <span>🏫</span>
                <span>Créer l'école et accéder au tableau de bord</span>
            </button>

            <p style="text-align:center;margin-top:16px;font-size:12px;color:var(--text-muted);">
                Déjà enregistré ?
                <a href="/edusuivi/login.php" style="color:#6366f1;text-decoration:none;font-weight:600;">
                    Se connecter →
                </a>
            </p>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="/edusuivi/js/fontawesome-icons.js"></script>
<script>
function toggleField(id) {
    const el = document.getElementById(id);
    el.type = el.type === 'password' ? 'text' : 'password';
}

function checkStrength(val) {
    const bars = ['bar1','bar2','bar3','bar4'];
    const hint = document.getElementById('pwdHint');
    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const colors = ['#ef4444','#f59e0b','#3b82f6','#10b981'];
    const labels = ['Trop court','Faible','Moyen','Fort','Très fort'];

    bars.forEach((b, i) => {
        document.getElementById(b).style.background = i < score ? colors[Math.min(score-1, 3)] : 'var(--border)';
    });
    hint.textContent = labels[score] || 'Minimum 6 caractères';
    hint.style.color = score > 0 ? colors[Math.min(score-1, 3)] : 'var(--text-muted)';
}

// Validation côté client
document.getElementById('registerForm')?.addEventListener('submit', function(e) {
    const pwd = document.getElementById('adminPwd').value;
    const confirm = document.getElementById('adminConfirm').value;
    if (pwd !== confirm) {
        e.preventDefault();
        alert('Les mots de passe ne correspondent pas.');
        return;
    }
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<span>⏳</span><span>Création en cours…</span>';
    btn.disabled = true;
});
</script>
</body>
</html>

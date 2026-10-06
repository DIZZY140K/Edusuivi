<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    // Les comptes 'school' vont aussi vers admin
    $dest = ($role === 'school') ? 'admin' : $role;
    redirect("/edusuivi/$dest/");
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_name']  = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['school_id']  = $user['school_id'] ?? null;

        // school → admin dashboard directement
        $dest = ($user['role'] === 'school') ? 'admin' : $user['role'];
        redirect("/edusuivi/$dest/");
    } else {
        $error = "Identifiants incorrects. Veuillez réessayer.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduSuivi — Connexion</title>
    <meta name="description" content="Connectez-vous à EduSuivi — Portail scolaire pour la RDC">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
    <link rel="stylesheet" href="/edusuivi/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ── Surcharge login pour le bouton "Créer une école" ── */
        .create-school-cta {
            display: flex;
            align-items: center;
            gap: 14px;
            background: linear-gradient(135deg, rgba(99,102,241,.12), rgba(129,140,248,.06));
            border: 1.5px solid rgba(99,102,241,.3);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            text-decoration: none;
            transition: all .25s;
            cursor: pointer;
        }

        .create-school-cta:hover {
            background: linear-gradient(135deg, rgba(99,102,241,.22), rgba(129,140,248,.12));
            border-color: rgba(99,102,241,.6);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(99,102,241,.2);
        }

        .create-school-cta .cta-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }

        .create-school-cta .cta-text strong {
            display: block;
            font-size: 14px; font-weight: 700;
            color: var(--text); margin-bottom: 2px;
        }

        .create-school-cta .cta-text span {
            font-size: 12px; color: var(--text-muted);
        }

        .create-school-cta .cta-arrow {
            margin-left: auto;
            color: #818cf8; font-size: 18px; font-weight: 700;
            transition: transform .2s;
        }

        .create-school-cta:hover .cta-arrow {
            transform: translateX(4px);
        }

        /* Badge réseau d'écoles */
        .school-count-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(99,102,241,.15);
            border: 1px solid rgba(99,102,241,.3);
            color: #818cf8;
            font-size: 12px; font-weight: 600;
            padding: 4px 12px; border-radius: 20px;
            margin-bottom: 20px;
        }

        /* ── Divider ── */
        .login-divider {
            display: flex; align-items: center; gap: 12px;
            margin: 20px 0;
        }

        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .login-divider span {
            font-size: 12px; color: var(--text-muted); white-space: nowrap;
        }

        /* ── Demo compact ── */
        .demo-accounts {
            background: rgba(0,0,0,.15);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 20px;
        }

        .demo-accounts .label {
            font-size: 11px; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .demo-btns {
            display: flex; gap: 6px; flex-wrap: wrap;
        }

        .demo-btn {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px; font-weight: 600;
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--text-muted);
            cursor: pointer;
            transition: all .2s;
        }

        .demo-btn:hover { border-color: var(--primary); color: var(--primary); }
        .demo-btn.admin  { border-color: rgba(212,147,13,.4); color: var(--primary); }
    </style>
</head>
<body>
<div class="login-container">

    <!-- ── PANNEAU GAUCHE (branding) ── -->
    <div class="login-hero">
        <div class="logo">
            <div class="logo-icon">E</div>
            <div class="logo-text">
                <h1>EduSuivi</h1>
                <p>Portail Scolaire — RDC</p>
            </div>
        </div>

        <span class="school-count-badge">🌐 Réseau scolaire numérique</span>

        <h2>Le suivi scolaire<br><span>à portée de main</span></h2>
        <p class="desc">
            Connectez parents, enseignants et administration pour une meilleure réussite scolaire en République Démocratique du Congo. Chaque école gère son propre espace.
        </p>

        <div class="login-stats">
            <div class="login-stat">
                <div class="number">500+</div>
                <div class="label">Élèves suivis</div>
            </div>
            <div class="login-stat">
                <div class="number">98%</div>
                <div class="label">Parents satisfaits</div>
            </div>
            <div class="login-stat">
                <div class="number">15</div>
                <div class="label">Établissements</div>
            </div>
        </div>
    </div>

    <!-- ── PANNEAU DROIT (formulaire) ── -->
    <div class="login-form-side">
        <div class="login-form-wrapper">
            <h2>Bienvenue !</h2>
            <p class="subtitle">Connectez-vous à votre espace personnel</p>

            <!-- ╔══════════════════════════════════════╗ -->
            <!-- ║  BOUTON CRÉER UNE NOUVELLE ÉCOLE     ║ -->
            <!-- ╚══════════════════════════════════════╝ -->
            <a href="/edusuivi/register_school.php" class="create-school-cta" id="btn-create-school">
                <div class="cta-icon">🏫</div>
                <div class="cta-text">
                    <strong>Créer une nouvelle école</strong>
                    <span>Enregistrez votre établissement en 1 minute</span>
                </div>
                <span class="cta-arrow">→</span>
            </a>

            <!-- Séparateur -->
            <div class="login-divider">
                <span>ou connectez-vous à votre école existante</span>
            </div>

            <!-- Comptes de démo -->
            <div class="demo-accounts">
                <p class="label">Comptes de démonstration</p>
                <div class="demo-btns">
                    <button class="demo-btn admin" onclick="fillDemo('admin@edusuivi.cd','admin123')">👤 Directeur démo</button>
                    <button class="demo-btn" onclick="fillDemo('ngozi@edusuivi.cd','prof123')">👨‍🏫 Enseignant</button>
                    <button class="demo-btn" onclick="fillDemo('marie@edusuivi.cd','parent123')">👨‍👩‍👧 Parent</button>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="error-msg"><?= escape($error) ?></div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label>Adresse email</label>
                    <div class="input-wrapper">
                        <span class="input-icon">✉</span>
                        <input type="email" id="email" name="email"
                               placeholder="votre@email.cd" required
                               value="<?= escape($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Mot de passe</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="password" name="password"
                               placeholder="••••••••" required>
                        <button type="button" id="togglePassword" onclick="togglePwd()"
                                style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--text-muted);padding:4px;line-height:1;"
                                title="Afficher/Masquer"><span id="pwdEye">👁</span></button>
                    </div>
                </div>
                <button type="submit" class="btn-login" id="submitLogin">
                    Se connecter
                </button>
                <div style="text-align:center;margin-top:16px;">
                    <a href="/edusuivi/forgot_password.php"
                       style="color:var(--text-muted);font-size:13px;text-decoration:none;transition:var(--transition);"
                       onmouseover="this.style.color='var(--primary)'"
                       onmouseout="this.style.color='var(--text-muted)'">
                        🔑 Mot de passe oublié ?
                    </a>
                </div>
            </form>

            <p class="login-footer">EduSuivi — Réseau scolaire numérique © <?= date('Y') ?></p>
        </div>
    </div>
</div>

<script src="/edusuivi/js/fontawesome-icons.js"></script>
<script src="/edusuivi/js/app.js"></script>
<script>
function togglePwd() {
    const input = document.getElementById('password');
    const eye = document.getElementById('pwdEye');
    if (input.type === 'password') {
        input.type = 'text';
        eye.textContent = '🙈';
    } else {
        input.type = 'password';
        eye.textContent = '👁';
    }
}
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitLogin');
    btn.textContent = 'Connexion…';
    btn.disabled = true;
});
</script>
</body>
</html>

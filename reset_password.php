<?php
require_once __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect("/edusuivi/{$_SESSION['user_role']}/");
}

$token = trim($_GET['token'] ?? '');
$error = $success = '';
$validToken = false;
$user = null;

// Appliquer migration si nécessaire
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME DEFAULT NULL");
} catch (PDOException $e) {}

if (empty($token)) {
    $error = "Lien invalide. Veuillez recommencer la procédure.";
} else {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $validToken = true;
    } else {
        $error = "Ce lien est invalide ou expiré. Veuillez faire une nouvelle demande.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $password2) {
        $error = "Les deux mots de passe ne correspondent pas.";
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?")
            ->execute([$hashed, $user['id']]);
        $success    = "Mot de passe réinitialisé avec succès !";
        $validToken = false;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduSuivi — Nouveau mot de passe</title>
    <meta name="description" content="Créez votre nouveau mot de passe EduSuivi">
    <link rel="stylesheet" href="/edusuivi/css/style.css">
</head>
<body>
<div class="login-container">
    <div class="login-hero">
        <div class="logo">
            <div class="logo-icon">E</div>
            <div class="logo-text">
                <h1>EduSuivi</h1>
                <p>Portail Scolaire — RDC</p>
            </div>
        </div>
        <h2>Nouveau<br><span>mot de passe</span></h2>
        <p class="desc">Choisissez un mot de passe sécurisé d'au moins 6 caractères. Ne le partagez avec personne.</p>
        <div class="login-stats">
            <div class="login-stat"><div class="number">🔒</div><div class="label">Chiffré</div></div>
            <div class="login-stat"><div class="number">⚡</div><div class="label">Sécurisé</div></div>
            <div class="login-stat"><div class="number">✓</div><div class="label">Validé</div></div>
        </div>
    </div>

    <div class="login-form-side">
        <div class="login-form-wrapper">
            <?php if ($success): ?>
                <div style="text-align:center;padding:16px 0;">
                    <div style="font-size:48px;margin-bottom:16px;">✓</div>
                    <h2 style="color:var(--success);">Mot de passe mis à jour !</h2>
                    <p class="subtitle" style="margin-bottom:28px;">Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.</p>
                    <a href="/edusuivi/login.php" class="btn-login" style="display:block;text-align:center;text-decoration:none;">
                        Se connecter maintenant →
                    </a>
                </div>

            <?php elseif ($error && !$validToken): ?>
                <div style="text-align:center;padding:16px 0;">
                    <div style="font-size:48px;margin-bottom:16px;">⚠️</div>
                    <h2>Lien invalide</h2>
                    <p class="subtitle" style="margin-bottom:28px;"><?= escape($error) ?></p>
                    <a href="/edusuivi/forgot_password.php" class="btn-login" style="display:block;text-align:center;text-decoration:none;">
                        ✉ Nouvelle demande
                    </a>
                    <div style="margin-top:16px;">
                        <a href="/edusuivi/login.php" style="color:var(--text-muted);font-size:13px;">← Retour à la connexion</a>
                    </div>
                </div>

            <?php else: ?>
                <h2>🔑 Nouveau mot de passe</h2>
                <p class="subtitle">Bonjour <strong><?= escape($user['name']) ?></strong>, choisissez votre nouveau mot de passe</p>

                <?php if ($error): ?>
                    <div class="error-msg"><?= escape($error) ?></div>
                <?php endif; ?>

                <form method="POST" id="resetForm">
                    <input type="hidden" name="token" value="<?= escape($token) ?>">

                    <div class="form-group">
                        <label>Nouveau mot de passe</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="password" name="password"
                                   placeholder="Minimum 6 caractères" required minlength="6">
                            <button type="button" onclick="togglePwd('password','eye1')"
                                    style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--text-muted);padding:4px;" title="Afficher">
                                <span id="eye1">👁</span>
                            </button>
                        </div>
                        <!-- Barre de force du mot de passe -->
                        <div style="margin-top:8px;">
                            <div id="strengthBar" style="height:4px;border-radius:2px;background:var(--border);transition:all .3s;">
                                <div id="strengthFill" style="height:100%;border-radius:2px;width:0;transition:all .3s;"></div>
                            </div>
                            <span id="strengthLabel" style="font-size:11px;color:var(--text-muted);"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirmer le mot de passe</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="password2" name="password2"
                                   placeholder="Répétez le mot de passe" required minlength="6">
                            <button type="button" onclick="togglePwd('password2','eye2')"
                                    style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--text-muted);padding:4px;" title="Afficher">
                                <span id="eye2">👁</span>
                            </button>
                        </div>
                        <div id="matchMsg" style="font-size:11px;margin-top:4px;"></div>
                    </div>

                    <button type="submit" class="btn-login" id="submitBtn">✓ Enregistrer le nouveau mot de passe</button>
                </form>

                <div style="text-align:center;margin-top:24px;">
                    <a href="/edusuivi/login.php" style="color:var(--text-muted);font-size:13px;text-decoration:none;">← Retour à la connexion</a>
                </div>
            <?php endif; ?>

            <p class="login-footer">EduSuivi — Système de suivi parental scolaire © 2025</p>
        </div>
    </div>
</div>

<script src="/edusuivi/js/fontawesome-icons.js"></script>
<script>
function togglePwd(inputId, eyeId) {
    const input = document.getElementById(inputId);
    const eye   = document.getElementById(eyeId);
    if (input.type === 'password') {
        input.type = 'text'; eye.textContent = '🙈';
    } else {
        input.type = 'password'; eye.textContent = '👁';
    }
}

// Barre de force du mot de passe
const pwdInput  = document.getElementById('password');
const fill      = document.getElementById('strengthFill');
const label     = document.getElementById('strengthLabel');
const pwd2      = document.getElementById('password2');
const matchMsg  = document.getElementById('matchMsg');

if (pwdInput) {
    pwdInput.addEventListener('input', function() {
        const val = this.value;
        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const pcts   = ['0%',  '20%',  '40%',  '65%',  '85%',  '100%'];
        const colors = ['transparent','#ef4444','#f59e0b','#3b82f6','#10b981','#059669'];
        const labels = ['',    'Très faible', 'Faible', 'Moyen', 'Bon', 'Excellent'];

        fill.style.width      = pcts[score];
        fill.style.background = colors[score];
        label.textContent     = labels[score];
        label.style.color     = colors[score];
    });
}

if (pwd2) {
    pwd2.addEventListener('input', function() {
        if (!pwdInput) return;
        if (this.value === pwdInput.value) {
            matchMsg.textContent = '✅ Les mots de passe correspondent';
            matchMsg.style.color = 'var(--success)';
        } else {
            matchMsg.textContent = '❌ Les mots de passe ne correspondent pas';
            matchMsg.style.color = 'var(--danger)';
        }
    });
}

document.getElementById('resetForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = '⏳ Enregistrement...';
});
</script>
</body>
</html>

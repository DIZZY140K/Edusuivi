<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    redirect("/edusuivi/$role/");
}

$error = $success = $devLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez saisir une adresse email valide.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Générer un token sécurisé
            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Appliquer la migration si les colonnes n'existent pas encore
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) DEFAULT NULL");
                $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME DEFAULT NULL");
            } catch (PDOException $e) { /* ignore si déjà existantes */ }

            // Sauvegarder le token en BDD
            $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?")
                ->execute([$token, $expiry, $user['id']]);

            // Construire le lien de réinitialisation
            $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host      = $_SERVER['HTTP_HOST'];
            $resetLink = "{$protocol}://{$host}/edusuivi/reset_password.php?token={$token}";

            // Envoyer l'email
            $mailer  = new Mailer();
            $subject = "Réinitialisation de votre mot de passe — EduSuivi";
            $body    = Mailer::resetPasswordTemplate($user['name'], $resetLink);
            $result  = $mailer->send($user['email'], $user['name'], $subject, $body);

            if ($mailer->devMode || $result === true) {
                $success = "Un lien de réinitialisation a été généré.";
                if ($mailer->devMode) {
                    $devLink = $resetLink; // Afficher le lien en mode dev
                }
            } else {
                $error = "Erreur lors de l'envoi de l'email. Réessayez.";
            }
        } else {
            // Sécurité : ne pas révéler si l'email existe
            $success = "Si cet email est enregistré, vous recevrez un lien de réinitialisation.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduSuivi — Mot de passe oublié</title>
    <meta name="description" content="Réinitialisez votre mot de passe EduSuivi">
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
        <h2>Mot de passe<br><span>oublié ?</span></h2>
        <p class="desc">Pas de panique ! Entrez votre adresse email et nous vous enverrons un lien pour réinitialiser votre mot de passe.</p>
        <div class="login-stats">
            <div class="login-stat"><div class="number">🔒</div><div class="label">Sécurisé</div></div>
            <div class="login-stat"><div class="number">⚡</div><div class="label">Rapide</div></div>
            <div class="login-stat"><div class="number">✓</div><div class="label">Fiable</div></div>
        </div>
    </div>

    <div class="login-form-side">
        <div class="login-form-wrapper">
            <h2>🔑 Réinitialiser</h2>
            <p class="subtitle">Entrez votre email pour recevoir un lien de réinitialisation</p>

            <?php if ($error): ?>
                <div class="error-msg"><?= escape($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="fp-success-box">
                    <div class="fp-success-icon">✓</div>
                    <p><?= escape($success) ?></p>
                    <?php if ($devLink): ?>
                        <div class="fp-dev-box">
                            <p><strong>🛠 Mode développement</strong></p>
                            <p style="font-size:12px;margin:6px 0 10px;opacity:.8;">L'email n'a pas été envoyé (SMTP non configuré). Cliquez sur le lien ci-dessous pour tester :</p>
                            <a href="<?= escape($devLink) ?>" class="fp-dev-link">→ Ouvrir le lien de réinitialisation</a>
                        </div>
                    <?php endif; ?>
                    <a href="/edusuivi/login.php" class="btn-login" style="margin-top:16px;display:block;text-align:center;text-decoration:none;">← Retour à la connexion</a>
                </div>
            <?php else: ?>
                <form method="POST" id="forgotForm">
                    <div class="form-group">
                        <label>Adresse email</label>
                        <div class="input-wrapper">
                            <span class="input-icon">✉</span>
                            <input type="email" name="email" placeholder="votre@email.cd" required
                                   value="<?= escape($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn-login" id="submitBtn">
                        <span id="btnText">Envoyer le lien</span>
                    </button>
                </form>

                <div style="text-align:center;margin-top:24px;">
                    <a href="/edusuivi/login.php" style="color:var(--text-muted);font-size:13px;text-decoration:none;">
                        ← Retour à la connexion
                    </a>
                </div>
            <?php endif; ?>

            <p class="login-footer">EduSuivi — Système de suivi parental scolaire © 2025</p>
        </div>
    </div>
</div>

<style>
.fp-success-box {
    background: var(--success-light);
    border: 1px solid rgba(27,122,67,.2);
    border-radius: var(--radius);
    padding: 24px;
    text-align: center;
    margin-bottom: 16px;
}
.fp-success-icon { font-size: 40px; margin-bottom: 12px; }
.fp-success-box p { color: var(--success); font-weight: 600; margin: 0; }

.fp-dev-box {
    background: rgba(240,180,41,.1);
    border: 1px dashed var(--gold);
    border-radius: var(--radius);
    padding: 16px;
    margin-top: 16px;
    text-align: left;
}
.fp-dev-box p { color: var(--text); margin: 0; font-size: 13px; }
.fp-dev-link {
    display: inline-block;
    background: var(--gold);
    color: white;
    padding: 10px 20px;
    border-radius: var(--radius);
    font-weight: 700;
    font-size: 13px;
    text-decoration: none;
    transition: var(--transition);
}
.fp-dev-link:hover { background: var(--gold-dark); transform: translateY(-1px); text-decoration: none; }
</style>

<script src="/edusuivi/js/fontawesome-icons.js"></script>
<script>
document.getElementById('forgotForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    const txt = document.getElementById('btnText');
    btn.disabled = true;
    txt.textContent = '⏳ Envoi en cours...';
});
</script>
</body>
</html>

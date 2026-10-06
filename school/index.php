<?php
// Cette page est obsolète — le directeur se connecte directement en tant qu'admin.
// Redirection automatique vers le bon espace selon le rôle connecté.
require_once __DIR__ . '/../includes/config.php';
if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    $dest = ($role === 'school') ? 'admin' : $role;
    header('Location: /edusuivi/' . $dest . '/');
} else {
    header('Location: /edusuivi/login.php');
}
exit;

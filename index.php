<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn()) {
    redirect("/edusuivi/{$_SESSION['user_role']}/");
} else {
    redirect("/edusuivi/login.php");
}

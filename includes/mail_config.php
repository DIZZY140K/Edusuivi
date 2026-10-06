<?php
/**
 * EduSuivi — Configuration email SMTP
 * Copiez ce fichier et remplissez vos informations Gmail
 *
 * Pour Gmail, vous devez activer "Mots de passe d'application" :
 * https://myaccount.google.com/apppasswords
 * (nécessite la validation en 2 étapes activée)
 */

return [
    // Serveur SMTP
    'smtp_host'  => 'smtp.gmail.com',
    'smtp_port'  => 587,

    // Vos identifiants Gmail
    'smtp_user'  => 'votre.email@gmail.com',   // ← Remplacez ici
    'smtp_pass'  => 'xxxx xxxx xxxx xxxx',      // ← Mot de passe d'application Gmail (16 caractères)

    // Expéditeur affiché
    'from_email' => 'votre.email@gmail.com',   // ← Même que smtp_user
    'from_name'  => 'EduSuivi — Portail Scolaire',

    // Mode développement
    // true  = affiche le lien de réinitialisation à l'écran (pas d'envoi réel)
    // false = envoie réellement l'email via SMTP
    'dev_mode'   => true,  // ← Mettez false quand vous avez configuré SMTP
];

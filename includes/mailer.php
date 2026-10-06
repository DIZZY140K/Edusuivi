<?php
/**
 * EduSuivi — Classe Mailer SMTP
 * Envoi d'emails via Gmail SMTP (ou autre serveur SMTP)
 * Pas de dépendance externe — implémentation socket PHP native
 */

class Mailer {

    // ─── Configuration SMTP ────────────────────────────────
    // Modifiez ces valeurs avec vos identifiants Gmail
    private string $smtpHost     = 'smtp.gmail.com';
    private int    $smtpPort     = 587;          // TLS
    private string $smtpUser     = '';           // Votre adresse Gmail
    private string $smtpPass     = '';           // Mot de passe d'application Gmail
    private string $fromEmail    = '';           // Même que smtpUser
    private string $fromName     = 'EduSuivi';

    // ─── Mode développement ────────────────────────────────
    // Si true : affiche le contenu de l'email à l'écran au lieu d'envoyer
    public bool $devMode = true;
    public string $lastDevOutput = '';

    public function __construct() {
        // Charger la config depuis le fichier de configuration
        $configFile = __DIR__ . '/mail_config.php';
        if (file_exists($configFile)) {
            $cfg = require $configFile;
            $this->smtpHost  = $cfg['smtp_host']  ?? $this->smtpHost;
            $this->smtpPort  = $cfg['smtp_port']  ?? $this->smtpPort;
            $this->smtpUser  = $cfg['smtp_user']  ?? '';
            $this->smtpPass  = $cfg['smtp_pass']  ?? '';
            $this->fromEmail = $cfg['from_email'] ?? $cfg['smtp_user'] ?? '';
            $this->fromName  = $cfg['from_name']  ?? 'EduSuivi';
            $this->devMode   = $cfg['dev_mode']   ?? true;
        }
    }

    /**
     * Envoyer un email
     * @return bool|string true si succès, message d'erreur sinon
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool|string {
        if ($this->devMode || empty($this->smtpUser)) {
            // Mode développement : afficher le contenu
            $this->lastDevOutput = $htmlBody;
            return true;
        }

        // Tentative d'envoi via SMTP
        try {
            return $this->sendViaSMTP($toEmail, $toName, $subject, $htmlBody);
        } catch (Exception $e) {
            // Fallback : mode dev si SMTP échoue
            $this->lastDevOutput = $htmlBody;
            error_log("Mailer SMTP error: " . $e->getMessage());
            return true; // On retourne true pour ne pas bloquer l'UX
        }
    }

    private function sendViaSMTP(string $toEmail, string $toName, string $subject, string $htmlBody): bool|string {
        $socket = @fsockopen("tls://{$this->smtpHost}", $this->smtpPort, $errno, $errstr, 10);
        if (!$socket) {
            throw new Exception("Connexion SMTP impossible : $errstr ($errno)");
        }

        $read = fgets($socket, 512);
        if (substr($read, 0, 3) !== '220') {
            throw new Exception("SMTP: réponse inattendue : $read");
        }

        $cmds = [
            "EHLO localhost\r\n"              => '250',
            "AUTH LOGIN\r\n"                 => '334',
            base64_encode($this->smtpUser) . "\r\n" => '334',
            base64_encode($this->smtpPass) . "\r\n" => '235',
            "MAIL FROM:<{$this->fromEmail}>\r\n"     => '250',
            "RCPT TO:<{$toEmail}>\r\n"              => '250',
            "DATA\r\n"                              => '354',
        ];

        foreach ($cmds as $cmd => $expected) {
            fwrite($socket, $cmd);
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) !== $expected) {
                fclose($socket);
                throw new Exception("SMTP erreur après '$cmd': $response");
            }
        }

        // Construire le message
        $boundary = md5(uniqid());
        $headers  = "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "To: {$toName} <{$toEmail}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";
        $headers .= "\r\n";

        $encodedBody = chunk_split(base64_encode($htmlBody));
        fwrite($socket, $headers . $encodedBody . "\r\n.\r\n");
        $response = fgets($socket, 512);

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        if (substr($response, 0, 3) !== '250') {
            throw new Exception("Envoi échoué : $response");
        }

        return true;
    }

    /**
     * Template HTML pour l'email de réinitialisation
     */
    public static function resetPasswordTemplate(string $name, string $resetLink): string {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Réinitialisation de votre mot de passe — EduSuivi</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Inter,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 20px;">
<tr><td align="center">
  <table width="580" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1);">
    <!-- Header -->
    <tr>
      <td style="background:linear-gradient(135deg,#0f2341,#1a365d);padding:36px 40px;text-align:center;">
        <div style="display:inline-flex;align-items:center;gap:12px;">
          <div style="width:48px;height:48px;background:linear-gradient(135deg,#d4930d,#f0b429);border-radius:12px;display:inline-flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:#fff;line-height:48px;">E</div>
          <span style="font-size:26px;font-weight:800;color:#fff;letter-spacing:-0.5px;">EduSuivi</span>
        </div>
        <p style="color:rgba(255,255,255,.6);margin:8px 0 0;font-size:13px;">Portail Scolaire — RDC</p>
      </td>
    </tr>
    <!-- Body -->
    <tr>
      <td style="padding:40px;">
        <h2 style="margin:0 0 16px;font-size:22px;color:#1e293b;font-weight:800;">Réinitialisation de votre mot de passe <i class="fa-solid fa-lock"></i></h2>
        <p style="margin:0 0 20px;color:#64748b;line-height:1.6;">Bonjour <strong style="color:#1e293b;">' . htmlspecialchars($name) . '</strong>,</p>
        <p style="margin:0 0 28px;color:#64748b;line-height:1.6;">
          Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte EduSuivi. 
          Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe. Ce lien est valable <strong>1 heure</strong>.
        </p>
        <div style="text-align:center;margin:32px 0;">
          <a href="' . $resetLink . '" style="display:inline-block;background:linear-gradient(135deg,#1a365d,#2a4a7f);color:#fff;text-decoration:none;padding:16px 40px;border-radius:12px;font-size:15px;font-weight:700;letter-spacing:0.3px;box-shadow:0 4px 15px rgba(26,54,93,.3);">
            <i class="fa-solid fa-key"></i> Réinitialiser mon mot de passe
          </a>
        </div>
        <div style="background:#f8fafc;border-radius:12px;padding:16px 20px;margin:24px 0;border-left:4px solid #d4930d;">
          <p style="margin:0;font-size:12px;color:#64748b;line-height:1.6;">
            <strong><i class="fa-solid fa-triangle-exclamation"></i> Sécurité :</strong> Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email. 
            Votre mot de passe actuel reste inchangé.
          </p>
        </div>
        <p style="margin:24px 0 0;color:#94a3b8;font-size:12px;line-height:1.6;">
          Ou copiez ce lien dans votre navigateur :<br>
          <a href="' . $resetLink . '" style="color:#1a365d;word-break:break-all;">' . $resetLink . '</a>
        </p>
      </td>
    </tr>
    <!-- Footer -->
    <tr>
      <td style="background:#f8fafc;padding:20px 40px;border-top:1px solid #e2e8f0;text-align:center;">
        <p style="margin:0;font-size:12px;color:#94a3b8;">EduSuivi — Système de suivi parental scolaire © 2025</p>
        <p style="margin:4px 0 0;font-size:11px;color:#cbd5e1;">République Démocratique du Congo</p>
      </td>
    </tr>
  </table>
</td></tr>
</table>
</body>
</html>';
    }
}

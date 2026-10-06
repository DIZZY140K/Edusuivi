<?php
/**
 * EduSuivi — Messagerie interne (style WhatsApp)
 * Logique commune aux 3 rôles : admin / teacher / parent
 */

function messagesPage($pdo, $currentUser, $role) {
    $userId  = $currentUser['id'];
    $success = $error = '';

    // ── Envoyer un message ──────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
        $recvId = intval($_POST['receiver_id'] ?? 0);
        $body   = trim($_POST['body'] ?? '');
        // subject automatique basé sur les noms
        $subject = trim($_POST['subject'] ?? 'Message');
        if (!$subject) $subject = 'Message';

        if ($recvId && $body) {
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, body) VALUES (?,?,?,?)")
                ->execute([$userId, $recvId, $subject, $body]);

            // Notification au destinataire (vers son propre espace de messagerie)
            try {
                $rRoleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $rRoleStmt->execute([$recvId]);
                $destRole = $rRoleStmt->fetchColumn() ?: 'parent';

                $pdo->prepare("INSERT INTO notifications (user_id,type,title,body,link) VALUES (?,?,?,?,?)")
                    ->execute([$recvId, 'message',
                        'Message de ' . $currentUser['name'],
                        substr($body, 0, 100),
                        "/edusuivi/$destRole/messages.php?contact=$userId"
                    ]);
            } catch (Exception $e) {}

            $success = "Message envoyé.";
        } else {
            $error = "Veuillez écrire un message.";
        }
    }

    // ── Supprimer un message ────────────────────────────────────────────────
    if (isset($_GET['delete_msg'])) {
        $msgId = intval($_GET['delete_msg']);
        $pdo->prepare("UPDATE messages SET is_deleted_sender=1 WHERE id=? AND sender_id=?")
            ->execute([$msgId, $userId]);
        $pdo->prepare("UPDATE messages SET is_deleted_receiver=1 WHERE id=? AND receiver_id=?")
            ->execute([$msgId, $userId]);
    }

    // ── Contact actif ───────────────────────────────────────────────────────
    $contactId = intval($_GET['contact'] ?? 0);
    $contactUser = null;
    if ($contactId) {
        $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ?");
        $stmt->execute([$contactId]);
        $contactUser = $stmt->fetch();

        // Marquer tous les messages de ce contact comme lus
        $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0")
            ->execute([$contactId, $userId]);
    }

    // ── Conversation avec le contact actif ─────────────────────────────────
    $conversation = [];
    if ($contactUser) {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   s.name AS sender_name, 
                   r.name AS receiver_name
            FROM messages m
            JOIN users s ON m.sender_id  = s.id
            JOIN users r ON m.receiver_id = r.id
            WHERE (
                (m.sender_id=? AND m.receiver_id=? AND m.is_deleted_sender=0)
                OR
                (m.sender_id=? AND m.receiver_id=? AND m.is_deleted_receiver=0)
            )
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$userId, $contactId, $contactId, $userId]);
        $conversation = $stmt->fetchAll();
    }

    // ── Liste des conversations (contacts avec échanges) ────────────────────
    // On cherche tous les utilisateurs avec qui on a eu des échanges,
    // en retournant le dernier message et le nombre de non-lus
    $convStmt = $pdo->prepare("
        SELECT 
            u.id, u.name, u.role,
            last_msg.body        AS last_body,
            last_msg.created_at  AS last_time,
            last_msg.sender_id   AS last_sender,
            COALESCE(unread.cnt, 0) AS unread_count
        FROM users u
        JOIN (
            SELECT 
                CASE WHEN sender_id=:me THEN receiver_id ELSE sender_id END AS partner_id,
                body, created_at, sender_id,
                ROW_NUMBER() OVER (
                    PARTITION BY CASE WHEN sender_id=:me THEN receiver_id ELSE sender_id END
                    ORDER BY created_at DESC
                ) AS rn
            FROM messages
            WHERE (sender_id=:me AND is_deleted_sender=0)
               OR (receiver_id=:me AND is_deleted_receiver=0)
        ) last_msg ON u.id = last_msg.partner_id AND last_msg.rn = 1
        LEFT JOIN (
            SELECT sender_id, COUNT(*) AS cnt
            FROM messages
            WHERE receiver_id=:me AND is_read=0 AND is_deleted_receiver=0
            GROUP BY sender_id
        ) unread ON unread.sender_id = u.id
        ORDER BY last_msg.created_at DESC
    ");
    $convStmt->execute([':me' => $userId]);
    $conversations = $convStmt->fetchAll();

    // Nombre total de non-lus
    $unreadCnt = (int) $pdo->prepare("
        SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0 AND is_deleted_receiver=0
    ")->execute([$userId]) ? $pdo->query("
        SELECT COUNT(*) FROM messages WHERE receiver_id=$userId AND is_read=0 AND is_deleted_receiver=0
    ")->fetchColumn() : 0;

    // Recalcul propre du unreadCnt
    $uStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0 AND is_deleted_receiver=0");
    $uStmt->execute([$userId]);
    $unreadCnt = (int) $uStmt->fetchColumn();

    // ── Destinataires disponibles pour nouveau message ──────────────────────
    $recipients = $pdo->query("SELECT id, name, role FROM users WHERE id != $userId ORDER BY role, name")->fetchAll();

    $roleLabels = ['admin' => 'Administrateur', 'teacher' => 'Enseignant', 'parent' => 'Parent'];

    return compact(
        'success', 'error',
        'conversations', 'conversation',
        'contactUser', 'contactId',
        'unreadCnt', 'recipients', 'roleLabels'
    );
}

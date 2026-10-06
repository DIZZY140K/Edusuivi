<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
if ($_SESSION['user_role'] !== 'admin') {
    $targetRole = in_array($_SESSION['user_role'], ['teacher', 'parent']) ? $_SESSION['user_role'] : 'login';
    $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: /edusuivi/{$targetRole}/messages.php{$qs}");
    exit;
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/messages_core.php';
$data = messagesPage($pdo, $currentUser, 'admin');
extract($data);
?>

<div class="page-header" style="margin-bottom:14px;">
    <div>
        <h2>💬 Messagerie</h2>
        <p><?= $unreadCnt ?> message(s) non lu(s)</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('composeModal')" style="background:var(--wa-green);border-color:var(--wa-green);">✉️ Nouveau message</button>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= escape($error) ?></div><?php endif; ?>

<div class="messaging-container">

    <!-- ── Colonne gauche : liste des conversations ── -->
    <div class="messages-sidebar" id="convSidebar">
        <div class="messages-sidebar-header">
            <h3>💬 Discussions</h3>
            <button class="wa-new-btn" onclick="openModal('composeModal')" title="Nouveau message">✏️ Nouveau</button>
        </div>

        <div class="wa-search">
            <div class="wa-search-wrap">
                <span class="wa-search-icon">🔍</span>
                <input type="text" id="searchConv" placeholder="Rechercher une conversation..." oninput="filterConversations(this.value)">
            </div>
        </div>

        <div class="messages-list" id="convList">
            <?php if (empty($conversations)): ?>
                <div style="padding:48px 24px;text-align:center;color:#8696a0;">
                    <div style="font-size:48px;margin-bottom:12px;opacity:.4;">💬</div>
                    <p style="font-size:13px;">Aucune conversation.<br>Envoyez le premier message !</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv):
                    $isActive  = ($conv['id'] == $contactId);
                    $hasUnread = ($conv['unread_count'] > 0);
                    $isMe      = ($conv['last_sender'] == $currentUser['id']);
                    $preview   = ($isMe ? '✓ ' : '') . htmlspecialchars(substr($conv['last_body'] ?? '', 0, 42));
                    $timeStr   = $conv['last_time'] ? date('H:i', strtotime($conv['last_time'])) : '';
                ?>
                <a href="?contact=<?= $conv['id'] ?>" class="wa-conv-item <?= $isActive ? 'active' : '' ?> <?= $hasUnread ? 'unread' : '' ?>" data-name="<?= strtolower(escape($conv['name'])) ?>">
                    <div class="wa-avatar"><?= strtoupper(substr($conv['name'], 0, 1)) ?></div>
                    <div class="wa-conv-meta">
                        <div class="wa-conv-name"><?= escape($conv['name']) ?></div>
                        <div class="wa-conv-preview"><?= $preview ?>...</div>
                    </div>
                    <div class="wa-conv-right">
                        <span class="wa-time"><?= $timeStr ?></span>
                        <?php if ($hasUnread): ?>
                            <span class="wa-unread-badge"><?= $conv['unread_count'] ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Colonne droite : conversation ouverte ── -->
    <div class="chat-view" id="chatPanel">
        <?php if ($contactUser): ?>
            <!-- Header de la conversation -->
            <div class="wa-chat-header">
                <div class="wa-chat-user">
                    <a href="messages.php" id="back-to-list-btn" style="display:none;margin-right:6px;color:#8696a0;font-size:18px;text-decoration:none;">←</a>
                    <div class="wa-chat-avatar"><?= strtoupper(substr($contactUser['name'], 0, 1)) ?></div>
                    <div>
                        <div class="wa-chat-name"><?= escape($contactUser['name']) ?></div>
                        <div class="wa-chat-sub"><?= $roleLabels[$contactUser['role']] ?? $contactUser['role'] ?> · En ligne</div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button onclick="openModal('composeModal')" style="background:none;border:none;cursor:pointer;font-size:18px;color:#8696a0;padding:6px;" title="Nouveau message">✉️</button>
                </div>
            </div>

            <!-- Zone des messages -->
            <div class="wa-messages-area" id="msgArea">
                <?php if (empty($conversation)): ?>
                    <div class="wa-empty">
                        <div class="wa-empty-icon">💬</div>
                        <h3>Nouvelle conversation</h3>
                        <p>Aucun message avec <?= escape($contactUser['name']) ?>.<br>Dites bonjour !</p>
                    </div>
                <?php else:
                    $prevDate = null;
                    foreach ($conversation as $msg):
                        $msgDate = date('Y-m-d', strtotime($msg['created_at']));
                        $isSent  = ($msg['sender_id'] == $currentUser['id']);
                        if ($msgDate !== $prevDate):
                            $prevDate = $msgDate;
                            $label = ($msgDate === date('Y-m-d')) ? "Aujourd'hui" :
                                     ($msgDate === date('Y-m-d', strtotime('-1 day')) ? 'Hier' :
                                     date('d/m/Y', strtotime($msgDate)));
                ?>
                    <div class="wa-date-divider"><span><?= $label ?></span></div>
                <?php endif; ?>
                    <div class="wa-msg-row <?= $isSent ? 'sent' : 'received' ?>">
                        <div class="wa-bubble">
                            <?php if (!$isSent): ?>
                                <div style="font-size:11px;font-weight:700;color:var(--wa-green);margin-bottom:3px;"><?= escape($msg['sender_name']) ?></div>
                            <?php endif; ?>
                            <div style="white-space:pre-wrap;"><?= escape($msg['body']) ?></div>
                            <span class="wa-bubble-time">
                                <?= date('H:i', strtotime($msg['created_at'])) ?>
                                <?php if ($isSent): ?>
                                    <span style="color:<?= $msg['is_read'] ? 'var(--wa-tick-color)' : '#8696a0' ?>;"><?= $msg['is_read'] ? '✓✓' : '✓' ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <!-- Barre de saisie -->
            <div class="wa-input-bar">
                <form method="POST" style="display:flex;gap:10px;width:100%;align-items:flex-end;" id="replyForm">
                    <input type="hidden" name="action" value="send">
                    <input type="hidden" name="receiver_id" value="<?= $contactUser['id'] ?>">
                    <input type="hidden" name="subject" value="Conversation">
                    <textarea name="body" id="msgBody" placeholder="Message à <?= escape($contactUser['name']) ?>..." required
                              rows="1" onkeydown="handleEnter(event)"></textarea>
                    <button type="submit" class="wa-send-btn" title="Envoyer">➤</button>
                </form>
            </div>

        <?php else: ?>
            <!-- État vide : aucune conversation sélectionnée -->
            <div class="wa-empty">
                <div class="wa-empty-icon">💬</div>
                <h3>Bienvenue dans la messagerie</h3>
                <p>Sélectionnez une conversation à gauche ou commencez-en une nouvelle.</p>
                <button class="btn btn-primary" style="margin-top:20px;background:var(--wa-green);border-color:var(--wa-green);" onclick="openModal('composeModal')">✉️ Nouveau message</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Modal Nouveau message ── -->
<div class="modal-overlay" id="composeModal">
    <div class="modal" style="max-width:480px;">
        <button class="modal-close-btn" onclick="closeModal('composeModal')">✕</button>
        <h3>✉️ Nouveau message</h3>
        <form method="POST">
            <input type="hidden" name="action" value="send">
            <div class="form-group" style="padding:16px 24px 0;">
                <label>Destinataire *</label>
                <select name="receiver_id" class="form-control" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($recipients as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= '[' . ($roleLabels[$r['role']] ?? $r['role']) . '] ' . escape($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="padding:10px 24px 0;">
                <label>Message *</label>
                <textarea name="body" class="form-control" rows="4" required placeholder="Votre message..."></textarea>
            </div>
            <input type="hidden" name="subject" value="Message">
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('composeModal')">Annuler</button>
                <button type="submit" class="btn btn-primary" style="background:var(--wa-green);border-color:var(--wa-green);">➤ Envoyer</button>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-scroll vers le bas des messages
const msgArea = document.getElementById('msgArea');
if (msgArea) msgArea.scrollTop = msgArea.scrollHeight;

// Envoi avec Entrée (Shift+Entrée = nouvelle ligne)
function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        const form = document.getElementById('replyForm');
        if (form && document.getElementById('msgBody').value.trim()) form.submit();
    }
}

// Auto-resize du textarea
const textarea = document.getElementById('msgBody');
if (textarea) {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 140) + 'px';
    });
}

// Filtrer les conversations
function filterConversations(query) {
    const items = document.querySelectorAll('.wa-conv-item');
    query = query.toLowerCase();
    items.forEach(item => {
        const name = item.getAttribute('data-name') || '';
        item.style.display = name.includes(query) ? 'flex' : 'none';
    });
}

// Retour mobile
const backBtn = document.getElementById('back-to-list-btn');
if (window.innerWidth <= 768 && backBtn) backBtn.style.display = 'inline-block';
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

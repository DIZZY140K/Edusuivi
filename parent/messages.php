<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
if ($_SESSION['user_role'] !== 'parent') {
    $targetRole = in_array($_SESSION['user_role'], ['admin', 'teacher']) ? $_SESSION['user_role'] : 'login';
    $qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: /edusuivi/{$targetRole}/messages.php{$qs}");
    exit;
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/messages_core.php';
$data = messagesPage($pdo, $currentUser, 'parent');
extract($data);
?>
<style>
.messaging-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    height: calc(100vh - 148px);
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-md);
}
</style>

<div class="page-header" style="margin-bottom:16px;">
    <div>
        <h2>💬 Messagerie</h2>
        <p><?= $unreadCnt ?> message(s) non lu(s)</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('composeModal')">✉️ Écrire à l'école</button>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= escape($error) ?></div><?php endif; ?>

<div class="messaging-container">

    <!-- ── Colonne gauche : conversations ── -->
    <div class="messages-sidebar">
        <div class="messages-sidebar-header">
            <h3 style="margin:0;font-size:15px;font-weight:700;">💬 Discussions</h3>
            <button class="wa-new-btn" onclick="openModal('composeModal')" style="padding:7px 12px;font-size:12px;">✉️ Nouveau</button>
        </div>
        <div class="wa-search">
            <div class="wa-search-wrap">
                <span class="wa-search-icon">🔍</span>
                <input type="text" id="searchConv" placeholder="Rechercher..." oninput="filterConversations(this.value)">
            </div>
        </div>
        <div class="messages-list" id="convList">
            <?php if (empty($conversations)): ?>
                <div style="padding:48px 20px;text-align:center;color:var(--text-muted);">
                    <div style="font-size:40px;margin-bottom:12px;">📭</div>
                    <p style="font-size:13px;">Aucun message encore.<br>Écrivez à l'établissement !</p>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv):
                    $isActive  = ($conv['id'] == $contactId);
                    $hasUnread = ($conv['unread_count'] > 0);
                    $isMe      = ($conv['last_sender'] == $currentUser['id']);
                    $preview   = ($isMe ? 'Vous : ' : '') . substr($conv['last_body'] ?? '', 0, 38);
                    $timeStr   = $conv['last_time'] ? date('d/m H:i', strtotime($conv['last_time'])) : '';
                ?>
                <a href="?contact=<?= $conv['id'] ?>" class="wa-conv-item <?= $isActive ? 'active' : '' ?> <?= $hasUnread ? 'unread' : '' ?>" data-name="<?= strtolower(escape($conv['name'])) ?>">
                    <div class="wa-avatar"><?= strtoupper(substr($conv['name'], 0, 1)) ?></div>
                    <div class="wa-conv-meta">
                        <div class="wa-conv-name"><?= escape($conv['name']) ?></div>
                        <div class="wa-conv-preview"><?= escape($preview) ?>...</div>
                    </div>
                    <div class="wa-conv-right">
                        <span class="wa-time"><?= $timeStr ?></span>
                        <?php if ($hasUnread): ?><span class="wa-unread-badge"><?= $conv['unread_count'] ?></span><?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Colonne droite : messages ── -->
    <div class="chat-view" style="display:flex;flex-direction:column;height:100%;">
        <?php if ($contactUser): ?>
            <div class="wa-chat-header">
                <div class="wa-chat-user">
                    <a href="messages.php" id="back-to-list-btn" class="btn btn-sm btn-outline" style="display:none;margin-right:4px;">← Retour</a>
                    <div class="wa-chat-avatar"><?= strtoupper(substr($contactUser['name'], 0, 1)) ?></div>
                    <div>
                        <div class="wa-chat-name"><?= escape($contactUser['name']) ?></div>
                        <div class="wa-chat-sub"><?= $roleLabels[$contactUser['role']] ?? $contactUser['role'] ?></div>
                    </div>
                </div>
            </div>

            <div class="wa-messages-area" id="msgArea">
                <?php if (empty($conversation)): ?>
                    <div class="wa-empty">
                        <div class="wa-empty-icon">💬</div>
                        <p>Commencez votre échange avec <?= escape($contactUser['name']) ?> !</p>
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
                                <div style="font-size:11px;font-weight:700;opacity:.7;margin-bottom:4px;"><?= escape($msg['sender_name']) ?></div>
                            <?php endif; ?>
                            <div style="white-space:pre-wrap;"><?= escape($msg['body']) ?></div>
                            <span class="wa-bubble-time">
                                <?= date('H:i', strtotime($msg['created_at'])) ?>
                                <?= $isSent ? ($msg['is_read'] ? ' ✓✓' : ' ✓') : '' ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <div class="wa-input-bar">
                <form method="POST" style="display:flex;gap:10px;width:100%;align-items:flex-end;" id="replyForm">
                    <input type="hidden" name="action" value="send">
                    <input type="hidden" name="receiver_id" value="<?= $contactUser['id'] ?>">
                    <input type="hidden" name="subject" value="Conversation">
                    <textarea name="body" id="msgBody" placeholder="Message à <?= escape($contactUser['name']) ?>..." required rows="1" onkeydown="handleEnter(event)"></textarea>
                    <button type="submit" class="wa-send-btn">➤</button>
                </form>
            </div>

        <?php else: ?>
            <div class="wa-empty">
                <div class="wa-empty-icon">🏫</div>
                <h3 style="margin-bottom:8px;">Messagerie École</h3>
                <p style="font-size:14px;max-width:260px;">Contactez directement l'administration ou les enseignants de votre enfant.</p>
                <button class="btn btn-primary" style="margin-top:16px;" onclick="openModal('composeModal')">✉️ Écrire à l'école</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nouveau message (parents écrivent à admin/enseignants seulement) -->
<div class="modal-overlay" id="composeModal">
    <div class="modal" style="max-width:500px;">
        <button class="modal-close-btn" onclick="closeModal('composeModal')">✕</button>
        <h3>✉️ Écrire à l'école</h3>
        <form method="POST">
            <input type="hidden" name="action" value="send">
            <div class="form-group" style="padding:16px 24px 0;">
                <label>Destinataire *</label>
                <select name="receiver_id" class="form-control" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($recipients as $r):
                        if ($r['role'] === 'parent') continue; // parents n'écrivent pas aux autres parents
                    ?>
                        <option value="<?= $r['id'] ?>">[<?= $roleLabels[$r['role']] ?? $r['role'] ?>] <?= escape($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="padding:10px 24px 0;">
                <label>Objet</label>
                <input type="text" name="subject" class="form-control" placeholder="ex: Question sur les notes de Jean">
            </div>
            <div class="form-group" style="padding:10px 24px 0;">
                <label>Message *</label>
                <textarea name="body" class="form-control" rows="5" required placeholder="Votre message..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('composeModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">📤 Envoyer</button>
            </div>
        </form>
    </div>
</div>

<script>
const msgArea = document.getElementById('msgArea');
if (msgArea) msgArea.scrollTop = msgArea.scrollHeight;

function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        const body = document.getElementById('msgBody');
        if (body && body.value.trim()) document.getElementById('replyForm').submit();
    }
}
const ta = document.getElementById('msgBody');
if (ta) ta.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});
function filterConversations(q) {
    document.querySelectorAll('.wa-conv-item').forEach(el => {
        el.style.display = (el.dataset.name || '').includes(q.toLowerCase()) ? 'flex' : 'none';
    });
}
if (window.innerWidth <= 768) {
    const b = document.getElementById('back-to-list-btn');
    if (b) b.style.display = 'inline-flex';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

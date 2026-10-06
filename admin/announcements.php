<?php require_once __DIR__ . '/../includes/header.php'; requireRole('admin');
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $stmt = $pdo->prepare("INSERT INTO announcements (title, content, author_id, target) VALUES (?,?,?,?)");
    $stmt->execute([trim($_POST['title']), trim($_POST['content']), $currentUser['id'], $_POST['target']]);
    $success = "Annonce publiée.";
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$_GET['delete']]);
    $success = "Annonce supprimée.";
}

$announcements = $pdo->query("SELECT a.*, u.name as author_name FROM announcements a JOIN users u ON a.author_id = u.id ORDER BY a.created_at DESC")->fetchAll();
$targetLabels = ['all'=>'Tout le monde','teachers'=>'Enseignants','parents'=>'Parents','class'=>'Une classe'];
?>
<div class="page-header"><h2>Annonces</h2></div>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Toutes les annonces</h3><button class="btn btn-primary" onclick="openModal('addAnn')">+ Nouvelle annonce</button></div>
    <?php foreach ($announcements as $a): ?>
        <div class="announcement-card">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <h4><?= escape($a['title']) ?></h4>
                    <div class="meta">Par <?= escape($a['author_name']) ?> — <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?> — <span class="badge badge-primary"><?= $targetLabels[$a['target']] ?? $a['target'] ?></span></div>
                    <p><?= nl2br(escape($a['content'])) ?></p>
                </div>
                <button class="btn btn-sm btn-danger" onclick="confirmDelete('Supprimer ?','?delete=<?= $a['id'] ?>')">🗑</button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal-overlay" id="addAnn">
    <div class="modal" style="max-width:520px;">
        <button class="modal-close-btn" onclick="closeModal('addAnn')">✕</button>
        <h3>📢 Nouvelle annonce</h3>
        <form method="POST" style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
            <div style="padding:16px 24px;overflow-y:auto;flex:1;">
            <input type="hidden" name="action" value="add">
            <div class="form-group"><label>Titre *</label><input type="text" name="title" class="form-control" required placeholder="Titre de l'annonce"></div>
            <div class="form-group"><label>Destinataires</label>
                <select name="target" class="form-control">
                    <option value="all">🌍 Tout le monde</option>
                    <option value="parents">👨‍👩‍👧 Parents</option>
                    <option value="teachers">👨‍🏫 Enseignants</option>
                </select>
            </div>
            <div class="form-group"><label>Contenu *</label><textarea name="content" class="form-control" rows="5" required placeholder="Contenu de l'annonce..."></textarea></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('addAnn')">Annuler</button>
                <button type="submit" class="btn btn-primary">📢 Publier</button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

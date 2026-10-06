<?php require_once __DIR__ . '/../includes/header.php'; requireRole('parent');

$announcements = $pdo->query("
    SELECT a.*, u.name as author_name 
    FROM announcements a JOIN users u ON a.author_id = u.id 
    WHERE a.target IN ('all','parents') 
    ORDER BY a.created_at DESC
")->fetchAll();
?>
<div class="page-header"><h2>Annonces</h2></div>

<?php foreach ($announcements as $a): ?>
<div class="announcement-card">
    <h4><?= escape($a['title']) ?></h4>
    <div class="meta">Par <?= escape($a['author_name']) ?> — <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></div>
    <p><?= nl2br(escape($a['content'])) ?></p>
</div>
<?php endforeach; ?>

<?php if (!$announcements): ?>
<div class="card"><p style="padding:20px;color:var(--text-muted);">Aucune annonce pour le moment.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

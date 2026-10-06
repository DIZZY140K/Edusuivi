<?php require_once __DIR__ . '/../includes/header.php'; requireRole('teacher');

$stmt = $pdo->prepare("
    SELECT DISTINCT c.*, sub.name as subject_name
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.id
    JOIN subjects sub ON ta.subject_id = sub.id
    WHERE ta.teacher_id = ?
");
$stmt->execute([$currentUser['id']]);
$classes = $stmt->fetchAll();

$selectedClass = $_GET['class_id'] ?? null;
$students = [];
if ($selectedClass) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE class_id = ? ORDER BY last_name");
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();
}
?>
<div class="page-header"><h2>Mes Classes</h2></div>

<div class="stats-grid">
    <?php foreach ($classes as $c): ?>
    <a href="?class_id=<?= $c['id'] ?>" style="text-decoration:none;">
        <div class="stat-card" style="<?= $selectedClass == $c['id'] ? 'border:2px solid var(--gold);' : '' ?>">
            <div class="icon-box gold">🏫</div>
            <div><div class="value" style="font-size:20px;"><?= escape($c['name']) ?></div><div class="label"><?= escape($c['subject_name']) ?></div></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($selectedClass && $students): ?>
<div class="card">
    <div class="card-header"><h3>Élèves de la classe</h3></div>
    <table>
        <thead><tr><th>#</th><th>Nom</th><th>Prénom</th><th>Sexe</th></tr></thead>
        <tbody>
        <?php foreach ($students as $i => $s): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><strong><?= escape($s['last_name']) ?></strong></td>
            <td><?= escape($s['first_name']) ?></td>
            <td><span class="badge badge-primary"><?= $s['gender'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php elseif ($selectedClass): ?>
<div class="card"><p style="padding:20px;color:var(--text-muted);">Aucun élève dans cette classe.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

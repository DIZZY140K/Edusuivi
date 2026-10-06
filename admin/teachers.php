<?php require_once __DIR__ . '/../includes/header.php'; requireRole('admin');

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $check->execute([$email]);
        if ($check->fetchColumn()) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'teacher')")->execute([$name,$email,$password]);
            $success = "Enseignant ajouté avec succès.";
        }
    } elseif ($_POST['action'] === 'edit') {
        $id = intval($_POST['teacher_id']);
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        
        $check = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $check->execute([$email, $id]);
        if ($check->fetchColumn()) {
            $error = "Cet email est déjà utilisé.";
        } else {
            $sql = "UPDATE users SET name=?, email=?";
            $params = [$name, $email];
            if (!empty($_POST['password'])) {
                $sql .= ", password=?";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id=? AND role='teacher'";
            $params[] = $id;
            $pdo->prepare($sql)->execute($params);
            $success = "Informations mises à jour.";
        }
    }
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM users WHERE id=? AND role='teacher'")->execute([intval($_GET['delete'])]);
    $success = "Enseignant supprimé.";
}

// Liste enseignants avec stats
$teachers = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(DISTINCT class_id) FROM teacher_assignments WHERE teacher_id=u.id) as classes_count,
           (SELECT COUNT(DISTINCT subject_id) FROM teacher_assignments WHERE teacher_id=u.id) as subjects_count
    FROM users u WHERE u.role='teacher' ORDER BY u.name
")->fetchAll();
?>

<div class="page-header">
    <div><h2>👨‍🏫 <?= getTeacherLabel(true) ?></h2><p>Gestion du personnel <?= getTeacherLabelLower() ?></p></div>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Nouveau <?= getTeacherLabelLower() ?></button>
</div>

<?php if($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?= escape($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Liste des <?= getTeacherLabelLower(true) ?></h3><span style="color:var(--text-muted);font-size:13px;"><?= count($teachers) ?> <?= getTeacherLabelLower() ?>(s)</span></div>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr><th>Nom complet</th><th>Email / Identifiant</th><th>Classes</th><th>Matières</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($teachers as $t): ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--gold-light));display:flex;align-items:center;justify-content:center;font-weight:700;color:#000;"><?= strtoupper(substr($t['name'],0,1)) ?></div>
                    <strong><?= escape($t['name']) ?></strong>
                </div>
            </td>
            <td><code style="color:var(--primary);font-size:12px;"><?= escape($t['email']) ?></code></td>
            <td><span class="badge badge-primary"><?= $t['classes_count'] ?></span></td>
            <td><span class="badge badge-success"><?= $t['subjects_count'] ?></span></td>
            <td style="white-space:nowrap;">
                <a href="classes.php?teacher_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline" title="Affectations">📋</a>
                <button class="btn btn-sm btn-outline" onclick="editTeacher(<?= htmlspecialchars(json_encode(['id'=>$t['id'],'name'=>$t['name'],'email'=>$t['email']])) ?>)">✏️</button>
                <button class="btn btn-sm btn-danger" onclick="confirmDelete('Supprimer <?= escape($t['name']) ?> ?','?delete=<?= $t['id'] ?>')">🗑</button>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($teachers)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px;">Aucun enseignant trouvé.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width: 450px;">
        <h3 style="text-align: center; border-bottom: none; padding: 20px 24px 8px;">👨‍🏫 Nouvel <?= getTeacherLabelLower() ?></h3>
        <form method="POST" style="padding: 10px 24px 24px;">
            <input type="hidden" name="action" value="add">
            <div class="form-group" style="text-align: center; margin-bottom: 12px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; text-align: center;">Nom complet *</label>
                <input type="text" name="name" class="form-control" required style="max-width: 320px; margin: 0 auto; text-align: center; padding: 8px 12px; font-size: 13px;">
            </div>
            <div class="form-group" style="text-align: center; margin-bottom: 12px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; text-align: center;">Email de connexion *</label>
                <input type="email" name="email" class="form-control" required placeholder="nom@ecole.cd" style="max-width: 320px; margin: 0 auto; text-align: center; padding: 8px 12px; font-size: 13px;">
            </div>
            <div class="form-group" style="text-align: center; margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; text-align: center;">Mot de passe provisoire *</label>
                <input type="text" name="password" class="form-control" required value="prof123" style="max-width: 320px; margin: 0 auto; text-align: center; padding: 8px 12px; font-size: 13px;">
            </div>
            <div class="modal-actions" style="justify-content: center; padding: 8px 0 0; border-top: none; background: transparent;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width: 450px;">
        <h3 style="text-align: center; border-bottom: none; padding: 20px 24px 8px;">✏️ Modifier l'<?= getTeacherLabelLower() ?></h3>
        <form method="POST" style="padding: 10px 24px 24px;">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="teacher_id" id="edit_id">
            <div class="form-group" style="text-align: center; margin-bottom: 12px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; text-align: center;">Nom complet *</label>
                <input type="text" name="name" id="edit_name" class="form-control" required style="max-width: 320px; margin: 0 auto; text-align: center; padding: 8px 12px; font-size: 13px;">
            </div>
            <div class="form-group" style="text-align: center; margin-bottom: 12px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; text-align: center;">Email de connexion *</label>
                <input type="email" name="email" id="edit_email" class="form-control" required style="max-width: 320px; margin: 0 auto; text-align: center; padding: 8px 12px; font-size: 13px;">
            </div>
            <div class="form-group" style="text-align: center; margin-bottom: 16px;">
                <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; text-align: center;">Nouveau mot de passe</label>
                <input type="text" name="password" class="form-control" placeholder="Laisser vide pour ne pas modifier" style="max-width: 320px; margin: 0 auto; text-align: center; padding: 8px 12px; font-size: 13px;">
            </div>
            <div class="modal-actions" style="justify-content: center; padding: 8px 0 0; border-top: none; background: transparent;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('editModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Sauvegarder</button>
            </div>
        </form>
    </div>
</div>

<script>
function editTeacher(t) {
    document.getElementById('edit_id').value = t.id;
    document.getElementById('edit_name').value = t.name;
    document.getElementById('edit_email').value = t.email;
    openModal('editModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

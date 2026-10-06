<?php require_once __DIR__ . '/../includes/header.php'; requireRole('admin');

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$name || !$email || !$password) {
            $error = "Nom, email et mot de passe sont obligatoires.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Adresse email invalide.";
        } elseif (strlen($password) < 6) {
            $error = "Le mot de passe doit contenir au moins 6 caractères.";
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = "Cette adresse email est déjà utilisée.";
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'parent')")
                    ->execute([$name, $email, $hashed]);
                $success = "Parent « $name » créé avec succès.";
            }
        }
    } elseif ($_POST['action'] === 'edit') {
        $id    = intval($_POST['parent_id']);
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if (!$name || !$email) {
            $error = "Nom et email sont obligatoires.";
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $id]);
            if ($check->fetch()) {
                $error = "Cette adresse email est déjà utilisée par un autre compte.";
            } else {
                if (!empty($pass) && strlen($pass) >= 6) {
                    $hashed = password_hash($pass, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE users SET name=?, email=?, password=? WHERE id=? AND role='parent'")
                        ->execute([$name, $email, $hashed, $id]);
                } else {
                    $pdo->prepare("UPDATE users SET name=?, email=? WHERE id=? AND role='parent'")
                        ->execute([$name, $email, $id]);
                }
                $success = "Parent modifié avec succès.";
            }
        }
    }
}

if (isset($_GET['delete'])) {
    $pid = intval($_GET['delete']);
    $pdo->prepare("UPDATE students SET parent_id = NULL WHERE parent_id = ?")->execute([$pid]);
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'parent'")->execute([$pid]);
    $success = "Parent supprimé. Les élèves liés ont été déliés.";
}

$parents = $pdo->query("
    SELECT u.id, u.name, u.email, u.created_at,
           GROUP_CONCAT(CONCAT(s.first_name,' ',s.last_name) SEPARATOR ', ') as children,
           COUNT(s.id) as children_count
    FROM users u
    LEFT JOIN students s ON s.parent_id = u.id
    WHERE u.role = 'parent'
    GROUP BY u.id
    ORDER BY u.name
")->fetchAll();
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
    <div>
        <h2>👨‍👩‍👧 Parents / Tuteurs</h2>
        <p><?= count($parents) ?> parent(s) enregistré(s)</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addParentModal')">+ Ajouter un parent</button>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><?= escape($error) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Liste des parents / tuteurs</h3>
    </div>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr>
            <th>#</th><th>Nom complet</th><th>Email</th><th>Élèves liés</th><th>Enregistré le</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($parents as $p): ?>
        <tr>
            <td><code style="font-size:11px;color:var(--primary);">#<?= $p['id'] ?></code></td>
            <td>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--gold));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:13px;flex-shrink:0;">
                        <?= strtoupper(substr($p['name'],0,1)) ?>
                    </div>
                    <strong><?= escape($p['name']) ?></strong>
                </div>
            </td>
            <td><?= escape($p['email']) ?></td>
            <td>
                <?php if ($p['children_count'] > 0): ?>
                    <span class="badge badge-success"><?= $p['children_count'] ?> élève(s)</span>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:3px;"><?= escape($p['children']) ?></div>
                <?php else: ?>
                    <span class="badge" style="background:var(--border);color:var(--text-muted);">Aucun élève</span>
                <?php endif; ?>
            </td>
            <td><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
            <td style="white-space:nowrap;">
                <button class="btn btn-sm btn-outline" onclick="editParent(<?= htmlspecialchars(json_encode($p)) ?>)" title="Modifier">✏️</button>
                <?php if ($p['children_count'] == 0): ?>
                    <button class="btn btn-sm btn-danger" onclick="confirmDelete('Supprimer ce parent ?','?delete=<?= $p['id'] ?>')" title="Supprimer">🗑</button>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline" disabled title="Délier d'abord les élèves" style="opacity:.4;cursor:not-allowed;">🗑</button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($parents)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:40px;">
                <div style="font-size:40px;margin-bottom:10px;">👨‍👩‍👧</div>
                <p>Aucun parent enregistré. Ajoutez le premier parent ci-dessus.</p>
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card" style="background:linear-gradient(135deg,rgba(26,54,93,.04),rgba(212,147,13,.03));border-color:rgba(212,147,13,.2);">
    <div style="display:flex;gap:16px;align-items:flex-start;">
        <div style="font-size:24px;">💡</div>
        <div>
            <h4 style="margin:0 0 5px;font-size:14px;">Comment lier un parent à un élève ?</h4>
            <p style="margin:0;font-size:12px;color:var(--text-muted);line-height:1.6;">
                1. Créez d'abord le parent ici.<br>
                2. Allez dans <a href="students.php">Gestion des élèves</a>.<br>
                3. Lors de l'ajout/modification d'un élève, sélectionnez le parent dans la liste.
            </p>
        </div>
    </div>
</div>

<!-- ═══ Modal Ajouter parent (compact) ═══ -->
<div class="modal-overlay" id="addParentModal">
    <div class="modal" style="max-width:400px;">
        <button class="modal-close-btn" onclick="closeModal('addParentModal')">✕</button>
        <h3 style="padding:14px 20px 12px;font-size:16px;">👨‍👩‍👧 Ajouter un parent</h3>
        <form method="POST" style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
            <div class="modal-body-compact">
                <input type="hidden" name="action" value="add">
                <div class="form-group-sm"><label>Nom complet *</label><input type="text" name="name" class="form-control-sm" placeholder="Marie Kabongo" required></div>
                <div class="form-group-sm"><label>Email *</label><input type="email" name="email" class="form-control-sm" placeholder="marie@exemple.cd" required></div>
                <div class="form-group-sm"><label>Mot de passe * <span style="font-size:10px;color:var(--text-muted);">(min. 6 car.)</span></label>
                    <input type="password" name="password" class="form-control-sm" placeholder="••••••••" required minlength="6">
                </div>
            </div>
            <div class="modal-actions-compact">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addParentModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Créer le compte</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Modal Modifier parent (compact) ═══ -->
<div class="modal-overlay" id="editParentModal">
    <div class="modal" style="max-width:400px;">
        <button class="modal-close-btn" onclick="closeModal('editParentModal')">✕</button>
        <h3 style="padding:14px 20px 12px;font-size:16px;">✏️ Modifier le parent</h3>
        <form method="POST" style="display:flex;flex-direction:column;flex:1;overflow:hidden;">
            <div class="modal-body-compact">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_p_id">
                <div class="form-group-sm"><label>Nom complet *</label><input type="text" name="name" id="edit_p_name" class="form-control-sm" required></div>
                <div class="form-group-sm"><label>Email *</label><input type="email" name="email" id="edit_p_email" class="form-control-sm" required></div>
                <div class="form-group-sm"><label>Nouveau mot de passe <span style="font-size:10px;color:var(--text-muted);">(laisser vide pour ne pas changer)</span></label>
                    <input type="password" name="password" class="form-control-sm" placeholder="••••••••" minlength="6">
                </div>
            </div>
            <div class="modal-actions-compact">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('editParentModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Sauvegarder</button>
            </div>
        </form>
    </div>
</div>

<script>
function editParent(p) {
    document.getElementById('edit_parent_id').value = p.id;
    document.getElementById('ep_name').value         = p.name;
    document.getElementById('ep_email').value        = p.email;
    document.getElementById('editPwd').value         = '';
    openModal('editParentModal');
}
function toggleField(id) {
    const el = document.getElementById(id);
    el.type  = (el.type === 'password') ? 'text' : 'password';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

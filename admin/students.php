<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('admin');

// AJAX handler for quick parent creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'add_parent') {
    header('Content-Type: application/json');
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$name || !$email || !$password) {
        echo json_encode(['success' => false, 'error' => "Nom, email et mot de passe sont obligatoires."]);
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => "Adresse email invalide."]);
    } elseif (strlen($password) < 6) {
        echo json_encode(['success' => false, 'error' => "Le mot de passe doit contenir au moins 6 caractères."]);
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => "Cette adresse email est déjà utilisée."]);
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'parent')");
            $stmt->execute([$name, $email, $hashed]);
            $newId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'parent' => ['id' => $newId, 'name' => $name, 'email' => $email]]);
        }
    }
    exit;
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $matricule = 'MAT-' . date('Y') . '-' . str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);
        $stmt = $pdo->prepare("INSERT INTO students (matricule,first_name,last_name,date_of_birth,gender,class_id,parent_id,option_filiere,phone,address) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$matricule, trim($_POST['first_name']), trim($_POST['last_name']),
            $_POST['date_of_birth']?:null, $_POST['gender'], $_POST['class_id']?:null,
            $_POST['parent_id']?:null, trim($_POST['option_filiere']), trim($_POST['phone']), trim($_POST['address'])]);
        $success = "Élève ajouté avec succès. Matricule : $matricule";
    } elseif ($_POST['action'] === 'edit') {
        $stmt = $pdo->prepare("UPDATE students SET first_name=?,last_name=?,date_of_birth=?,gender=?,class_id=?,parent_id=?,option_filiere=?,phone=?,address=? WHERE id=?");
        $stmt->execute([trim($_POST['first_name']), trim($_POST['last_name']),
            $_POST['date_of_birth']?:null, $_POST['gender'], $_POST['class_id']?:null,
            $_POST['parent_id']?:null, trim($_POST['option_filiere']), trim($_POST['phone']), trim($_POST['address']),
            $_POST['student_id']]);
        $success = "Élève modifié avec succès.";
    }
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$_GET['delete']]);
    $success = "Élève supprimé.";
}

$search = trim($_GET['search'] ?? '');
$filterClass = $_GET['class_id'] ?? '';
$filterGender = $_GET['gender'] ?? '';

$sql = "SELECT s.*, c.name as class_name, u.name as parent_name,
        TIMESTAMPDIFF(YEAR,s.date_of_birth,CURDATE()) as age
        FROM students s
        LEFT JOIN classes c ON s.class_id=c.id
        LEFT JOIN users u ON s.parent_id=u.id WHERE (c.school_level = ? OR s.class_id IS NULL)";
$params = [getSchoolMode()];
if ($search) { $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.matricule LIKE ?)"; $params = array_merge($params,["%$search%","%$search%","%$search%"]); }
if ($filterClass) { $sql .= " AND s.class_id=?"; $params[] = $filterClass; }
if ($filterGender) { $sql .= " AND s.gender=?"; $params[] = $filterGender; }
$sql .= " ORDER BY s.last_name, s.first_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$students = $stmt->fetchAll();

// Export CSV AVANT tout affichage HTML
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=eleves_'.date('Y-m-d').'.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 pour Excel
    fputcsv($out, ['Matricule','Nom','Prénom','Sexe','Âge','Classe','Option','Parent','Téléphone','Adresse'], ';');
    foreach ($students as $s) {
        fputcsv($out, [$s['matricule']??'',$s['last_name'],$s['first_name'],$s['gender'],$s['age']??'',$s['class_name']??'',$s['option_filiere']??'',$s['parent_name']??'',$s['phone']??'',$s['address']??''], ';');
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';

$stmtCl = $pdo->prepare("SELECT * FROM classes WHERE school_level = ? ORDER BY name");
$stmtCl->execute([getSchoolMode()]);
$classes = $stmtCl->fetchAll();
$parents = $pdo->query("SELECT id, name, email FROM users WHERE role='parent' ORDER BY name")->fetchAll();
?>

<div class="page-header">
    <div>
        <h2>👨‍🎓 <?= t('students') ?></h2>
        <p><?= count($students) ?> élève(s) trouvé(s)</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ <?= t('add_student') ?></button>
</div>

<?php if ($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>

<!-- Barre de recherche -->
<div class="card" style="padding:14px;margin-bottom:16px;">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:2;min-width:180px;margin:0;">
            <label style="font-size:12px;font-weight:600;color:var(--text-muted);">🔍 Recherche</label>
            <input type="text" name="search" class="form-control" placeholder="Nom, prénom, matricule..." value="<?= escape($search) ?>" style="padding:9px 12px;font-size:13px;">
        </div>
        <div class="form-group" style="flex:1;min-width:130px;margin:0;">
            <label style="font-size:12px;font-weight:600;color:var(--text-muted);"><?= t('class') ?></label>
            <select name="class_id" class="form-control" style="padding:9px 12px;font-size:13px;">
                <option value=""><?= t('all') ?></option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterClass===$c['id']?'selected':'' ?>><?= escape($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1;min-width:110px;margin:0;">
            <label style="font-size:12px;font-weight:600;color:var(--text-muted);"><?= t('gender') ?></label>
            <select name="gender" class="form-control" style="padding:9px 12px;font-size:13px;">
                <option value=""><?= t('all') ?></option>
                <option value="M" <?= $filterGender==='M'?'selected':'' ?>><?= t('male') ?></option>
                <option value="F" <?= $filterGender==='F'?'selected':'' ?>><?= t('female') ?></option>
            </select>
        </div>
        <div style="display:flex;gap:6px;">
            <button type="submit" class="btn btn-primary btn-sm"><?= t('filter') ?></button>
            <a href="students.php" class="btn btn-outline btn-sm">✕</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3>Liste des élèves</h3>
        <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="btn btn-outline btn-sm">📥 CSV</a>
    </div>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr>
            <th>Matricule</th><th>Nom Complet</th><th>Sexe</th><th>Âge</th>
            <th>Classe</th><th>Option</th><th>Parent</th><th>Tél.</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($students as $s): ?>
            <tr>
                <td><code style="font-size:11px;color:var(--primary)"><?= escape($s['matricule']??'—') ?></code></td>
                <td><strong><?= escape($s['last_name'].' '.$s['first_name']) ?></strong></td>
                <td><span class="badge <?= $s['gender']==='M'?'badge-primary':'badge-danger' ?>"><?= $s['gender']==='M'?'M':'F' ?></span></td>
                <td><?= $s['age'] ?? '—' ?> ans</td>
                <td><?= escape($s['class_name']??'—') ?></td>
                <td><?= escape($s['option_filiere']??'—') ?></td>
                <td><?= escape($s['parent_name']??'—') ?></td>
                <td><?= escape($s['phone']??'—') ?></td>
                <td style="white-space:nowrap;">
                    <button class="btn btn-sm btn-outline" onclick="showHistory(<?= $s['id'] ?>)" title="Historique">📋</button>
                    <button class="btn btn-sm btn-outline" onclick="editStudent(<?= htmlspecialchars(json_encode($s)) ?>)" title="Modifier">✏️</button>
                    <a href="bulletin.php?student_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline" title="Bulletin">📄</a>
                    <button class="btn btn-sm btn-danger" onclick="confirmDelete('Supprimer <?= escape($s['first_name']) ?> ?','?delete=<?= $s['id'] ?>')" title="Supprimer">🗑</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?><tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px;">Aucun élève trouvé</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ═══ Modal Ajouter élève (compact) ═══ -->
<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:500px;">
        <button class="modal-close-btn" onclick="closeModal('addModal')">✕</button>
        <h3 style="padding:14px 20px 12px;font-size:16px;">👨‍🎓 Ajouter un élève</h3>
        <form method="POST" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
            <div class="modal-body-compact">
                <input type="hidden" name="action" value="add">
                <div class="form-row-2">
                    <div class="form-group-sm"><label>Nom *</label><input type="text" name="last_name" class="form-control-sm" required placeholder="Kabongo"></div>
                    <div class="form-group-sm"><label>Prénom *</label><input type="text" name="first_name" class="form-control-sm" required placeholder="Jean"></div>
                </div>
                <div class="form-row-2">
                    <div class="form-group-sm"><label>Date de naissance</label><input type="date" name="date_of_birth" class="form-control-sm"></div>
                    <div class="form-group-sm"><label>Sexe</label>
                        <select name="gender" class="form-control-sm"><option value="M">Masculin</option><option value="F">Féminin</option></select>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group-sm"><label>Classe</label>
                        <select name="class_id" class="form-control-sm"><option value="">— Choisir —</option>
                        <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-sm"><label>Option/Filière</label><input type="text" name="option_filiere" class="form-control-sm" placeholder="Sciences..."></div>
                </div>
                <div class="form-row-2">
                    <div class="form-group-sm">
                        <label style="display:flex;justify-content:space-between;">
                            <span>Parent</span>
                            <button type="button" onclick="openQuickParentModal()" style="background:none;border:none;color:var(--primary);font-weight:700;font-size:11px;cursor:pointer;padding:0;">+ Créer</button>
                        </label>
                        <select name="parent_id" id="add_parent_select" class="form-control-sm">
                            <option value="">— Aucun —</option>
                            <?php foreach ($parents as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= escape($p['name']) ?> (<?= escape($p['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-sm"><label>Téléphone</label><input type="text" name="phone" class="form-control-sm" placeholder="+243 8X..."></div>
                </div>
                <div class="form-group-sm"><label>Adresse</label><input type="text" name="address" class="form-control-sm" placeholder="Quartier, Avenue..."></div>
            </div>
            <div class="modal-actions-compact">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Modal Modifier élève (compact) ═══ -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:500px;">
        <button class="modal-close-btn" onclick="closeModal('editModal')">✕</button>
        <h3 style="padding:14px 20px 12px;font-size:16px;">✏️ Modifier l'élève</h3>
        <form method="POST" id="editForm" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
            <div class="modal-body-compact">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="student_id" id="edit_id">
                <div class="form-row-2">
                    <div class="form-group-sm"><label>Nom *</label><input type="text" name="last_name" id="edit_last_name" class="form-control-sm" required></div>
                    <div class="form-group-sm"><label>Prénom *</label><input type="text" name="first_name" id="edit_first_name" class="form-control-sm" required></div>
                </div>
                <div class="form-row-2">
                    <div class="form-group-sm"><label>Date de naissance</label><input type="date" name="date_of_birth" id="edit_dob" class="form-control-sm"></div>
                    <div class="form-group-sm"><label>Sexe</label>
                        <select name="gender" id="edit_gender" class="form-control-sm"><option value="M">Masculin</option><option value="F">Féminin</option></select>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group-sm"><label>Classe</label>
                        <select name="class_id" id="edit_class" class="form-control-sm"><option value="">— Choisir —</option>
                        <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-sm"><label>Option/Filière</label><input type="text" name="option_filiere" id="edit_option" class="form-control-sm"></div>
                </div>
                <div class="form-row-2">
                    <div class="form-group-sm">
                        <label style="display:flex;justify-content:space-between;">
                            <span>Parent</span>
                            <button type="button" onclick="openQuickParentModal()" style="background:none;border:none;color:var(--primary);font-weight:700;font-size:11px;cursor:pointer;padding:0;">+ Créer</button>
                        </label>
                        <select name="parent_id" id="edit_parent" class="form-control-sm">
                            <option value="">— Aucun —</option>
                            <?php foreach ($parents as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= escape($p['name']) ?> (<?= escape($p['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group-sm"><label>Téléphone</label><input type="text" name="phone" id="edit_phone" class="form-control-sm"></div>
                </div>
                <div class="form-group-sm"><label>Adresse</label><input type="text" name="address" id="edit_address" class="form-control-sm"></div>
            </div>
            <div class="modal-actions-compact">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('editModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Sauvegarder</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ Modal Historique ═══ -->
<div class="modal-overlay" id="historyModal">
    <div class="modal" style="max-width:680px;">
        <button class="modal-close-btn" onclick="closeModal('historyModal')">✕</button>
        <h3>📋 Historique de l'élève</h3>
        <div id="historyContent" style="max-height:460px;overflow-y:auto;padding:16px 24px;">Chargement...</div>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" onclick="closeModal('historyModal')">Fermer</button>
        </div>
    </div>
</div>

<!-- ═══ Modal Quick Parent (compact) ═══ -->
<div class="modal-overlay" id="quickParentModal" style="z-index:10000010;">
    <div class="modal" style="max-width:380px;">
        <button class="modal-close-btn" onclick="closeModal('quickParentModal')">✕</button>
        <h3 style="padding:14px 20px 12px;font-size:16px;">👨‍👩‍👧 Créer un parent</h3>
        <form id="quickParentForm" onsubmit="submitQuickParent(event)" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
            <div class="modal-body-compact">
                <div id="quickParentError" class="alert alert-danger" style="display:none;padding:8px 12px;font-size:12px;margin-bottom:10px;"></div>
                <div class="form-group-sm"><label>Nom complet *</label><input type="text" id="qp_name" class="form-control-sm" placeholder="Marie Kabongo" required></div>
                <div class="form-group-sm"><label>Email *</label><input type="email" id="qp_email" class="form-control-sm" placeholder="marie@exemple.cd" required></div>
                <div class="form-group-sm"><label>Mot de passe * <span style="font-size:10px;color:var(--text-muted);">(min. 6 car.)</span></label>
                    <input type="password" id="qp_password" class="form-control-sm" placeholder="••••••••" required minlength="6">
                </div>
            </div>
            <div class="modal-actions-compact">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('quickParentModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Créer</button>
            </div>
        </form>
    </div>
</div>

<script>
function editStudent(s) {
    document.getElementById('edit_id').value = s.id;
    document.getElementById('edit_last_name').value = s.last_name;
    document.getElementById('edit_first_name').value = s.first_name;
    document.getElementById('edit_dob').value = s.date_of_birth || '';
    document.getElementById('edit_gender').value = s.gender;
    document.getElementById('edit_class').value = s.class_id || '';
    document.getElementById('edit_option').value = s.option_filiere || '';
    document.getElementById('edit_parent').value = s.parent_id || '';
    document.getElementById('edit_phone').value = s.phone || '';
    document.getElementById('edit_address').value = s.address || '';
    openModal('editModal');
}

function showHistory(studentId) {
    openModal('historyModal');
    document.getElementById('historyContent').innerHTML = '<p style="text-align:center;padding:20px;">⏳ Chargement...</p>';
    fetch('/edusuivi/admin/student_history.php?id=' + studentId)
        .then(r => r.text())
        .then(html => { document.getElementById('historyContent').innerHTML = html; })
        .catch(() => { document.getElementById('historyContent').innerHTML = '<p>Erreur de chargement.</p>'; });
}

function openQuickParentModal() {
    document.getElementById('quickParentError').style.display = 'none';
    document.getElementById('qp_name').value = '';
    document.getElementById('qp_email').value = '';
    document.getElementById('qp_password').value = '';
    openModal('quickParentModal');
}

function submitQuickParent(e) {
    e.preventDefault();
    const name = document.getElementById('qp_name').value.trim();
    const email = document.getElementById('qp_email').value.trim();
    const password = document.getElementById('qp_password').value;
    const errorDiv = document.getElementById('quickParentError');
    errorDiv.style.display = 'none';
    const formData = new FormData();
    formData.append('ajax_action', 'add_parent');
    formData.append('name', name);
    formData.append('email', email);
    formData.append('password', password);
    fetch('students.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const selects = [document.getElementById('add_parent_select'), document.getElementById('edit_parent')];
            selects.forEach(select => {
                if (select) {
                    const opt = document.createElement('option');
                    opt.value = res.parent.id;
                    opt.textContent = `${res.parent.name} (${res.parent.email})`;
                    select.appendChild(opt);
                    select.value = res.parent.id;
                }
            });
            closeModal('quickParentModal');
        } else {
            errorDiv.textContent = '❌ ' + res.error;
            errorDiv.style.display = 'block';
        }
    })
    .catch(() => {
        errorDiv.textContent = '❌ Une erreur est survenue.';
        errorDiv.style.display = 'block';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

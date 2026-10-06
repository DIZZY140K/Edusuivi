<?php require_once __DIR__ . '/../includes/header.php'; requireRole('admin');

$success = $error = '';
$currentMode = getSchoolMode();

// Niveaux selon le mode de gestion
if ($currentMode === 'primaire') {
    $drcLevels = [
        '1ère année'  => '1ère année primaire',
        '2ème année'  => '2ème année primaire',
        '3ème année'  => '3ème année primaire',
        '4ème année'  => '4ème année primaire',
        '5ème année'  => '5ème année primaire',
        '6ème année'  => '6ème année primaire',
    ];
} else {
    $drcLevels = [
        '7ème année'         => '7ème (Tronc Commun)',
        '8ème année'         => '8ème (Tronc Commun)',
        '1ère Humanités'     => '1ère Humanités',
        '2ème Humanités'     => '2ème Humanités',
        '3ème Humanités'     => '3ème Humanités',
        '4ème Humanités'     => '4ème Humanités',
    ];
}
$optionClasses = ['Scientifique', 'Littéraire', 'Commerciale', 'Pédagogie'];
$sections = ['A','B','C','D','E','F'];

// ── CRUD Classes ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_class') {
        $level   = trim($_POST['level']);
        $section = trim($_POST['section'] ?? '');
        $option  = trim($_POST['option_h'] ?? '');
        // Construire le nom automatique ou utiliser le nom saisi
        if (!empty($_POST['custom_name'])) {
            $name = trim($_POST['custom_name']);
        } else {
            // Format : Niveau + Option + Section  ex: "3ème Humanités Scientifique A"
            $name = $level;
            if ($option) $name .= ' ' . $option;
            if ($section) $name .= ' ' . $section;
        }
        $pdo->prepare("INSERT INTO classes (name, level, teacher_id, school_level) VALUES (?,?,?,?)")
            ->execute([$name, $level, $_POST['teacher_id'] ?: null, $currentMode]);
        $success = "Classe « $name » créée avec succès.";
    } elseif ($_POST['action'] === 'edit_class') {
        $pdo->prepare("UPDATE classes SET name=?, level=?, teacher_id=? WHERE id=?")
            ->execute([trim($_POST['name']), trim($_POST['level']), $_POST['teacher_id'] ?: null, intval($_POST['class_id'])]);
        $success = "Classe modifiée.";
    } elseif ($_POST['action'] === 'assign_teacher') {
        $pdo->prepare("INSERT IGNORE INTO teacher_assignments (teacher_id, class_id, subject_id) VALUES (?,?,?)")
            ->execute([intval($_POST['teacher_id']), intval($_POST['class_id']), intval($_POST['subject_id'])]);
        $success = "Affectation ajoutée.";
    }
}

if (isset($_GET['delete_class'])) {
    $pdo->prepare("DELETE FROM classes WHERE id=?")->execute([intval($_GET['delete_class'])]);
    $success = "Classe supprimée.";
}
if (isset($_GET['unassign'])) {
    $pdo->prepare("DELETE FROM teacher_assignments WHERE id=?")->execute([intval($_GET['unassign'])]);
    $success = "Affectation retirée.";
}

// ── Données ─────────────────────────────────────────────
$classes = $pdo->prepare("
    SELECT c.*, u.name as titular_name,
           (SELECT COUNT(*) FROM students WHERE class_id=c.id) as students_count
    FROM classes c LEFT JOIN users u ON c.teacher_id=u.id
    WHERE c.school_level = ?
    ORDER BY c.level, c.name
");
$classes->execute([$currentMode]);
$classes = $classes->fetchAll();

$teachers = $pdo->query("SELECT id, name FROM users WHERE role='teacher' ORDER BY name")->fetchAll();
$subjects = $pdo->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll();

$assignments = $pdo->query("
    SELECT ta.*, u.name as teacher_name, c.name as class_name, sub.name as subject_name
    FROM teacher_assignments ta
    JOIN users u ON ta.teacher_id=u.id
    JOIN classes c ON ta.class_id=c.id
    JOIN subjects sub ON ta.subject_id=sub.id
    ORDER BY c.name, sub.name
")->fetchAll();
$byClass = [];
foreach($assignments as $a) $byClass[$a['class_id']][] = $a;

// Grouper les classes par niveau
$classesByLevel = [];
foreach($classes as $c) {
    $classesByLevel[$c['level'] ?? 'Autre'][] = $c;
}
?>

<div class="page-header">
    <div>
        <h2>🏫 Classes &amp; Affectations</h2>
        <p><?= $currentMode === 'primaire' ? 'École Primaire — 1ère à 6ème année' : 'École Secondaire — 7ème → 4ème Humanités' ?></p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addClassModal')">+ Nouvelle classe</button>
</div>

<?php if($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>
<?php if($error):   ?><div class="alert alert-danger"><?= escape($error) ?></div><?php endif; ?>

<!-- ── Vue par niveaux ───────────────────────────────── -->
<?php if(empty($classes)): ?>
<div class="card" style="padding:48px;text-align:center;color:var(--text-muted);">
    <div style="font-size:48px;margin-bottom:12px;">🏫</div>
    <h3>Aucune classe créée</h3>
    <p style="margin-top:8px;font-size:14px;">Cliquez sur "+ Nouvelle classe" pour commencer.</p>
</div>
<?php else: ?>

<?php foreach($classesByLevel as $levelName => $levelClasses): ?>
<div style="margin-bottom:28px;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
        <div style="height:1px;flex:1;background:var(--border);"></div>
        <span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--text-muted);white-space:nowrap;">
            🎓 <?= escape($levelName) ?>
        </span>
        <div style="height:1px;flex:1;background:var(--border);"></div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;">
        <?php foreach($levelClasses as $c):
            $hasStudents = $c['students_count'] > 0;
            $assignCount = count($byClass[$c['id']] ?? []);
        ?>
        <div class="card" style="padding:20px;position:relative;transition:all .2s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.3)'" onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                <div>
                    <div style="font-size:17px;font-weight:800;color:var(--text);"><?= escape($c['name']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= escape($c['level'] ?? '') ?></div>
                </div>
                <span style="background:rgba(99,102,241,.15);color:#818cf8;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px;">
                    <?= $c['students_count'] ?> élève<?= $c['students_count'] != 1 ? 's' : '' ?>
                </span>
            </div>

            <div style="display:flex;flex-direction:column;gap:6px;font-size:12px;margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:8px;color:var(--text-muted);">
                    <span>👨‍🏫</span>
                    <span><?= escape($c['titular_name'] ?? 'Aucun titulaire') ?></span>
                </div>
                <?php if (($c['school_level'] ?? '') === 'primaire' || $currentMode === 'primaire'): ?>
                <div style="display:flex;align-items:center;gap:8px;color:#10b981;font-weight:600;font-size:11px;">
                    <span>🎓</span>
                    <span>Gère tous les cours du primaire</span>
                </div>
                <?php else: ?>
                <div style="display:flex;align-items:center;gap:8px;color:var(--text-muted);">
                    <span>📚</span>
                    <span><?= $assignCount ?> cours assigné<?= $assignCount != 1 ? 's' : '' ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if(!empty($byClass[$c['id']])): ?>
            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px;">
                <?php foreach(array_slice($byClass[$c['id']], 0, 4) as $a): ?>
                <span style="background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:6px;padding:2px 8px;font-size:10px;color:var(--text-muted);">
                    <?= escape($a['subject_name']) ?>
                </span>
                <?php endforeach; ?>
                <?php if(count($byClass[$c['id']]) > 4): ?>
                <span style="font-size:10px;color:var(--text-muted);padding:2px 6px;">+<?= count($byClass[$c['id']]) - 4 ?> autres</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div style="display:flex;gap:6px;border-top:1px solid var(--border);padding-top:12px;">
                <a href="/edusuivi/admin/students.php?class_id=<?= $c['id'] ?>" class="btn btn-sm btn-outline" style="flex:1;justify-content:center;font-size:11px;gap:6px;">👨‍🎓 Élèves</a>
                <button class="btn btn-sm btn-outline" onclick="editClass(<?= htmlspecialchars(json_encode($c)) ?>)" style="padding:6px 10px;" title="Modifier">✏️</button>
                <button class="btn btn-sm btn-danger" onclick="confirmDelete('Supprimer la classe <?= escape($c['name']) ?> ?','?delete_class=<?= $c['id'] ?>')" style="padding:6px 10px;" title="Supprimer">🗑</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<!-- ── Tableau d'affectations ─────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:8px;">
    <!-- Form affectation -->
    <div class="card">
        <div class="card-header"><h3>🔗 Associer Enseignant ↔ Cours ↔ Classe</h3></div>
        <div style="padding:16px;">
            <form method="POST">
                <input type="hidden" name="action" value="assign_teacher">
                <div class="form-group"><label>Enseignant</label>
                    <select name="teacher_id" class="form-control" required>
                        <option value="">— Choisir —</option>
                        <?php foreach($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= escape($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Classe</label>
                    <select name="class_id" class="form-control" required>
                        <option value="">— Choisir —</option>
                        <?php foreach($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>Matière</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">— Choisir —</option>
                        <?php foreach($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= escape($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">🔗 Créer l'affectation</button>
            </form>
        </div>
    </div>

    <!-- Résumé affectations récentes -->
    <div class="card">
        <div class="card-header"><h3>📋 Dernières affectations</h3></div>
        <table>
            <thead><tr><th>Classe</th><th>Matière</th><th><?= getTeacherLabel() ?></th><th></th></tr></thead>
            <tbody>
            <?php $recent = array_slice($assignments, 0, 12); ?>
            <?php foreach($recent as $a): ?>
            <tr>
                <td><strong><?= escape($a['class_name']) ?></strong></td>
                <td style="font-size:12px;"><?= escape($a['subject_name']) ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= escape($a['teacher_name']) ?></td>
                <td>
                    <a href="?unassign=<?= $a['id'] ?>" class="btn btn-sm btn-outline" style="border:none;color:#ef4444;padding:4px 8px;" onclick="return confirm('Retirer cette affectation ?')">✕</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($assignments)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px;">Aucune affectation</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══ MODAL Nouvelle classe ═════════════════════ -->
<div class="modal-overlay" id="addClassModal">
    <div class="modal" style="max-width:520px;">
        <button class="modal-close-btn" onclick="closeModal('addClassModal')">✕</button>
        <h3 style="padding:16px 48px 14px 20px;font-size:18px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0;background:var(--card);">
            <span style="width:36px;height:36px;background:linear-gradient(135deg,#6366f1,#818cf8);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">🏫</span>
            Nouvelle classe
        </h3>
        <form method="POST" id="addClassForm" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
            <div class="modal-body-compact">
                <input type="hidden" name="action" value="add_class">

                <!-- Étape 1 : Niveau -->
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid var(--border);">1. Niveau scolaire</div>
                <div class="form-group-sm">
                    <label>Niveau *</label>
                    <select name="level" class="form-control-sm" id="levelSelect" onchange="updateClassName()" required>
                        <option value="">— Choisir le niveau —</option>
                        <?php foreach($drcLevels as $val => $label): ?>
                        <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($currentMode === 'secondaire'): ?>
                <!-- Étape 2 : Option (4 choix fixes) -->
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid var(--border);margin-top:14px;">2. Option de la classe</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:4px;" id="optionBtnGroup">
                    <?php foreach($optionClasses as $opt): ?>
                    <button type="button" class="option-btn"
                        onclick="selectOption('<?= $opt ?>')"
                        style="padding:10px 6px;border:1.5px solid var(--border);border-radius:10px;background:rgba(255,255,255,0.03);color:var(--text-muted);font-size:13px;font-weight:600;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;">
                        <?php
                            $icons = [
                                'Scientifique' => '🔬',
                                'Littéraire'   => '📖',
                                'Commerciale'  => '💼',
                                'Pédagogie'    => '🎓'
                            ];
                            echo $icons[$opt] ?? '📌';
                        ?> <?= $opt ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="option_h" id="optionHidden" value="">
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:2px;" id="optionHint">Aucune option sélectionnée (facultatif)</div>
                <?php else: ?>
                <input type="hidden" name="option_h" id="optionHidden" value="">
                <?php endif; ?>

                <!-- Étape 3 : Section A/B/C -->
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid var(--border);margin-top:14px;"><?= $currentMode === 'secondaire' ? '3.' : '2.' ?> Section (A, B, C…)</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:4px;" id="sectionBtnGroup">
                    <?php foreach($sections as $s): ?>
                    <button type="button" class="section-btn"
                        onclick="selectSection('<?= $s ?>')"
                        style="width:44px;height:44px;border:1.5px solid var(--border);border-radius:10px;background:rgba(255,255,255,0.03);color:var(--text-muted);font-size:15px;font-weight:700;cursor:pointer;transition:all .2s;">
                        <?= $s ?>
                    </button>
                    <?php endforeach; ?>
                    <button type="button" id="sectionNoneBtn"
                        onclick="selectSection('')"
                        style="height:44px;padding:0 14px;border:1.5px solid rgba(239,68,68,0.3);border-radius:10px;background:rgba(239,68,68,0.06);color:#ef4444;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;">
                        Aucune
                    </button>
                </div>
                <input type="hidden" name="section" id="sectionHidden" value="">

                <!-- Prévisualisation du nom généré -->
                <div style="background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(99,102,241,.04));border:1.5px solid rgba(99,102,241,.25);border-radius:10px;padding:12px 16px;margin-top:14px;">
                    <div style="font-size:10px;color:rgba(129,140,248,.9);margin-bottom:4px;text-transform:uppercase;letter-spacing:1px;">Nom de la classe généré</div>
                    <div style="font-size:17px;font-weight:800;color:var(--text);" id="namePreview">—</div>
                </div>

                <!-- Titulaire -->
                <div style="margin-top:14px;" class="form-group-sm">
                    <label style="font-weight:700;">
                        👨‍🏫 <?= getTeacherLabel() ?> <?= $currentMode === 'primaire' ? 'de la classe (Titulaire) *' : 'titulaire (optionnel)' ?>
                    </label>
                    <select name="teacher_id" class="form-control-sm" <?= $currentMode === 'primaire' ? 'required' : '' ?>>
                        <option value="">— <?= $currentMode === 'primaire' ? 'Sélectionner l\'enseignant' : 'Aucun' ?> —</option>
                        <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= escape($t['name']) ?></option><?php endforeach; ?>
                    </select>
                    <?php if ($currentMode === 'primaire'): ?>
                    <div style="font-size:11px;color:#10b981;margin-top:4px;font-weight:500;">
                        ℹ️ À l'école primaire, cet enseignant gère l'ensemble des cours de la classe.
                    </div>
                    <?php else: ?>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">
                        ℹ️ L'affectation cours par cours des professeurs s'effectue dans <a href="subjects.php#section-assignments" style="color:var(--gold);text-decoration:underline;">Cours & Matières</a>.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-actions-compact">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addClassModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Créer la classe</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL Modifier classe ═══════════════════════════ -->
<div class="modal-overlay" id="editClassModal">
    <div class="modal" style="max-width:420px;">
        <button class="modal-close-btn" onclick="closeModal('editClassModal')">✕</button>
        <h3 style="padding:14px 20px 12px;font-size:16px;">✏️ Modifier la classe</h3>
        <form method="POST" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
            <div class="modal-body-compact">
                <input type="hidden" name="action" value="edit_class">
                <input type="hidden" name="class_id" id="edit_class_id">
                <div class="form-group-sm">
                    <label>Nom *</label>
                    <input type="text" name="name" id="edit_class_name" class="form-control-sm" required>
                </div>
                <div class="form-group-sm">
                    <label>Niveau</label>
                    <select name="level" id="edit_class_level" class="form-control-sm">
                        <option value="">— Choisir —</option>
                        <?php foreach($drcLevels as $val => $label): ?>
                        <option value="<?= $val ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-sm">
                    <label style="font-weight:700;">👨‍🏫 <?= getTeacherLabel() ?> <?= $currentMode === 'primaire' ? 'de la classe (Titulaire)' : 'titulaire' ?></label>
                    <select name="teacher_id" id="edit_class_teacher" class="form-control-sm">
                        <option value="">— <?= $currentMode === 'primaire' ? 'Sélectionner l\'enseignant' : 'Aucun' ?> —</option>
                        <?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= escape($t['name']) ?></option><?php endforeach; ?>
                    </select>
                    <?php if ($currentMode === 'primaire'): ?>
                    <div style="font-size:11px;color:#10b981;margin-top:4px;font-weight:500;">
                        ℹ️ À l'école primaire, cet enseignant gère l'ensemble des cours de la classe.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-actions-compact">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('editClassModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">Sauvegarder</button>
            </div>
        </form>
    </div>
</div>

<script>
let selectedOption  = '';
let selectedSection = '';

function selectOption(opt) {
    // Toggle : cliquer sur la même option la désélectionne
    selectedOption = (selectedOption === opt) ? '' : opt;
    document.getElementById('optionHidden').value = selectedOption;
    document.getElementById('optionHint').textContent = selectedOption
        ? '✅ Option sélectionnée : ' + selectedOption
        : 'Aucune option sélectionnée (facultatif)';
    // Mettre à jour le style des boutons
    document.querySelectorAll('.option-btn').forEach(btn => {
        const isActive = btn.textContent.trim().includes(selectedOption) && selectedOption !== '';
        btn.style.borderColor  = isActive ? 'var(--primary)' : 'var(--border)';
        btn.style.background   = isActive ? 'rgba(212,147,13,0.12)' : 'rgba(255,255,255,0.03)';
        btn.style.color        = isActive ? 'var(--primary)' : 'var(--text-muted)';
    });
    updateClassName();
}

function selectSection(sec) {
    selectedSection = sec;
    document.getElementById('sectionHidden').value = sec;
    // Mettre à jour le style des boutons
    document.querySelectorAll('.section-btn').forEach(btn => {
        const isActive = btn.textContent.trim() === sec && sec !== '';
        btn.style.borderColor  = isActive ? '#6366f1' : 'var(--border)';
        btn.style.background   = isActive ? 'rgba(99,102,241,0.12)' : 'rgba(255,255,255,0.03)';
        btn.style.color        = isActive ? '#818cf8' : 'var(--text-muted)';
    });
    // Bouton "Aucune"
    const noneBtn = document.getElementById('sectionNoneBtn');
    if (noneBtn) {
        noneBtn.style.background  = sec === '' ? 'rgba(239,68,68,0.15)' : 'rgba(239,68,68,0.06)';
        noneBtn.style.borderColor = sec === '' ? '#ef4444' : 'rgba(239,68,68,0.3)';
    }
    updateClassName();
}

function updateClassName() {
    const level = document.getElementById('levelSelect').value;
    // Construire le nom : Niveau + Option + Section
    let parts = [];
    if (level) parts.push(level);
    if (selectedOption) parts.push(selectedOption);
    if (selectedSection) parts.push(selectedSection);
    const name = parts.join(' ');
    document.getElementById('namePreview').textContent = name || '—';
}

function editClass(c) {
    document.getElementById('edit_class_id').value = c.id;
    document.getElementById('edit_class_name').value = c.name;
    document.getElementById('edit_class_level').value = c.level || '';
    document.getElementById('edit_class_teacher').value = c.teacher_id || '';
    openModal('editClassModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

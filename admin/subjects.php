<?php require_once __DIR__ . '/../includes/header.php'; requireRole('admin');

$currentMode = getSchoolMode();
$filterLevel = $_GET['level'] ?? $currentMode; // 'primaire', 'secondaire', ou 'all'
$error = '';
$success = '';

// ── TRAITEMENT DES FORMULAIRES ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. AJOUT D'UN COURS
    if ($action === 'create_subject') {
        $name        = trim($_POST['name'] ?? '');
        $schoolLevel = $_POST['school_level'] ?? 'secondaire';
        $category    = trim($_POST['category'] ?? 'Général');

        if (empty($name)) {
            $error = "Le nom du cours est obligatoire.";
        } else {
            // Vérifier doublon
            $chk = $pdo->prepare("SELECT id FROM subjects WHERE LOWER(name) = LOWER(?) AND school_level = ?");
            $chk->execute([$name, $schoolLevel]);
            if ($chk->fetch()) {
                $error = "Ce cours existe déjà pour ce cycle d'enseignement.";
            } else {
                $ins = $pdo->prepare("INSERT INTO subjects (name, school_level, category) VALUES (?, ?, ?)");
                $ins->execute([$name, $schoolLevel, $category]);
                $success = "Le cours « " . escape($name) . " » a été enregistré avec succès.";
            }
        }
    }

    // 2. MODIFICATION D'UN COURS
    if ($action === 'edit_subject') {
        $id          = intval($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $schoolLevel = $_POST['school_level'] ?? 'secondaire';
        $category    = trim($_POST['category'] ?? 'Général');

        if (empty($name) || !$id) {
            $error = "Le nom du cours est obligatoire.";
        } else {
            $upd = $pdo->prepare("UPDATE subjects SET name = ?, school_level = ?, category = ? WHERE id = ?");
            $upd->execute([$name, $schoolLevel, $category, $id]);
            $success = "Le cours a été mis à jour avec succès.";
        }
    }

    // 3. SUPPRESSION D'UN COURS
    if ($action === 'delete_subject') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            // Vérifier s'il y a des notes ou évaluations associées
            $cntNotes = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE subject_id = ?");
            $cntNotes->execute([$id]);
            $hasGrades = $cntNotes->fetchColumn() > 0;

            $cntEvals = $pdo->prepare("SELECT COUNT(*) FROM evaluations WHERE subject_id = ?");
            $cntEvals->execute([$id]);
            $hasEvals = $cntEvals->fetchColumn() > 0;

            if ($hasGrades || $hasEvals) {
                $error = "Impossible de supprimer ce cours car des notes ou évaluations y sont déjà rattachées.";
            } else {
                // Supprimer les affectations professeurs
                $pdo->prepare("DELETE FROM teacher_assignments WHERE subject_id = ?")->execute([$id]);
                // Supprimer le cours
                $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$id]);
                $success = "Le cours a été supprimé avec succès.";
            }
        }
    }

    // 4. AFFECTATION D'UN PROFESSEUR À UN COURS ET UNE CLASSE (SECONDAIRE)
    if ($action === 'assign_teacher') {
        $teacherId = intval($_POST['teacher_id'] ?? 0);
        $classId   = intval($_POST['class_id'] ?? 0);
        $subjectId = intval($_POST['subject_id'] ?? 0);

        if (!$teacherId || !$classId || !$subjectId) {
            $error = "Veuillez sélectionner la classe, le cours et le professeur.";
        } else {
            // Vérifier si cette affectation existe déjà
            $chk = $pdo->prepare("SELECT id FROM teacher_assignments WHERE class_id = ? AND subject_id = ?");
            $chk->execute([$classId, $subjectId]);
            $existing = $chk->fetch();

            if ($existing) {
                // Mettre à jour le professeur assigné
                $upd = $pdo->prepare("UPDATE teacher_assignments SET teacher_id = ? WHERE id = ?");
                $upd->execute([$teacherId, $existing['id']]);
                $success = "Le professeur assigné à ce cours a été mis à jour.";
            } else {
                // Créer une nouvelle affectation
                $ins = $pdo->prepare("INSERT INTO teacher_assignments (teacher_id, class_id, subject_id) VALUES (?, ?, ?)");
                $ins->execute([$teacherId, $classId, $subjectId]);
                $success = "Le professeur a été associé au cours avec succès.";
            }
        }
    }

    // 5. SUPPRESSION D'UNE AFFECTATION
    if ($action === 'remove_assignment') {
        $assignId = intval($_POST['assignment_id'] ?? 0);
        if ($assignId) {
            $pdo->prepare("DELETE FROM teacher_assignments WHERE id = ?")->execute([$assignId]);
            $success = "L'affectation du professeur a été retirée.";
        }
    }
}

// ── REQUÊTES POUR L'AFFICHAGE ────────────────────────────────
$whereLevel = "";
$paramsLevel = [];
if ($filterLevel === 'primaire') {
    $whereLevel = "WHERE s.school_level IN ('primaire', 'les_deux')";
} elseif ($filterLevel === 'secondaire') {
    $whereLevel = "WHERE s.school_level IN ('secondaire', 'les_deux')";
}

$subjectsStmt = $pdo->prepare("
    SELECT s.*, 
           (SELECT COUNT(DISTINCT ta.class_id) FROM teacher_assignments ta WHERE ta.subject_id = s.id) as classes_count,
           (SELECT COUNT(DISTINCT ta.teacher_id) FROM teacher_assignments ta WHERE ta.subject_id = s.id) as teachers_count
    FROM subjects s
    $whereLevel
    ORDER BY s.school_level, s.category, s.name
");
$subjectsStmt->execute($paramsLevel);
$subjects = $subjectsStmt->fetchAll();

// Statistiques globales
$totalPrimaire   = $pdo->query("SELECT COUNT(*) FROM subjects WHERE school_level IN ('primaire', 'les_deux')")->fetchColumn();
$totalSecondaire = $pdo->query("SELECT COUNT(*) FROM subjects WHERE school_level IN ('secondaire', 'les_deux')")->fetchColumn();
$totalAssignments = $pdo->query("SELECT COUNT(*) FROM teacher_assignments")->fetchColumn();

// Listes pour le formulaire d'affectation
$secondaryClasses = $pdo->query("SELECT id, name FROM classes WHERE school_level = 'secondaire' ORDER BY name")->fetchAll();
$secondarySubjects = $pdo->query("SELECT id, name, category FROM subjects WHERE school_level IN ('secondaire', 'les_deux') ORDER BY name")->fetchAll();
$professors = $pdo->query("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll();

// Liste des affectations secondaires actuelles
$assignmentsStmt = $pdo->query("
    SELECT ta.id as assign_id, c.name as class_name, c.school_level, s.name as subject_name, s.category, u.name as teacher_name
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.id
    JOIN subjects s ON ta.subject_id = s.id
    JOIN users u ON ta.teacher_id = u.id
    WHERE c.school_level = 'secondaire'
    ORDER BY c.name, s.name
");
$assignments = $assignmentsStmt->fetchAll();
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
    <div>
        <h2>📚 Gestion des Cours &amp; Matières</h2>
        <p>Enregistrez les cours pour le primaire et le secondaire, puis associez les professeurs à leurs cours</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-primary" onclick="openCreateModal()">+ Nouveau Cours</button>
        <a href="#section-assignments" class="btn btn-gold">👨‍🏫 Associer un Professeur</a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger" style="margin-bottom:20px;">⚠️ <?= escape($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success" style="margin-bottom:20px;">✅ <?= escape($success) ?></div>
<?php endif; ?>

<!-- ── Cartes Statistiques ──────────────────────────────────── -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="icon-box blue">🏫</div>
        <div>
            <div class="value"><?= $totalPrimaire ?></div>
            <div class="label">Cours École Primaire</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box gold">🎓</div>
        <div>
            <div class="value"><?= $totalSecondaire ?></div>
            <div class="label">Cours École Secondaire</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box green">👨‍🏫</div>
        <div>
            <div class="value"><?= $totalAssignments ?></div>
            <div class="label">Affectations Cours / Professeurs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box red">📄</div>
        <div>
            <div class="value">Bulletin</div>
            <div class="label">Génération 100% Dynamique</div>
        </div>
    </div>
</div>

<!-- ── FILTRE DE CYCLE (TABS) ───────────────────────────────── -->
<div class="tabs" style="margin-bottom:20px;">
    <a href="?level=primaire" class="tab <?= $filterLevel==='primaire'?'active':'' ?>">
        🏫 Cours Primaire (<?= $totalPrimaire ?>)
    </a>
    <a href="?level=secondaire" class="tab <?= $filterLevel==='secondaire'?'active':'' ?>">
        🎓 Cours Secondaire (<?= $totalSecondaire ?>)
    </a>
    <a href="?level=all" class="tab <?= $filterLevel==='all'?'active':'' ?>">
        🌐 Tous les cycles
    </a>
</div>

<!-- ── TABLEAU DES COURS ────────────────────────────────────── -->
<div class="card" style="margin-bottom:32px;">
    <div class="card-header" style="justify-content:space-between;align-items:center;">
        <div>
            <h3 style="display:flex;align-items:center;gap:8px;">
                📋 Répertoire des cours enregistrés
                <span style="font-size:12px;font-weight:400;color:var(--text-muted);">(<?= count($subjects) ?> cours affichés)</span>
            </h3>
            <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0 0;">
                Ces cours sont ceux qui apparaîtront sur les bulletins des élèves une fois affectés aux classes
            </p>
        </div>
    </div>

    <?php if (empty($subjects)): ?>
    <div style="padding:40px;text-align:center;color:var(--text-muted);">
        Aucun cours enregistré pour ce cycle d'enseignement.
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Intitulé du Cours</th>
                <th>Cycle d'enseignement</th>
                <th>Domaine / Catégorie</th>
                <th style="text-align:center;">Classes associées</th>
                <th style="text-align:center;">Professeurs affectés</th>
                <th style="text-align:center;min-width:130px;">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($subjects as $s): 
            $lvlBadge = [
                'primaire'   => ['label'=>'École Primaire', 'bg'=>'rgba(59,130,246,.12)', 'color'=>'#3b82f6', 'icon'=>'🏫'],
                'secondaire' => ['label'=>'École Secondaire', 'bg'=>'rgba(212,147,13,.12)', 'color'=>'#d4930d', 'icon'=>'🎓'],
                'les_deux'   => ['label'=>'Primaire & Secondaire', 'bg'=>'rgba(16,185,129,.12)', 'color'=>'#10b981', 'icon'=>'🌐'],
            ][$s['school_level']] ?? ['label'=>$s['school_level'], 'bg'=>'#eee', 'color'=>'#333', 'icon'=>'📌'];
        ?>
        <tr>
            <td>
                <strong style="font-size:14px;color:var(--text);"><?= escape($s['name']) ?></strong>
            </td>
            <td>
                <span style="background:<?= $lvlBadge['bg'] ?>;color:<?= $lvlBadge['color'] ?>;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:5px;">
                    <span><?= $lvlBadge['icon'] ?></span> <?= $lvlBadge['label'] ?>
                </span>
            </td>
            <td>
                <span class="badge badge-primary" style="font-size:11px;">
                    <?= escape($s['category'] ?: 'Général') ?>
                </span>
            </td>
            <td style="text-align:center;font-weight:700;">
                <?= $s['classes_count'] ?> classe(s)
            </td>
            <td style="text-align:center;">
                <span style="font-weight:700;color:<?= $s['teachers_count']>0 ? '#10b981' : 'var(--text-muted)' ?>;">
                    <?= $s['teachers_count'] ?> prof(s)
                </span>
            </td>
            <td style="text-align:center;">
                <button type="button" class="btn btn-sm btn-outline" onclick="openEditModal(<?= $s['id'] ?>, '<?= escape(addslashes($s['name'])) ?>', '<?= $s['school_level'] ?>', '<?= escape(addslashes($s['category'])) ?>')">
                    ✏️ Modifier
                </button>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Confirmer la suppression du cours « <?= escape(addslashes($s['name'])) ?> » ?');">
                    <input type="hidden" name="action" value="delete_subject">
                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" style="padding:4px 8px;" title="Supprimer">🗑</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── SECTION D'AFFECTATION DES PROFESSEURS DU SECONDAIRE ──── -->
<div class="card" id="section-assignments" style="border:1.5px solid var(--border);border-radius:14px;background:linear-gradient(135deg,rgba(212,147,13,.04),rgba(255,255,255,.01));">
    <div class="card-header" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--gold);margin-bottom:4px;">
                École Secondaire
            </div>
            <h3 style="margin:0;display:flex;align-items:center;gap:8px;">
                👨‍🏫 Association des Professeurs avec leurs Cours
            </h3>
            <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0 0;">
                Choisissez la classe du secondaire, le cours à dispenser et le professeur responsable. Ce sont ces cours qui figureront sur le bulletin du secondaire !
            </p>
            <div style="margin-top:8px;font-size:11px;color:#10b981;display:flex;align-items:center;gap:6px;background:rgba(16,185,129,0.08);padding:6px 12px;border-radius:8px;border:1px solid rgba(16,185,129,0.2);">
                ℹ️
                <span><strong>Règle de l'École Primaire :</strong> Pour le primaire, l'enseignant est directement associé à sa classe dans <a href="classes.php" style="color:var(--gold);font-weight:700;text-decoration:underline;">Gestion des Classes</a> et prend en charge l'ensemble des cours du primaire sans affectation individuelle matière par matière.</span>
            </div>
        </div>
    </div>

    <!-- Formulaire d'affectation -->
    <div style="padding:20px;border-bottom:1px solid var(--border);background:var(--card);">
        <form method="POST" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)) auto;gap:16px;align-items:flex-end;">
            <input type="hidden" name="action" value="assign_teacher">
            
            <div class="form-group" style="margin:0;">
                <label>🏫 Classe du Secondaire *</label>
                <select name="class_id" class="form-control" required>
                    <option value="">-- Choisir une classe --</option>
                    <?php foreach ($secondaryClasses as $sc): ?>
                    <option value="<?= $sc['id'] ?>">
                        <?= escape($sc['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <label>📚 Cours à dispenser *</label>
                <select name="subject_id" class="form-control" required>
                    <option value="">-- Choisir le cours --</option>
                    <?php foreach ($secondarySubjects as $ss): ?>
                    <option value="<?= $ss['id'] ?>">
                        <?= escape($ss['name']) ?> [<?= escape($ss['category'] ?: 'Général') ?>]
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin:0;">
                <label>👨‍🏫 Professeur responsable *</label>
                <select name="teacher_id" class="form-control" required>
                    <option value="">-- Choisir le professeur --</option>
                    <?php foreach ($professors as $p): ?>
                    <option value="<?= $p['id'] ?>">
                        <?= escape($p['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="height:42px;">
                🔗 Associer le Professeur
            </button>
        </form>
    </div>

    <!-- Liste des affectations du secondaire -->
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Classe</th>
                <th>Cours dispensé</th>
                <th>Domaine</th>
                <th>Professeur responsable</th>
                <th style="text-align:center;">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($assignments)): ?>
        <tr>
            <td colspan="5" style="text-align:center;padding:24px;color:var(--text-muted);">
                Aucune affectation de professeur n'a encore été effectuée pour le secondaire.
            </td>
        </tr>
        <?php else: ?>
        <?php foreach ($assignments as $a): ?>
        <tr>
            <td>
                <strong><?= escape($a['class_name']) ?></strong>
            </td>
            <td>
                <span class="badge badge-primary" style="font-size:12px;">
                    📚 <?= escape($a['subject_name']) ?>
                </span>
            </td>
            <td style="font-size:12px;color:var(--text-muted);">
                <?= escape($a['category'] ?: 'Général') ?>
            </td>
            <td>
                <span style="font-weight:700;color:var(--text);">
                    👨‍🏫 <?= escape($a['teacher_name']) ?>
                </span>
            </td>
            <td style="text-align:center;">
                <form method="POST" style="display:inline;" onsubmit="return confirm('Retirer cette affectation ?');">
                    <input type="hidden" name="action" value="remove_assignment">
                    <input type="hidden" name="assignment_id" value="<?= $a['assign_id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger" style="padding:4px 8px;" title="Retirer l'affectation">❌ Dissocier</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ── MODAL : NOUVEAU COURS ────────────────────────────────── -->
<div class="modal-overlay" id="createModal" style="display:none;">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <h3>📚 Nouveau Cours</h3>
            <button class="modal-close" onclick="closeCreateModal()">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_subject">
            <div class="modal-body">
                <div class="form-group">
                    <label>Intitulé du cours *</label>
                    <input type="text" name="name" class="form-control" placeholder="ex: Mathématiques, Physique, Français..." required>
                </div>
                <div class="form-group">
                    <label>Cycle d'enseignement *</label>
                    <select name="school_level" class="form-control" required>
                        <option value="primaire" <?= $filterLevel==='primaire'?'selected':'' ?>>École Primaire</option>
                        <option value="secondaire" <?= $filterLevel==='secondaire'?'selected':'' ?>>École Secondaire</option>
                        <option value="les_deux">Les deux cycles (Primaire &amp; Secondaire)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Domaine / Catégorie</label>
                    <input type="text" name="category" class="form-control" placeholder="ex: Langues, Sciences Exactes, Sciences Humaines..." list="category-suggestions">
                    <datalist id="category-suggestions">
                        <option value="Langues">
                        <option value="Sciences Exactes">
                        <option value="Sciences Naturelles">
                        <option value="Sciences Humaines">
                        <option value="Technologies">
                        <option value="Arts & Culture">
                        <option value="Sport & Santé">
                    </datalist>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCreateModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer le cours</button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL : MODIFIER UN COURS ────────────────────────────── -->
<div class="modal-overlay" id="editModal" style="display:none;">
    <div class="modal" style="max-width:500px;">
        <div class="modal-header">
            <h3>✏️ Modifier le Cours</h3>
            <button class="modal-close" onclick="closeEditModal()">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_subject">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Intitulé du cours *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Cycle d'enseignement *</label>
                    <select name="school_level" id="edit_school_level" class="form-control" required>
                        <option value="primaire">École Primaire</option>
                        <option value="secondaire">École Secondaire</option>
                        <option value="les_deux">Les deux cycles (Primaire &amp; Secondaire)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Domaine / Catégorie</label>
                    <input type="text" name="category" id="edit_category" class="form-control" list="category-suggestions">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}
function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
}
function openEditModal(id, name, level, category) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_school_level').value = level;
    document.getElementById('edit_category').value = category;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
// Close modals on click outside
window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('createModal')) closeCreateModal();
    if (e.target === document.getElementById('editModal')) closeEditModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

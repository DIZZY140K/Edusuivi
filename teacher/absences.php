<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('teacher');
$currentUser = getUser();

// ── Traitement AJAX : matières disponibles pour une classe ──────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'subjects_for_class') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $classId = intval($_GET['class_id']);
    
    // Vérifier si c'est une classe primaire dont le prof est titulaire (il a tous les cours)
    $chkPrim = $pdo->prepare("SELECT school_level, teacher_id FROM classes WHERE id = ?");
    $chkPrim->execute([$classId]);
    $cRow = $chkPrim->fetch();

    if ($cRow && $cRow['school_level'] === 'primaire' && $cRow['teacher_id'] == $currentUser['id']) {
        $stmt = $pdo->query("SELECT id, name FROM subjects WHERE school_level IN ('primaire', 'les_deux') ORDER BY name");
        echo json_encode($stmt->fetchAll());
    } else {
        $stmt = $pdo->prepare("
            SELECT s.id, s.name
            FROM teacher_assignments ta
            JOIN subjects s ON ta.subject_id = s.id
            WHERE ta.teacher_id = ? AND ta.class_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$currentUser['id'], $classId]);
        echo json_encode($stmt->fetchAll());
    }
    exit;
}

require_once __DIR__ . '/../includes/header.php';

// ── Traitement POST : enregistrer la séance ─────────────────────────────
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_session') {
    $classId   = intval($_POST['class_id']);
    $subjectId = intval($_POST['subject_id']);
    $date      = $_POST['date'] ?: date('Y-m-d');
    $absents   = $_POST['absents'] ?? []; // tableau des student_id absents

    // Vérifier que ce prof est bien affecté à cette classe/matière OU titulaire de la classe primaire
    $isAuthorized = false;
    $chkPrim = $pdo->prepare("SELECT school_level, teacher_id FROM classes WHERE id = ?");
    $chkPrim->execute([$classId]);
    $cRow = $chkPrim->fetch();
    if ($cRow && $cRow['school_level'] === 'primaire' && $cRow['teacher_id'] == $currentUser['id']) {
        $isAuthorized = true;
    } else {
        $check = $pdo->prepare("SELECT id FROM teacher_assignments WHERE teacher_id=? AND class_id=? AND subject_id=?");
        $check->execute([$currentUser['id'], $classId, $subjectId]);
        if ($check->fetch()) {
            $isAuthorized = true;
        }
    }

    if (!$isAuthorized) {
        $error = "Accès non autorisé pour cette classe/matière.";
    } else {
        // Récupérer tous les élèves de la classe
        $allStudents = $pdo->prepare("SELECT id FROM students WHERE class_id=?");
        $allStudents->execute([$classId]);
        $allIds = array_column($allStudents->fetchAll(), 'id');

        $stmtIns = $pdo->prepare("
            INSERT INTO absences (student_id, subject_id, date, justified, reason, recorded_by)
            VALUES (?, ?, ?, 0, NULL, ?)
            ON DUPLICATE KEY UPDATE recorded_by = VALUES(recorded_by)
        ");
        $stmtDel = $pdo->prepare("
            DELETE FROM absences WHERE student_id=? AND subject_id=? AND date=?
        ");

        foreach ($allIds as $sid) {
            if (in_array($sid, array_map('intval', $absents))) {
                // Marquer absent
                $stmtIns->execute([$sid, $subjectId, $date, $currentUser['id']]);
            } else {
                // Marquer présent = supprimer l'absence si elle existait
                $stmtDel->execute([$sid, $subjectId, $date]);
            }
        }

        // Notification au titulaire si des absents
        if (!empty($absents)) {
            try {
                $titular = $pdo->prepare("SELECT teacher_id FROM classes WHERE id=? AND teacher_id IS NOT NULL");
                $titular->execute([$classId]);
                $titularId = $titular->fetchColumn();
                if ($titularId && $titularId != $currentUser['id']) {
                    $subName = $pdo->prepare("SELECT name FROM subjects WHERE id=?");
                    $subName->execute([$subjectId]);
                    $subName = $subName->fetchColumn();
                    $pdo->prepare("INSERT INTO notifications (user_id,type,title,body,link) VALUES (?,?,?,?,?)")
                        ->execute([
                            $titularId, 'absence',
                            '📋 Absences signalées',
                            count($absents) . ' absence(s) en ' . $subName . ' le ' . date('d/m/Y', strtotime($date)),
                            '/edusuivi/teacher/titulaire.php'
                        ]);
                }
            } catch(Exception $e) {}
        }

        $success = count($absents) . " absence(s) enregistrée(s) pour cette séance.";
    }
}

// ── Données : classes du prof ───────────────────────────────────────────
// 1. Classes issues d'affectations secondaires
$stmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.name, c.school_level
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.id
    WHERE ta.teacher_id = ?
    ORDER BY c.level, c.name
");
$stmt->execute([$currentUser['id']]);
$myClasses = $stmt->fetchAll();

// 2. Classes primaires dont l'enseignant est le titulaire
$stmtPrim = $pdo->prepare("SELECT id, name, school_level FROM classes WHERE teacher_id = ? AND school_level = 'primaire' ORDER BY level, name");
$stmtPrim->execute([$currentUser['id']]);
$primClasses = $stmtPrim->fetchAll();
foreach ($primClasses as $pc) {
    if (!in_array($pc['id'], array_column($myClasses, 'id'))) {
        $myClasses[] = $pc;
    }
}

$selectedClassId   = intval($_GET['class_id']   ?? $_POST['class_id']   ?? ($myClasses[0]['id'] ?? 0));
$selectedSubjectId = intval($_GET['subject_id']  ?? $_POST['subject_id'] ?? 0);
$selectedDate      = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');

// Matières du prof pour la classe sélectionnée
$mySubjects = [];
if ($selectedClassId) {
    $chkPrim = $pdo->prepare("SELECT school_level, teacher_id FROM classes WHERE id = ?");
    $chkPrim->execute([$selectedClassId]);
    $cRow = $chkPrim->fetch();

    if ($cRow && $cRow['school_level'] === 'primaire' && $cRow['teacher_id'] == $currentUser['id']) {
        $mySubjects = $pdo->query("SELECT id, name FROM subjects WHERE school_level IN ('primaire', 'les_deux') ORDER BY name")->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT s.id, s.name
            FROM teacher_assignments ta
            JOIN subjects s ON ta.subject_id = s.id
            WHERE ta.teacher_id = ? AND ta.class_id = ?
            ORDER BY s.name
        ");
        $stmt->execute([$currentUser['id'], $selectedClassId]);
        $mySubjects = $stmt->fetchAll();
    }

    if (!$selectedSubjectId && !empty($mySubjects)) {
        $selectedSubjectId = $mySubjects[0]['id'];
    }
}

// Élèves de la classe + leur statut d'absence pour cette séance
$students = [];
$absentIds = [];
if ($selectedClassId && $selectedSubjectId) {
    $stmt = $pdo->prepare("
        SELECT s.id, s.first_name, s.last_name, s.matricule,
               a.id as absence_id
        FROM students s
        LEFT JOIN absences a ON a.student_id = s.id
            AND a.subject_id = ? AND a.date = ?
        WHERE s.class_id = ?
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute([$selectedSubjectId, $selectedDate, $selectedClassId]);
    $students = $stmt->fetchAll();
    $absentIds = array_column(array_filter($students, fn($s) => $s['absence_id']), 'id');
}

// Historique des 30 dernières absences enregistrées par ce prof
$history = $pdo->prepare("
    SELECT a.*, CONCAT(s.first_name,' ',s.last_name) as student_name,
           c.name as class_name, sub.name as subject_name
    FROM absences a
    JOIN students s ON a.student_id = s.id
    JOIN classes c ON s.class_id = c.id
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    WHERE a.recorded_by = ?
    ORDER BY a.date DESC, a.created_at DESC LIMIT 30
");
$history->execute([$currentUser['id']]);
$recentAbs = $history->fetchAll();
?>

<div class="page-header">
    <div>
        <h2>📋 Présences &amp; Absences</h2>
        <p>Enregistrez les présences par séance de cours</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success" style="display:flex;align-items:center;gap:10px;">
    ✓ <?= escape($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger">⚠️ <?= escape($error) ?></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 1.4fr; gap:20px; align-items:start;">

    <!-- ── Panneau de saisie ─────────────────────────────── -->
    <div class="card" style="position:sticky; top:20px;">
        <div style="padding:20px 24px 16px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:12px;">
            <span style="width:38px;height:38px;background:linear-gradient(135deg,#6366f1,#818cf8);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;">✏️</span>
            <div>
                <div style="font-weight:700; font-size:15px;">Nouvelle séance</div>
                <div style="font-size:12px; color:var(--text-muted);">Cochez les élèves absents</div>
            </div>
        </div>

        <form method="POST" id="sessionForm">
            <input type="hidden" name="action" value="save_session">
            <div style="padding:16px 20px; display:flex; flex-direction:column; gap:14px;">

                <!-- Classe -->
                <div class="form-group" style="margin:0;">
                    <label>🏫 Classe *</label>
                    <select name="class_id" id="classSelect" class="form-control" onchange="loadSubjects(this.value)" required>
                        <option value="">— Choisir une classe —</option>
                        <?php foreach ($myClasses as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedClassId == $c['id'] ? 'selected' : '' ?>>
                            <?= escape($c['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Matière (dynamique) -->
                <div class="form-group" style="margin:0;">
                    <label>📚 Matière *</label>
                    <select name="subject_id" id="subjectSelect" class="form-control" required>
                        <option value="">— Choisir d'abord une classe —</option>
                        <?php foreach ($mySubjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selectedSubjectId == $s['id'] ? 'selected' : '' ?>>
                            <?= escape($s['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date -->
                <div class="form-group" style="margin:0;">
                    <label>📅 Date du cours *</label>
                    <input type="date" name="date" id="dateInput" class="form-control"
                           value="<?= escape($selectedDate) ?>"
                           onchange="reloadStudents()">
                </div>

                <!-- Liste des élèves -->
                <div id="studentListSection" style="<?= empty($students) ? 'display:none;' : '' ?>">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <label style="margin:0; font-weight:700;">👥 Élèves</label>
                        <div style="display:flex; gap:6px;">
                            <button type="button" onclick="markAll(false)" style="font-size:11px; padding:3px 10px; border-radius:6px; border:1px solid rgba(16,185,129,0.3); background:rgba(16,185,129,0.08); color:#10b981; cursor:pointer;">✓ Tous présents</button>
                            <button type="button" onclick="markAll(true)" style="font-size:11px; padding:3px 10px; border-radius:6px; border:1px solid rgba(239,68,68,0.3); background:rgba(239,68,68,0.08); color:#ef4444; cursor:pointer;">✕ Tous absents</button>
                        </div>
                    </div>

                    <div id="studentList" style="display:flex; flex-direction:column; gap:6px; max-height:320px; overflow-y:auto;">
                        <?php foreach ($students as $s):
                            $isAbsent = in_array($s['id'], $absentIds);
                        ?>
                        <label class="student-row <?= $isAbsent ? 'absent' : 'present' ?>"
                               id="row_<?= $s['id'] ?>"
                               style="display:flex; align-items:center; gap:10px; padding:9px 12px; border-radius:8px; cursor:pointer; border:1px solid var(--border); transition:all .15s;
                               <?= $isAbsent ? 'background:rgba(239,68,68,0.08);border-color:rgba(239,68,68,0.3);' : 'background:rgba(16,185,129,0.05);border-color:rgba(16,185,129,0.2);' ?>">
                            <input type="checkbox" name="absents[]" value="<?= $s['id'] ?>"
                                   <?= $isAbsent ? 'checked' : '' ?>
                                   onchange="toggleRow(this, <?= $s['id'] ?>)"
                                   style="width:16px; height:16px; accent-color:#ef4444;">
                            <span style="flex:1; font-size:13px; font-weight:500;"><?= escape($s['last_name'] . ' ' . $s['first_name']) ?></span>
                            <span id="status_<?= $s['id'] ?>" style="font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px;
                                <?= $isAbsent ? 'background:rgba(239,68,68,0.15);color:#ef4444;' : 'background:rgba(16,185,129,0.15);color:#10b981;' ?>">
                                <?= $isAbsent ? 'Absent' : 'Présent' ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top:8px; font-size:11px; color:var(--text-muted); text-align:right;">
                        <span id="absentCount"><?= count($absentIds) ?></span> absent(s) /
                        <span><?= count($students) ?></span> élèves
                    </div>
                </div>

                <?php if (empty($students) && $selectedClassId && $selectedSubjectId): ?>
                <div style="text-align:center; color:var(--text-muted); font-size:13px; padding:12px;">
                    Aucun élève dans cette classe.
                </div>
                <?php elseif (empty($students)): ?>
                <div style="text-align:center; color:var(--text-muted); font-size:13px; padding:12px; border:1px dashed var(--border); border-radius:8px;">
                    👉 Sélectionnez une classe et une matière pour afficher les élèves
                </div>
                <?php endif; ?>

            </div>

            <?php if (!empty($students)): ?>
            <div style="padding:12px 20px; border-top:1px solid var(--border);">
                <button type="submit" class="btn btn-primary" style="width:100%; font-size:14px;">
                    💾 Enregistrer la séance
                </button>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ── Historique ────────────────────────────────────── -->
    <div class="card">
        <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px;">
            <span style="font-size:16px;">📋</span>
            <div style="font-weight:700; font-size:15px;">Mes dernières absences enregistrées</div>
        </div>

        <?php if (empty($recentAbs)): ?>
        <div style="padding:40px; text-align:center; color:var(--text-muted);">
            <div style="font-size:40px; margin-bottom:12px;">✅</div>
            Aucune absence enregistrée pour l'instant.
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%;">
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th>Classe</th>
                        <th>Matière</th>
                        <th>Date</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentAbs as $a): ?>
                <tr>
                    <td style="font-weight:600;"><?= escape($a['student_name']) ?></td>
                    <td style="font-size:12px; color:var(--text-muted);"><?= escape($a['class_name'] ?? '—') ?></td>
                    <td style="font-size:12px;">
                        <span style="background:rgba(99,102,241,0.12); color:#818cf8; padding:2px 8px; border-radius:8px; font-size:11px;">
                            <?= escape($a['subject_name'] ?? '—') ?>
                        </span>
                    </td>
                    <td style="font-size:12px;"><?= date('d/m/Y', strtotime($a['date'])) ?></td>
                    <td>
                        <span style="font-size:11px; font-weight:700; padding:3px 9px; border-radius:10px;
                            <?= $a['justified'] ? 'background:rgba(16,185,129,0.15);color:#10b981;' : 'background:rgba(239,68,68,0.12);color:#ef4444;' ?>">
                            <?= $a['justified'] ? '✓ Justifiée' : '✕ Injustifiée' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function loadSubjects(classId) {
    const sel = document.getElementById('subjectSelect');
    sel.innerHTML = '<option value="">Chargement...</option>';
    if (!classId) { sel.innerHTML = '<option value="">— Choisir d\'abord une classe —</option>'; return; }

    fetch(`?ajax=subjects_for_class&class_id=${classId}`)
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">— Choisir la matière —</option>';
            data.forEach(s => {
                sel.innerHTML += `<option value="${s.id}">${s.name}</option>`;
            });
            if (data.length === 1) { sel.value = data[0].id; reloadStudents(); }
        });
    document.getElementById('studentListSection').style.display = 'none';
}

function reloadStudents() {
    const classId   = document.getElementById('classSelect').value;
    const subjectId = document.getElementById('subjectSelect').value;
    const date      = document.getElementById('dateInput').value;
    if (classId && subjectId && date) {
        window.location = `?class_id=${classId}&subject_id=${subjectId}&date=${date}`;
    }
}

document.getElementById('subjectSelect').addEventListener('change', reloadStudents);

function toggleRow(cb, id) {
    const row    = document.getElementById('row_' + id);
    const status = document.getElementById('status_' + id);
    if (cb.checked) {
        row.style.background    = 'rgba(239,68,68,0.08)';
        row.style.borderColor   = 'rgba(239,68,68,0.3)';
        status.style.background = 'rgba(239,68,68,0.15)';
        status.style.color      = '#ef4444';
        status.textContent      = 'Absent';
    } else {
        row.style.background    = 'rgba(16,185,129,0.05)';
        row.style.borderColor   = 'rgba(16,185,129,0.2)';
        status.style.background = 'rgba(16,185,129,0.15)';
        status.style.color      = '#10b981';
        status.textContent      = 'Présent';
    }
    updateCount();
}

function markAll(absent) {
    document.querySelectorAll('[name="absents[]"]').forEach(cb => {
        cb.checked = absent;
        toggleRow(cb, cb.value);
    });
}

function updateCount() {
    const total = document.querySelectorAll('[name="absents[]"]').length;
    const checked = document.querySelectorAll('[name="absents[]"]:checked').length;
    document.getElementById('absentCount').textContent = checked;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

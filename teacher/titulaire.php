<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('teacher');
$currentUser = getUser();

// Vérifier que ce prof est bien titulaire d'une classe
$titularClass = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ? LIMIT 1");
$titularClass->execute([$currentUser['id']]);
$myClass = $titularClass->fetch();

// ── AJAX : justifier une absence ─────────────────────────────────────────
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'justify') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    if (!$myClass) {
        echo json_encode(['ok' => false, 'error' => 'Non titulaire']);
        exit;
    }
    $absId  = intval($_POST['abs_id']);
    $reason = trim($_POST['reason'] ?? '');
    $pdo->prepare("UPDATE absences SET justified=1, reason=? WHERE id=? AND student_id IN (SELECT id FROM students WHERE class_id=?)")
        ->execute([$reason, $absId, $myClass['id']]);
    echo json_encode(['ok' => true]);
    exit;
}

require_once __DIR__ . '/../includes/header.php';

if (!$myClass) {
    echo '<div class="main-content"><div class="card" style="padding:48px;text-align:center;">
        <div style="font-size:48px;margin-bottom:16px;">🏫</div>
        <h3>Vous n\'êtes pas titulaire d\'une classe</h3>
        <p style="color:var(--text-muted);margin-top:8px;">Contactez l\'administrateur pour être affecté comme titulaire.</p>
    </div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ── Données : élèves de ma classe + stats absences ────────────────────────
$students = $pdo->prepare("
    SELECT s.id, s.first_name, s.last_name, s.matricule,
           COUNT(a.id)                                     as total_abs,
           SUM(CASE WHEN a.justified=1 THEN 1 ELSE 0 END) as justified_abs,
           SUM(CASE WHEN a.justified=0 THEN 1 ELSE 0 END) as unjust_abs
    FROM students s
    LEFT JOIN absences a ON a.student_id = s.id
    WHERE s.class_id = ?
    GROUP BY s.id
    ORDER BY unjust_abs DESC, s.last_name
");
$students->execute([$myClass['id']]);
$students = $students->fetchAll();

// Absences du jour dans ma classe (toutes matières)
$today = date('Y-m-d');
$todayAbs = $pdo->prepare("
    SELECT a.*, CONCAT(s.first_name,' ',s.last_name) as student_name,
           sub.name as subject_name
    FROM absences a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    WHERE s.class_id = ? AND a.date = ?
    ORDER BY sub.name, s.last_name
");
$todayAbs->execute([$myClass['id'], $today]);
$todayAbsences = $todayAbs->fetchAll();

// Historique des 60 derniers jours
$filter_student = intval($_GET['student'] ?? 0);
$histQuery = "
    SELECT a.*, CONCAT(s.first_name,' ',s.last_name) as student_name,
           sub.name as subject_name,
           u.name as recorded_by_name
    FROM absences a
    JOIN students s ON a.student_id = s.id
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    LEFT JOIN users u ON a.recorded_by = u.id
    WHERE s.class_id = ?
    " . ($filter_student ? "AND a.student_id = $filter_student" : "") . "
    ORDER BY a.date DESC, s.last_name
    LIMIT 100
";
$histStmt = $pdo->prepare($histQuery);
$histStmt->execute([$myClass['id']]);
$history = $histStmt->fetchAll();

// Nb d'élèves avec 3+ absences injustifiées
$alertCount = count(array_filter($students, fn($s) => $s['unjust_abs'] >= 3));
?>

<div class="page-header">
    <div>
        <h2>🏫 Ma classe — <?= escape($myClass['name']) ?></h2>
        <p>Suivi des absences · Titulaire : <?= escape($currentUser['name']) ?></p>
    </div>
    <a href="/edusuivi/teacher/absences.php" class="btn btn-primary">📝 Enregistrer une séance</a>
</div>

<!-- ── Statistiques rapides ──────────────────────────────────────── -->
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px;">
    <div class="card" style="padding:18px; text-align:center;">
        <div style="font-size:28px; font-weight:800; color:var(--primary);"><?= count($students) ?></div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Élèves</div>
    </div>
    <div class="card" style="padding:18px; text-align:center;">
        <div style="font-size:28px; font-weight:800; color:#ef4444;"><?= count($todayAbsences) ?></div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Absences aujourd'hui</div>
    </div>
    <div class="card" style="padding:18px; text-align:center;">
        <div style="font-size:28px; font-weight:800; color:#f59e0b;"><?= $alertCount ?></div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Élèves à risque (≥3 inj.)</div>
    </div>
    <div class="card" style="padding:18px; text-align:center;">
        <div style="font-size:28px; font-weight:800; color:#10b981;"><?= array_sum(array_column($students,'justified_abs')) ?></div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">Absences justifiées</div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1.3fr; gap:20px; align-items:start;">

    <!-- ── Tableau des élèves ──────────────────────────────── -->
    <div class="card">
        <div style="padding:14px 20px; border-bottom:1px solid var(--border); font-weight:700;">👥 Suivi par élève</div>
        <div style="overflow-y:auto; max-height:480px;">
            <table style="width:100%;">
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th style="text-align:center;">Total</th>
                        <th style="text-align:center;">Justif.</th>
                        <th style="text-align:center;">Injust.</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $s):
                    $risk = $s['unjust_abs'] >= 3;
                ?>
                <tr style="<?= $risk ? 'background:rgba(239,68,68,0.04);' : '' ?>">
                    <td>
                        <a href="?student=<?= $s['id'] ?>" style="font-weight:<?= $filter_student == $s['id'] ? '800' : '600' ?>; color:var(--text); text-decoration:none;">
                            <?= escape($s['last_name'] . ' ' . $s['first_name']) ?>
                        </a>
                        <?php if ($risk): ?>
                        <span style="font-size:10px; background:rgba(239,68,68,0.15); color:#ef4444; padding:1px 6px; border-radius:8px; margin-left:4px;">⚠️ À risque</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; font-weight:700;"><?= $s['total_abs'] ?></td>
                    <td style="text-align:center; color:#10b981; font-weight:600;"><?= $s['justified_abs'] ?></td>
                    <td style="text-align:center; color:<?= $s['unjust_abs'] > 0 ? '#ef4444' : 'var(--text-muted)' ?>; font-weight:700;"><?= $s['unjust_abs'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($filter_student): ?>
        <div style="padding:10px 20px; border-top:1px solid var(--border);">
            <a href="?" style="font-size:12px; color:var(--primary);">← Voir tous les élèves</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Panneau droite : Aujourd'hui + Historique ────────── -->
    <div style="display:flex; flex-direction:column; gap:16px;">

        <!-- Absences du jour -->
        <div class="card">
            <div style="padding:14px 20px; border-bottom:1px solid var(--border); font-weight:700;">
                📅 Aujourd'hui — <?= date('d/m/Y') ?>
                <?php if (!empty($todayAbsences)): ?>
                <span style="font-size:11px; background:rgba(239,68,68,0.15); color:#ef4444; padding:2px 8px; border-radius:10px; margin-left:8px;"><?= count($todayAbsences) ?> absence(s)</span>
                <?php endif; ?>
            </div>
            <?php if (empty($todayAbsences)): ?>
            <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:13px;">
                🎉 Aucune absence signalée aujourd'hui
            </div>
            <?php else: ?>
            <div style="padding:12px 16px; display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($todayAbsences as $a): ?>
                <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 12px; background:rgba(239,68,68,0.05); border:1px solid rgba(239,68,68,0.15); border-radius:8px;">
                    <div>
                        <div style="font-weight:600; font-size:13px;"><?= escape($a['student_name']) ?></div>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                            <?= escape($a['subject_name'] ?? 'Matière non précisée') ?>
                        </div>
                    </div>
                    <?php if (!$a['justified']): ?>
                    <button onclick="openJustify(<?= $a['id'] ?>, '<?= escape($a['student_name']) ?>')"
                            style="font-size:11px; padding:4px 10px; border-radius:6px; border:1px solid rgba(16,185,129,0.3); background:rgba(16,185,129,0.08); color:#10b981; cursor:pointer;">
                        ✅ Justifier
                    </button>
                    <?php else: ?>
                    <span style="font-size:11px; color:#10b981; font-weight:600;">✅ Justifiée</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Historique -->
        <div class="card">
            <div style="padding:14px 20px; border-bottom:1px solid var(--border); font-weight:700;">
                📜 Historique <?= $filter_student ? '— ' . escape($students[array_search($filter_student, array_column($students,'id'))]['last_name'] ?? '') : '' ?>
            </div>
            <?php if (empty($history)): ?>
            <div style="padding:24px; text-align:center; color:var(--text-muted);">Aucune absence enregistrée.</div>
            <?php else: ?>
            <div style="overflow-x:auto; max-height:360px; overflow-y:auto;">
                <table style="width:100%;">
                    <thead>
                        <tr>
                            <th>Élève</th>
                            <th>Matière</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $a): ?>
                    <tr id="absrow_<?= $a['id'] ?>">
                        <td style="font-size:12px; font-weight:600;"><?= escape($a['student_name']) ?></td>
                        <td>
                            <span style="font-size:11px; background:rgba(99,102,241,0.1); color:#818cf8; padding:2px 7px; border-radius:8px;">
                                <?= escape($a['subject_name'] ?? '—') ?>
                            </span>
                        </td>
                        <td style="font-size:12px;"><?= date('d/m/Y', strtotime($a['date'])) ?></td>
                        <td id="status_<?= $a['id'] ?>">
                            <span style="font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px;
                                <?= $a['justified'] ? 'background:rgba(16,185,129,0.12);color:#10b981;' : 'background:rgba(239,68,68,0.12);color:#ef4444;' ?>">
                                <?= $a['justified'] ? '✅ Justifiée' : '⚠️ Injustifiée' ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!$a['justified']): ?>
                            <button onclick="openJustify(<?= $a['id'] ?>, '<?= escape($a['student_name']) ?>')"
                                    style="font-size:10px; padding:3px 8px; border-radius:6px; border:1px solid rgba(16,185,129,0.3); background:none; color:#10b981; cursor:pointer;">
                                Justifier
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Modal Justification ─────────────────────────────────────── -->
<div class="modal-overlay" id="justifyModal">
    <div class="modal" style="max-width:400px;">
        <button class="modal-close-btn" onclick="closeModal('justifyModal')">✕</button>
        <h3 style="padding:18px 22px 14px; font-size:16px; border-bottom:1px solid var(--border);">
            ✅ Justifier une absence
        </h3>
        <div style="padding:18px 22px;">
            <p style="margin-bottom:14px; font-size:14px;">
                Absence de <strong id="justifyStudentName"></strong>
            </p>
            <div class="form-group">
                <label>Motif de justification</label>
                <textarea id="justifyReason" class="form-control" rows="3"
                          placeholder="ex: Maladie, Permission parentale, Deuil..."></textarea>
            </div>
        </div>
        <div style="padding:12px 22px; border-top:1px solid var(--border); display:flex; gap:10px;">
            <button class="btn btn-outline" onclick="closeModal('justifyModal')" style="flex:1;">Annuler</button>
            <button class="btn btn-primary" onclick="submitJustify()" style="flex:1;">✅ Valider</button>
        </div>
    </div>
</div>

<script>
let justifyAbsId = null;

function openJustify(absId, studentName) {
    justifyAbsId = absId;
    document.getElementById('justifyStudentName').textContent = studentName;
    document.getElementById('justifyReason').value = '';
    openModal('justifyModal');
}

function submitJustify() {
    const reason = document.getElementById('justifyReason').value.trim();
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax_action=justify&abs_id=${justifyAbsId}&reason=${encodeURIComponent(reason)}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            // Mettre à jour le statut visuellement
            const statusEl = document.getElementById('status_' + justifyAbsId);
            if (statusEl) {
                statusEl.innerHTML = '<span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;background:rgba(16,185,129,0.12);color:#10b981;">✅ Justifiée</span>';
            }
            closeModal('justifyModal');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

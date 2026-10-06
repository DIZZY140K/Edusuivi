<?php require_once __DIR__ . '/../includes/header.php'; requireRole('teacher');

$success = $error = '';
$school_year = '2024-2025';

// ── Mes affectations ─────────────────────────────────────────
// 1. Affectations secondaires (cours par cours)
$stmt = $pdo->prepare("
    SELECT ta.id, ta.class_id, ta.subject_id, c.name as class_name, c.school_level, sub.name as subject_name
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.id
    JOIN subjects sub ON ta.subject_id = sub.id
    WHERE ta.teacher_id = ?
    ORDER BY c.name, sub.name
");
$stmt->execute([$currentUser['id']]);
$assignments = $stmt->fetchAll();

// 2. Classes primaires dont l'enseignant est titulaire (gère toute la classe et tous les cours !)
$stmtPrimClasses = $pdo->prepare("SELECT id, name, school_level FROM classes WHERE teacher_id = ? AND school_level = 'primaire' ORDER BY name");
$stmtPrimClasses->execute([$currentUser['id']]);
$primClasses = $stmtPrimClasses->fetchAll();

if (!empty($primClasses)) {
    $primSubs = $pdo->query("SELECT id, name FROM subjects WHERE school_level IN ('primaire', 'les_deux') ORDER BY name")->fetchAll();
    foreach ($primClasses as $pc) {
        foreach ($primSubs as $ps) {
            $already = false;
            foreach ($assignments as $existing) {
                if ($existing['class_id'] == $pc['id'] && $existing['subject_id'] == $ps['id']) {
                    $already = true; break;
                }
            }
            if (!$already) {
                $assignments[] = [
                    'id'           => 0,
                    'class_id'     => $pc['id'],
                    'subject_id'   => $ps['id'],
                    'class_name'   => $pc['name'],
                    'school_level' => 'primaire',
                    'subject_name' => $ps['name']
                ];
            }
        }
    }
}

$selectedClass   = intval($_GET['class_id'] ?? ($assignments[0]['class_id'] ?? 0));
$selectedSubject = intval($_GET['subject_id'] ?? ($assignments[0]['subject_id'] ?? 0));

// Trouver l'affectation active et déterminer le cycle
$currentAssignment = null;
foreach ($assignments as $a) {
    if ($a['class_id'] == $selectedClass && $a['subject_id'] == $selectedSubject) {
        $currentAssignment = $a;
        break;
    }
}
if (!$currentAssignment && !empty($assignments)) {
    $currentAssignment = $assignments[0];
    $selectedClass = $currentAssignment['class_id'];
    $selectedSubject = $currentAssignment['subject_id'];
}

$isPrimary = ($currentAssignment['school_level'] ?? 'secondaire') === 'primaire';

// Périodes selon le cycle (Système officiel RDC)
if ($isPrimary) {
    $periods = [
        '1ère Période', '2ème Période', 'Examen 1er Trimestre',
        '3ème Période', '4ème Période', 'Examen 2ème Trimestre',
        '5ème Période', '6ème Période', 'Examen 3ème Trimestre'
    ];
} else {
    $periods = [
        '1ère Période', '2ème Période', 'Examen 1er Semestre',
        '3ème Période', '4ème Période', 'Examen 2ème Semestre'
    ];
}

$selectedPeriod = $_GET['period'] ?? $periods[0];
if (!in_array($selectedPeriod, $periods)) {
    $selectedPeriod = $periods[0];
}

// ── Maxima de la période (stocké dans period_maxima) ────────
$defaultPeriodMax = (strpos($selectedPeriod, 'Examen') !== false) ? 40.0 : 20.0;
$pmStmt = $pdo->prepare("SELECT max_grade FROM period_maxima WHERE class_id=? AND subject_id=? AND period=?");
$pmStmt->execute([$selectedClass, $selectedSubject, $selectedPeriod]);
$periodMaxGrade = $pmStmt->fetchColumn();
if ($periodMaxGrade === false || $periodMaxGrade === null) {
    $periodMaxGrade = $defaultPeriodMax;
} else {
    $periodMaxGrade = floatval($periodMaxGrade);
}

// ── TRAITEMENT DES ACTIONS ──────────────────────────────────

// 1. Définir / modifier le Maxima de la période
if (($_POST['action'] ?? '') === 'save_period_max') {
    $newMax = floatval($_POST['period_max_grade'] ?? 20);
    if ($newMax > 0) {
        $stmtMax = $pdo->prepare("INSERT INTO period_maxima (class_id, subject_id, period, max_grade) 
            VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE max_grade = VALUES(max_grade)");
        $stmtMax->execute([$selectedClass, $selectedSubject, $selectedPeriod, $newMax]);
        $periodMaxGrade = $newMax;
        
        // Recalculer et synchroniser les notes de la période
        syncPeriodGrades($pdo, $selectedClass, $selectedSubject, $selectedPeriod, $periodMaxGrade, $currentUser['id']);
        $success = "Maxima de la période défini à $newMax pts et notes synchronisées.";
    }
}

// 2. Ajouter une évaluation élémentaire (Devoir, Interro, Exercice, Examen)
if (($_POST['action'] ?? '') === 'add_evaluation') {
    $evalType  = $_POST['evaluation_type'] ?? 'interrogation';
    $evalTitle = trim($_POST['title'] ?? '');
    if (!$evalTitle) {
        $evalLabels = ['devoir'=>'Devoir', 'interrogation'=>'Interrogation', 'exercice'=>'Exercice', 'examen'=>'Examen'];
        $evalTitle = ($evalLabels[$evalType] ?? 'Évaluation') . ' du ' . date('d/m/Y');
    }
    $evalMax   = floatval($_POST['max_score'] ?? 20);
    if ($evalMax <= 0) $evalMax = 20;
    $evalDate  = $_POST['eval_date'] ?: date('Y-m-d');
    $scores    = $_POST['scores'] ?? [];

    $count = 0;
    $stmtEval = $pdo->prepare("INSERT INTO evaluations (student_id, subject_id, class_id, teacher_id, evaluation_type, title, period, score, max_score, eval_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($scores as $sId => $val) {
        if ($val === '' || $val === null) continue;
        $score = floatval($val);
        $stmtEval->execute([intval($sId), $selectedSubject, $selectedClass, $currentUser['id'], $evalType, $evalTitle, $selectedPeriod, $score, $evalMax, $evalDate]);
        $count++;
    }

    // Recalculer et synchroniser automatiquement la note de période dans grades
    syncPeriodGrades($pdo, $selectedClass, $selectedSubject, $selectedPeriod, $periodMaxGrade, $currentUser['id']);
    $success = "Évaluation « $evalTitle » enregistrée pour $count élève(s). Notes de période recalculées automatiquement.";
}

// 3. Supprimer une évaluation
if (isset($_GET['delete_eval'])) {
    $evalTitle = $_GET['delete_eval'];
    $delStmt = $pdo->prepare("DELETE FROM evaluations WHERE class_id=? AND subject_id=? AND period=? AND title=?");
    $delStmt->execute([$selectedClass, $selectedSubject, $selectedPeriod, $evalTitle]);
    
    // Recalculer la période
    syncPeriodGrades($pdo, $selectedClass, $selectedSubject, $selectedPeriod, $periodMaxGrade, $currentUser['id']);
    $success = "Évaluation supprimée et notes de période recalculées.";
}

// 4. Saisie manuelle / directe des notes de période
if (($_POST['action'] ?? '') === 'save_grades') {
    $grades   = $_POST['grades'] ?? [];
    $comments = $_POST['comments'] ?? [];
    $max_grade = floatval($_POST['max_grade'] ?? $periodMaxGrade);

    $count = 0;
    foreach ($grades as $student_id => $val) {
        if ($val === '') continue;
        $grade = floatval($val);
        $comment = trim($comments[$student_id] ?? '');

        $check = $pdo->prepare("SELECT id FROM grades WHERE student_id=? AND subject_id=? AND period=?");
        $check->execute([$student_id, $selectedSubject, $selectedPeriod]);
        $existing = $check->fetchColumn();

        if ($existing) {
            $pdo->prepare("UPDATE grades SET grade=?, max_grade=?, comment=?, teacher_id=? WHERE id=?")
                ->execute([$grade, $max_grade, $comment, $currentUser['id'], $existing]);
        } else {
            $pdo->prepare("INSERT INTO grades (student_id, subject_id, teacher_id, grade, max_grade, period, comment) VALUES (?,?,?,?,?,?,?)")
                ->execute([$student_id, $selectedSubject, $currentUser['id'], $grade, $max_grade, $selectedPeriod, $comment]);
        }
        $count++;
    }
    $success = "$count note(s) enregistrée(s) avec succès.";
}

// ── FONCTION DE CONSOLIDATION / SYNCHRONISATION ──────────────
function syncPeriodGrades($pdo, $classId, $subjectId, $period, $periodMax, $teacherId) {
    // Récupérer les élèves de la classe
    $students = $pdo->prepare("SELECT id FROM students WHERE class_id=?");
    $students->execute([$classId]);
    $studentIds = $students->fetchAll(PDO::FETCH_COLUMN);

    foreach ($studentIds as $sId) {
        // Somme des scores et des max_scores pour cet élève sur cette période
        $stmt = $pdo->prepare("SELECT SUM(score) as total_score, SUM(max_score) as total_max FROM evaluations WHERE student_id=? AND subject_id=? AND period=?");
        $stmt->execute([$sId, $subjectId, $period]);
        $res = $stmt->fetch();

        if ($res && $res['total_max'] > 0) {
            // Note ramenée sur le maxima de la période
            $calcGrade = round(($res['total_score'] / $res['total_max']) * $periodMax, 2);

            // Vérifier si la note existe déjà dans grades
            $check = $pdo->prepare("SELECT id FROM grades WHERE student_id=? AND subject_id=? AND period=?");
            $check->execute([$sId, $subjectId, $period]);
            $existing = $check->fetchColumn();

            if ($existing) {
                $pdo->prepare("UPDATE grades SET grade=?, max_grade=?, teacher_id=? WHERE id=?")
                    ->execute([$calcGrade, $periodMax, $teacherId, $existing]);
            } else {
                $pdo->prepare("INSERT INTO grades (student_id, subject_id, teacher_id, grade, max_grade, period) VALUES (?,?,?,?,?,?)")
                    ->execute([$sId, $subjectId, $teacherId, $calcGrade, $periodMax, $period]);
            }
        }
    }
}

// ── CHARGEMENT DES DONNÉES POUR L'AFFICHAGE ──────────────────
$students = [];
$existingGrades = [];
$evaluationsList = [];
$evalsByStudent = [];

if ($selectedClass && $selectedSubject) {
    // Élèves
    $stmt = $pdo->prepare("SELECT * FROM students WHERE class_id = ? ORDER BY last_name, first_name");
    $stmt->execute([$selectedClass]);
    $students = $stmt->fetchAll();

    // Notes de période consolidées (table grades)
    $stmt = $pdo->prepare("SELECT * FROM grades WHERE subject_id=? AND period=? AND student_id IN (SELECT id FROM students WHERE class_id=?)");
    $stmt->execute([$selectedSubject, $selectedPeriod, $selectedClass]);
    foreach ($stmt->fetchAll() as $g) {
        $existingGrades[$g['student_id']] = $g;
    }

    // Évaluations distinctes pour cette classe/matière/période
    $stmtEvals = $pdo->prepare("
        SELECT evaluation_type, title, max_score, eval_date, COUNT(*) as count_scores
        FROM evaluations
        WHERE class_id=? AND subject_id=? AND period=?
        GROUP BY evaluation_type, title, max_score, eval_date
        ORDER BY eval_date ASC, id ASC
    ");
    $stmtEvals->execute([$selectedClass, $selectedSubject, $selectedPeriod]);
    $evaluationsList = $stmtEvals->fetchAll();

    // Toutes les notes d'évaluations individuelles
    $stmtAllScores = $pdo->prepare("
        SELECT student_id, title, score, max_score, evaluation_type
        FROM evaluations
        WHERE class_id=? AND subject_id=? AND period=?
    ");
    $stmtAllScores->execute([$selectedClass, $selectedSubject, $selectedPeriod]);
    foreach ($stmtAllScores->fetchAll() as $row) {
        $evalsByStudent[$row['student_id']][$row['title']] = $row;
    }
}
?>

<div class="page-header">
    <div>
        <h2>📝 Saisie des Notes &amp; Évaluations</h2>
        <p>
            Gestion des évaluations (Devoirs, Interros, Exercices, Examens) &bull;
            Mode <?= $isPrimary ? '🏫 Primaire (Trimestres / 6 Périodes)' : '🎓 Secondaire (Semestres / 4 Périodes)' ?>
        </p>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success">✓ <?= escape($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger">⚠️ <?= escape($error) ?></div><?php endif; ?>

<!-- ── Sélecteur de Classe / Matière / Période ───────────────── -->
<div class="card" style="padding:18px;margin-bottom:24px;">
    <form method="GET" style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;" id="selectForm">
        <div class="form-group" style="flex:2;min-width:220px;margin:0;">
            <label>🏫 Classe &amp; Matière</label>
            <select name="class_id" class="form-control" onchange="updateSubject(this)">
                <?php foreach ($assignments as $a): ?>
                <option value="<?= $a['class_id'] ?>" data-sub="<?= $a['subject_id'] ?>" <?= ($selectedClass==$a['class_id'] && $selectedSubject==$a['subject_id'])?'selected':'' ?>>
                    <?= escape($a['class_name']) ?> — <?= escape($a['subject_name']) ?> (<?= $a['school_level'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="subject_id" id="hiddenSubject" value="<?= $selectedSubject ?>">
        
        <div class="form-group" style="flex:1.5;min-width:200px;margin:0;">
            <label>📅 Période académique</label>
            <select name="period" class="form-control" onchange="document.getElementById('selectForm').submit()">
                <?php foreach($periods as $p): ?>
                <option value="<?= $p ?>" <?= $selectedPeriod===$p?'selected':'' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Afficher</button>
    </form>
</div>

<?php if (empty($students)): ?>
<div class="card" style="padding:40px;text-align:center;color:var(--text-muted);">
    <div style="font-size:40px;margin-bottom:10px;">👨‍🎓</div>
    Aucun élève trouvé dans cette classe.
</div>
<?php else: ?>

<!-- ── Bandeau Maxima de la Période ──────────────────────────── -->
<div class="card" style="padding:16px 20px;margin-bottom:20px;background:linear-gradient(135deg,rgba(99,102,241,.08),rgba(129,140,248,.04));border:1.5px solid rgba(99,102,241,.25);border-radius:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
    <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--primary);margin-bottom:2px;">Barème officiel du bulletin</div>
        <div style="font-size:16px;font-weight:800;color:var(--text);">
            Maxima de la période (<?= escape($selectedPeriod) ?>) : <span style="color:#10b981;"><?= $periodMaxGrade ?> pts</span>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
            La sommation des devoirs, interros et exercices sera automatiquement ramenée sur ce maxima.
        </div>
    </div>
    <form method="POST" style="display:flex;align-items:center;gap:10px;margin:0;">
        <input type="hidden" name="action" value="save_period_max">
        <label style="margin:0;font-size:13px;font-weight:600;">Modifier le Maxima :</label>
        <input type="number" name="period_max_grade" class="form-control" value="<?= $periodMaxGrade ?>" style="width:90px;text-align:center;font-weight:700;" min="1" step="0.5" required>
        <button type="submit" class="btn btn-sm btn-primary">💾 Appliquer</button>
    </form>
</div>

<!-- ── Boutons d'action rapides ──────────────────────────────── -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
    <div style="display:flex;gap:8px;align-items:center;">
        <span class="badge badge-primary"><?= count($students) ?> élève(s)</span>
        <span class="badge badge-success"><?= count($evaluationsList) ?> évaluation(s) saisie(s)</span>
    </div>
    <button class="btn btn-primary" onclick="openModal('addEvalModal')">
        + Nouvelle Évaluation (Devoir, Interro, Examen…)
    </button>
</div>

<!-- ── Tableau Récapitulatif Complet ─────────────────────────── -->
<div class="card" style="padding:0;overflow:hidden;">
    <div class="card-header" style="padding:16px 20px;">
        <h3>📊 Carnet de notes — <?= escape($selectedPeriod) ?></h3>
        <span style="font-size:12px;color:var(--text-muted);">
            Évaluations élémentaires &bull; Note de période pour le bulletin
        </span>
    </div>

    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th style="min-width:180px;">Élève</th>
                <?php foreach($evaluationsList as $ev): ?>
                <th style="text-align:center;min-width:120px;">
                    <div style="font-size:11px;">
                        <?php
                            $typeIcon = [
                                'devoir'        => '📝',
                                'interrogation' => '❓',
                                'exercice'      => '✏️',
                                'examen'        => '🎓'
                            ][$ev['evaluation_type']] ?? '📄';
                            echo $typeIcon . ' ' . escape($ev['title']);
                        ?>
                    </div>
                    <div style="font-size:10px;color:var(--primary);font-weight:700;">
                        sur <?= $ev['max_score'] ?>
                    </div>
                    <div style="margin-top:2px;">
                        <a href="?class_id=<?= $selectedClass ?>&subject_id=<?= $selectedSubject ?>&period=<?= urlencode($selectedPeriod) ?>&delete_eval=<?= urlencode($ev['title']) ?>"
                           style="color:#ef4444;font-size:10px;text-decoration:none;" onclick="return confirm('Supprimer cette évaluation ?')" title="Supprimer">🗑</a>
                    </div>
                </th>
                <?php endforeach; ?>
                <th style="text-align:center;min-width:110px;background:rgba(99,102,241,.06);">
                    Total Obtenu
                </th>
                <th style="text-align:center;min-width:130px;background:rgba(16,185,129,.1);color:#10b981;">
                    🎯 Note Période<br><small style="font-weight:600;">(sur <?= $periodMaxGrade ?>)</small>
                </th>
                <th style="text-align:center;min-width:80px;">Statut</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $s): 
            $sId = $s['id'];
            $totalScore = 0;
            $totalMax = 0;
            $hasEvals = false;

            $periodGrade = $existingGrades[$sId]['grade'] ?? null;
        ?>
        <tr>
            <td>
                <strong><?= escape($s['last_name'] . ' ' . $s['first_name']) ?></strong>
                <div style="font-size:11px;color:var(--text-muted);"><?= escape($s['matricule']??'') ?></div>
            </td>

            <!-- Colonnes des évaluations -->
            <?php foreach($evaluationsList as $ev): 
                $evData = $evalsByStudent[$sId][$ev['title']] ?? null;
                if ($evData !== null) {
                    $hasEvals = true;
                    $totalScore += floatval($evData['score']);
                    $totalMax += floatval($evData['max_score']);
                }
            ?>
            <td style="text-align:center;">
                <?php if ($evData !== null): ?>
                    <span style="font-weight:700;font-size:13px;"><?= $evData['score'] ?></span>
                <?php else: ?>
                    <span style="color:var(--text-muted);font-size:12px;">—</span>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>

            <!-- Total Obtenu -->
            <td style="text-align:center;background:rgba(99,102,241,.03);font-weight:700;">
                <?php if ($totalMax > 0): ?>
                    <?= $totalScore ?> / <?= $totalMax ?>
                <?php else: ?>
                    <span style="color:var(--text-muted);font-size:12px;">—</span>
                <?php endif; ?>
            </td>

            <!-- Note Finale de Période (sur Maxima Période) -->
            <td style="text-align:center;background:rgba(16,185,129,.04);">
                <?php if ($periodGrade !== null): ?>
                    <span style="font-size:16px;font-weight:800;color:#10b981;"><?= $periodGrade ?></span>
                    <span style="font-size:11px;color:var(--text-muted);">/ <?= $periodMaxGrade ?></span>
                <?php else: ?>
                    <span style="color:#ef4444;font-size:12px;font-weight:600;">Non coté</span>
                <?php endif; ?>
            </td>

            <!-- Statut pour le bulletin -->
            <td style="text-align:center;">
                <?php if ($periodGrade !== null): ?>
                    <span class="badge badge-success" style="font-size:10px;">Prêt bulletin</span>
                <?php else: ?>
                    <span class="badge badge-warning" style="font-size:10px;background:#f59e0b;color:#fff;">Incomplet</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- ── Saisie Directe / Manuelle de secours ──────────────────── -->
<div class="card" style="margin-top:24px;">
    <div class="card-header" onclick="document.getElementById('directGradeSection').style.display = document.getElementById('directGradeSection').style.display==='none'?'block':'none';" style="cursor:pointer;">
        <h3>✏️ Saisie manuelle directe des notes de période (Optionnelle)</h3>
        <span style="font-size:12px;color:var(--primary);font-weight:600;">Afficher / Masquer ▼</span>
    </div>
    <div id="directGradeSection" style="display:none;padding:16px 20px;">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">
            Vous pouvez ajuster manuellement la note globale de la période sans passer par les évaluations détaillées si nécessaire.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="save_grades">
            <input type="hidden" name="max_grade" value="<?= $periodMaxGrade ?>">
            <table>
                <thead><tr><th>Élève</th><th style="width:120px;">Note (sur <?= $periodMaxGrade ?>)</th><th>Appréciation</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): 
                    $g = $existingGrades[$s['id']] ?? null;
                ?>
                <tr>
                    <td><strong><?= escape($s['last_name'] . ' ' . $s['first_name']) ?></strong></td>
                    <td>
                        <input type="number" name="grades[<?= $s['id'] ?>]" class="form-control" step="0.25" min="0" max="<?= $periodMaxGrade ?>" value="<?= $g ? $g['grade'] : '' ?>" style="text-align:center;font-weight:700;">
                    </td>
                    <td>
                        <input type="text" name="comments[<?= $s['id'] ?>]" class="form-control" value="<?= escape($g['comment']??'') ?>" placeholder="Commentaire optionnel...">
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top:16px;text-align:right;">
                <button type="submit" class="btn btn-primary">💾 Enregistrer manuellement</button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<!-- ════ MODAL NOUVELLE ÉVALUATION ═════════════════════════════ -->
<div class="modal-overlay" id="addEvalModal">
    <div class="modal" style="max-width:620px;max-height:90vh;display:flex;flex-direction:column;">
        <button class="modal-close-btn" onclick="closeModal('addEvalModal')">✕</button>
        <div style="padding:16px 24px;border-bottom:1px solid var(--border);">
            <h3 style="margin:0;font-size:18px;display:flex;align-items:center;gap:10px;">
                <span>📝</span> Nouvelle Évaluation — <?= escape($selectedPeriod) ?>
            </h3>
            <p style="margin:4px 0 0;font-size:12px;color:var(--text-muted);">
                Ajoutez un devoir, une interrogation, un exercice ou un examen avec son barème.
            </p>
        </div>

        <form method="POST" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
            <input type="hidden" name="action" value="add_evaluation">
            <div style="padding:20px;overflow-y:auto;flex:1;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div class="form-group-sm">
                        <label>Type d'évaluation *</label>
                        <select name="evaluation_type" class="form-control-sm" required>
                            <option value="interrogation">❓ Interrogation</option>
                            <option value="devoir">📝 Devoir</option>
                            <option value="exercice">✏️ Exercice</option>
                            <option value="examen">🎓 Examen</option>
                        </select>
                    </div>
                    <div class="form-group-sm">
                        <label>Note Maximale (Barème) *</label>
                        <input type="number" name="max_score" class="form-control-sm" value="20" min="1" step="0.5" required style="text-align:center;font-weight:700;">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-bottom:16px;">
                    <div class="form-group-sm">
                        <label>Intitulé (ex: Interro 1, Devoir maison...)</label>
                        <input type="text" name="title" class="form-control-sm" placeholder="ex: Interro 1 - Vocabulaire" required>
                    </div>
                    <div class="form-group-sm">
                        <label>Date</label>
                        <input type="date" name="eval_date" class="form-control-sm" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin-bottom:10px;padding-bottom:6px;border-bottom:1px solid var(--border);">
                    Notes des élèves (<?= count($students) ?>)
                </div>

                <div style="max-height:260px;overflow-y:auto;padding-right:6px;">
                    <?php foreach ($students as $s): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid var(--border);">
                        <div>
                            <strong><?= escape($s['last_name'] . ' ' . $s['first_name']) ?></strong>
                            <small style="display:block;color:var(--text-muted);font-size:10px;"><?= escape($s['matricule']??'') ?></small>
                        </div>
                        <div style="width:100px;">
                            <input type="number" name="scores[<?= $s['id'] ?>]" class="form-control-sm" step="0.25" min="0" placeholder="Note" style="text-align:center;font-weight:700;">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-actions-compact" style="padding:14px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addEvalModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">💾 Enregistrer l'évaluation &amp; Recalculer</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateSubject(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('hiddenSubject').value = opt.getAttribute('data-sub');
    document.getElementById('selectForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

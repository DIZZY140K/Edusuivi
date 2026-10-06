<?php require_once __DIR__ . '/../includes/header.php'; requireRole('parent');

// ── Récupérer les enfants du parent ───────────────────────────
$stmt = $pdo->prepare("
    SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, s.first_name, s.last_name,
           s.matricule, c.name as class_name, c.school_level
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.parent_id = ?
    ORDER BY s.first_name
");
$stmt->execute([$currentUser['id']]);
$children = $stmt->fetchAll();

$childData = [];

foreach ($children as $child) {
    $cId = $child['id'];

    // 1. Évaluations élémentaires détaillées (Devoirs, Interros, Exercices, Examens)
    $evalsStmt = $pdo->prepare("
        SELECT e.*, sub.name as subject_name, u.name as teacher_name
        FROM evaluations e
        JOIN subjects sub ON e.subject_id = sub.id
        LEFT JOIN users u ON e.teacher_id = u.id
        WHERE e.student_id = ?
        ORDER BY e.eval_date DESC, e.id DESC
    ");
    $evalsStmt->execute([$cId]);
    $evaluations = $evalsStmt->fetchAll();

    // 2. Notes de période consolidées (table grades)
    $gradesStmt = $pdo->prepare("
        SELECT g.*, sub.name as subject_name
        FROM grades g
        JOIN subjects sub ON g.subject_id = sub.id
        WHERE g.student_id = ?
        ORDER BY g.period, sub.name
    ");
    $gradesStmt->execute([$cId]);
    $grades = $gradesStmt->fetchAll();

    // Groupées par période
    $gradesByPeriod = [];
    foreach ($grades as $g) {
        $gradesByPeriod[$g['period']][] = $g;
    }

    // Statistiques globales
    $totalEvals = count($evaluations);
    $totalScore = 0;
    $totalMax   = 0;
    $bestScore  = 0;
    $bestScoreLabel = '—';
    $successCount = 0;

    foreach ($evaluations as $ev) {
        $totalScore += floatval($ev['score']);
        $totalMax   += floatval($ev['max_score']);
        $pct = ($ev['score'] / $ev['max_score']) * 100;
        if ($pct >= 50) $successCount++;
        if ($pct > $bestScore) {
            $bestScore = $pct;
            $bestScoreLabel = $ev['score'] . ' / ' . $ev['max_score'] . ' (' . $ev['subject_name'] . ')';
        }
    }

    // Moyenne générale globale (sur les évaluations ou sur les notes de période)
    if ($totalMax > 0) {
        $globalAvg = round(($totalScore / $totalMax) * 100, 1);
    } elseif (count($grades) > 0) {
        $gSum = 0;
        foreach ($grades as $g) $gSum += ($g['grade'] / $g['max_grade']) * 100;
        $globalAvg = round($gSum / count($grades), 1);
    } else {
        $globalAvg = 0;
    }

    $childData[] = [
        'info'          => $child,
        'evaluations'   => $evaluations,
        'gradesByPeriod'=> $gradesByPeriod,
        'globalAvg'     => $globalAvg,
        'totalEvals'    => $totalEvals,
        'bestScore'     => $bestScoreLabel,
        'successRate'   => $totalEvals > 0 ? round(($successCount / $totalEvals) * 100) : 0
    ];
}
?>

<div class="page-header">
    <div>
        <h2>📝 Résultats &amp; Notes Détaillées</h2>
        <p>Consultez en temps réel chaque devoir, interrogation, exercice et examen de vos enfants</p>
    </div>
</div>

<?php if (empty($children)): ?>
<div class="card" style="padding:48px;text-align:center;color:var(--text-muted);">
    <div style="font-size:48px;margin-bottom:12px;">👶</div>
    <h3>Aucun enfant associé à votre compte</h3>
    <p style="font-size:14px;margin-top:6px;">Contactez l'administration de l'établissement si votre enfant n'apparaît pas.</p>
</div>
<?php else: ?>

<!-- ── Sélecteur d'enfant (Tabs) ──────────────────────────────── -->
<div class="tabs" style="margin-bottom:24px;">
    <?php foreach ($childData as $i => $cd): ?>
    <button class="tab <?= $i===0?'active':'' ?>" onclick="switchChildTab(<?= $i ?>)" id="btn-child-<?= $i ?>">
        👶 <?= escape($cd['info']['name']) ?>
        <small style="opacity:0.75;font-weight:400;margin-left:4px;">(<?= escape($cd['info']['class_name'] ?? 'Classe') ?>)</small>
    </button>
    <?php endforeach; ?>
</div>

<?php foreach ($childData as $i => $cd):
    $avgColor = $cd['globalAvg'] >= 65 ? '#10b981' : ($cd['globalAvg'] >= 50 ? '#f59e0b' : '#ef4444');
?>
<div class="child-view" id="child-view-<?= $i ?>" style="<?= $i===0 ? '' : 'display:none;' ?>">

    <!-- ── Cartes Récapitulatives Haut ───────────────────────── -->
    <div class="stats-grid" style="margin-bottom:24px;">
        <div class="stat-card">
            <div class="icon-box" style="background:<?= $avgColor ?>22;color:<?= $avgColor ?>;font-size:24px;">📈</div>
            <div>
                <div class="value" style="color:<?= $avgColor ?>;"><?= $cd['globalAvg'] ?>%</div>
                <div class="label">Moyenne générale</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box blue">📚</div>
            <div>
                <div class="value"><?= $cd['totalEvals'] ?></div>
                <div class="label">Évaluations notées</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box green">🎯</div>
            <div>
                <div class="value"><?= $cd['successRate'] ?>%</div>
                <div class="label">Taux de réussite</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box gold">🏆</div>
            <div>
                <div class="value" style="font-size:15px;line-height:1.3;font-weight:700;"><?= escape($cd['bestScore']) ?></div>
                <div class="label">Meilleure performance</div>
            </div>
        </div>
    </div>

    <!-- ── Section 1 : Détail Précis de chaque Évaluation ──────── -->
    <div class="card" style="margin-bottom:28px;">
        <div class="card-header" style="justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 style="display:flex;align-items:center;gap:8px;">
                    🔍 Détail précis des évaluations (Interros, Devoirs, Exercices, Examens)
                </h3>
                <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0 0;">
                    Chaque note attribuée par les enseignants avec barème, date et appréciation
                </p>
            </div>
            
            <!-- Filtre interactif par type -->
            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <button type="button" class="btn btn-sm btn-primary filter-btn-<?= $i ?>" onclick="filterEvals(<?= $i ?>, 'all', this)">Tous</button>
                <button type="button" class="btn btn-sm btn-outline filter-btn-<?= $i ?>" onclick="filterEvals(<?= $i ?>, 'interrogation', this)">❓ Interros</button>
                <button type="button" class="btn btn-sm btn-outline filter-btn-<?= $i ?>" onclick="filterEvals(<?= $i ?>, 'devoir', this)">📝 Devoirs</button>
                <button type="button" class="btn btn-sm btn-outline filter-btn-<?= $i ?>" onclick="filterEvals(<?= $i ?>, 'exercice', this)">✏️ Exercices</button>
                <button type="button" class="btn btn-sm btn-outline filter-btn-<?= $i ?>" onclick="filterEvals(<?= $i ?>, 'examen', this)">🎓 Examens</button>
            </div>
        </div>

        <?php if (empty($cd['evaluations'])): ?>
        <div style="padding:40px;text-align:center;color:var(--text-muted);">
            <div style="font-size:36px;margin-bottom:10px;">📋</div>
            Aucune évaluation élémentaire n'a encore été enregistrée pour cet élève.
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table style="font-size:13px;" id="evals-table-<?= $i ?>">
            <thead>
                <tr>
                    <th style="min-width:90px;">Date</th>
                    <th style="min-width:140px;">Type d'épreuve</th>
                    <th>Intitulé de l'évaluation</th>
                    <th>Matière</th>
                    <th style="text-align:center;min-width:110px;">Note obtenue</th>
                    <th style="text-align:center;min-width:90px;">Pourcentage</th>
                    <th style="min-width:140px;">Période</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cd['evaluations'] as $ev): 
                $pct = round(($ev['score'] / $ev['max_score']) * 100, 1);
                $badgeColor = $pct >= 65 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
                $typeLabels = [
                    'interrogation' => ['label' => 'Interrogation', 'icon' => '❓', 'bg' => 'rgba(59,130,246,.12)', 'color' => '#3b82f6'],
                    'devoir'        => ['label' => 'Devoir',        'icon' => '📝', 'bg' => 'rgba(212,147,13,.12)',  'color' => '#d4930d'],
                    'exercice'      => ['label' => 'Exercice',      'icon' => '✏️', 'bg' => 'rgba(16,185,129,.12)', 'color' => '#10b981'],
                    'examen'        => ['label' => 'Examen',        'icon' => '🎓', 'bg' => 'rgba(239,68,68,.12)',  'color' => '#ef4444'],
                ];
                $tInfo = $typeLabels[$ev['evaluation_type']] ?? ['label' => $ev['evaluation_type'], 'icon' => '📄', 'bg' => '#eee', 'color' => '#333'];
            ?>
            <tr class="eval-row-<?= $i ?>" data-type="<?= $ev['evaluation_type'] ?>">
                <td style="color:var(--text-muted);font-size:12px;white-space:nowrap;">
                    <?= date('d/m/Y', strtotime($ev['eval_date'])) ?>
                </td>
                <td>
                    <span style="background:<?= $tInfo['bg'] ?>;color:<?= $tInfo['color'] ?>;padding:4px 10px;border-radius:12px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px;">
                        <span><?= $tInfo['icon'] ?></span> <?= $tInfo['label'] ?>
                    </span>
                </td>
                <td>
                    <strong><?= escape($ev['title']) ?></strong>
                    <?php if (!empty($ev['teacher_name'])): ?>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                        Par <?= escape($ev['teacher_name']) ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge badge-primary" style="font-size:11px;"><?= escape($ev['subject_name']) ?></span>
                </td>
                <td style="text-align:center;font-weight:800;font-size:15px;color:<?= $badgeColor ?>;">
                    <?= $ev['score'] ?> <span style="font-size:11px;color:var(--text-muted);font-weight:400;">/ <?= $ev['max_score'] ?></span>
                </td>
                <td style="text-align:center;">
                    <span style="font-weight:700;color:<?= $badgeColor ?>;"><?= $pct ?>%</span>
                    <div style="width:100%;height:4px;background:rgba(255,255,255,.08);border-radius:2px;margin-top:4px;overflow:hidden;">
                        <div style="width:<?= min(100, $pct) ?>%;height:100%;background:<?= $badgeColor ?>;"></div>
                    </div>
                </td>
                <td style="font-size:12px;color:var(--text-muted);">
                    <?= escape($ev['period']) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Section 2 : Synthèse Périodique & Bulletin ──────────── -->
    <div class="card">
        <div class="card-header" style="justify-content:space-between;align-items:center;">
            <div>
                <h3>🎯 Synthèse des Notes de Périodes &amp; Trimestres</h3>
                <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0 0;">
                    Notes consolidées servant au calcul du bulletin scolaire
                </p>
            </div>
            <a href="/edusuivi/admin/bulletin.php?student_id=<?= $cd['info']['id'] ?>" target="_blank" class="btn btn-primary btn-sm">
                📄 Ouvrir le bulletin scolaire officiel →
            </a>
        </div>

        <?php if (empty($cd['gradesByPeriod'])): ?>
        <div style="padding:32px;text-align:center;color:var(--text-muted);">
            Aucune note de période n'a encore été consolidée.
        </div>
        <?php else: ?>
        <div style="padding:16px 20px;display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:16px;">
            <?php foreach ($cd['gradesByPeriod'] as $pName => $pGrades): 
                $pSum = 0; $pMax = 0;
                foreach ($pGrades as $pg) {
                    $pSum += floatval($pg['grade']);
                    $pMax += floatval($pg['max_grade']);
                }
                $pPct = $pMax > 0 ? round(($pSum / $pMax) * 100, 1) : 0;
                $pColor = $pPct >= 65 ? '#10b981' : ($pPct >= 50 ? '#f59e0b' : '#ef4444');
            ?>
            <div style="border:1px solid var(--border);border-radius:12px;padding:16px;background:rgba(255,255,255,.02);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border);">
                    <strong style="font-size:14px;color:var(--text);"><?= escape($pName) ?></strong>
                    <span style="font-size:14px;font-weight:800;color:<?= $pColor ?>;"><?= $pPct ?>%</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($pGrades as $pg): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;font-size:12px;">
                        <span style="color:var(--text-muted);"><?= escape($pg['subject_name']) ?></span>
                        <strong><?= $pg['grade'] ?> / <?= $pg['max_grade'] ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
<?php endforeach; ?>

<?php endif; ?>

<script>
function switchChildTab(idx) {
    document.querySelectorAll('.child-view').forEach(v => v.style.display = 'none');
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    
    const target = document.getElementById('child-view-' + idx);
    const btn    = document.getElementById('btn-child-' + idx);
    if (target) target.style.display = 'block';
    if (btn) btn.classList.add('active');
}

function filterEvals(childIdx, type, btn) {
    const table = document.getElementById('evals-table-' + childIdx);
    if (!table) return;

    table.querySelectorAll('.filter-btn-' + childIdx).forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline');
    });
    btn.classList.remove('btn-outline');
    btn.classList.add('btn-primary');

    const rows = table.querySelectorAll('.eval-row-' + childIdx);
    rows.forEach(r => {
        if (type === 'all' || r.getAttribute('data-type') === type) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

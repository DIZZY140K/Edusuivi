<?php require_once __DIR__ . '/../includes/config.php'; requireLogin();
// AJAX: historique d'un élève
$id = intval($_GET['id'] ?? 0);
if (!$id) exit('ID invalide');

$student = $pdo->prepare("SELECT s.*, c.name as class_name, u.name as parent_name,
    TIMESTAMPDIFF(YEAR,s.date_of_birth,CURDATE()) as age
    FROM students s LEFT JOIN classes c ON s.class_id=c.id LEFT JOIN users u ON s.parent_id=u.id
    WHERE s.id=?");
$student->execute([$id]); $s = $student->fetch();
if (!$s) exit('Élève introuvable');

$grades = $pdo->prepare("SELECT g.*, sub.name as subject_name, u.name as teacher_name
    FROM grades g JOIN subjects sub ON g.subject_id=sub.id JOIN users u ON g.teacher_id=u.id
    WHERE g.student_id=? ORDER BY g.period, sub.name");
$grades->execute([$id]); $gradeList = $grades->fetchAll();

$absences = $pdo->prepare("SELECT * FROM absences WHERE student_id=? ORDER BY date DESC LIMIT 10");
$absences->execute([$id]); $absenceList = $absences->fetchAll();

// Calculer la moyenne générale
$totalPct = 0; $cnt = 0;
foreach ($gradeList as $g) { $totalPct += ($g['grade']/$g['max_grade'])*100; $cnt++; }
$avgGeneral = $cnt > 0 ? round($totalPct/$cnt, 2) : 0;
?>
<div style="padding:4px;">
    <!-- Infos élève -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:12px;background:rgba(255,255,255,0.04);border-radius:8px;margin-bottom:16px;">
        <div><strong>Matricule :</strong> <code><?= escape($s['matricule']??'—') ?></code></div>
        <div><strong>Classe :</strong> <?= escape($s['class_name']??'—') ?></div>
        <div><strong>Âge :</strong> <?= $s['age']??'—' ?> ans</div>
        <div><strong>Option :</strong> <?= escape($s['option_filiere']??'—') ?></div>
        <div><strong>Parent :</strong> <?= escape($s['parent_name']??'—') ?></div>
        <div><strong>Téléphone :</strong> <?= escape($s['phone']??'—') ?></div>
    </div>

    <!-- Moyenne générale -->
    <div style="text-align:center;margin-bottom:16px;">
        <div style="font-size:2rem;font-weight:700;color:<?= $avgGeneral>=10?'#10b981':'#ef4444' ?>">
            <?= $avgGeneral ?>%
        </div>
        <div style="color:var(--text-muted);font-size:13px;">Moyenne générale toutes périodes</div>
    </div>

    <!-- Notes par trimestre -->
    <?php
    $byPeriod = [];
    foreach ($gradeList as $g) { $byPeriod[$g['period']][] = $g; }
    foreach ($byPeriod as $period => $glist):
        $pctSum = 0; foreach ($glist as $g) { $pctSum += ($g['grade']/$g['max_grade'])*100; }
        $pAvg = round($pctSum/count($glist), 1);
    ?>
    <div style="margin-bottom:16px;">
        <h4 style="color:var(--primary);border-bottom:1px solid var(--border);padding-bottom:6px;margin-bottom:8px;">
            📚 <?= escape($period) ?> — Moy: <span style="color:<?= $pAvg>=50?'#10b981':'#ef4444' ?>"><?= $pAvg ?>%</span>
        </h4>
        <table style="width:100%;font-size:13px;">
            <thead><tr><th>Matière</th><th>Note</th><th>Max</th><th>%</th><th>Enseignant</th></tr></thead>
            <tbody>
            <?php foreach ($glist as $g):
                $pct = round(($g['grade']/$g['max_grade'])*100,1);
            ?>
                <tr>
                    <td><?= escape($g['subject_name']) ?></td>
                    <td><strong><?= $g['grade'] ?></strong></td>
                    <td><?= $g['max_grade'] ?></td>
                    <td><span style="color:<?= $pct>=50?'#10b981':'#ef4444' ?>"><?= $pct ?>%</span></td>
                    <td style="color:var(--text-muted)"><?= escape($g['teacher_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <?php if (empty($gradeList)): ?>
        <p style="color:var(--text-muted);text-align:center;">Aucune note enregistrée.</p>
    <?php endif; ?>

    <!-- Absences récentes -->
    <h4 style="color:var(--primary);border-bottom:1px solid var(--border);padding-bottom:6px;margin-bottom:8px;">📅 Absences récentes</h4>
    <?php if ($absenceList): ?>
    <table style="width:100%;font-size:13px;">
        <thead><tr><th>Date</th><th>Statut</th><th>Motif</th></tr></thead>
        <tbody>
        <?php foreach ($absenceList as $a): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($a['date'])) ?></td>
                <td><span class="badge <?= $a['justified']?'badge-success':'badge-danger' ?>"><?= $a['justified']?'Justifiée':'Non justifiée' ?></span></td>
                <td><?= escape($a['reason']??'—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="color:var(--text-muted);text-align:center;">Aucune absence enregistrée.</p>
    <?php endif; ?>

    <div style="margin-top:16px;text-align:right;">
        <a href="/edusuivi/admin/bulletin.php?student_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm" target="_blank">📄 Voir le bulletin</a>
    </div>
</div>

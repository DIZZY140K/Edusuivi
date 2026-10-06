<?php require_once __DIR__ . '/../includes/header.php'; requireRole('parent');

$stmt = $pdo->prepare("SELECT s.id, CONCAT(s.first_name,' ',s.last_name) as name, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.parent_id=?");
$stmt->execute([$currentUser['id']]);
$children = $stmt->fetchAll();
$childIds = array_column($children, 'id');

// Stats
$totalGrades = $totalAbs = $avgPct = $totalDebt = 0;
if ($childIds) {
    $placeholders = implode(',', array_fill(0, count($childIds), '?'));
    $totalGrades = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE student_id IN ($placeholders)")->execute($childIds) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0; // fallback simplified below
    $totalGrades = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE student_id IN ($placeholders)"); $totalGrades->execute($childIds); $totalGrades = $totalGrades->fetchColumn();
    $totalAbs = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE student_id IN ($placeholders)"); $totalAbs->execute($childIds); $totalAbs = $totalAbs->fetchColumn();
    
    $avg = $pdo->prepare("SELECT AVG(grade/max_grade*100) FROM grades WHERE student_id IN ($placeholders)"); $avg->execute($childIds); 
    $avgPct = round((float)$avg->fetchColumn(), 1);

    $fees = $pdo->prepare("SELECT SUM(amount_due) as due, (SELECT SUM(amount_paid) FROM fee_payments WHERE student_id IN ($placeholders)) as paid FROM student_fees WHERE student_id IN ($placeholders)");
    $fees->execute(array_merge($childIds,$childIds)); $f = $fees->fetch();
    $totalDebt = max(0, ($f['due']??0) - ($f['paid']??0));
}

// Bilan absences semaine en cours
$thisWeekMon = date('Y-m-d', strtotime('monday this week'));
$thisWeekSat = date('Y-m-d', strtotime('saturday this week'));
$thisWeekAbsCount = 0;
$thisWeekUnjustCount = 0;

if ($childIds) {
    $placeholders = implode(',', array_fill(0, count($childIds), '?'));
    $wStmt = $pdo->prepare("SELECT COUNT(*) as tot, SUM(CASE WHEN justified=0 THEN 1 ELSE 0 END) as unjust FROM absences WHERE student_id IN ($placeholders) AND date BETWEEN ? AND ?");
    $wStmt->execute(array_merge($childIds, [$thisWeekMon, $thisWeekSat]));
    $wData = $wStmt->fetch();
    $thisWeekAbsCount = intval($wData['tot'] ?? 0);
    $thisWeekUnjustCount = intval($wData['unjust'] ?? 0);
}

// Alertes spécifiques parent
$alerts = [];
if ($totalDebt > 0) {
    $alerts[] = ['icon'=>'💰','color'=>'#ef4444','msg'=>"Il vous reste ".number_format($totalDebt,0,',',' ')." FC de frais scolaires à régler.", 'link'=>'fees.php'];
}
if ($childIds) {
    $abs = $pdo->prepare("SELECT COUNT(*) as c FROM absences WHERE student_id IN ($placeholders) AND justified=0");
    $abs->execute($childIds);
    if (($c = $abs->fetchColumn()) > 0) {
        $alerts[] = ['icon'=>'📅','color'=>'#f0b429','msg'=>"Vous avez $c absence(s) non justifiée(s) à régulariser.", 'link'=>'absences.php'];
    }
    // Bilan semaine en cours
    if ($thisWeekUnjustCount > 0) {
        $alerts[] = ['icon'=>'⚠️','color'=>'#ef4444','msg'=>"Bilan de la semaine : $thisWeekUnjustCount absence(s) non justifiée(s) pour vos enfants.", 'link'=>'absences.php'];
    }
}

// Événements (agenda)
$upcomingEvents = $pdo->query("SELECT title, event_date FROM agenda_events WHERE target IN ('all','parents') AND event_date >= CURRENT_DATE ORDER BY event_date ASC LIMIT 3")->fetchAll();

// Évaluations récentes détaillées (Devoirs, Interros, Examens)
$recentEvaluations = [];

if ($childIds) {
    $placeholders = implode(',', array_fill(0, count($childIds), '?'));
    $evalStmt = $pdo->prepare("
        SELECT e.*, s.first_name, s.last_name, sub.name as subject_name
        FROM evaluations e
        JOIN students s ON e.student_id = s.id
        JOIN subjects sub ON e.subject_id = sub.id
        WHERE e.student_id IN ($placeholders)
        ORDER BY e.eval_date DESC, e.id DESC
        LIMIT 6
    ");
    $evalStmt->execute($childIds);
    $recentEvaluations = $evalStmt->fetchAll();
}
?>

<div class="page-header">
    <div>
        <h2>Tableau de bord</h2>
        <p>Bienvenue, <?= escape($currentUser['name']) ?> — Suivi de vos enfants</p>
    </div>
</div>

<?php if($alerts): ?>
<div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px;">
    <?php foreach($alerts as $a): ?>
    <div style="background:rgba(255,255,255,.03);border-left:4px solid <?= $a['color'] ?>;padding:16px 20px;border-radius:4px;display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;gap:12px;align-items:center;"><span style="font-size:20px;color:<?= $a['color'] ?>;"><?= $a['icon'] ?></span><span><?= escape($a['msg']) ?></span></div>
        <a href="<?= $a['link'] ?>" class="btn btn-sm btn-outline">Voir →</a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card"><div class="icon-box blue">👨‍🎓</div><div><div class="value"><?= count($children) ?></div><div class="label">Enfant(s) inscrit(s)</div></div></div>
    <div class="stat-card"><div class="icon-box gold">📈</div><div><div class="value"><?= $avgPct ?>%</div><div class="label">Moyenne générale</div></div></div>
    <div class="stat-card"><div class="icon-box red">📋</div><div><div class="value"><?= $totalAbs ?></div><div class="label">Absences totales</div></div></div>
    <div class="stat-card"><div class="icon-box green">💰</div><div><div class="value"><?= number_format($totalDebt,0,',',' ') ?></div><div class="label">Dette (FC)</div></div></div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
    <!-- Mes enfants -->
    <div class="card">
        <div class="card-header" style="justify-content:space-between;">
            <h3>Dossiers des enfants</h3>
            <a href="grades.php" class="btn btn-sm btn-outline">Voir toutes les notes</a>
        </div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:16px;">
            <?php foreach ($children as $child): 
                $cAvg = $pdo->prepare("SELECT AVG(grade/max_grade*100) FROM grades WHERE student_id=?"); $cAvg->execute([$child['id']]); $avg = round((float)$cAvg->fetchColumn(),1);
            ?>
            <div style="background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:12px;padding:20px;display:flex;justify-content:space-between;align-items:center;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <div style="width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,var(--gold),var(--gold-light));display:flex;align-items:center;justify-content:center;color:#000;font-weight:800;font-size:22px;"><?= strtoupper(substr($child['name'],0,1)) ?></div>
                    <div>
                        <div style="font-weight:700;font-size:16px;"><?= escape($child['name']) ?></div>
                        <div style="color:var(--text-muted);font-size:13px;"><?= escape($child['class_name']??'—') ?></div>
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Moyenne</div>
                    <div style="font-size:24px;font-weight:800;color:<?= $avg>=65?'#10b981':($avg>=50?'#f0b429':'#ef4444') ?>;"><?= $avg ?: '0' ?>%</div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(!$children): ?><p style="text-align:center;color:var(--text-muted);">Aucun enfant</p><?php endif; ?>
        </div>
    </div>

    <!-- Dernières évaluations notées -->
    <div class="card" style="margin-top:24px;">
        <div class="card-header" style="justify-content:space-between;align-items:center;">
            <h3>📝 Dernières évaluations reçues</h3>
            <a href="grades.php" class="btn btn-sm btn-outline">Voir tout en détail →</a>
        </div>
        <div style="padding:16px;">
            <?php if(empty($recentEvaluations)): ?>
            <p style="text-align:center;color:var(--text-muted);margin:16px 0;">Aucune évaluation récente.</p>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach($recentEvaluations as $rev): 
                    $pct = round(($rev['score'] / $rev['max_score']) * 100, 1);
                    $badgeColor = $pct >= 65 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
                    $typeIcon = [
                        'interrogation' => '❓',
                        'devoir'        => '📝',
                        'exercice'      => '✏️',
                        'examen'        => '🎓'
                    ][$rev['evaluation_type']] ?? '📄';
                ?>
                <div style="background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:10px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:14px;color:var(--primary);"><?= $typeIcon ?></span>
                            <strong><?= escape($rev['title']) ?></strong>
                            <span class="badge badge-primary" style="font-size:10px;"><?= escape($rev['subject_name']) ?></span>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                            👨‍🎓 <?= escape($rev['first_name'] . ' ' . $rev['last_name']) ?> &bull; Le <?= date('d/m/Y', strtotime($rev['eval_date'])) ?> &bull; <?= escape($rev['period']) ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:16px;font-weight:800;color:<?= $badgeColor ?>;"><?= $rev['score'] ?> / <?= $rev['max_score'] ?></span>
                        <div style="font-size:11px;color:<?= $badgeColor ?>;font-weight:700;"><?= $pct ?>%</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

    <!-- Agenda de l'école -->
    <div>
        <div class="card">
            <div class="card-header" style="justify-content:space-between;">
                <h3>📅 Agenda de l'école</h3>
                <a href="/edusuivi/admin/agenda.php" class="btn btn-sm btn-outline">Ouvrir</a>
            </div>
            <div style="padding:12px;">
                <?php foreach($upcomingEvents as $e): 
                    $days = (strtotime($e['event_date']) - strtotime(date('Y-m-d'))) / 86400;
                ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--border);">
                    <div style="font-weight:600;font-size:13px;"><?= escape($e['title']) ?></div>
                    <div style="font-size:11px;color:var(--primary);margin-top:4px;"><?= $days==0?'Aujourd\'hui':($days==1?'Demain':"Dans $days jours") ?> (<?= date('d/m',strtotime($e['event_date'])) ?>)</div>
                </div>
                <?php endforeach; ?>
                <?php if(!$upcomingEvents): ?><p style="text-align:center;color:var(--text-muted);font-size:13px;padding:20px 0;">Aucun événement à venir</p><?php endif; ?>
            </div>
        </div>
        
        <!-- Contact école -->
        <div class="card" style="margin-top:24px;background:var(--primary);color:#000;">
            <div style="padding:24px;text-align:center;">
                <div style="font-size:32px;margin-bottom:12px;">💬</div>
                <h3 style="margin:0 0 8px;font-size:16px;">Besoin d'aide ?</h3>
                <p style="font-size:13px;margin:0 0 16px;opacity:.8;">Contactez la direction ou les enseignants.</p>
                <a href="messages.php" style="display:inline-block;background:#000;color:var(--primary);padding:8px 20px;border-radius:20px;font-size:13px;font-weight:700;text-decoration:none;">Envoyer un message</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

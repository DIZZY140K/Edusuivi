<?php require_once __DIR__ . '/../includes/header.php'; requireRole('admin');

// Infos de l'école du directeur
$schoolInfo = null;
if (!empty($currentUser['school_id'])) {
    try {
        $sStmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
        $sStmt->execute([$currentUser['school_id']]);
        $schoolInfo = $sStmt->fetch();
    } catch(Exception $e) {}
}

// Statistiques filtrées par mode
$mode = getSchoolMode();
$totalStudents = $pdo->prepare("SELECT COUNT(*) FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE c.school_level=? OR s.class_id IS NULL");
$totalStudents->execute([$mode]); $totalStudents = $totalStudents->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn();
$totalClasses  = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE school_level=?");
$totalClasses->execute([$mode]); $totalClasses = $totalClasses->fetchColumn();
$globalAvg     = $pdo->prepare("SELECT ROUND(AVG(g.grade/g.max_grade*100),1) FROM grades g JOIN students s ON g.student_id=s.id LEFT JOIN classes c ON s.class_id=c.id WHERE c.school_level=?");
$globalAvg->execute([$mode]); $globalAvg = $globalAvg->fetchColumn();

// Notes récentes
$recentGrades = $pdo->query("SELECT g.*, s.first_name, s.last_name, sub.name as subject_name FROM grades g JOIN students s ON g.student_id = s.id JOIN subjects sub ON g.subject_id = sub.id ORDER BY g.created_at DESC LIMIT 5")->fetchAll();

// Événements à venir
$upcomingEvents = $pdo->query("SELECT title, event_date, event_type FROM agenda_events WHERE event_date >= CURRENT_DATE ORDER BY event_date ASC LIMIT 4")->fetchAll();

// Statistiques financières
$finance = $pdo->query("SELECT SUM(amount_due) as due, (SELECT SUM(amount_paid) FROM fee_payments) as paid FROM student_fees")->fetch();
$debt = max(0, ($finance['due']??0) - ($finance['paid']??0));

// Alertes intelligentes
$alerts = checkSmartAlerts($pdo, $currentUser['id']);
?>

<?php if ($schoolInfo): ?>
<!-- Bannière École -->
<div style="background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(129,140,248,.05));border:1px solid rgba(99,102,241,.2);border-radius:14px;padding:18px 24px;margin-bottom:24px;display:flex;align-items:center;gap:18px;">
    <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#818cf8);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;box-shadow:0 4px 12px rgba(99,102,241,.3);">🏫</div>
    <div style="flex:1;">
        <div style="font-size:11px;color:rgba(129,140,248,.8);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:2px;">Établissement</div>
        <div style="font-size:18px;font-weight:800;color:var(--text);"><?= escape($schoolInfo['name']) ?></div>
        <?php if ($schoolInfo['address']): ?>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">📍 <?= escape($schoolInfo['address']) ?></div>
        <?php endif; ?>
    </div>
    <div style="text-align:right;flex-shrink:0;">
        <div style="font-size:11px;color:var(--text-muted);">Année scolaire</div>
        <div style="font-weight:700;color:var(--primary);font-size:15px;"><?= date('Y') . ' - ' . (date('Y')+1) ?></div>
        <a href="/edusuivi/logout.php" style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;font-size:12px;color:var(--text-muted);text-decoration:none;padding:5px 12px;border:1px solid var(--border);border-radius:20px;transition:all .2s;" onmouseover="this.style.borderColor='#ef4444';this.style.color='#ef4444'" onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">🔄 Changer d'école</a>
    </div>
</div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h2>Tableau de bord</h2>
        <p>Bienvenue, <?= escape($currentUser['name']) ?> — Directeur de l'établissement</p>
    </div>
    <div style="text-align:right;">
        <div style="font-size:12px;color:var(--text-muted);">Aujourd'hui</div>
        <div style="font-weight:700;color:var(--primary);"><?= date('d/m/Y') ?></div>
    </div>
</div>

<!-- Grid principale stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="icon-box blue">👨‍🎓</div>
        <div><div class="value"><?= $totalStudents ?></div><div class="label">Élèves inscrits</div></div>
    </div>
    <div class="stat-card">
        <div class="icon-box gold">👨‍🏫</div>
        <div><div class="value"><?= $totalTeachers ?></div><div class="label">Enseignants</div></div>
    </div>
    <div class="stat-card">
        <div class="icon-box green">🏫</div>
        <div><div class="value"><?= $totalClasses ?></div><div class="label">Classes</div></div>
    </div>
    <div class="stat-card">
        <div class="icon-box red">📈</div>
        <div><div class="value"><?= $globalAvg ?: 0 ?>%</div><div class="label">Moyenne globale</div></div>
    </div>
</div>

<!-- Alertes intelligentes (si existantes) -->
<?php if($alerts): ?>
<div style="margin-bottom:24px;">
    <h3 style="font-size:16px;margin-bottom:12px;display:flex;align-items:center;gap:8px;">💡 Alertes intelligentes <span class="badge badge-primary"><?= count($alerts) ?></span></h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px;">
        <?php foreach(array_slice($alerts,0,4) as $al): ?>
        <div style="background:rgba(var(--<?= $al['type']==='danger'?'239,68,68':($al['type']==='warning'?'240,180,41':($al['type']==='success'?'16,185,129':'59,130,246')) ?>),.1);border-left:3px solid var(--<?= $al['type']==='danger'?'red':($al['type']==='warning'?'gold':($al['type']==='success'?'green':'blue')) ?>);padding:12px;border-radius:4px;display:flex;gap:12px;align-items:center;">
            <div style="font-size:20px;"><?= $al['icon'] ?></div>
            <div style="font-size:13px;line-height:1.4;"><?= escape($al['msg']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Colonnes de contenu -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
    
    <!-- Colonne gauche -->
    <div>
        <!-- Graphique -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header">
                <h3>Distribution des notes par classe</h3>
            </div>
            <div style="padding:16px;">
                <canvas id="classesChart" style="max-height:220px;"></canvas>
            </div>
        </div>

        <!-- Dernières notes -->
        <div class="card">
            <div class="card-header" style="justify-content:space-between;">
                <h3>Dernières notes encodées</h3>
                <a href="reports.php" class="btn btn-sm btn-outline">Voir tout</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Élève</th><th>Classe</th><th>Matière</th><th>Note</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($recentGrades as $g): ?>
                    <tr>
                        <td><strong><?= escape($g['last_name'] . ' ' . $g['first_name']) ?></strong></td>
                        <td><span class="badge badge-primary">...</span></td>
                        <td><?= escape($g['subject_name']) ?></td>
                        <td><strong><?= $g['grade'] ?></strong> / <?= $g['max_grade'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if(!$recentGrades): ?><tr><td colspan="4" style="text-align:center;padding:20px;">Aucune note récente</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Colonne droite -->
    <div>
        <!-- Agenda Imminent -->
        <div class="card">
            <div class="card-header" style="justify-content:space-between;">
                <h3>📅 Prochainement</h3>
                <a href="agenda.php" class="btn btn-sm btn-outline">Tout voir</a>
            </div>
            <div style="padding:12px;">
                <?php foreach($upcomingEvents as $e): 
                    $days = (strtotime($e['event_date']) - strtotime(date('Y-m-d'))) / 86400;
                    $label = $days==0?'Aujourd\'hui':($days==1?'Demain':"Dans $days j");
                    $icons = [
                        'reunion' => '🤝',
                        'examen' => '📝',
                        'activite' => '🎭',
                        'conge' => '🌴',
                        'autre' => '📌'
                    ];
                ?>
                <div style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--border);align-items:center;">
                    <div style="font-size:20px;width:28px;text-align:center;"><?= $icons[$e['event_type']]??'📌' ?></div>
                    <div>
                        <div style="font-weight:600;font-size:13px;"><?= escape($e['title']) ?></div>
                        <div style="font-size:11px;color:var(--primary);margin-top:2px;"><?= $label ?> — <?= date('d/m',strtotime($e['event_date'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(!$upcomingEvents): ?><div style="text-align:center;color:var(--text-muted);padding:20px;font-size:13px;">Rien de prévu</div><?php endif; ?>
            </div>
        </div>

        <!-- Raccourcis rapides -->
        <div class="card" style="margin-top:24px;">
            <div class="card-header"><h3>⚡ Actions rapides</h3></div>
            <div style="padding:16px;display:flex;flex-direction:column;gap:10px;">
                <a href="students.php" class="btn btn-outline" style="justify-content:flex-start;gap:10px;">👨‍🎓 Gérer les élèves</a>
                <a href="messages.php" class="btn btn-outline" style="justify-content:flex-start;gap:10px;">💬 Envoyer un message</a>
                <a href="bulletin.php" class="btn btn-outline" style="justify-content:flex-start;gap:10px;">📄 Imprimer un bulletin</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

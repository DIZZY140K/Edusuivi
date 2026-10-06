<?php require_once __DIR__ . '/../includes/header.php'; requireRole('admin');

// ─── Données pour graphiques ───────────────────────────────
// Moyennes par classe
$clasAvg = $pdo->query("
    SELECT c.name, ROUND(AVG(g.grade/g.max_grade*100),1) as avg, COUNT(DISTINCT s.id) as students
    FROM classes c
    JOIN students s ON s.class_id=c.id
    JOIN grades g ON g.student_id=s.id
    GROUP BY c.id ORDER BY c.name
")->fetchAll();

// Moyennes par matière
$subAvg = $pdo->query("
    SELECT sub.name, ROUND(AVG(g.grade/g.max_grade*100),1) as avg, COUNT(*) as cnt
    FROM subjects sub JOIN grades g ON g.subject_id=sub.id
    GROUP BY sub.id ORDER BY avg DESC
")->fetchAll();

// Évolution trimestrielle (toutes classes)
$evolution = $pdo->query("
    SELECT period, ROUND(AVG(grade/max_grade*100),1) as avg, COUNT(*) as cnt
    FROM grades GROUP BY period ORDER BY FIELD(period,'1er Trimestre','2ème Trimestre','3ème Trimestre')
")->fetchAll();

// Répartition mentions
$mentions = $pdo->query("
    SELECT 
        SUM(CASE WHEN (grade/max_grade*100) >= 80 THEN 1 ELSE 0 END) as tres_bien,
        SUM(CASE WHEN (grade/max_grade*100) >= 65 AND (grade/max_grade*100) < 80 THEN 1 ELSE 0 END) as bien,
        SUM(CASE WHEN (grade/max_grade*100) >= 50 AND (grade/max_grade*100) < 65 THEN 1 ELSE 0 END) as assez_bien,
        SUM(CASE WHEN (grade/max_grade*100) >= 40 AND (grade/max_grade*100) < 50 THEN 1 ELSE 0 END) as passable,
        SUM(CASE WHEN (grade/max_grade*100) < 40 THEN 1 ELSE 0 END) as insuffisant
    FROM grades
")->fetch();

// Top 10 élèves
$top10 = $pdo->query("
    SELECT s.first_name, s.last_name, c.name as class_name,
           ROUND(AVG(g.grade/g.max_grade*100),1) as avg
    FROM students s
    JOIN grades g ON g.student_id=s.id
    LEFT JOIN classes c ON s.class_id=c.id
    GROUP BY s.id ORDER BY avg DESC LIMIT 10
")->fetchAll();

// Absences par classe
$absClass = $pdo->query("
    SELECT c.name, COUNT(a.id) as total,
           SUM(a.justified) as justified,
           SUM(1-a.justified) as unjustified
    FROM classes c
    JOIN students s ON s.class_id=c.id
    JOIN absences a ON a.student_id=s.id
    GROUP BY c.id ORDER BY total DESC
")->fetchAll();

// Tableau comparatif classe × matière
$compare = $pdo->query("
    SELECT c.name as class_name, sub.name as subject_name,
           ROUND(AVG(g.grade/g.max_grade*100),1) as avg
    FROM classes c
    JOIN students s ON s.class_id=c.id
    JOIN grades g ON g.student_id=s.id
    JOIN subjects sub ON g.subject_id=sub.id
    GROUP BY c.id, sub.id ORDER BY c.name, sub.name
")->fetchAll();

// Indexer le tableau comparatif
$compareMatrix = [];
$compareClasses = [];
$compareSubjects = [];
foreach ($compare as $row) {
    $compareMatrix[$row['class_name']][$row['subject_name']] = $row['avg'];
    if (!in_array($row['class_name'], $compareClasses)) $compareClasses[] = $row['class_name'];
    if (!in_array($row['subject_name'], $compareSubjects)) $compareSubjects[] = $row['subject_name'];
}
?>

<div class="page-header">
    <div>
        <h2>📊 Statistiques & Graphiques</h2>
        <p>Analyse des performances scolaires</p>
    </div>
    <a href="reports.php" class="btn btn-outline">📈 Rapports détaillés</a>
</div>

<!-- Stats rapides -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <?php
    $totalGrades = $pdo->query("SELECT COUNT(*) FROM grades")->fetchColumn();
    $globalAvg   = $pdo->query("SELECT ROUND(AVG(grade/max_grade*100),1) FROM grades")->fetchColumn();
    $totalAbs    = $pdo->query("SELECT COUNT(*) FROM absences")->fetchColumn();
    $passRate    = $pdo->query("SELECT ROUND(SUM(CASE WHEN grade/max_grade*100>=50 THEN 1 ELSE 0 END)/COUNT(*)*100,1) FROM grades")->fetchColumn();
    ?>
    <div class="stat-card"><div class="icon-box blue">📝</div><div><div class="value"><?= $totalGrades ?></div><div class="label">Notes totales</div></div></div>
    <div class="stat-card"><div class="icon-box gold">📊</div><div><div class="value"><?= $globalAvg ?>%</div><div class="label">Moyenne globale</div></div></div>
    <div class="stat-card"><div class="icon-box green">✅</div><div><div class="value"><?= $passRate ?>%</div><div class="label">Taux de réussite</div></div></div>
    <div class="stat-card"><div class="icon-box red">📅</div><div><div class="value"><?= $totalAbs ?></div><div class="label">Absences totales</div></div></div>
</div>

<!-- Graphiques principaux -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
    <!-- Barres : moyennes par classe -->
    <div class="card">
        <div class="card-header"><h3>📊 Moyenne par classe</h3></div>
        <div style="padding:16px;"><canvas id="chartClasses" height="220"></canvas></div>
    </div>
    <!-- Camembert : mentions -->
    <div class="card">
        <div class="card-header"><h3>🥧 Répartition des mentions</h3></div>
        <div style="padding:16px;position:relative;height:260px;"><canvas id="chartMentions"></canvas></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:24px;">
    <!-- Courbe : évolution trimestrielle -->
    <div class="card">
        <div class="card-header"><h3>📈 Évolution des moyennes par trimestre</h3></div>
        <div style="padding:16px;"><canvas id="chartEvolution" height="180"></canvas></div>
    </div>
    <!-- Barres horizontales : matières -->
    <div class="card">
        <div class="card-header"><h3>📚 Moyenne par matière</h3></div>
        <div style="padding:16px;"><canvas id="chartSubjects" height="300"></canvas></div>
    </div>
</div>

<!-- Absences par classe -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
    <div class="card">
        <div class="card-header"><h3>📅 Absences par classe</h3></div>
        <div style="padding:16px;"><canvas id="chartAbsences" height="220"></canvas></div>
    </div>
    <!-- Top 10 -->
    <div class="card">
        <div class="card-header"><h3>🏆 Top 10 élèves</h3></div>
        <div style="padding:16px;">
            <?php foreach ($top10 as $i => $s): $color = $s['avg']>=65?'#10b981':($s['avg']>=50?'#f0b429':'#ef4444'); ?>
            <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--border);">
                <div style="width:28px;height:28px;border-radius:50%;background:<?= $i<3?'var(--primary)':'rgba(255,255,255,.1)' ?>;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:<?= $i<3?'#000':'var(--text-muted)' ?>;"><?= $i+1 ?></div>
                <div style="flex:1;">
                    <div style="font-weight:600;font-size:13px;"><?= escape($s['last_name'].' '.$s['first_name']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= escape($s['class_name']??'') ?></div>
                </div>
                <div style="font-weight:700;color:<?= $color ?>;"><?= $s['avg'] ?>%</div>
            </div>
            <?php endforeach; ?>
            <?php if (!$top10): ?><p style="text-align:center;color:var(--text-muted);padding:20px;">Aucune donnée</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- Tableau comparatif classe × matière -->
<?php if ($compareMatrix): ?>
<div class="card">
    <div class="card-header">
        <h3>📋 Tableau comparatif — Classe × Matière</h3>
        <a href="reports.php?export=csv" class="btn btn-outline btn-sm">📥 CSV</a>
    </div>
    <div style="overflow-x:auto;padding:0 4px;">
    <table>
        <thead>
            <tr>
                <th>Matière</th>
                <?php foreach($compareClasses as $cl): ?><th style="text-align:center;"><?= escape($cl) ?></th><?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach($compareSubjects as $sub): ?>
        <tr>
            <td><strong><?= escape($sub) ?></strong></td>
            <?php foreach($compareClasses as $cl): ?>
            <?php $v = $compareMatrix[$cl][$sub] ?? null; $color = $v ? ($v>=65?'#10b981':($v>=50?'#f0b429':'#ef4444')) : 'var(--text-muted)'; ?>
            <td style="text-align:center;">
                <?php if($v !== null): ?>
                <span style="display:inline-block;padding:3px 8px;border-radius:12px;background:<?= $v>=65?'rgba(16,185,129,.15)':($v>=50?'rgba(240,180,41,.15)':'rgba(239,68,68,.15)') ?>;color:<?= $color ?>;font-weight:700;font-size:12px;"><?= $v ?>%</span>
                <?php else: ?><span style="color:var(--text-muted);">—</span><?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<script>
const chartColors = ['#d4930d','#10b981','#3b82f6','#ef4444','#8b5cf6','#f59e0b','#06b6d4','#ec4899'];
const chartOpts = { responsive:true, plugins:{ legend:{ labels:{ color:'#94a3b8', font:{size:12} } } }, scales:{ x:{ ticks:{color:'#94a3b8'}, grid:{color:'rgba(255,255,255,.05)'} }, y:{ ticks:{color:'#94a3b8'}, grid:{color:'rgba(255,255,255,.05)'}, min:0, max:100 } } };

// Classes
new Chart(document.getElementById('chartClasses'), {
    type:'bar',
    data:{
        labels:<?= json_encode(array_column($clasAvg,'name')) ?>,
        datasets:[{
            label:'Moyenne (%)',
            data:<?= json_encode(array_column($clasAvg,'avg')) ?>,
            backgroundColor:chartColors.map(c=>c+'99'),
            borderColor:chartColors,
            borderWidth:2, borderRadius:6
        }]
    },
    options:{...chartOpts, plugins:{...chartOpts.plugins, legend:{display:false}}}
});

// Mentions (camembert)
new Chart(document.getElementById('chartMentions'), {
    type:'doughnut',
    data:{
        labels:['Très Bien','Bien','Assez Bien','Passable','Insuffisant'],
        datasets:[{
            data:[<?= $mentions['tres_bien']?:0 ?>,<?= $mentions['bien']?:0 ?>,<?= $mentions['assez_bien']?:0 ?>,<?= $mentions['passable']?:0 ?>,<?= $mentions['insuffisant']?:0 ?>],
            backgroundColor:['#10b981','#3b82f6','#f0b429','#f97316','#ef4444'],
            borderWidth:0
        }]
    },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right', labels:{ color:'#94a3b8', font:{size:11} } } } }
});

// Évolution trimestrielle (courbe)
new Chart(document.getElementById('chartEvolution'), {
    type:'line',
    data:{
        labels:<?= json_encode(array_column($evolution,'period')) ?>,
        datasets:[{
            label:'Moyenne globale (%)',
            data:<?= json_encode(array_column($evolution,'avg')) ?>,
            borderColor:'#d4930d', backgroundColor:'rgba(212,147,13,.15)',
            tension:0.4, fill:true, borderWidth:3,
            pointBackgroundColor:'#d4930d', pointRadius:6
        }]
    },
    options:chartOpts
});

// Matières (barres horizontales)
new Chart(document.getElementById('chartSubjects'), {
    type:'bar',
    data:{
        labels:<?= json_encode(array_column($subAvg,'name')) ?>,
        datasets:[{
            label:'Moyenne (%)',
            data:<?= json_encode(array_column($subAvg,'avg')) ?>,
            backgroundColor:'rgba(59,130,246,.7)', borderColor:'#3b82f6',
            borderWidth:2, borderRadius:4
        }]
    },
    options:{
        indexAxis:'y',
        responsive:true,
        plugins:{ legend:{display:false} },
        scales:{ x:{ ticks:{color:'#94a3b8'}, grid:{color:'rgba(255,255,255,.05)'}, min:0, max:100 }, y:{ ticks:{color:'#94a3b8', font:{size:11}}, grid:{display:false} } }
    }
});

// Absences
new Chart(document.getElementById('chartAbsences'), {
    type:'bar',
    data:{
        labels:<?= json_encode(array_column($absClass,'name')) ?>,
        datasets:[
            { label:'Justifiées', data:<?= json_encode(array_column($absClass,'justified')) ?>, backgroundColor:'rgba(16,185,129,.7)', borderRadius:4 },
            { label:'Non justifiées', data:<?= json_encode(array_column($absClass,'unjustified')) ?>, backgroundColor:'rgba(239,68,68,.7)', borderRadius:4 }
        ]
    },
    options:{...chartOpts, plugins:{...chartOpts.plugins, legend:{labels:{color:'#94a3b8'}}}, scales:{...chartOpts.scales, y:{...chartOpts.scales.y, min:undefined, max:undefined}}}
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php require_once __DIR__ . '/../includes/header.php'; requireRole('parent');

// ── Récupérer les enfants ─────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as name, s.first_name, s.last_name,
           s.matricule, c.name as class_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.parent_id = ?
    ORDER BY s.first_name
");
$stmt->execute([$currentUser['id']]);
$children = $stmt->fetchAll();

$selectedChildId = intval($_GET['child_id'] ?? ($children[0]['id'] ?? 0));
$currentChild = null;
foreach ($children as $c) {
    if ($c['id'] == $selectedChildId) {
        $currentChild = $c;
        break;
    }
}
if (!$currentChild && !empty($children)) {
    $currentChild = $children[0];
    $selectedChildId = $currentChild['id'];
}

$absences = [];
$weeksData = [];
$currentWeekStats = [
    'total'       => 0,
    'justified'   => 0,
    'unjustified' => 0,
    'days'        => ['Mon'=>0, 'Tue'=>0, 'Wed'=>0, 'Thu'=>0, 'Fri'=>0, 'Sat'=>0]
];

$thisWeekMon = date('Y-m-d', strtotime('monday this week'));
$thisWeekSat = date('Y-m-d', strtotime('saturday this week'));

$frenchDays = [
    'Monday'    => 'Lundi',
    'Tuesday'   => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday'  => 'Jeudi',
    'Friday'    => 'Vendredi',
    'Saturday'  => 'Samedi',
    'Sunday'    => 'Dimanche'
];

if ($selectedChildId) {
    $stmtAbs = $pdo->prepare("
        SELECT a.*, sub.name as subject_name, u.name as recorded_name
        FROM absences a
        LEFT JOIN subjects sub ON a.subject_id = sub.id
        LEFT JOIN users u ON a.recorded_by = u.id
        WHERE a.student_id = ?
        ORDER BY a.date DESC, a.id DESC
    ");
    $stmtAbs->execute([$selectedChildId]);
    $absences = $stmtAbs->fetchAll();

    // ── Grouper par semaine pour le récapitulatif ─────────────
    foreach ($absences as $a) {
        $aDate = $a['date'];
        $aTimestamp = strtotime($aDate);
        $wMon = date('Y-m-d', strtotime('monday this week', $aTimestamp));
        $wSat = date('Y-m-d', strtotime('saturday this week', $aTimestamp));
        $wKey = $wMon . '_' . $wSat;

        if (!isset($weeksData[$wKey])) {
            $weeksData[$wKey] = [
                'monday'      => $wMon,
                'saturday'    => $wSat,
                'total'       => 0,
                'justified'   => 0,
                'unjustified' => 0,
                'is_current'  => ($wMon === $thisWeekMon),
                'days'        => ['Mon'=>0, 'Tue'=>0, 'Wed'=>0, 'Thu'=>0, 'Fri'=>0, 'Sat'=>0],
                'absences'    => []
            ];
        }

        $weeksData[$wKey]['total']++;
        if ($a['justified']) {
            $weeksData[$wKey]['justified']++;
        } else {
            $weeksData[$wKey]['unjustified']++;
        }

        $shortDay = date('D', $aTimestamp);
        if (isset($weeksData[$wKey]['days'][$shortDay])) {
            $weeksData[$wKey]['days'][$shortDay]++;
        }

        $weeksData[$wKey]['absences'][] = $a;

        // Stats semaine en cours
        if ($aDate >= $thisWeekMon && $aDate <= $thisWeekSat) {
            $currentWeekStats['total']++;
            if ($a['justified']) $currentWeekStats['justified']++;
            else $currentWeekStats['unjustified']++;
            if (isset($currentWeekStats['days'][$shortDay])) {
                $currentWeekStats['days'][$shortDay]++;
            }
        }
    }
}

// Totaux globaux
$totalAll = count($absences);
$totalJust = count(array_filter($absences, fn($x) => $x['justified']));
$totalUnjust = $totalAll - $totalJust;
?>

<div class="page-header">
    <div>
        <h2>📋 Suivi des Absences &amp; Assiduité</h2>
        <p>Absences quotidiennes et récapitulatif de fin de semaine pour vos enfants</p>
    </div>
</div>

<?php if (empty($children)): ?>
<div class="card" style="padding:40px;text-align:center;color:var(--text-muted);">
    Aucun enfant associé à votre compte.
</div>
<?php else: ?>

<!-- ── Sélecteur d'enfant (Boutons) ─────────────────────────── -->
<div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;">
    <?php foreach ($children as $c): ?>
    <a href="?child_id=<?= $c['id'] ?>" class="btn <?= $selectedChildId == $c['id'] ? 'btn-primary' : 'btn-outline' ?>" style="font-weight:700;">
        👶 <?= escape($c['first_name'] . ' ' . $c['last_name']) ?>
        <small style="opacity:0.8;font-weight:400;margin-left:4px;">(<?= escape($c['class_name'] ?? 'Classe') ?>)</small>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Cartes Statistiques Globales ─────────────────────────── -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="icon-box red">📅</div>
        <div>
            <div class="value"><?= $totalAll ?></div>
            <div class="label">Absences au total</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box green">✅</div>
        <div>
            <div class="value"><?= $totalJust ?></div>
            <div class="label">Absences justifiées</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box gold">⚠️</div>
        <div>
            <div class="value" style="color:<?= $totalUnjust > 0 ? '#ef4444' : '#10b981' ?>;"><?= $totalUnjust ?></div>
            <div class="label">Non justifiées</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box blue">⏰</div>
        <div>
            <div class="value"><?= $currentWeekStats['total'] ?></div>
            <div class="label">Cette semaine</div>
        </div>
    </div>
</div>

<!-- ── BILAN DE FIN DE SEMAINE (RÉCAPITULATIF HEBDOMADAIRE) ──── -->
<div class="card" style="margin-bottom:28px;border:1.5px solid var(--border);border-radius:14px;padding:24px;background:linear-gradient(135deg,rgba(99,102,241,.05),rgba(129,140,248,.02));">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-bottom:18px;">
        <div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--primary);margin-bottom:4px;">
                Bilan Hebdomadaire
            </div>
            <h3 style="margin:0;font-size:20px;font-weight:800;display:flex;align-items:center;gap:8px;">
                📊 Récapitulatif de fin de semaine
                <span style="font-size:13px;font-weight:500;color:var(--text-muted);">
                    (Semaine du <?= date('d/m', strtotime($thisWeekMon)) ?> au <?= date('d/m/Y', strtotime($thisWeekSat)) ?>)
                </span>
            </h3>
        </div>

        <?php if ($currentWeekStats['total'] === 0): ?>
        <span style="background:rgba(16,185,129,.15);color:#10b981;padding:6px 16px;border-radius:20px;font-weight:700;font-size:13px;border:1px solid rgba(16,185,129,.3);display:inline-flex;align-items:center;gap:6px;">
            <span>🎉</span> 100% Assiduité (0 absence)
        </span>
        <?php elseif ($currentWeekStats['unjustified'] === 0): ?>
        <span style="background:rgba(240,180,41,.15);color:#d4930d;padding:6px 16px;border-radius:20px;font-weight:700;font-size:13px;border:1px solid rgba(240,180,41,.3);display:inline-flex;align-items:center;gap:6px;">
            <span>ℹ️</span> <?= $currentWeekStats['total'] ?> absence(s) — Toutes justifiées
        </span>
        <?php else: ?>
        <span style="background:rgba(239,68,68,.15);color:#ef4444;padding:6px 16px;border-radius:20px;font-weight:700;font-size:13px;border:1px solid rgba(239,68,68,.3);display:inline-flex;align-items:center;gap:6px;">
            <span>⚠️</span> <?= $currentWeekStats['unjustified'] ?> absence(s) non justifiée(s)
        </span>
        <?php endif; ?>
    </div>

    <!-- Grille jour par jour de la semaine en cours -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(120px, 1fr));gap:12px;margin-top:16px;">
        <?php
        $weekDaysList = [
            'Mon' => 'Lundi',
            'Tue' => 'Mardi',
            'Wed' => 'Mercredi',
            'Thu' => 'Jeudi',
            'Fri' => 'Vendredi',
            'Sat' => 'Samedi'
        ];
        foreach ($weekDaysList as $dCode => $dLabel):
            $dCount = $currentWeekStats['days'][$dCode] ?? 0;
            $isAbsent = ($dCount > 0);
        ?>
        <div style="background:var(--card);border:1.5px solid <?= $isAbsent ? '#ef4444' : 'var(--border)' ?>;border-radius:10px;padding:12px 10px;text-align:center;">
            <div style="font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:6px;"><?= $dLabel ?></div>
            <?php if ($isAbsent): ?>
            <div style="font-size:18px;line-height:1;margin-bottom:4px;">❌</div>
            <div style="font-size:12px;font-weight:700;color:#ef4444;"><?= $dCount ?> absence(s)</div>
            <?php else: ?>
            <div style="font-size:18px;line-height:1;margin-bottom:4px;">✅</div>
            <div style="font-size:12px;color:#10b981;font-weight:600;">Présent</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── HISTORIQUE DE TOUTES LES SEMAINES ──────────────────────── -->
<?php if (!empty($weeksData)): ?>
<div class="card" style="margin-bottom:28px;">
    <div class="card-header">
        <h3>📅 Récapitulatif hebdomadaire archivé</h3>
        <span style="font-size:12px;color:var(--text-muted);"><?= count($weeksData) ?> semaine(s) répertoriée(s)</span>
    </div>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Semaine</th>
                <th style="text-align:center;">Total Absences</th>
                <th style="text-align:center;">Justifiées</th>
                <th style="text-align:center;">Non Justifiées</th>
                <th>Jours affectés</th>
                <th>Bilan</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($weeksData as $w): 
            $wLabel = 'Du ' . date('d/m', strtotime($w['monday'])) . ' au ' . date('d/m/Y', strtotime($w['saturday']));
            $affectedDays = [];
            foreach ($w['days'] as $k => $cnt) {
                if ($cnt > 0) $affectedDays[] = $weekDaysList[$k] . ' (' . $cnt . ')';
            }
        ?>
        <tr>
            <td>
                <strong><?= $wLabel ?></strong>
                <?php if ($w['is_current']): ?>
                <span class="badge badge-primary" style="font-size:10px;margin-left:6px;">Cette semaine</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;font-weight:700;"><?= $w['total'] ?></td>
            <td style="text-align:center;">
                <span style="color:#10b981;font-weight:700;"><?= $w['justified'] ?></span>
            </td>
            <td style="text-align:center;">
                <span style="color:<?= $w['unjustified']>0 ? '#ef4444' : '#10b981' ?>;font-weight:700;"><?= $w['unjustified'] ?></span>
            </td>
            <td style="font-size:12px;color:var(--text-muted);">
                <?= !empty($affectedDays) ? implode(', ', $affectedDays) : 'Aucun jour' ?>
            </td>
            <td>
                <?php if ($w['unjustified'] > 0): ?>
                <span class="badge badge-danger" style="font-size:11px;">À régulariser</span>
                <?php else: ?>
                <span class="badge badge-success" style="font-size:11px;">Régularisée</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- ── JOURNAL QUOTIDIEN DES ABSENCES (JOUR PAR JOUR) ────────── -->
<div class="card">
    <div class="card-header" style="justify-content:space-between;align-items:center;">
        <div>
            <h3>📅 Journal quotidien des absences (Jour par jour)</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0 0;">
                Liste chronologique de chaque absence constatée pour votre enfant
            </p>
        </div>
        <span class="badge badge-primary"><?= count($absences) ?> enregistrement(s)</span>
    </div>

    <?php if (empty($absences)): ?>
    <div style="padding:40px;text-align:center;color:var(--text-muted);">
        <div style="font-size:40px;margin-bottom:10px;">🎉</div>
        Félicitations ! Aucune absence n'a été constatée pour cet élève.
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th style="min-width:130px;">Date &amp; Jour</th>
                <th>Matière concernée</th>
                <th style="min-width:120px;text-align:center;">Statut</th>
                <th>Motif / Justificatif</th>
                <th>Enseignant / Surveillant</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($absences as $a): 
            $dayName = date('l', strtotime($a['date']));
            $frenchDay = $frenchDays[$dayName] ?? $dayName;
            $formattedDate = $frenchDay . ' ' . date('d/m/Y', strtotime($a['date']));
        ?>
        <tr>
            <td>
                <strong><?= $formattedDate ?></strong>
            </td>
            <td>
                <span class="badge badge-primary" style="font-size:11px;">
                    📚 <?= escape($a['subject_name'] ?? 'Séance de cours') ?>
                </span>
            </td>
            <td style="text-align:center;">
                <?php if ($a['justified']): ?>
                <span class="badge badge-success" style="font-size:11px;padding:4px 10px;">
                    ✅ Justifiée
                </span>
                <?php else: ?>
                <span class="badge badge-danger" style="font-size:11px;padding:4px 10px;">
                    ❌ Non justifiée
                </span>
                <?php endif; ?>
            </td>
            <td>
                <div style="font-size:13px;color:<?= $a['reason'] ? 'var(--text)' : 'var(--text-muted)' ?>;">
                    <?= escape($a['reason'] ?: 'Aucun motif renseigné') ?>
                </div>
            </td>
            <td>
                <div style="font-size:12px;color:var(--text-muted);">
                    <?= escape($a['recorded_name'] ?? 'Administration') ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

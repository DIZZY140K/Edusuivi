<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('admin');

// ── Export CSV des moyennes ─────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=rapport_moyennes_'.date('Y-m-d').'.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['Classe', 'Effectif Élèves', 'Moyenne Générale (%)', 'Taux de Réussite (%)'], ';');

    try {
        $stmt = $pdo->query("
            SELECT
                c.name AS class_name,
                COUNT(DISTINCT s.id) AS student_count,
                ROUND(AVG(g.grade / g.max_grade * 100), 1) AS avg_pct,
                ROUND(
                    SUM(CASE WHEN (g.grade / g.max_grade * 100) >= 50 THEN 1 ELSE 0 END)
                    / NULLIF(COUNT(g.id), 0) * 100, 1
                ) AS pass_rate
            FROM classes c
            LEFT JOIN students s ON s.class_id = c.id
            LEFT JOIN grades g   ON g.student_id = s.id AND g.max_grade > 0
            GROUP BY c.id
            ORDER BY avg_pct DESC
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['class_name'] ?? '',
                $row['student_count'] ?? 0,
                $row['avg_pct'] !== null ? $row['avg_pct'] . '%' : 'N/A',
                $row['pass_rate'] !== null ? $row['pass_rate'] . '%' : 'N/A'
            ], ';');
        }
    } catch (Exception $e) {}

    fclose($out);
    exit;
}

require_once __DIR__ . '/../includes/header.php';

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 1 — Global Stats
// ═══════════════════════════════════════════════════════════════════════════
$totalStudents  = 0;
$totalClasses   = 0;
$globalAvg      = 0;
$feeCollectRate = 0;
$totalDue       = 0;
$totalPaid      = 0;
$totalDebt      = 0;

try {
    $totalStudents = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
} catch (Exception $e) {}

try {
    $totalClasses = (int) $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
} catch (Exception $e) {}

try {
    $globalAvg = (float) $pdo->query("SELECT ROUND(AVG(grade/max_grade*100),1) FROM grades WHERE max_grade > 0")->fetchColumn();
} catch (Exception $e) {}

try {
    $feeRow = $pdo->query("
        SELECT
            COALESCE(SUM(sf.amount_due), 0)                                     AS total_due,
            COALESCE((SELECT SUM(fp.amount_paid) FROM fee_payments fp), 0)       AS total_paid
        FROM student_fees sf
    ")->fetch();
    $totalDue       = (float) ($feeRow['total_due']  ?? 0);
    $totalPaid      = (float) ($feeRow['total_paid'] ?? 0);
    $totalDebt      = max(0, $totalDue - $totalPaid);
    $feeCollectRate = $totalDue > 0 ? round($totalPaid / $totalDue * 100, 1) : 0;
} catch (Exception $e) {}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 2 — Performance par classe
// ═══════════════════════════════════════════════════════════════════════════
$classesPerfData = [];
try {
    $stmt = $pdo->query("
        SELECT
            c.name                                                          AS class_name,
            COUNT(DISTINCT s.id)                                            AS student_count,
            ROUND(AVG(g.grade / g.max_grade * 100), 1)                     AS avg_pct,
            ROUND(
                SUM(CASE WHEN (g.grade / g.max_grade * 100) >= 50 THEN 1 ELSE 0 END)
                / COUNT(g.id) * 100, 1
            )                                                               AS pass_rate
        FROM classes c
        LEFT JOIN students s ON s.class_id = c.id
        LEFT JOIN grades g   ON g.student_id = s.id AND g.max_grade > 0
        GROUP BY c.id
        ORDER BY avg_pct DESC
    ");
    $classesPerfData = $stmt->fetchAll();
} catch (Exception $e) {}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 3 — Top 10 meilleurs élèves
// ═══════════════════════════════════════════════════════════════════════════
$top10 = [];
try {
    $stmt = $pdo->query("
        SELECT
            CONCAT(s.last_name, ' ', s.first_name)          AS full_name,
            c.name                                           AS class_name,
            ROUND(AVG(g.grade / g.max_grade * 100), 1)      AS avg_pct
        FROM students s
        JOIN grades g   ON g.student_id = s.id AND g.max_grade > 0
        LEFT JOIN classes c ON s.class_id = c.id
        GROUP BY s.id
        ORDER BY avg_pct DESC
        LIMIT 10
    ");
    $top10 = $stmt->fetchAll();
} catch (Exception $e) {}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 4 — Performance par matière
// ═══════════════════════════════════════════════════════════════════════════
$subjectPerf = [];
try {
    $stmt = $pdo->query("
        SELECT
            sub.name                                         AS subject_name,
            COUNT(g.id)                                      AS grade_count,
            ROUND(AVG(g.grade / g.max_grade * 100), 1)      AS avg_pct
        FROM subjects sub
        JOIN grades g ON g.subject_id = sub.id AND g.max_grade > 0
        GROUP BY sub.id
        ORDER BY avg_pct DESC
    ");
    $subjectPerf = $stmt->fetchAll();
} catch (Exception $e) {}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 5 — Absences par classe
// ═══════════════════════════════════════════════════════════════════════════
$absenceData = [];
try {
    $stmt = $pdo->query("
        SELECT
            c.name                                           AS class_name,
            COUNT(a.id)                                      AS total_abs,
            SUM(CASE WHEN a.justified = 0 THEN 1 ELSE 0 END) AS unjustified
        FROM classes c
        JOIN students s ON s.class_id = c.id
        JOIN absences a ON a.student_id = s.id
        GROUP BY c.id
        ORDER BY total_abs DESC
    ");
    $absenceData = $stmt->fetchAll();
} catch (Exception $e) {}

// ═══════════════════════════════════════════════════════════════════════════
// Helper: colour coding for averages
// ═══════════════════════════════════════════════════════════════════════════
function avgColor($v) {
    if ($v === null || $v === '' || $v == 0) return '#94a3b8';
    if ($v >= 70) return '#10b981';
    if ($v >= 50) return '#f59e0b';
    return '#ef4444';
}
function avgBg($v) {
    if ($v === null || $v === '' || $v == 0) return 'rgba(148,163,184,.1)';
    if ($v >= 70) return 'rgba(16,185,129,.15)';
    if ($v >= 50) return 'rgba(245,158,11,.15)';
    return 'rgba(239,68,68,.15)';
}
?>

<!-- ═══════════ PAGE HEADER ═══════════ -->
<div class="page-header">
    <div>
        <h2>📈 Rapports &amp; Analyses</h2>
        <p>Vue d'ensemble des performances académiques et financières</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <a href="statistics.php" class="btn btn-outline">📊 Graphiques</a>
        <button class="btn btn-primary no-print" onclick="window.print()">🖨️ Imprimer</button>
    </div>
</div>

<!-- ═══════════ STAT CARDS ═══════════ -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:28px;">
    <div class="stat-card">
        <div class="icon-box blue">👥</div>
        <div>
            <div class="value"><?= number_format($totalStudents) ?></div>
            <div class="label">Élèves inscrits</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box gold">📊</div>
        <div>
            <div class="value"><?= $globalAvg > 0 ? $globalAvg . '%' : '—' ?></div>
            <div class="label">Moyenne globale</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box green">💰</div>
        <div>
            <div class="value"><?= $feeCollectRate > 0 ? $feeCollectRate . '%' : '—' ?></div>
            <div class="label">Taux recouvrement</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box red">🏫</div>
        <div>
            <div class="value"><?= number_format($totalClasses) ?></div>
            <div class="label">Classes actives</div>
        </div>
    </div>
</div>

<!-- ═══════════ PERFORMANCE PAR CLASSE ═══════════ -->
<div class="card" style="margin-bottom:28px;">
    <div class="card-header">
        <h3>🏫 Performance par classe</h3>
        <span style="font-size:13px;color:var(--text-muted);"><?= count($classesPerfData) ?> classe(s)</span>
    </div>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Classe</th>
                    <th style="text-align:center;">Élèves</th>
                    <th style="text-align:center;">Moyenne</th>
                    <th style="text-align:center;">Réussite %</th>
                    <th style="min-width:180px;">Barre visuelle</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($classesPerfData): ?>
                <?php foreach ($classesPerfData as $row):
                    $avg      = $row['avg_pct']  ?? 0;
                    $pass     = $row['pass_rate'] ?? 0;
                    $avgC     = avgColor($avg);
                    $avgB     = avgBg($avg);
                    $passC    = avgColor($pass);
                ?>
                <tr>
                    <td><strong><?= escape($row['class_name']) ?></strong></td>
                    <td style="text-align:center;">
                        <span style="font-weight:600;"><?= (int)$row['student_count'] ?></span>
                    </td>
                    <td style="text-align:center;">
                        <span style="display:inline-block;padding:3px 10px;border-radius:20px;
                            background:<?= $avgB ?>;color:<?= $avgC ?>;font-weight:700;font-size:13px;">
                            <?= $avg > 0 ? $avg . '%' : '—' ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <span style="display:inline-block;padding:3px 10px;border-radius:20px;
                            background:<?= avgBg($pass) ?>;color:<?= $passC ?>;font-weight:700;font-size:13px;">
                            <?= $pass > 0 ? $pass . '%' : '—' ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;background:rgba(255,255,255,.08);border-radius:6px;height:10px;overflow:hidden;">
                                <div style="height:100%;width:<?= min(100, $avg) ?>%;
                                    background:linear-gradient(90deg,<?= $avgC ?>,<?= $avgC ?>cc);
                                    border-radius:6px;transition:.4s;"></div>
                            </div>
                            <span style="font-size:11px;color:var(--text-muted);min-width:36px;"><?= $avg > 0 ? $avg . '%' : '—' ?></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px;">
                        Aucune donnée de performance disponible
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════ TOP 10 MEILLEURS ÉLÈVES ═══════════ -->
<div class="card" style="margin-bottom:28px;">
    <div class="card-header">
        <h3>🏆 Top 10 meilleurs élèves</h3>
        <span style="font-size:13px;color:var(--text-muted);">Classement général toutes classes</span>
    </div>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;text-align:center;">#</th>
                    <th>Élève</th>
                    <th>Classe</th>
                    <th style="text-align:center;">Moyenne</th>
                    <th style="min-width:160px;">Performance</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($top10): ?>
                <?php foreach ($top10 as $i => $s):
                    $rank  = $i + 1;
                    $avg   = $s['avg_pct'] ?? 0;
                    $avgC  = avgColor($avg);
                    $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : $rank));
                    $rankBg = $rank <= 3 ? 'var(--primary)' : 'rgba(255,255,255,.08)';
                    $rankFg = $rank <= 3 ? '#000' : 'var(--text-muted)';
                ?>
                <tr style="<?= $rank <= 3 ? 'background:rgba(212,147,13,.04);' : '' ?>">
                    <td style="text-align:center;">
                        <div style="width:32px;height:32px;border-radius:50%;
                            background:<?= $rankBg ?>;color:<?= $rankFg ?>;
                            display:flex;align-items:center;justify-content:center;
                            font-weight:800;font-size:<?= $rank <= 3 ? '16px' : '12px' ?>;margin:auto;">
                            <?= $medal ?>
                        </div>
                    </td>
                    <td>
                        <strong style="font-size:14px;"><?= escape($s['full_name']) ?></strong>
                    </td>
                    <td>
                        <span style="font-size:12px;color:var(--text-muted);
                            background:rgba(255,255,255,.06);padding:2px 8px;border-radius:10px;">
                            <?= escape($s['class_name'] ?? '—') ?>
                        </span>
                    </td>
                    <td style="text-align:center;">
                        <span style="display:inline-block;padding:4px 12px;border-radius:20px;
                            background:<?= avgBg($avg) ?>;color:<?= $avgC ?>;
                            font-weight:800;font-size:14px;">
                            <?= $avg > 0 ? $avg . '%' : '—' ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <div style="flex:1;background:rgba(255,255,255,.08);border-radius:6px;height:8px;overflow:hidden;">
                                <div style="height:100%;width:<?= min(100, $avg) ?>%;
                                    background:<?= $avgC ?>;border-radius:6px;"></div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px;">
                        Aucune note enregistrée
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ═══════════ GRILLE MATIÈRES + ABSENCES ═══════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">

    <!-- Performance par matière -->
    <div class="card">
        <div class="card-header">
            <h3>📚 Performance par matière</h3>
            <span style="font-size:12px;color:var(--text-muted);"><?= count($subjectPerf) ?> matière(s)</span>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th style="text-align:center;">Notes</th>
                        <th style="text-align:center;">Moyenne</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($subjectPerf): ?>
                    <?php foreach ($subjectPerf as $sub):
                        $avg  = $sub['avg_pct'] ?? 0;
                        $avgC = avgColor($avg);
                        $avgB = avgBg($avg);
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="width:8px;height:8px;border-radius:50%;background:<?= $avgC ?>;flex-shrink:0;"></div>
                                <span style="font-weight:500;"><?= escape($sub['subject_name']) ?></span>
                            </div>
                        </td>
                        <td style="text-align:center;color:var(--text-muted);font-size:12px;">
                            <?= number_format((int)$sub['grade_count']) ?>
                        </td>
                        <td style="text-align:center;">
                            <span style="display:inline-block;padding:2px 10px;border-radius:20px;
                                background:<?= $avgB ?>;color:<?= $avgC ?>;font-weight:700;font-size:12px;">
                                <?= $avg > 0 ? $avg . '%' : '—' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center;color:var(--text-muted);padding:28px;">
                            Aucune matière avec des notes
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Absences par classe -->
    <div class="card">
        <div class="card-header">
            <h3>📅 Absences par classe</h3>
            <span style="font-size:12px;color:var(--text-muted);"><?= count($absenceData) ?> classe(s)</span>
        </div>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Classe</th>
                        <th style="text-align:center;">Total</th>
                        <th style="text-align:center;">Non justifiées</th>
                        <th style="min-width:90px;">Répartition</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($absenceData): ?>
                    <?php foreach ($absenceData as $abs):
                        $total      = (int) $abs['total_abs'];
                        $unjust     = (int) $abs['unjustified'];
                        $unjustPct  = $total > 0 ? round($unjust / $total * 100) : 0;
                        $unjustC    = $unjustPct >= 50 ? '#ef4444' : ($unjustPct >= 25 ? '#f59e0b' : '#10b981');
                    ?>
                    <tr>
                        <td><strong><?= escape($abs['class_name']) ?></strong></td>
                        <td style="text-align:center;font-weight:700;"><?= $total ?></td>
                        <td style="text-align:center;">
                            <span style="color:#ef4444;font-weight:700;"><?= $unjust ?></span>
                            <span style="font-size:11px;color:var(--text-muted);">(<?= $unjustPct ?>%)</span>
                        </td>
                        <td>
                            <div style="background:rgba(255,255,255,.08);border-radius:4px;height:8px;overflow:hidden;position:relative;">
                                <!-- Justifiées (vert) -->
                                <div style="position:absolute;left:0;top:0;height:100%;
                                    width:<?= 100 - $unjustPct ?>%;background:#10b981;"></div>
                                <!-- Non justifiées (rouge) -->
                                <div style="position:absolute;right:0;top:0;height:100%;
                                    width:<?= $unjustPct ?>%;background:#ef4444;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center;color:var(--text-muted);padding:28px;">
                            Aucune absence enregistrée
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Légende -->
        <?php if ($absenceData): ?>
        <div style="padding:12px 16px;border-top:1px solid var(--border);display:flex;gap:16px;">
            <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted);">
                <div style="width:10px;height:10px;border-radius:2px;background:#10b981;"></div> Justifiées
            </div>
            <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted);">
                <div style="width:10px;height:10px;border-radius:2px;background:#ef4444;"></div> Non justifiées
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- ═══════════ RÉCAPITULATIF FINANCIER ═══════════ -->
<div class="card" style="margin-bottom:28px;">
    <div class="card-header">
        <h3>💰 Récapitulatif financier</h3>
        <a href="fees.php" class="btn btn-outline btn-sm no-print">Gérer les frais →</a>
    </div>
    <div style="padding:24px;">

        <!-- Chiffres clés -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:28px;">
            <!-- Total dû -->
            <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);
                border-radius:12px;padding:20px;text-align:center;">
                <div style="font-size:11px;color:#3b82f6;font-weight:700;letter-spacing:1px;
                    text-transform:uppercase;margin-bottom:8px;">💳 Total dû</div>
                <div style="font-size:26px;font-weight:800;color:var(--text);">
                    <?= number_format($totalDue, 0, ',', ' ') ?>
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Francs congolais</div>
            </div>

            <!-- Total perçu -->
            <div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);
                border-radius:12px;padding:20px;text-align:center;">
                <div style="font-size:11px;color:#10b981;font-weight:700;letter-spacing:1px;
                    text-transform:uppercase;margin-bottom:8px;">✅ Total perçu</div>
                <div style="font-size:26px;font-weight:800;color:#10b981;">
                    <?= number_format($totalPaid, 0, ',', ' ') ?>
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Francs congolais</div>
            </div>

            <!-- Reste à percevoir -->
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
                border-radius:12px;padding:20px;text-align:center;">
                <div style="font-size:11px;color:#ef4444;font-weight:700;letter-spacing:1px;
                    text-transform:uppercase;margin-bottom:8px;">⚠️ Reste à percevoir</div>
                <div style="font-size:26px;font-weight:800;color:#ef4444;">
                    <?= number_format($totalDebt, 0, ',', ' ') ?>
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Francs congolais</div>
            </div>
        </div>

        <!-- Barre de recouvrement -->
        <div style="background:rgba(255,255,255,.04);border:1px solid var(--border);
            border-radius:12px;padding:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div>
                    <div style="font-weight:600;font-size:15px;">Taux de recouvrement global</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                        Montant perçu sur montant total dû
                    </div>
                </div>
                <div style="font-size:32px;font-weight:800;
                    color:<?= $feeCollectRate >= 75 ? '#10b981' : ($feeCollectRate >= 50 ? '#f59e0b' : '#ef4444') ?>;">
                    <?= $feeCollectRate ?>%
                </div>
            </div>

            <!-- Barre principale -->
            <div style="background:rgba(255,255,255,.08);border-radius:8px;height:18px;overflow:hidden;position:relative;">
                <?php
                $barColor = $feeCollectRate >= 75 ? '#10b981' : ($feeCollectRate >= 50 ? '#f59e0b' : '#ef4444');
                $barGrad  = $feeCollectRate >= 75
                    ? 'linear-gradient(90deg,#059669,#10b981)'
                    : ($feeCollectRate >= 50
                        ? 'linear-gradient(90deg,#d97706,#f59e0b)'
                        : 'linear-gradient(90deg,#dc2626,#ef4444)');
                ?>
                <div style="height:100%;width:<?= min(100, $feeCollectRate) ?>%;
                    background:<?= $barGrad ?>;border-radius:8px;
                    transition:width .5s ease;position:relative;overflow:hidden;">
                    <!-- Shimmer overlay -->
                    <div style="position:absolute;top:0;left:0;right:0;bottom:0;
                        background:linear-gradient(90deg,transparent 0%,rgba(255,255,255,.15) 50%,transparent 100%);"></div>
                </div>
            </div>

            <!-- Repères -->
            <div style="display:flex;justify-content:space-between;margin-top:6px;">
                <span style="font-size:11px;color:var(--text-muted);">0%</span>
                <span style="font-size:11px;color:#f59e0b;">50%</span>
                <span style="font-size:11px;color:#10b981;">75%</span>
                <span style="font-size:11px;color:var(--text-muted);">100%</span>
            </div>

            <!-- Indicateurs de statut -->
            <div style="display:flex;gap:20px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
                    <div style="width:10px;height:10px;border-radius:50%;background:#10b981;"></div>
                    <span style="color:var(--text-muted);">≥ 75% — Bon</span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
                    <div style="width:10px;height:10px;border-radius:50%;background:#f59e0b;"></div>
                    <span style="color:var(--text-muted);">50–75% — Moyen</span>
                </div>
                <div style="display:flex;align-items:center;gap:6px;font-size:12px;">
                    <div style="width:10px;height:10px;border-radius:50%;background:#ef4444;"></div>
                    <span style="color:var(--text-muted);">&lt; 50% — Critique</span>
                </div>
                <?php if ($totalDue == 0): ?>
                <div style="font-size:12px;color:var(--text-muted);font-style:italic;">
                    Aucun frais enregistré
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════ PRINT CSS ═══════════ -->
<style>
@media print {
    .no-print,
    .sidebar,
    .mobile-toggle,
    .mobile-overlay,
    .header,
    .lang-switcher,
    .sidebar-footer,
    .page-header .btn,
    .page-header button,
    .card-header .btn,
    .card-header a { display: none !important; }

    body, html { background: #fff !important; color: #000 !important; }

    .app-layout  { display: block !important; }
    .main-wrapper { margin: 0 !important; padding: 0 !important; }
    .main-content { padding: 12px !important; }

    .card {
        border: 1px solid #ccc !important;
        box-shadow: none !important;
        break-inside: avoid;
        margin-bottom: 16px !important;
    }

    table { font-size: 11px !important; }
    th, td { border: 1px solid #ddd !important; padding: 5px 8px !important; color: #000 !important; }
    th { background: #f0f0f0 !important; }

    .stats-grid { display: flex !important; flex-wrap: wrap !important; gap: 10px !important; }
    .stat-card  { border: 1px solid #ccc !important; padding: 10px !important; flex: 1 !important; min-width: 120px !important; }
    .stat-card .value { color: #000 !important; }

    .page-header h2 { color: #000 !important; font-size: 20px !important; }
    .page-header p  { color: #555 !important; }

    .card-header h3 { color: #000 !important; }
    .card-header    { background: #f5f5f5 !important; border-bottom: 1px solid #ccc !important; }

    /* Grid → stack on print */
    div[style*="grid-template-columns:1fr 1fr"] {
        display: block !important;
    }
    div[style*="grid-template-columns:repeat(3,1fr)"] {
        display: flex !important;
        gap: 10px !important;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

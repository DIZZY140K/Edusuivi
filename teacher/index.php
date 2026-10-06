<?php require_once __DIR__ . '/../includes/header.php'; requireRole('teacher');

// Affectations
$stmt = $pdo->prepare("SELECT ta.*, c.name as class_name, sub.name as subject_name, c.id as class_id, sub.id as sub_id, (SELECT COUNT(*) FROM students WHERE class_id=c.id) as students_count FROM teacher_assignments ta JOIN classes c ON ta.class_id=c.id JOIN subjects sub ON ta.subject_id=sub.id WHERE ta.teacher_id=?");
$stmt->execute([$currentUser['id']]);
$assignments = $stmt->fetchAll();
$totalStudents = array_sum(array_column($assignments, 'students_count'));

// Titulaire de quelles classes ?
$titulaire = $pdo->prepare("SELECT name FROM classes WHERE teacher_id=?");
$titulaire->execute([$currentUser['id']]);
$titularClasses = $titulaire->fetchAll(PDO::FETCH_COLUMN);

// Absences non justifiées
$absStmt = $pdo->prepare("SELECT a.*, s.first_name, s.last_name, c.name as class_name FROM absences a JOIN students s ON a.student_id=s.id JOIN classes c ON s.class_id=c.id WHERE a.recorded_by=? AND a.justified=0 ORDER BY a.date DESC LIMIT 5");
$absStmt->execute([$currentUser['id']]);
$unjustifiedAbs = $absStmt->fetchAll();

// Agenda
$upcomingEvents = $pdo->query("SELECT title, event_date FROM agenda_events WHERE target IN ('all','teachers') AND event_date >= CURRENT_DATE ORDER BY event_date ASC LIMIT 3")->fetchAll();

// Dernières notes que j'ai saisies
$recentGrades = $pdo->prepare("SELECT g.*, s.first_name, s.last_name, sub.name as subject_name FROM grades g JOIN students s ON g.student_id = s.id JOIN subjects sub ON g.subject_id = sub.id WHERE g.teacher_id=? ORDER BY g.created_at DESC LIMIT 5");
$recentGrades->execute([$currentUser['id']]);
$recentGrades = $recentGrades->fetchAll();
?>

<div class="page-header">
    <div>
        <h2>Tableau de bord</h2>
        <p>Espace Enseignant — <?= escape($currentUser['name']) ?></p>
    </div>
</div>

<?php if($titularClasses): ?>
<div class="alert alert-success" style="border-left: 4px solid var(--success); background: var(--success-light); color: var(--success);">
    🎓 Vous êtes titulaire de la (des) classe(s) : <strong><?= escape(implode(', ', $titularClasses)) ?></strong>.
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card"><div class="icon-box blue">🏫</div><div><div class="value"><?= count($assignments) ?></div><div class="label">Classes/Matières</div></div></div>
    <div class="stat-card"><div class="icon-box gold">👨‍🎓</div><div><div class="value"><?= $totalStudents ?></div><div class="label">Élèves concernés</div></div></div>
    <div class="stat-card"><div class="icon-box green">📝</div><div><div class="value"><?= $pdo->prepare("SELECT COUNT(*) FROM grades WHERE teacher_id=?")->execute([$currentUser['id']]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0; //fallback ?>
        <?php $cntG = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE teacher_id=?"); $cntG->execute([$currentUser['id']]); echo $cntG->fetchColumn(); ?>
    </div><div class="label">Notes saisies</div></div></div>
    <div class="stat-card"><div class="icon-box red">⚠️</div><div><div class="value"><?= count($unjustifiedAbs) ?></div><div class="label">Absences non justifiées</div></div></div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">

    <!-- Colonne Principale -->
    <div>
        <!-- Mes Affectations -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header" style="justify-content:space-between;">
                <h3>Mes Cours et Classes</h3>
                <a href="grades.php" class="btn btn-sm btn-primary">Saisir des notes</a>
            </div>
            <div style="padding:16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <?php foreach($assignments as $a): ?>
                <a href="grades.php?class_id=<?= $a['class_id'] ?>&subject_id=<?= $a['sub_id'] ?>" style="display:block;text-decoration:none;background:rgba(255,255,255,.02);border:1px solid var(--border);border-radius:8px;padding:16px;transition:.2s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
                    <div style="color:var(--primary);font-weight:700;font-size:16px;margin-bottom:4px;"><?= escape($a['class_name']) ?></div>
                    <div style="color:var(--text);font-size:13px;margin-bottom:12px;"><?= escape($a['subject_name']) ?></div>
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);">
                        <span>👨‍🎓 <?= $a['students_count'] ?> élèves</span>
                        <span>Saisir →</span>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if(!$assignments): ?><p style="color:var(--text-muted);font-size:13px;text-align:center;width:100%;">Aucune classe assignée.</p><?php endif; ?>
            </div>
        </div>

        <!-- Dernières notes -->
        <div class="card">
            <div class="card-header"><h3>📝 Dernières saisies de notes</h3></div>
            <table>
                <thead><tr><th>Élève</th><th>Matière</th><th>Note</th><th>%</th></tr></thead>
                <tbody>
                <?php foreach ($recentGrades as $g): 
                    $pct = round(($g['grade']/$g['max_grade'])*100,1);
                    $color = $pct>=65?'#10b981':($pct>=50?'#f0b429':'#ef4444');
                ?>
                    <tr>
                        <td><strong><?= escape($g['last_name'] . ' ' . $g['first_name']) ?></strong></td>
                        <td style="color:var(--text-muted);font-size:13px;"><?= escape($g['subject_name']) ?></td>
                        <td style="font-weight:700;"><?= $g['grade'] ?>/<?= $g['max_grade'] ?></td>
                        <td><span style="color:<?= $color ?>;font-weight:700;font-size:12px;"><?= $pct ?>%</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if(!$recentGrades): ?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted);">Aucune note saisie récemment.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Colonne latérale -->
    <div>
        <!-- Agenda -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-header" style="justify-content:space-between;">
                <h3>📅 Prochainement</h3>
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
                <?php if(!$upcomingEvents): ?><p style="text-align:center;color:var(--text-muted);font-size:13px;padding:10px 0;">Aucun événement</p><?php endif; ?>
            </div>
        </div>

        <!-- Absences à régulariser -->
        <div class="card">
            <div class="card-header"><h3>⚠️ Absences à régulariser</h3></div>
            <div style="padding:12px;">
                <?php foreach($unjustifiedAbs as $a): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);">
                    <div>
                        <div style="font-size:13px;font-weight:600;"><?= escape($a['last_name'].' '.$a['first_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= escape($a['class_name']) ?> — <?= date('d/m/Y',strtotime($a['date'])) ?></div>
                    </div>
                    <a href="absences.php" class="btn btn-sm btn-outline">Traiter</a>
                </div>
                <?php endforeach; ?>
                <?php if(!$unjustifiedAbs): ?><p style="text-align:center;color:var(--text-muted);font-size:13px;padding:10px 0;">Toutes les absences sont justifiées.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

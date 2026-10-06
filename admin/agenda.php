<?php require_once __DIR__ . '/../includes/header.php';
requireLogin();
$isAdmin = $currentUser['role'] === 'admin';
$isTeacher = $currentUser['role'] === 'teacher';
$success = $error = '';

// Ajouter événement (Directeur / admin only)
if (($_POST['action'] ?? '') === 'add' && $isAdmin) {
    $stmt = $pdo->prepare("INSERT INTO agenda_events (title,description,event_type,event_date,event_time,end_date,target,target_class_id,created_by) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        trim($_POST['title']), trim($_POST['description']),
        $_POST['event_type'], $_POST['event_date'],
        $_POST['event_time'] ?: null, $_POST['end_date'] ?: null,
        $_POST['target'], $_POST['target_class_id'] ?: null,
        $currentUser['id']
    ]);
    // Notifier les utilisateurs cibles
    try {
        $target = $_POST['target'];
        if ($target === 'all') $roles = ['admin','teacher','parent'];
        elseif ($target === 'parents') $roles = ['parent'];
        elseif ($target === 'teachers') $roles = ['teacher'];
        else $roles = [];
        if ($roles) {
            $placeholders = implode(',', array_fill(0, count($roles), '?'));
            $users = $pdo->prepare("SELECT id FROM users WHERE role IN ($placeholders)");
            $users->execute($roles);
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id,type,title,body,link) VALUES (?,?,?,?,?)");
            foreach ($users->fetchAll() as $u) {
                $notifStmt->execute([$u['id'], 'event', '📅 Nouvel événement : '.$_POST['title'], $_POST['event_date'], '/edusuivi/admin/agenda.php']);
            }
        }
    } catch(Exception $e){}
    $success = "Événement ajouté avec succès.";
}

if (isset($_GET['delete']) && $isAdmin) {
    $pdo->prepare("DELETE FROM agenda_events WHERE id=?")->execute([intval($_GET['delete'])]);
    $success = "Événement supprimé.";
}

// Filtre mois
$month = intval($_GET['month'] ?? date('m'));
$year  = intval($_GET['year']  ?? date('Y'));
$filterType = $_GET['type'] ?? '';

$sql = "SELECT e.*, u.name as creator_name FROM agenda_events e JOIN users u ON e.created_by=u.id WHERE MONTH(e.event_date)=? AND YEAR(e.event_date)=?";
$params = [$month, $year];
if ($filterType) { $sql .= " AND e.event_type=?"; $params[] = $filterType; }
// Filtrer selon le rôle
if ($currentUser['role'] === 'parent') {
    $sql .= " AND e.target IN ('all','parents')";
} elseif ($currentUser['role'] === 'teacher') {
    $sql .= " AND e.target IN ('all','teachers')";
}
$sql .= " ORDER BY e.event_date, e.event_time";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$events = $stmt->fetchAll();

// Événements à venir (7 jours)
$upSql = "SELECT e.*, u.name as creator_name FROM agenda_events e JOIN users u ON e.created_by=u.id WHERE e.event_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE,INTERVAL 7 DAY)";
if ($currentUser['role'] === 'parent') {
    $upSql .= " AND e.target IN ('all','parents')";
} elseif ($currentUser['role'] === 'teacher') {
    $upSql .= " AND e.target IN ('all','teachers')";
}
$upSql .= " ORDER BY e.event_date LIMIT 10";
$upcoming = $pdo->query($upSql)->fetchAll();

$classes = $pdo->query("SELECT * FROM classes ORDER BY name")->fetchAll();

$typeColors = ['reunion'=>'#3b82f6','examen'=>'#ef4444','activite'=>'#10b981','conge'=>'#8b5cf6','autre'=>'#f59e0b'];
$typeIcons  = [
    'reunion'  => '🤝',
    'examen'   => '📝',
    'activite' => '🎭',
    'conge'    => '🌴',
    'autre'    => '📌'
];
$typeLabels = ['reunion'=>'Réunion','examen'=>'Examen','activite'=>'Activité','conge'=>'Congé','autre'=>'Autre'];

// Noms de mois
$monthNames = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear  = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear  = $month == 12 ? $year + 1 : $year;
?>

<div class="page-header">
    <div>
        <h2>📅 Agenda scolaire</h2>
        <p>Réunions, examens et activités de l'établissement</p>
    </div>
    <?php if ($isAdmin): ?>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ Ajouter un événement</button>
    <?php endif; ?>
</div>

<?php if($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 300px;gap:24px;">

<!-- Colonne principale -->
<div>
    <!-- Navigation mois -->
    <div class="card" style="padding:16px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-outline btn-sm">← <?= $monthNames[$prevMonth] ?></a>
            <h3 style="margin:0;font-size:20px;"><?= $monthNames[$month] ?> <?= $year ?></h3>
            <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-outline btn-sm"><?= $monthNames[$nextMonth] ?> →</a>
        </div>
        <!-- Filtres type -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
            <a href="?month=<?= $month ?>&year=<?= $year ?>" class="btn btn-sm <?= !$filterType?'btn-primary':'btn-outline' ?>">Tous</a>
            <?php foreach($typeLabels as $k=>$v): ?>
            <a href="?month=<?= $month ?>&year=<?= $year ?>&type=<?= $k ?>" class="btn btn-sm <?= $filterType===$k?'btn-primary':'btn-outline' ?>" style="<?= $filterType===$k?'':'border-color:'.$typeColors[$k].';color:'.$typeColors[$k] ?>"><?= $typeIcons[$k] ?> <?= $v ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Liste des événements -->
    <?php if(empty($events)): ?>
    <div class="card" style="padding:40px;text-align:center;color:var(--text-muted);">
        <div style="font-size:40px;margin-bottom:10px;">📅</div>
        <p>Aucun événement pour ce mois.</p>
    </div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach($events as $e):
        $color   = $typeColors[$e['event_type']] ?? '#f59e0b';
        $icon    = $typeIcons[$e['event_type']] ?? '📌';
        $isToday = $e['event_date'] === date('Y-m-d');
        $isPast  = $e['event_date'] < date('Y-m-d');
    ?>
    <div class="card" style="border-left:4px solid <?= $color ?>;padding:16px 20px;<?= $isToday?'background:rgba(212,147,13,.05);':'' ?><?= $isPast?'opacity:.7;':'' ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
            <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                    <span style="font-size:20px;"><?= $icon ?></span>
                    <strong style="font-size:16px;"><?= escape($e['title']) ?></strong>
                    <?php if($isToday): ?><span class="badge badge-primary">Aujourd'hui</span><?php endif; ?>
                    <span style="font-size:12px;padding:2px 8px;border-radius:20px;background:<?= $color ?>22;color:<?= $color ?>;"><?= $typeLabels[$e['event_type']]??'' ?></span>
                </div>
                <?php if($e['description']): ?>
                <p style="color:var(--text-muted);font-size:13px;margin:0 0 8px;"><?= escape($e['description']) ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:16px;font-size:12px;color:var(--text-muted);">
                    <span>📅 <?= date('d/m/Y', strtotime($e['event_date'])) ?><?= $e['end_date'] ? ' → '.date('d/m/Y',strtotime($e['end_date'])) : '' ?></span>
                    <?php if($e['event_time']): ?><span>⏰ <?= date('H\hi', strtotime($e['event_time'])) ?></span><?php endif; ?>
                    <span>👤 <?= escape($e['creator_name']) ?></span>
                    <span>🎯 <?= $e['target']==='all'?'Tous':($e['target']==='parents'?'Parents':($e['target']==='teachers'?'Enseignants':'Classe')) ?></span>
                </div>
            </div>
            <?php if($isAdmin): ?>
            <button class="btn btn-sm btn-danger" onclick="confirmDelete('Supprimer cet événement ?','?delete=<?= $e['id'] ?>')">🗑</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Colonne latérale : événements à venir -->
<div>
    <div class="card">
        <div class="card-header"><h3>⏰ Dans les 7 prochains jours</h3></div>
        <div style="padding:8px;">
        <?php if(empty($upcoming)): ?>
        <p style="text-align:center;color:var(--text-muted);padding:20px;font-size:13px;">Aucun événement imminent</p>
        <?php else: ?>
        <?php foreach($upcoming as $e):
            $color = $typeColors[$e['event_type']] ?? '#f59e0b';
            $icon  = $typeIcons[$e['event_type']] ?? '📌';
            $days  = (strtotime($e['event_date']) - strtotime(date('Y-m-d'))) / 86400;
        ?>
        <div style="display:flex;gap:10px;padding:10px 8px;border-bottom:1px solid var(--border);">
            <div style="width:36px;height:36px;border-radius:8px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;"><?= $icon ?></div>
            <div style="flex:1;">
                <div style="font-weight:600;font-size:13px;"><?= escape(substr($e['title'],0,35)) ?></div>
                <div style="font-size:11px;color:<?= $color ?>;margin-top:2px;"><?= $days==0?'Aujourd\'hui':($days==1?'Demain':'Dans '.(int)$days.' j') ?> — <?= date('d/m',strtotime($e['event_date'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>

    <!-- Mini stats -->
    <div class="card" style="margin-top:16px;">
        <div class="card-header"><h3>📊 Ce mois</h3></div>
        <div style="padding:16px;">
        <?php foreach($typeLabels as $k=>$v):
            $cnt = count(array_filter($events, fn($e)=>$e['event_type']===$k));
            if(!$cnt) continue;
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);">
            <span style="font-size:13px;"><?= $typeIcons[$k] ?> <?= $v ?></span>
            <span style="font-weight:700;color:<?= $typeColors[$k] ?>;"><?= $cnt ?></span>
        </div>
        <?php endforeach; ?>
        <?php if(empty($events)): ?><p style="color:var(--text-muted);font-size:13px;text-align:center;">Aucun</p><?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Modal Ajouter (Directeur uniquement) -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:580px;">
        <button class="modal-close-btn" onclick="closeModal('addModal')">✕</button>

        <!-- En-tête centré -->
        <div style="text-align:center;padding:24px 24px 16px;border-bottom:1px solid var(--border);margin-bottom:4px;flex-shrink:0;">
            <div style="width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-dark,#1a56db));display:flex;align-items:center;justify-content:center;font-size:24px;margin:0 auto 10px;">📅</div>
            <h3 style="margin:0;font-size:18px;font-weight:700;">Nouvel événement</h3>
            <p style="margin:4px 0 0;font-size:12px;color:var(--text-muted);">Planifiez une activité, réunion ou examen</p>
        </div>

        <form method="POST" style="display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;">
            <div class="modal-body-compact">
                <input type="hidden" name="action" value="add">

                <!-- Section : Informations -->
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin:16px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border);">Informations</div>

                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Titre <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="title" class="form-control" required placeholder="ex: Réunion parents-enseignants">
                    </div>
                    <div class="form-group">
                        <label>Type d'événement</label>
                        <select name="event_type" class="form-control">
                            <?php foreach($typeLabels as $k=>$v): ?>
                            <option value="<?= $k ?>"><?= $typeIcons[$k].' '.$v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Détails optionnels…"></textarea>
                </div>

                <!-- Section : Date & Heure -->
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin:16px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border);">Date &amp; Heure</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date de début <span style="color:var(--danger)">*</span></label>
                        <input type="date" name="event_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Heure</label>
                        <input type="time" name="event_time" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Date de fin</label>
                        <input type="date" name="end_date" class="form-control">
                    </div>
                </div>

                <!-- Section : Destinataires -->
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);margin:16px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border);">Destinataires</div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Public cible</label>
                        <select name="target" class="form-control" id="targetSelect" onchange="document.getElementById('classRow').style.display=this.value==='class'?'flex':'none'">
                            <option value="all">🌐 Tout le monde</option>
                            <option value="parents">👨‍👩‍👧 Parents seulement</option>
                            <option value="teachers">🎓 Enseignants seulement</option>
                            <option value="class">🏫 Une classe</option>
                        </select>
                    </div>
                    <div class="form-group" id="classRow" style="display:none;">
                        <label>Classe concernée</label>
                        <select name="target_class_id" class="form-control">
                            <option value="">— Choisir —</option>
                            <?php foreach($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="modal-actions-compact">
                <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('addModal')">Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">📅 Créer l'événement</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

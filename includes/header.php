<?php
require_once __DIR__ . '/config.php';
requireLogin();
$currentUser = getUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$roleLabels = ['school' => 'Portail École', 'admin' => t('admin_role'), 'teacher' => getTeacherLabel(), 'parent' => t('parent_role')];

$unreadMsgs = getUnreadMessages($pdo, $currentUser['id']);
$unreadNotifs = getUnreadCount($pdo, $currentUser['id']);
$currentLang = $_SESSION['lang'] ?? 'fr';

$navItems = [
    'school' => [
        ['url' => '/edusuivi/school/index.php',  'icon' => 'fa-solid fa-chart-pie', 'label' => 'Tableau de bord', 'key' => 'index'],
        ['url' => '/edusuivi/school/admins.php', 'icon' => 'fa-solid fa-users', 'label' => 'Mon Administrateur','key' => 'admins'],
    ],
    'admin' => [
        ['url' => '/edusuivi/admin/index.php',    'icon' => 'fa-solid fa-chart-pie', 'label' => 'dashboard', 'key'=>'index'],
        ['url' => '/edusuivi/admin/students.php',  'icon' => 'fa-solid fa-user-graduate', 'label' => 'students',  'key'=>'students'],
        ['url' => '/edusuivi/admin/parents.php',   'icon' => 'fa-solid fa-users', 'label' => 'parents',   'key'=>'parents'],
        ['url' => '/edusuivi/admin/teachers.php',  'icon' => 'fa-solid fa-chalkboard-user', 'label' => 'teachers',  'key'=>'teachers'],
        ['url' => '/edusuivi/admin/classes.php',   'icon' => 'fa-solid fa-school', 'label' => 'classes',   'key'=>'classes'],
        ['url' => '/edusuivi/admin/subjects.php',  'icon' => 'fa-solid fa-book-open', 'label' => 'subjects',  'key'=>'subjects'],
        ['url' => '/edusuivi/admin/bulletin.php',  'icon' => 'fa-solid fa-file-lines', 'label' => 'bulletin',  'key'=>'bulletin'],
        ['url' => '/edusuivi/admin/statistics.php','icon' => 'fa-solid fa-chart-line', 'label' => 'statistics','key'=>'statistics'],
        ['url' => '/edusuivi/admin/reports.php',   'icon' => 'fa-solid fa-clipboard-list', 'label' => 'reports',   'key'=>'reports'],
        ['url' => '/edusuivi/admin/agenda.php',    'icon' => 'fa-solid fa-calendar-days', 'label' => 'agenda',    'key'=>'agenda'],
        ['url' => '/edusuivi/admin/fees.php',      'icon' => 'fa-solid fa-sack-dollar', 'label' => 'fees',      'key'=>'fees'],
        ['url' => '/edusuivi/admin/messages.php',  'icon' => 'fa-solid fa-comments', 'label' => 'messages',  'key'=>'messages', 'badge'=>$unreadMsgs],
        ['url' => '/edusuivi/admin/backup.php',    'icon' => 'fa-solid fa-floppy-disk', 'label' => 'backup',    'key'=>'backup']
    ],
    'teacher' => array_merge([
        ['url' => '/edusuivi/teacher/index.php',    'icon' => 'fa-solid fa-chart-pie', 'label' => 'dashboard', 'key'=>'index'],
        ['url' => '/edusuivi/teacher/grades.php',   'icon' => 'fa-solid fa-pen-to-square', 'label' => 'grades',    'key'=>'grades'],
        ['url' => '/edusuivi/teacher/absences.php', 'icon' => 'fa-solid fa-clipboard-list', 'label' => 'absences',  'key'=>'absences'],
    ],
    // "Ma classe" uniquement si le prof est titulaire
    (function() use ($pdo, $currentUser) {
        try {
            $s = $pdo->prepare("SELECT id FROM classes WHERE teacher_id=? LIMIT 1");
            $s->execute([$currentUser['id']]);
            return $s->fetch() ? [['url'=>'/edusuivi/teacher/titulaire.php','icon'=>'fa-solid fa-school','label'=>'Ma classe','key'=>'titulaire']] : [];
        } catch(Exception $e) { return []; }
    })(),
    [
        ['url' => '/edusuivi/admin/agenda.php',      'icon' => 'fa-solid fa-calendar-days', 'label' => 'agenda',   'key'=>'agenda'],
        ['url' => '/edusuivi/teacher/messages.php',  'icon' => 'fa-solid fa-comments', 'label' => 'messages', 'key'=>'messages', 'badge'=>$unreadMsgs]
    ]),
    'parent' => [
        ['url' => '/edusuivi/parent/index.php', 'icon' => 'fa-solid fa-chart-pie', 'label' => 'dashboard', 'key'=>'index'],
        ['url' => '/edusuivi/parent/grades.php', 'icon' => 'fa-solid fa-pen-to-square', 'label' => 'grades', 'key'=>'grades'],
        ['url' => '/edusuivi/parent/absences.php', 'icon' => 'fa-solid fa-clipboard-list', 'label' => 'absences', 'key'=>'absences'],
        ['url' => '/edusuivi/admin/agenda.php',  'icon' => 'fa-solid fa-calendar-days', 'label' => 'agenda', 'key'=>'agenda'],
        ['url' => '/edusuivi/parent/fees.php', 'icon' => 'fa-solid fa-sack-dollar', 'label' => 'fees', 'key'=>'fees'],
        ['url' => '/edusuivi/parent/messages.php', 'icon' => 'fa-solid fa-comments', 'label' => 'messages', 'key'=>'messages', 'badge'=>$unreadMsgs]
    ]
];
$items = $navItems[$currentUser['role']] ?? [];
$langUrl = strtok($_SERVER['REQUEST_URI'], '?');
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduSuivi — <?= t('app_subtitle') ?></title>
    <meta name="description" content="Portail scolaire EduSuivi — Gestion scolaire complète pour la RDC">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
    <link rel="stylesheet" href="/edusuivi/css/style.css?v=<?= filemtime(__DIR__.'/../css/style.css') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<script>
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }
</script>
<div class="app-layout">
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars fa-icon" aria-hidden="true"></i></button>
    <div class="mobile-overlay" onclick="toggleSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon">E</div>
            <div>
                <h1><?= t('app_name') ?></h1>
                <p><?= t('app_subtitle') ?></p>
            </div>
        </div>

        <div class="sidebar-user">
            <div class="avatar"><?= strtoupper(substr($currentUser['name'], 0, 1)) ?></div>
            <div class="info">
                <div class="name"><?= escape($currentUser['name']) ?></div>
                <span class="role-badge"><?= $roleLabels[$currentUser['role']] ?? $currentUser['role'] ?></span>
                <?php
                // Afficher le nom de l'école pour les admins
                if ($currentUser['role'] === 'admin' && !empty($currentUser['school_id'])) {
                    try {
                        $schoolStmt = $pdo->prepare("SELECT name FROM schools WHERE id=?");
                        $schoolStmt->execute([$currentUser['school_id']]);
                        $schoolName = $schoolStmt->fetchColumn();
                        if ($schoolName) {
                            echo '<div style="font-size:10px;color:var(--primary);margin-top:4px;display:flex;align-items:center;gap:4px;"><i class="fa-solid fa-school fa-icon" aria-hidden="true"></i><span>' . escape($schoolName) . '</span></div>';
                        }
                    } catch(Exception $e) {}
                }
                ?>
            </div>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($items as $item): ?>
                <a href="<?= $item['url'] ?>" class="<?= $currentPage === $item['key'] ? 'active' : '' ?>">
                    <span class="icon"><i class="<?= escape($item['icon']) ?> fa-icon" aria-hidden="true"></i></span>
                    <?php
                    // Les items 'school' ont des labels raw, les autres utilisent t()
                    $navLabel = $item['label'];
                    if ($navLabel === 'teachers') {
                        echo getTeacherLabel(true);
                    } elseif (in_array($navLabel, ['dashboard','students','parents','classes','subjects','bulletin','statistics','reports','agenda','fees','messages','backup','grades','absences'])) {
                        echo t($navLabel);
                    } elseif ($navLabel === 'Ma classe') {
                        echo 'Ma classe';
                    } else {
                        echo htmlspecialchars($navLabel, ENT_QUOTES, 'UTF-8');
                    }
                    ?>
                    <?php if (!empty($item['badge']) && $item['badge'] > 0): ?>
                        <span class="nav-badge"><?= $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Sélecteur de mode Primaire / Secondaire -->
        <?php if (in_array($currentUser['role'], ['admin', 'school'])): ?>
        <?php $currentMode = getSchoolMode(); ?>
        <div class="mode-switcher">
            <span class="mode-label"><i class="fa-solid fa-gear fa-icon" aria-hidden="true"></i> Mode de gestion</span>
            <div class="mode-buttons">
                <a href="<?= $langUrl ?>?mode=primaire" class="mode-btn <?= $currentMode==='primaire'?'active-primaire':'' ?>" title="École Primaire">
                    <i class="fa-solid fa-school fa-icon" aria-hidden="true"></i> Primaire
                </a>
                <a href="<?= $langUrl ?>?mode=secondaire" class="mode-btn <?= $currentMode==='secondaire'?'active-secondaire':'' ?>" title="École Secondaire">
                    <i class="fa-solid fa-graduation-cap fa-icon" aria-hidden="true"></i> Secondaire
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sélecteur de langue -->
        <div class="lang-switcher">
            <span class="lang-label"><i class="fa-solid fa-globe fa-icon" aria-hidden="true"></i> <?= t('language') ?></span>
            <div class="lang-flags">
                <a href="<?= $langUrl ?>?lang=fr" class="<?= $currentLang==='fr'?'active':'' ?>" title="Français">FR</a>
                <a href="<?= $langUrl ?>?lang=ln" class="<?= $currentLang==='ln'?'active':'' ?>" title="Lingala">LN</a>
                <a href="<?= $langUrl ?>?lang=sw" class="<?= $currentLang==='sw'?'active':'' ?>" title="Swahili">SW</a>
                <a href="<?= $langUrl ?>?lang=kg" class="<?= $currentLang==='kg'?'active':'' ?>" title="Kikongo">KG</a>
            </div>
        </div>

        <div class="sidebar-footer">
            <?php if ($currentUser['role'] === 'admin' && !empty($currentUser['school_id'])): ?>
            <a href="/edusuivi/logout.php" style="color:#ef4444;opacity:.8;" title="Se déconnecter et changer d'école">
                <span class="icon"><i class="fa-solid fa-rotate fa-icon" aria-hidden="true"></i></span>
                Changer d'école
            </a>
            <?php else: ?>
            <a href="/edusuivi/logout.php">
                <span class="icon"><i class="fa-solid fa-right-from-bracket fa-icon" aria-hidden="true"></i></span>
                <?= t('logout') ?>
            </a>
            <?php endif; ?>
        </div>
    </aside>

    <div class="main-wrapper">
        <!-- Top header -->
        <div class="header" style="position:sticky;top:0;z-index:100;">
        <button class="mobile-toggle" onclick="toggleSidebar()">☰</button>
        <div style="display:flex;align-items:center;gap:20px;">
            <!-- Notifications -->
            <div style="position:relative;z-index:110;" id="notif-container">
                <?php
                    $notifCount = 0; $notifs = [];
                    if (isset($pdo) && $currentUser) {
                        $cnt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
                        $cnt->execute([$currentUser['id']]); $notifCount = $cnt->fetchColumn();
                        
                        $nStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
                        $nStmt->execute([$currentUser['id']]); $notifs = $nStmt->fetchAll();
                    }
                ?>
                <button class="btn btn-outline" style="border:none;background:transparent;font-size:18px;position:relative;padding:8px;color:var(--text);" onclick="toggleNotifs()">
                    🔔
                    <?php if($notifCount > 0): ?>
                    <span style="position:absolute;top:0;right:0;background:#ef4444;color:#fff;font-size:10px;font-weight:700;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;"><?= $notifCount ?></span>
                    <?php endif; ?>
                </button>
                <!-- Dropdown Notifs -->
                <div id="notif-dropdown" style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:340px;background:var(--card, #ffffff) !important;border:1.5px solid var(--border);border-radius:12px;box-shadow:0 14px 36px rgba(0,0,0,0.28);z-index:120;overflow:hidden;">
                    <div style="padding:14px 18px;background:var(--card, #ffffff);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                        <h4 style="margin:0;font-size:14px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;">🔔 Notifications</h4>
                        <?php if($notifCount > 0): ?>
                        <button onclick="markNotifsRead()" style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:12px;font-weight:600;">Tout marquer lu</button>
                        <?php endif; ?>
                    </div>
                    <div style="max-height:320px;overflow-y:auto;background:var(--card, #ffffff);">
                        <?php foreach($notifs as $n):
                            $nLink = $n['link'] ?: '#';
                            if ($n['type'] === 'message' || strpos($nLink, '/messages.php') !== false) {
                                $myRole = $currentUser['role'] ?? 'parent';
                                $nLink  = preg_replace('#^/edusuivi/(admin|teacher|parent)/messages\.php#', "/edusuivi/{$myRole}/messages.php", $nLink);
                            }
                        ?>
                        <a href="<?= escape($nLink) ?>" style="display:block;padding:12px 18px;border-bottom:1px solid var(--border);text-decoration:none;background:<?= $n['is_read']?'var(--card, #ffffff)':'rgba(212,147,13,.08)' ?>;transition:.2s;">
                            <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:3px;"><?= escape($n['title']) ?></div>
                            <div style="font-size:12px;color:var(--text-muted);line-height:1.4;"><?= escape(substr($n['body'],0,65)) ?>...</div>
                            <div style="font-size:10px;color:var(--primary);font-weight:600;margin-top:4px;">🕐 <?= date('d/m H:i',strtotime($n['created_at'])) ?></div>
                        </a>
                        <?php endforeach; ?>
                        <?php if(!$notifs): ?><div style="padding:28px;text-align:center;color:var(--text-muted);font-size:13px;background:var(--card, #ffffff);">Aucune notification</div><?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profil -->
            <div style="display:flex;align-items:center;gap:12px;padding-left:20px;border-left:1px solid var(--border);">
                <div style="text-align:right;">
                    <div style="font-weight:600;font-size:14px;"><?= escape($currentUser['name']) ?></div>
                    <div style="font-size:12px;color:var(--primary);text-transform:uppercase;letter-spacing:1px;"><?= escape($currentUser['role']) ?></div>
                </div>
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--gold-light));display:flex;align-items:center;justify-content:center;color:#000;font-weight:800;font-size:18px;">
                    <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleNotifs() {
        const d = document.getElementById('notif-dropdown');
        d.style.display = d.style.display === 'none' ? 'block' : 'none';
    }
    function markNotifsRead() {
        fetch('/edusuivi/api/notifications.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'mark_read'})
        }).then(r=>r.json()).then(d=>{
            if(d.success) window.location.reload();
        });
    }
    document.addEventListener('click', e => {
        const c = document.getElementById('notif-container');
        if(c && !c.contains(e.target)) document.getElementById('notif-dropdown').style.display='none';
    });
    </script>

    <main class="main-content">

<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'edusuivi');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// ─── Système de langue ───────────────────────────────────────────────────────
function loadLang($code = null) {
    $allowed = ['fr', 'ln', 'sw', 'kg'];
    if (!$code) {
        $code = $_SESSION['lang'] ?? 'fr';
    }
    if (!in_array($code, $allowed)) $code = 'fr';
    $file = __DIR__ . '/../lang/' . $code . '.php';
    if (file_exists($file)) {
        return require $file;
    }
    return require __DIR__ . '/../lang/fr.php';
}

// Change de langue si demandé
if (isset($_GET['lang'])) {
    $allowed = ['fr', 'ln', 'sw', 'kg'];
    $newLang = $_GET['lang'];
    if (in_array($newLang, $allowed)) {
        $_SESSION['lang'] = $newLang;
        // Sauvegarder en BDD si connecté
        if (isset($_SESSION['user_id'])) {
            try {
                $pdo->prepare("INSERT INTO user_preferences (user_id, lang) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE lang=VALUES(lang)")
                    ->execute([$_SESSION['user_id'], $newLang]);
            } catch(Exception $e) { /* table peut ne pas exister encore */ }
        }
    }
    // Redirect sans le paramètre lang
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    $params = $_GET;
    unset($params['lang']);
    $query = http_build_query($params);
    header('Location: ' . $url . ($query ? '?' . $query : ''));
    exit;
}

// Charger la langue de préférence BDD si connecté
if (isset($_SESSION['user_id']) && !isset($_SESSION['lang'])) {
    try {
        $prefRow = $pdo->prepare("SELECT lang FROM user_preferences WHERE user_id = ?");
        $prefRow->execute([$_SESSION['user_id']]);
        $pref = $prefRow->fetch();
        if ($pref) $_SESSION['lang'] = $pref['lang'];
    } catch(Exception $e) { /* silently skip */ }
}


$LANG = loadLang();

function t($key) {
    global $LANG;
    return $LANG[$key] ?? $key;
}

// ─── Mode Primaire / Secondaire ──────────────────────────────────────────────
if (isset($_GET['mode'])) {
    $allowedModes = ['primaire', 'secondaire'];
    $newMode = $_GET['mode'];
    if (in_array($newMode, $allowedModes)) {
        $_SESSION['school_mode'] = $newMode;
    }
    // Redirect sans le paramètre mode
    $url = strtok($_SERVER['REQUEST_URI'], '?');
    $params = $_GET;
    unset($params['mode']);
    $query = http_build_query($params);
    header('Location: ' . $url . ($query ? '?' . $query : ''));
    exit;
}

// Default mode
if (!isset($_SESSION['school_mode'])) {
    $_SESSION['school_mode'] = 'secondaire';
}

function getSchoolMode() {
    return $_SESSION['school_mode'] ?? 'secondaire';
}

function getTeacherLabel($plural = false) {
    $mode = getSchoolMode();
    if ($mode === 'primaire') {
        return $plural ? 'Enseignants' : 'Enseignant';
    }
    return $plural ? 'Professeurs' : 'Professeur';
}

function getTeacherLabelLower($plural = false) {
    return mb_strtolower(getTeacherLabel($plural));
}

// ─── Auth ────────────────────────────────────────────────────────────────────
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /edusuivi/login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: /edusuivi/login.php');
        exit;
    }
}

function getUser() {
    return [
        'id'          => $_SESSION['user_id'] ?? null,
        'name'        => $_SESSION['user_name'] ?? '',
        'email'       => $_SESSION['user_email'] ?? '',
        'role'        => $_SESSION['user_role'] ?? '',
        'school_id'   => $_SESSION['school_id'] ?? null,
        'school_mode' => $_SESSION['school_mode'] ?? 'secondaire',
    ];
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function escape($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ─── Notifications non lues ──────────────────────────────────────────────────
function getUnreadCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch(Exception $e) { return 0; }
}

function getUnreadMessages($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0 AND is_deleted_receiver=0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch(Exception $e) { return 0; }
}

// ─── Alertes intelligentes ───────────────────────────────────────────────────
function checkSmartAlerts($pdo, $userId) {
    $alerts = [];
    try {
        // 1. Chute de notes > 20%
        $stmt = $pdo->query("
            SELECT s.id, CONCAT(s.first_name,' ',s.last_name) as name,
                   AVG(g1.grade/g1.max_grade*100) as avg_t1,
                   AVG(g2.grade/g2.max_grade*100) as avg_t2
            FROM students s
            LEFT JOIN grades g1 ON g1.student_id=s.id AND g1.period='1er Trimestre'
            LEFT JOIN grades g2 ON g2.student_id=s.id AND g2.period='2ème Trimestre'
            GROUP BY s.id HAVING avg_t1 IS NOT NULL AND avg_t2 IS NOT NULL AND (avg_t1 - avg_t2) > 20
        ");
        foreach($stmt->fetchAll() as $row) {
            $alerts[] = ['type'=>'danger','icon'=>'📉',
                'msg'=> $row['name'] . ' — chute de ' . round($row['avg_t1']-$row['avg_t2'],1) . '% des résultats'];
        }
        // 2. Absences répétées (>= 3 non justifiées)
        $stmt = $pdo->query("
            SELECT s.id, CONCAT(s.first_name,' ',s.last_name) as name, COUNT(*) as cnt
            FROM absences a JOIN students s ON a.student_id=s.id
            WHERE a.justified=0 GROUP BY s.id HAVING cnt >= 3
        ");
        foreach($stmt->fetchAll() as $row) {
            $alerts[] = ['type'=>'warning','icon'=>'🔴',
                'msg'=> $row['name'] . ' — ' . $row['cnt'] . ' absences injustifiées'];
        }
        // 3. Frais impayés
        $stmt = $pdo->query("
            SELECT CONCAT(s.first_name,' ',s.last_name) as name,
                   SUM(sf.amount_due) - COALESCE(SUM(fp.amount_paid),0) as due
            FROM student_fees sf
            JOIN students s ON sf.student_id=s.id
            LEFT JOIN fee_payments fp ON fp.student_id=s.id
            GROUP BY s.id HAVING due > 0 LIMIT 5
        ");
        foreach($stmt->fetchAll() as $row) {
            $alerts[] = ['type'=>'info','icon'=>'💰',
                'msg'=> $row['name'] . ' — '.number_format($row['due'],0,',',' ').' FC impayés'];
        }
        // 4. Événements dans 2 jours
        $stmt = $pdo->query("
            SELECT title, event_date FROM agenda_events
            WHERE event_date BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY)
        ");
        foreach($stmt->fetchAll() as $row) {
            $alerts[] = ['type'=>'success','icon'=>'📅',
                'msg'=> '«'.$row['title'].'» le '.date('d/m/Y', strtotime($row['event_date']))];
        }
    } catch(Exception $e) { /* ignore if tables don't exist yet */ }
    return $alerts;
}

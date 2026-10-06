<?php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');

// ── Export CSV ─────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=frais_'.date('Y-m-d').'.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['Classe','Élève','Matricule','Total dû (FC)','Payé (FC)','Solde (FC)','Statut'],';');
    
    $allStudents = $pdo->query("
        SELECT CONCAT(s.first_name,' ',s.last_name) as student_name, s.matricule,
               c.name as class_name,
               COALESCE(SUM(sf.amount_due),0) as total_due,
               COALESCE((SELECT SUM(fp.amount_paid) FROM fee_payments fp WHERE fp.student_id=s.id),0) as total_paid
        FROM students s
        LEFT JOIN classes c ON s.class_id=c.id
        LEFT JOIN student_fees sf ON sf.student_id=s.id
        GROUP BY s.id ORDER BY c.name, s.last_name
    ")->fetchAll();
    
    foreach($allStudents as $st) {
        $b = $st['total_due'] - $st['total_paid'];
        $status = $b<=0&&$st['total_due']>0?'Soldé':($st['total_paid']>0?'Partiel':($st['total_due']>0?'Impayé':'Aucun'));
        fputcsv($out,[$st['class_name']??'',$st['student_name'],$st['matricule']??'',$st['total_due'],$st['total_paid'],max(0,$b),$status],';');
    }
    fclose($out);
    exit;
}

// ── Auto-migration : ajouter nb_tranches et tranche_note si manquant ──────
try {
    $cols = $pdo->query("SHOW COLUMNS FROM fee_types LIKE 'nb_tranches'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE fee_types ADD COLUMN nb_tranches TINYINT DEFAULT 1 AFTER is_active");
        $pdo->exec("ALTER TABLE fee_types ADD COLUMN tranche_note TEXT DEFAULT NULL AFTER nb_tranches");
    }
} catch(Exception $e) {}

// ── Auto-migration : ajouter tranche_paid si manquant ──────
try {
    $cols = $pdo->query("SHOW COLUMNS FROM fee_payments LIKE 'tranche_paid'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE fee_payments ADD COLUMN tranche_paid VARCHAR(100) DEFAULT NULL AFTER amount_paid");
    }
} catch(Exception $e) {}

// ── Auto-migration : ajouter payment_currency et exchange_rate si manquant ──────
try {
    $cols = $pdo->query("SHOW COLUMNS FROM fee_payments LIKE 'payment_currency'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE fee_payments ADD COLUMN payment_currency VARCHAR(5) DEFAULT 'FC' AFTER tranche_paid");
        $pdo->exec("ALTER TABLE fee_payments ADD COLUMN exchange_rate DECIMAL(10,2) DEFAULT NULL AFTER payment_currency");
    }
} catch(Exception $e) {}

// ── Helper : Récupérer les tranches d'un type de frais sous forme de tableau ──────
function getFeeTranches($ft) {
    $note = $ft['tranche_note'] ?? '';
    $decoded = json_decode($note, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    // Fallback si ce n'est pas du JSON
    $nb = intval($ft['nb_tranches'] ?? 1);
    $amount = floatval($ft['amount'] ?? 0);
    $trancheAmt = $nb > 0 ? round($amount / $nb) : $amount;
    
    $tranches = [];
    if ($nb >= 1) {
        $tranches[] = ['name' => 'Acompte', 'amount' => $trancheAmt];
    }
    if ($nb >= 2) {
        $tranches[] = ['name' => 'Deuxième tranche', 'amount' => $trancheAmt];
    }
    if ($nb >= 3) {
        $tranches[] = ['name' => 'Troisième tranche', 'amount' => $trancheAmt];
    }
    for ($i = 4; $i <= $nb; $i++) {
        $tranches[] = ['name' => $i . 'ème tranche', 'amount' => $trancheAmt];
    }
    return $tranches;
}

// ── Endpoint AJAX : Détails de paiement d'un élève pour un frais donné ──────
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_fee_details') {
    header('Content-Type: application/json');
    $studentId = intval($_GET['student_id'] ?? 0);
    $feeTypeId = intval($_GET['fee_type_id'] ?? 0);
    $year = $_GET['year'] ?? date('Y').'-'.(date('Y')+1);
    
    $ftStmt = $pdo->prepare("SELECT * FROM fee_types WHERE id = ?");
    $ftStmt->execute([$feeTypeId]);
    $feeType = $ftStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$feeType) {
        echo json_encode(['error' => 'Type de frais introuvable']);
        exit;
    }
    
    $sfStmt = $pdo->prepare("SELECT id, amount_due FROM student_fees WHERE student_id = ? AND fee_type_id = ? AND school_year = ?");
    $sfStmt->execute([$studentId, $feeTypeId, $year]);
    $studentFee = $sfStmt->fetch(PDO::FETCH_ASSOC);
    
    $amountDue = $feeType['amount'];
    if ($studentFee) {
        $amountDue = $studentFee['amount_due'];
    }
    
    $paidStmt = $pdo->prepare("
        SELECT COALESCE(SUM(fp.amount_paid), 0) as total_paid 
        FROM fee_payments fp 
        JOIN student_fees sf ON fp.student_fee_id = sf.id
        WHERE sf.student_id = ? AND sf.fee_type_id = ? AND sf.school_year = ?
    ");
    $paidStmt->execute([$studentId, $feeTypeId, $year]);
    $totalPaid = floatval($paidStmt->fetchColumn());
    
    $paymentsStmt = $pdo->prepare("
        SELECT fp.amount_paid, fp.tranche_paid, fp.notes 
        FROM fee_payments fp
        JOIN student_fees sf ON fp.student_fee_id = sf.id
        WHERE sf.student_id = ? AND sf.fee_type_id = ? AND sf.school_year = ?
        ORDER BY fp.payment_date ASC
    ");
    $paymentsStmt->execute([$studentId, $feeTypeId, $year]);
    $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'fee_type' => $feeType,
        'total_due' => $amountDue,
        'total_paid' => $totalPaid,
        'remaining' => max(0, $amountDue - $totalPaid),
        'payments' => $payments
    ]);
    exit;
}

// ── Enregistrer un paiement (DOIT être avant header.php pour pouvoir faire un redirect) ──
if (($_POST['action'] ?? '') === 'pay') {
    $studentId   = intval($_POST['student_id']);
    $feeTypeId   = intval($_POST['fee_type_id']);
    $currency    = in_array($_POST['payment_currency'] ?? 'FC', ['FC','USD']) ? $_POST['payment_currency'] : 'FC';
    $exchRate    = floatval($_POST['exchange_rate'] ?? 1);
    $rawAmount   = floatval($_POST['amount_paid']);
    $amount      = $currency === 'USD' ? round($rawAmount * $exchRate) : $rawAmount; // Toujours stocké en FC
    $method      = $_POST['payment_method'] ?? 'cash';
    $ref         = trim($_POST['transaction_ref'] ?? '');
    $date        = $_POST['payment_date'] ?: date('Y-m-d');
    $notes       = trim($_POST['notes'] ?? '');
    $year        = trim($_POST['school_year_pay'] ?? date('Y').'-'.(date('Y')+1));
    $tranchePaid = trim($_POST['tranche_paid'] ?? '');

    if ($studentId && $feeTypeId && $amount > 0) {
        // Trouver ou créer la ligne student_fees
        $sf = $pdo->prepare("SELECT id, amount_due FROM student_fees WHERE student_id=? AND fee_type_id=? AND school_year=?");
        $sf->execute([$studentId, $feeTypeId, $year]);
        $sfRow = $sf->fetch();

        if (!$sfRow) {
            $ft = $pdo->prepare("SELECT amount FROM fee_types WHERE id=?");
            $ft->execute([$feeTypeId]);
            $ftAmt = $ft->fetchColumn() ?: $amount;
            $pdo->prepare("INSERT INTO student_fees (student_id,fee_type_id,amount_due,school_year) VALUES (?,?,?,?)")
                ->execute([$studentId, $feeTypeId, $ftAmt, $year]);
            $sfId = $pdo->lastInsertId();
        } else {
            $sfId = $sfRow['id'];
        }

        $receipt = 'REC-'.date('Y').'-'.strtoupper(substr(md5(uniqid()),0,6));
        $recordedBy = intval($_SESSION['user_id'] ?? 0);
        try {
            $pdo->prepare("INSERT INTO fee_payments (student_fee_id,student_id,amount_paid,tranche_paid,payment_currency,exchange_rate,payment_method,transaction_ref,payment_date,receipt_number,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$sfId, $studentId, $amount, $tranchePaid, $currency, ($currency==='USD'?$exchRate:null), $method, $ref, $date, $receipt, $notes, $recordedBy]);
        } catch(Exception $e) {
            $pdo->prepare("INSERT INTO fee_payments (student_fee_id,student_id,amount_paid,tranche_paid,payment_method,transaction_ref,payment_date,receipt_number,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$sfId, $studentId, $amount, $tranchePaid, $method, $ref, $date, $receipt, $notes, $recordedBy]);
        }

        // Notification parent
        try {
            $pid = $pdo->prepare("SELECT parent_id FROM students WHERE id=?");
            $pid->execute([$studentId]); $parentId = $pid->fetchColumn();
            if ($parentId) {
                $displayAmt = number_format($amount, 0, ',', ' ').' FC';
                $pdo->prepare("INSERT INTO notifications (user_id,type,title,body,link) VALUES (?,?,?,?,?)")
                    ->execute([$parentId,'fee','✅ Paiement enregistré','Reçu '.$receipt.' — '.$displayAmt,'/edusuivi/parent/fees.php']);
            }
        } catch(Exception $e) {}

        // Redirection vers le reçu (AVANT tout output HTML)
        header("Location: fees.php?receipt_id=".urlencode($receipt)."&class_id=".intval($_POST['class_id_ctx'] ?? 0));
        exit;
    }
    // En cas d'erreur, on continue et affichera le message après header.php
    $_SESSION['pay_error'] = "Données invalides (montant ou élève manquant).";
    header("Location: fees.php?class_id=".intval($_POST['class_id_ctx'] ?? 0));
    exit;
}

// Charger l'entête global
require_once __DIR__ . '/../includes/header.php';
$success = $error = '';
// Récupérer l'erreur de paiement stockée en session si applicable
if (!empty($_SESSION['pay_error'])) {
    $error = $_SESSION['pay_error'];
    unset($_SESSION['pay_error']);
}

// ── Gestion des types de frais (CRUD) ─────────────────────
if (($_POST['action'] ?? '') === 'add_fee_type') {
    $name   = trim($_POST['ft_name'] ?? '');
    $amount = floatval($_POST['ft_amount'] ?? 0);
    $desc   = trim($_POST['ft_desc'] ?? '');
    $year   = trim($_POST['ft_year'] ?? date('Y').'-'.(date('Y')+1));
    
    $tranche_names = $_POST['tranche_names'] ?? [];
    $tranche_amounts = $_POST['tranche_amounts'] ?? [];
    $tranches_data = [];
    $total_amount_calc = 0;
    
    foreach ($tranche_names as $index => $t_name) {
        $t_name = trim($t_name);
        $t_amount = floatval($tranche_amounts[$index] ?? 0);
        if ($t_name !== '') {
            $tranches_data[] = [
                'name' => $t_name,
                'amount' => $t_amount
            ];
            $total_amount_calc += $t_amount;
        }
    }
    
    if (empty($tranches_data)) {
        $tranches_data[] = [
            'name' => 'Acompte',
            'amount' => $amount
        ];
        $total_amount_calc = $amount;
    } else {
        $amount = $total_amount_calc;
    }
    
    $nb_tranches = count($tranches_data);
    $tranche_note = json_encode($tranches_data, JSON_UNESCAPED_UNICODE);
    
    if ($name && $amount > 0) {
        $pdo->prepare("INSERT INTO fee_types (name,amount,description,school_year,is_active,nb_tranches,tranche_note) VALUES (?,?,?,?,1,?,?)")
            ->execute([$name, $amount, $desc, $year, $nb_tranches, $tranche_note]);
        $success = "Type de frais « $name » créé.";
    } else { $error = "Nom et montant requis."; }
}

if (($_POST['action'] ?? '') === 'edit_fee_type') {
    $name   = trim($_POST['ft_name'] ?? '');
    $amount = floatval($_POST['ft_amount'] ?? 0);
    $desc   = trim($_POST['ft_desc'] ?? '');
    $year   = trim($_POST['ft_year'] ?? date('Y').'-'.(date('Y')+1));
    $ft_id  = intval($_POST['ft_id'] ?? 0);
    
    $tranche_names = $_POST['tranche_names'] ?? [];
    $tranche_amounts = $_POST['tranche_amounts'] ?? [];
    $tranches_data = [];
    $total_amount_calc = 0;
    
    foreach ($tranche_names as $index => $t_name) {
        $t_name = trim($t_name);
        $t_amount = floatval($tranche_amounts[$index] ?? 0);
        if ($t_name !== '') {
            $tranches_data[] = [
                'name' => $t_name,
                'amount' => $t_amount
            ];
            $total_amount_calc += $t_amount;
        }
    }
    
    if (empty($tranches_data)) {
        $tranches_data[] = [
            'name' => 'Acompte',
            'amount' => $amount
        ];
        $total_amount_calc = $amount;
    } else {
        $amount = $total_amount_calc;
    }
    
    $nb_tranches = count($tranches_data);
    $tranche_note = json_encode($tranches_data, JSON_UNESCAPED_UNICODE);
    
    if ($name && $amount > 0 && $ft_id) {
        $pdo->prepare("UPDATE fee_types SET name=?,amount=?,description=?,school_year=?,nb_tranches=?,tranche_note=? WHERE id=?")
            ->execute([$name, $amount, $desc, $year, $nb_tranches, $tranche_note, $ft_id]);
        $success = "Type de frais modifié.";
    } else { $error = "Nom, montant et ID requis."; }
}

if (isset($_GET['toggle_fee']) && is_numeric($_GET['toggle_fee'])) {
    $pdo->exec("UPDATE fee_types SET is_active = 1-is_active WHERE id=".intval($_GET['toggle_fee']));
    $success = "Statut du type de frais modifié.";
}

// ── Le bloc 'pay' est désormais traité AVANT l'inclusion de header.php (voir ci-dessus)

// ── Données de base ──────────────────────────────────────
$selectedClass = isset($_GET['class_id']) && is_numeric($_GET['class_id']) ? intval($_GET['class_id']) : null;
$school_year   = $_GET['year'] ?? date('Y').'-'.(date('Y')+1);

$classes  = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM students WHERE class_id=c.id) as nb FROM classes c ORDER BY c.level, c.name")->fetchAll();
$feeTypes = $pdo->query("
    SELECT ft.*,
           COALESCE((SELECT SUM(sf.amount_due) FROM student_fees sf WHERE sf.fee_type_id=ft.id), 0) as total_due,
           COALESCE((SELECT SUM(fp.amount_paid) FROM fee_payments fp JOIN student_fees sf ON fp.student_fee_id=sf.id WHERE sf.fee_type_id=ft.id), 0) as total_paid
    FROM fee_types ft 
    ORDER BY ft.school_year DESC, ft.name
")->fetchAll();
$activeFeeTypes = array_filter($feeTypes, fn($f) => $f['is_active']);

// Récupérer les infos de l'école
$schoolInfo = null;
try {
    if (!empty($currentUser['school_id'])) {
        $s = $pdo->prepare("SELECT * FROM schools WHERE id=?");
        $s->execute([$currentUser['school_id']]);
        $schoolInfo = $s->fetch();
    }
} catch(Exception $e) {}

// ── Stats globales frais ──────────────────────────────────
$feeGlobal = $pdo->query("
    SELECT COALESCE(SUM(sf.amount_due),0) as total_due,
           COALESCE((SELECT SUM(fp.amount_paid) FROM fee_payments fp),0) as total_paid
    FROM student_fees sf WHERE sf.school_year='$school_year'
")->fetch();
$totalDue  = $feeGlobal['total_due'];
$totalPaid = $feeGlobal['total_paid'];
$totalDebt = max(0, $totalDue - $totalPaid);
$feeRate   = $totalDue > 0 ? round($totalPaid/$totalDue*100) : 0;

// Infos par classe (pour les cards)
$classFeeStats = [];
foreach($classes as $c) {
    $cStat = $pdo->prepare("
        SELECT COALESCE(SUM(sf.amount_due),0) as due,
               COALESCE((SELECT SUM(fp2.amount_paid) FROM fee_payments fp2 JOIN student_fees sf2 ON fp2.student_fee_id=sf2.id WHERE sf2.student_id IN (SELECT id FROM students WHERE class_id=?)),0) as paid,
               (SELECT COUNT(*) FROM students WHERE class_id=?) as nb_students,
               (SELECT COUNT(DISTINCT sf3.student_id) FROM student_fees sf3 WHERE sf3.student_id IN (SELECT id FROM students WHERE class_id=?) AND sf3.school_year=?) as nb_with_fees
        FROM student_fees sf
        WHERE sf.student_id IN (SELECT id FROM students WHERE class_id=?) AND sf.school_year=?
    ");
    $cStat->execute([$c['id'],$c['id'],$c['id'],$school_year,$c['id'],$school_year]);
    $classFeeStats[$c['id']] = $cStat->fetch();
}

// ── Élèves de la classe sélectionnée ──────────────────────
$students = [];
if ($selectedClass) {
    $stmt = $pdo->prepare("
        SELECT s.id as student_id, CONCAT(s.first_name,' ',s.last_name) as student_name,
               s.matricule, c.name as class_name,
               COALESCE(SUM(sf.amount_due),0) as total_due,
               COALESCE((SELECT SUM(fp.amount_paid) FROM fee_payments fp WHERE fp.student_id=s.id),0) as total_paid
        FROM students s
        LEFT JOIN classes c ON s.class_id=c.id
        LEFT JOIN student_fees sf ON sf.student_id=s.id AND sf.school_year=?
        WHERE s.class_id=?
        GROUP BY s.id
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute([$school_year, $selectedClass]);
    $students = $stmt->fetchAll();
}

// ── Vue reçu ──────────────────────────────────────────────
$viewReceipt = null;
if (isset($_GET['receipt_id'])) {
    $rStmt = $pdo->prepare("
        SELECT fp.*, CONCAT(s.first_name,' ',s.last_name) as student_name, s.matricule,
               c.name as class_name, ft.name as fee_type_name, u.name as recorder_name
        FROM fee_payments fp
        JOIN students s ON fp.student_id=s.id
        JOIN student_fees sf ON fp.student_fee_id=sf.id
        JOIN fee_types ft ON sf.fee_type_id=ft.id
        LEFT JOIN classes c ON s.class_id=c.id
        JOIN users u ON fp.recorded_by=u.id
        WHERE fp.receipt_number=?
    ");
        $rStmt->execute([trim($_GET['receipt_id'])]);
    $viewReceipt = $rStmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>💰 Frais Scolaires</h2>
        <p>Suivi des paiements par classe — Année scolaire <?= escape($school_year) ?></p>
    </div>
    <div style="display:flex;gap:8px;">
        <button class="btn btn-outline" onclick="openModal('feeConfigModal')">⚙️ Config. des frais</button>
        <a href="?export=csv&year=<?= escape($school_year) ?>" class="btn btn-outline">📥 Exporter CSV</a>
    </div>
</div>

<?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if($error):   ?><div class="alert alert-danger"><?= escape($error) ?></div><?php endif; ?>

<!-- ── Reçu d'impression ────────────────────────────────── -->
<?php if($viewReceipt): ?>
<div class="card" id="receipt-print" style="margin-bottom:24px;border:2px solid var(--primary);">
    <div style="padding:28px;">
        <!-- En-tête reçu -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;padding-bottom:20px;border-bottom:2px dashed var(--border);">
            <div>
                <div style="font-size:24px;font-weight:900;color:var(--primary);"><?= escape($schoolInfo['name'] ?? 'EduSuivi') ?></div>
                <?php if($schoolInfo): ?>
                <div style="color:var(--text-muted);font-size:12px;margin-top:2px;">📍 <?= escape($schoolInfo['address'] ?? '') ?></div>
                <?php endif; ?>
                <div style="color:var(--text-muted);font-size:12px;">Portail Scolaire · RDC</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:20px;font-weight:800;text-transform:uppercase;letter-spacing:1px;">Reçu de Paiement</div>
                <div style="color:var(--primary);font-size:18px;font-weight:900;margin-top:4px;">N° <?= escape($viewReceipt['receipt_number']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Émis le <?= date('d/m/Y à H:i', strtotime($viewReceipt['created_at'])) ?></div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
            <div>
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Élève</div>
                <div style="font-size:16px;font-weight:700;"><?= escape($viewReceipt['student_name']) ?></div>
                <div style="font-size:13px;color:var(--text-muted);margin-top:2px;">
                    Matricule : <?= escape($viewReceipt['matricule'] ?? '—') ?>
                </div>
                <div style="font-size:13px;color:var(--text-muted);">
                    Classe : <?= escape($viewReceipt['class_name'] ?? '—') ?>
                </div>
            </div>
            <div>
                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Détails du paiement</div>
                <div><strong>Date :</strong> <?= date('d/m/Y', strtotime($viewReceipt['payment_date'])) ?></div>
                <div><strong>Mode :</strong> <?= ucfirst(str_replace('_', ' ', $viewReceipt['payment_method'])) ?></div>
                <?php if($viewReceipt['transaction_ref']): ?>
                <div><strong>Référence :</strong> <?= escape($viewReceipt['transaction_ref']) ?></div>
                <?php endif; ?>
                <div><strong>Caissier :</strong> <?= escape($viewReceipt['recorder_name']) ?></div>
            </div>
        </div>

        <!-- Montant principal -->
        <div style="background:linear-gradient(135deg,rgba(212,147,13,.12),rgba(212,147,13,.05));border:1px solid rgba(212,147,13,.3);border-radius:14px;padding:24px;text-align:center;margin-bottom:24px;">
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:4px;">Objet du paiement</div>
            <div style="font-size:16px;font-weight:600;color:var(--text);margin-bottom:8px;"><?= escape($viewReceipt['fee_type_name']) ?></div>
            <div style="font-size:42px;font-weight:900;color:var(--primary);line-height:1;"><?= number_format($viewReceipt['amount_paid'],0,',',' ') ?></div>
            <div style="font-size:20px;font-weight:700;color:var(--primary);">Francs Congolais (FC)</div>
        </div>

        <?php if($viewReceipt['notes']): ?>
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:16px;padding:10px;background:rgba(255,255,255,.03);border-radius:8px;">
            📝 <?= escape($viewReceipt['notes']) ?>
        </div>
        <?php endif; ?>

        <div style="display:flex;justify-content:space-between;align-items:center;padding-top:16px;border-top:1px dashed var(--border);">
            <div style="font-size:12px;color:var(--text-muted);">Document généré par EduSuivi — Ce reçu est valable comme preuve de paiement</div>
            <div style="display:flex;gap:8px;" class="no-print">
                <button class="btn btn-primary btn-sm" onclick="window.print()">🖨️ Imprimer</button>
                <a href="fees.php<?= $selectedClass ? '?class_id='.$selectedClass : '' ?>" class="btn btn-outline btn-sm">← Retour</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Stats globales ────────────────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <div class="stat-card">
        <div class="icon-box blue">💳</div>
        <div><div class="value"><?= number_format($totalDue,0,',',' ') ?></div><div class="label">Total dû (FC)</div></div>
    </div>
    <div class="stat-card">
        <div class="icon-box green">✅</div>
        <div><div class="value"><?= number_format($totalPaid,0,',',' ') ?></div><div class="label">Total perçu (FC)</div></div>
    </div>
    <div class="stat-card">
        <div class="icon-box red">⚠️</div>
        <div><div class="value"><?= number_format($totalDebt,0,',',' ') ?></div><div class="label">Reste à percevoir (FC)</div></div>
    </div>
    <div class="stat-card">
        <div class="icon-box gold">📊</div>
        <div>
            <div class="value"><?= $feeRate ?>%</div>
            <div class="label">Taux de recouvrement</div>
            <div style="background:rgba(255,255,255,.1);border-radius:4px;height:4px;margin-top:6px;overflow:hidden;">
                <div style="height:100%;background:<?= $feeRate>=80?'#10b981':($feeRate>=50?'#f59e0b':'#ef4444') ?>;width:<?= $feeRate ?>%;transition:.6s;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ── Vue : Grille des classes OU Liste élèves ─────────── -->
<?php if (!$selectedClass): ?>

<!-- GRILLE DES CLASSES -->
<div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
    <h3 style="font-size:16px;font-weight:700;">Sélectionnez une classe</h3>
    <form method="GET" style="display:flex;gap:8px;align-items:center;">
        <label style="font-size:13px;color:var(--text-muted);">Année :</label>
        <select name="year" class="form-control" style="width:auto;" onchange="this.form.submit()">
            <?php
            $years = [date('Y').'-'.(date('Y')+1), (date('Y')-1).'-'.date('Y'), (date('Y')-2).'-'.(date('Y')-1)];
            foreach($years as $y): ?>
            <option value="<?= $y ?>" <?= $school_year===$y?'selected':'' ?>><?= $y ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
    <?php foreach($classes as $c):
        $cs  = $classFeeStats[$c['id']] ?? ['due'=>0,'paid'=>0,'nb_students'=>0];
        $cDue  = $cs['due'] ?? 0;
        $cPaid = $cs['paid'] ?? 0;
        $cBalance = max(0, $cDue - $cPaid);
        $cRate = $cDue > 0 ? round($cPaid/$cDue*100) : 0;
        $cColor = $cRate >= 80 ? '#10b981' : ($cRate >= 50 ? '#f59e0b' : '#ef4444');
    ?>
    <a href="?class_id=<?= $c['id'] ?>&year=<?= escape($school_year) ?>" style="text-decoration:none;" class="fee-class-card">
        <div class="card" style="padding:20px;transition:all .2s;cursor:pointer;border:1.5px solid var(--border);">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px;">
                <div>
                    <div style="font-size:17px;font-weight:800;color:var(--text);"><?= escape($c['name']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= escape($c['level'] ?? '') ?></div>
                </div>
                <span style="background:rgba(99,102,241,.12);color:#818cf8;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px;">
                    <?= $c['nb'] ?> élève<?= $c['nb'] != 1 ? 's' : '' ?>
                </span>
            </div>

            <div style="margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text-muted);margin-bottom:5px;">
                    <span>Recouvrement</span>
                    <span style="color:<?= $cColor ?>;font-weight:700;"><?= $cRate ?>%</span>
                </div>
                <div style="background:rgba(255,255,255,.08);border-radius:4px;height:6px;overflow:hidden;">
                    <div style="height:100%;background:<?= $cColor ?>;width:<?= $cRate ?>%;transition:.5s;border-radius:4px;"></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;">
                <div style="background:rgba(16,185,129,.08);border-radius:8px;padding:8px;">
                    <div style="color:var(--text-muted);">Perçu</div>
                    <div style="font-weight:700;color:#10b981;"><?= number_format($cPaid,0,',',' ') ?> FC</div>
                </div>
                <div style="background:rgba(239,68,68,.08);border-radius:8px;padding:8px;">
                    <div style="color:var(--text-muted);">Solde</div>
                    <div style="font-weight:700;color:#ef4444;"><?= number_format($cBalance,0,',',' ') ?> FC</div>
                </div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<?php else: ?>

<!-- LISTE DES ÉLÈVES D'UNE CLASSE -->
<?php
$classInfo = null;
foreach($classes as $c) { if($c['id'] == $selectedClass) { $classInfo = $c; break; } }
$cStat = $classFeeStats[$selectedClass] ?? ['due'=>0,'paid'=>0];
$cBalance = max(0, $cStat['due'] - $cStat['paid']);
$cRate2 = $cStat['due'] > 0 ? round($cStat['paid']/$cStat['due']*100) : 0;
?>

<!-- Breadcrumb + Stats classe -->
<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <a href="fees.php?year=<?= escape($school_year) ?>" class="btn btn-outline btn-sm">← Toutes les classes</a>
    <span style="color:var(--text-muted);">›</span>
    <strong style="font-size:16px;"><?= escape($classInfo['name'] ?? 'Classe') ?></strong>
    <span style="color:var(--text-muted);font-size:13px;"><?= count($students) ?> élèves</span>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card" style="padding:14px;">
        <div class="icon-box blue" style="width:36px;height:36px;font-size:16px;">👥</div>
        <div><div class="value" style="font-size:22px;"><?= count($students) ?></div><div class="label">Élèves</div></div>
    </div>
    <div class="stat-card" style="padding:14px;">
        <div class="icon-box green" style="width:36px;height:36px;font-size:16px;">✅</div>
        <div><div class="value" style="font-size:22px;"><?= number_format($cStat['paid'],0,',',' ') ?></div><div class="label">Perçu (FC)</div></div>
    </div>
    <div class="stat-card" style="padding:14px;">
        <div class="icon-box red" style="width:36px;height:36px;font-size:16px;">⚠️</div>
        <div><div class="value" style="font-size:22px;"><?= number_format($cBalance,0,',',' ') ?></div><div class="label">Solde (FC)</div></div>
    </div>
    <div class="stat-card" style="padding:14px;">
        <div class="icon-box gold" style="width:36px;height:36px;font-size:16px;">📊</div>
        <div><div class="value" style="font-size:22px;"><?= $cRate2 ?>%</div><div class="label">Taux recouvrement</div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>👥 Élèves — <?= escape($classInfo['name'] ?? '') ?></h3>
        <span style="color:var(--text-muted);font-size:13px;">Année <?= escape($school_year) ?></span>
    </div>
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>Élève</th>
                <th>Total dû</th>
                <th>Payé</th>
                <th>Solde</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($students as $s):
            $balance  = $s['total_due'] - $s['total_paid'];
            $pct      = $s['total_due'] > 0 ? round($s['total_paid']/$s['total_due']*100) : 0;
            $stColor  = $balance <= 0 && $s['total_due'] > 0 ? '#10b981' : ($s['total_paid'] > 0 ? '#f59e0b' : '#ef4444');
            $stLabel  = $balance <= 0 && $s['total_due'] > 0 ? 'Soldé' : ($s['total_paid'] > 0 ? 'Partiel' : ($s['total_due'] > 0 ? 'Impayé' : 'Non inscrit'));
        ?>
        <tr>
            <td>
                <div style="font-weight:600;"><?= escape($s['student_name']) ?></div>
                <div style="font-size:11px;color:var(--text-muted);"><?= escape($s['matricule'] ?? '') ?></div>
            </td>
            <td><?= $s['total_due'] > 0 ? number_format($s['total_due'],0,',',' ').' FC' : '<span style="color:var(--text-muted);">—</span>' ?></td>
            <td style="color:#10b981;font-weight:600;"><?= number_format($s['total_paid'],0,',',' ') ?> FC</td>
            <td style="color:<?= $balance > 0 ? '#ef4444' : '#10b981' ?>;font-weight:700;">
                <?= number_format(max(0,$balance),0,',',' ') ?> FC
            </td>
            <td>
                <div style="display:flex;flex-direction:column;gap:4px;">
                    <span style="font-size:12px;font-weight:700;color:<?= $stColor ?>;"><?= $stLabel ?></span>
                    <?php if($s['total_due'] > 0): ?>
                    <div style="background:rgba(255,255,255,.08);border-radius:4px;height:5px;width:80px;overflow:hidden;">
                        <div style="height:100%;background:<?= $stColor ?>;width:<?= $pct ?>%;"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </td>
            <td style="white-space:nowrap;">
                <button class="btn btn-sm btn-primary" onclick="openPayModal(<?= htmlspecialchars(json_encode($s)) ?>, <?= $selectedClass ?>)" title="Enregistrer paiement">💳 Payer</button>
                <a href="?class_id=<?= $selectedClass ?>&year=<?= escape($school_year) ?>&history=<?= $s['student_id'] ?>#history-<?= $s['student_id'] ?>" class="btn btn-sm btn-outline" title="Historique">📋</a>
            </td>
        </tr>
        <?php if(isset($_GET['history']) && $_GET['history'] == $s['student_id']): ?>
        <tr id="history-<?= $s['student_id'] ?>">
            <td colspan="6" style="padding:0;">
                <?php
                $hist = $pdo->prepare("SELECT fp.*, ft.name as fee_type_name FROM fee_payments fp JOIN student_fees sf ON fp.student_fee_id=sf.id JOIN fee_types ft ON sf.fee_type_id=ft.id WHERE fp.student_id=? ORDER BY fp.payment_date DESC");
                $hist->execute([$s['student_id']]); $histRows = $hist->fetchAll();
                ?>
                <div style="background:rgba(99,102,241,.04);border-top:1px solid rgba(99,102,241,.2);padding:16px 20px;">
                    <div style="font-size:13px;font-weight:700;margin-bottom:10px;color:var(--primary);">📋 Historique des paiements — <?= escape($s['student_name']) ?></div>
                    <?php if($histRows): ?>
                    <table style="font-size:12px;">
                        <thead><tr><th>Date</th><th>Type</th><th>Montant</th><th>Mode</th><th>N° Reçu</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach($histRows as $h): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($h['payment_date'])) ?></td>
                            <td><?= escape($h['fee_type_name']) ?></td>
                            <td style="color:#10b981;font-weight:700;"><?= number_format($h['amount_paid'],0,',',' ') ?> FC</td>
                            <td><?= ucfirst(str_replace('_',' ',$h['payment_method'])) ?></td>
                            <td><code style="color:var(--primary);font-size:11px;"><?= escape($h['receipt_number']) ?></code></td>
                            <td><a href="?class_id=<?= $selectedClass ?>&receipt_id=<?= urlencode($h['receipt_number']) ?>" class="btn btn-sm btn-outline">🧾 Reçu</a></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="color:var(--text-muted);font-size:13px;">Aucun paiement enregistré.</div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php if(empty($students)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px;">Aucun élève dans cette classe</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- ═══ MODAL Config des Frais ═════════════════════════════ -->
<div class="modal-overlay" id="feeConfigModal">
    <div class="modal" style="max-width:900px; max-height:90vh; display:flex; flex-direction:column;">
        <button class="modal-close-btn" onclick="closeModal('feeConfigModal')">✕</button>
        <h3 style="padding:20px 24px 16px; font-size:18px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; flex-shrink:0; background:var(--card-bg); z-index:1;">
            <span style="width:36px;height:36px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">⚙️</span>
            Configuration des types de frais
        </h3>

        <div style="display:grid; grid-template-columns: 1.2fr 1fr; flex:1; min-height:0; overflow:hidden;">
            <!-- Left Pane: Add/Edit Form -->
            <div style="border-right:1px solid var(--border); display:flex; flex-direction:column; min-height:0;">
                <form method="POST" id="feeTypeForm" style="display:flex; flex-direction:column; height:100%; margin:0; min-height:0;">
                    <input type="hidden" name="action" id="feeTypeAction" value="add_fee_type">
                    <input type="hidden" name="ft_id" id="ft_id_hidden">

                    <!-- Scrollable form fields -->
                    <div style="flex:1; overflow-y:auto; padding:20px 24px; display:flex; flex-direction:column; gap:12px;">
                        <div style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); border-bottom:1px solid var(--border); padding-bottom:6px; margin-bottom:6px;" id="formTitle">
                            ➕ Nouveau type de frais
                        </div>

                        <div class="form-group">
                            <label>Intitulé du frais *</label>
                            <input type="text" name="ft_name" id="ft_name" class="form-control" placeholder="ex: Minerval annuel, Frais d'inscription" required>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div class="form-group">
                                <label>Année scolaire</label>
                                <input type="text" name="ft_year" id="ft_year" class="form-control" value="<?= date('Y').'-'.(date('Y')+1) ?>" placeholder="2024-2025">
                            </div>
                            <div class="form-group">
                                <label>Montant total (FC) *</label>
                                <input type="number" name="ft_amount" id="ft_amount" class="form-control" placeholder="0" required readonly style="background:rgba(255,255,255,0.05); cursor:not-allowed;">
                                <small style="color:var(--text-muted); font-size:10px;">Calculé via la somme des tranches</small>
                            </div>
                        </div>

                        <!-- Tranches configuration -->
                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <label style="font-weight:700; font-size:12px; color:var(--text);">Configuration des tranches</label>
                                <button type="button" class="btn btn-sm btn-outline" onclick="addTrancheRow()" style="font-size:11px; padding:2px 8px;">➕ Ajouter une tranche</button>
                            </div>
                            <div id="tranchesContainer" style="display:flex; flex-direction:column; gap:8px;">
                                <!-- Tranches added dynamically -->
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description / Notes</label>
                            <textarea name="ft_desc" id="ft_desc" class="form-control" rows="2" placeholder="Optionnel..." style="min-height:50px;"></textarea>
                        </div>
                    </div>

                    <!-- Fixed submit bar at bottom -->
                    <div style="flex-shrink:0; padding:12px 24px; border-top:1px solid var(--border); background:var(--card-bg); display:flex; gap:8px;">
                        <button type="submit" class="btn btn-primary" id="feeTypeSubmitBtn" style="flex:1;">➕ Ajouter le type de frais</button>
                        <button type="button" class="btn btn-outline" id="feeTypeCancelBtn" style="display:none;" onclick="resetFeeTypeForm()">Annuler</button>
                    </div>
                </form>
            </div>
            
            <!-- Right Pane: Existing types list -->
            <div style="padding:20px 24px; overflow-y:auto; background:rgba(0,0,0,0.08);">
                <div style="font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); border-bottom:1px solid var(--border); padding-bottom:6px; margin-bottom:12px;">
                    📋 Types de frais existants
                </div>
                
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <?php if(empty($feeTypes)): ?>
                    <div style="text-align:center; color:var(--text-muted); padding:24px; font-size:13px;">Aucun type de frais créé</div>
                    <?php else: ?>
                        <?php foreach($feeTypes as $ft): 
                            $ftDue = floatval($ft['total_due'] ?? 0);
                            $ftPaid = floatval($ft['total_paid'] ?? 0);
                            $ftRemaining = max(0, $ftDue - $ftPaid);
                            $tranchesList = getFeeTranches($ft);
                        ?>
                        <div style="display:flex; flex-direction:column; gap:8px; padding:12px; background:var(--card); border:1px solid var(--border); border-radius:10px;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <div style="flex:1;">
                                    <div style="font-weight:700; font-size:14px; <?= $ft['is_active'] ? '' : 'opacity:.5; text-decoration:line-through;' ?>"><?= escape($ft['name']) ?></div>
                                    <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
                                        Année : <?= escape($ft['school_year']) ?>
                                        <?php if($ft['description']): ?> · <?= escape($ft['description']) ?><?php endif; ?>
                                    </div>
                                </div>
                                <div style="font-size:14px; font-weight:800; color:var(--primary); white-space:nowrap;"><?= number_format($ft['amount'],0,',',' ') ?> FC</div>
                            </div>
                            
                            <!-- Tranches display -->
                            <div style="font-size:11px; background:rgba(255,255,255,0.02); border-radius:6px; padding:6px 10px; border:1px dashed var(--border);">
                                <strong style="color:var(--text-muted);">Tranches :</strong>
                                <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:4px;">
                                    <?php foreach($tranchesList as $tr): ?>
                                    <span style="background:rgba(212,147,13,0.08); color:var(--primary); padding:2px 6px; border-radius:4px; font-size:10px;">
                                        <?= escape($tr['name']) ?>: <strong><?= number_format($tr['amount'],0,',',' ') ?> FC</strong>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Global Paid/Remaining details -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:11px; margin-top:4px; padding-top:6px; border-top:1px solid rgba(255,255,255,0.05);">
                                <div style="color:#10b981;">Perçu : <strong><?= number_format($ftPaid,0,',',' ') ?> FC</strong></div>
                                <div style="color:#ef4444;">Reste : <strong><?= number_format($ftRemaining,0,',',' ') ?> FC</strong></div>
                            </div>
                            
                            <div style="display:flex; justify-content:flex-end; gap:6px; margin-top:4px;">
                                <button class="btn btn-sm btn-outline" onclick="editFeeType(<?= htmlspecialchars(json_encode($ft)) ?>, <?= htmlspecialchars(json_encode($tranchesList)) ?>)" style="font-size:10px; padding:2px 6px;">✏️ Modifier</button>
                                <a href="?toggle_fee=<?= $ft['id'] ?>" class="btn btn-sm btn-outline" style="font-size:10px; padding:2px 6px; color: <?= $ft['is_active'] ? '#ef4444' : '#10b981' ?>;" onclick="return confirm('Changer le statut de ce type de frais ?')">
                                    <?= $ft['is_active'] ? '🔴 Désactiver' : '🟢 Activer' ?>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="payModal">
    <div class="modal" style="max-width:560px; max-height:92vh; display:flex; flex-direction:column;">
        <button class="modal-close-btn" onclick="closeModal('payModal')">✕</button>
        <h3 style="padding:20px 24px 16px; font-size:18px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; flex-shrink:0; background:var(--card-bg);">
            <span style="width:36px;height:36px;background:linear-gradient(135deg,#10b981,#059669);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">💳</span>
            Enregistrer un paiement
        </h3>

        <div id="payStudentInfo" style="margin:16px 24px 0; background:rgba(99,102,241,.08); border:1px solid rgba(99,102,241,.2); border-radius:10px; padding:12px 16px; font-size:13px; flex-shrink:0;"></div>

        <form method="POST" id="payForm" style="padding:0; margin:0; display:flex; flex-direction:column; flex:1; min-height:0; overflow:hidden;">
            <div style="flex:1; overflow-y:auto; padding:16px 24px;">
                <input type="hidden" name="action" value="pay">
                <input type="hidden" name="student_id" id="pay_student_id">
                <input type="hidden" name="class_id_ctx" id="pay_class_id_ctx">
                <input type="hidden" name="school_year_pay" value="<?= escape($school_year) ?>">
                <input type="hidden" name="payment_currency" id="pay_currency" value="FC">
                <input type="hidden" name="exchange_rate" id="pay_exchange_rate" value="1">

                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid var(--border);">Détails du paiement</div>

                <div class="form-group">
                    <label>Type de frais *</label>
                    <select name="fee_type_id" id="pay_fee_type_id" class="form-control" required onchange="fetchStudentFeeDetails()">
                        <option value="">— Choisir le type de frais —</option>
                        <?php foreach($activeFeeTypes as $ft): ?>
                        <option value="<?= $ft['id'] ?>">
                            <?= escape($ft['name']) ?> — <?= number_format($ft['amount'],0,',',' ') ?> FC
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Résumé simple du compte -->
                <div id="paymentSummaryPanel" style="display:none; background:rgba(255,255,255,0.02); border:1px solid var(--border); border-radius:10px; padding:14px 16px; margin-bottom:16px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; text-align:center; font-size:12px;">
                        <div style="background:rgba(99,102,241,0.06); border-radius:8px; padding:10px 6px;">
                            <div style="color:var(--text-muted); margin-bottom:4px;">Montant total</div>
                            <div style="font-size:14px; font-weight:800; color:var(--text);" id="lblTotalDue">0 FC</div>
                        </div>
                        <div style="background:rgba(16,185,129,0.06); border-radius:8px; padding:10px 6px;">
                            <div style="color:#10b981; margin-bottom:4px;">Déjà payé</div>
                            <div style="font-size:14px; font-weight:800; color:#10b981;" id="lblTotalPaid">0 FC</div>
                        </div>
                        <div style="background:rgba(239,68,68,0.06); border-radius:8px; padding:10px 6px;">
                            <div style="color:#ef4444; margin-bottom:4px;">Reste à payer</div>
                            <div style="font-size:14px; font-weight:800; color:#ef4444;" id="lblRemaining">0 FC</div>
                        </div>
                    </div>
                </div>

                <!-- Sélection de tranche -->
                <div id="tranchesSection" style="display:none; margin-bottom:16px;">
                    <label style="font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); display:block; margin-bottom:8px;">Sélectionner une tranche à payer</label>
                    <div id="trancheBadgesContainer" style="display:flex; flex-direction:column; gap:8px;">
                        <!-- Badges added dynamically -->
                    </div>
                    <div style="margin-top:8px;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="addCustomPaymentTranche()" style="font-size:10px; padding:2px 8px;">➕ Tranche personnalisée</button>
                    </div>
                </div>

                <input type="hidden" name="tranche_paid" id="pay_tranche_paid">

                <!-- Convertisseur de devise -->
                <div style="background:linear-gradient(135deg,rgba(16,185,129,0.06),rgba(16,185,129,0.02)); border:1px solid rgba(16,185,129,0.2); border-radius:10px; padding:12px 14px; margin-bottom:14px;">
                    <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); margin-bottom:10px;">💱 Devise de paiement</div>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; align-items:end;">
                        <div>
                            <label style="font-size:12px; color:var(--text-muted); margin-bottom:4px; display:block;">Payer en</label>
                            <div style="display:flex; border-radius:8px; overflow:hidden; border:1px solid var(--border);">
                                <button type="button" id="btnCurrencyFC" onclick="setCurrency('FC')" style="flex:1; padding:8px; font-size:13px; font-weight:700; background:var(--primary); color:#000; border:none; cursor:pointer; transition:all .2s;">🪙 FC</button>
                                <button type="button" id="btnCurrencyUSD" onclick="setCurrency('USD')" style="flex:1; padding:8px; font-size:13px; font-weight:700; background:transparent; color:var(--text-muted); border:none; cursor:pointer; transition:all .2s;">💵 USD</button>
                            </div>
                        </div>
                        <div id="rateRow">
                            <label style="font-size:12px; color:var(--text-muted); margin-bottom:4px; display:block;">Taux (1 USD =)</label>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <input type="number" id="pay_rate_input" value="2800" min="1" step="100"
                                    style="flex:1; padding:7px 10px; font-size:13px; font-weight:700; background:rgba(255,255,255,0.05); border:1px solid var(--border); border-radius:8px; color:var(--text);"
                                    oninput="onRateChange()">
                                <span style="font-size:12px; color:var(--text-muted); white-space:nowrap;">FC</span>
                            </div>
                        </div>
                    </div>
                    <!-- Équivalent affiché en temps réel -->
                    <div id="usdEquivRow" style="display:none; margin-top:10px; padding:8px 10px; background:rgba(255,255,255,0.03); border-radius:8px; font-size:12px; text-align:center; color:var(--text-muted);">
                        Équivalent : <span id="usdEquivDisplay" style="font-weight:700; color:#10b981;"></span>
                    </div>
                </div>

                <!-- Montant + Date -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label id="pay_amount_label">Montant payé (FC) *</label>
                        <input type="number" name="amount_paid" id="pay_amount" class="form-control" step="1" min="1" placeholder="ex: 5000" required oninput="updateLiveCalculations()">
                        <small id="pay_amount_hint" style="color:#10b981; font-size:11px; display:block; margin-top:3px;"></small>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Date</label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <!-- Aperçu après paiement -->
                <div id="liveCalcPreview" style="display:none; background:rgba(16,185,129,0.06); border:1px solid rgba(16,185,129,0.2); border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                        <span>✅ Total payé après validation :</span>
                        <strong style="color:#10b981;" id="lblNewPaid">0 FC</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span>⏳ Reste à payer après validation :</span>
                        <strong style="color:#ef4444;" id="lblNewRemaining">0 FC</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label>Mode de paiement *</label>
                    <select name="payment_method" class="form-control" id="payMethodSelect" onchange="toggleRef(this.value)">
                        <option value="cash">💵 Espèces (Cash)</option>
                        <option value="orange_money">🟠 Orange Money</option>
                        <option value="mpesa">🟢 M-Pesa</option>
                        <option value="airtel_money">🔴 Airtel Money</option>
                        <option value="cheque">📄 Chèque</option>
                        <option value="virement">🏦 Virement</option>
                    </select>
                </div>

                <div class="form-group" id="refRow" style="display:none;">
                    <label>Référence de transaction</label>
                    <input type="text" name="transaction_ref" class="form-control" placeholder="ex: OM-1234567">
                </div>

                <div class="form-group">
                    <label>Notes <span style="color:var(--text-muted); font-weight:400; font-size:11px;">(optionnel)</span></label>
                    <textarea name="notes" id="pay_notes" class="form-control" rows="2" placeholder="Remarques..."></textarea>
                </div>
            </div>

            <div class="modal-actions" style="flex-shrink:0; border-top:1px solid var(--border); padding:12px 24px; display:flex; gap:10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('payModal')">Annuler</button>
                <button type="submit" class="btn btn-primary" style="flex:1;">✅ Valider &amp; Générer le reçu</button>
            </div>
        </form>
    </div>
</div>

<style>
.fee-class-card:hover .card {
    transform: translateY(-3px);
    box-shadow: 0 10px 28px rgba(0,0,0,.3);
    border-color: rgba(99,102,241,.4) !important;
}
@media print {
    .no-print, .sidebar, .mobile-toggle, .page-header button, .page-header a,
    form, .card:not(#receipt-print), .stats-grid { display:none !important; }
    .main-content { margin:0 !important; padding:0 !important; }
    .app-layout { display:block !important; }
    #receipt-print { border:2px solid #000 !important; margin:0 !important; }
}
</style>

<script>
let currentFeeData = null;

// ── Gestion du Modal de Paiement ────────────────────────────────
function openPayModal(s, classId) {
    document.getElementById('pay_student_id').value = s.student_id;
    document.getElementById('pay_class_id_ctx').value = classId || 0;
    
    const balance = Math.max(0, s.total_due - s.total_paid);
    document.getElementById('payStudentInfo').innerHTML =
        `<strong style="font-size:15px;">${s.student_name}</strong><br>
         <span style="color:var(--text-muted);">${s.class_name || '—'}</span> &nbsp;·&nbsp;
         Dette globale : <span style="color:#ef4444;font-weight:700;">${Math.round(balance).toLocaleString('fr')} FC</span>`;
         
    document.getElementById('pay_fee_type_id').value = '';
    document.getElementById('paymentSummaryPanel').style.display = 'none';
    document.getElementById('tranchesSection').style.display = 'none';
    document.getElementById('liveCalcPreview').style.display = 'none';
    document.getElementById('usdEquivRow').style.display = 'none';
    document.getElementById('pay_amount').value = '';
    document.getElementById('pay_amount_hint').textContent = '';
    document.getElementById('pay_tranche_paid').value = '';
    document.getElementById('pay_notes').value = '';
    // Remettre FC par défaut
    setCurrency('FC');
    currentFeeData = null;
    openModal('payModal');
}

function fetchStudentFeeDetails() {
    const studentId = document.getElementById('pay_student_id').value;
    const feeTypeId = document.getElementById('pay_fee_type_id').value;
    const schoolYear = '<?= escape($school_year) ?>';
    
    if (!feeTypeId) {
        document.getElementById('paymentSummaryPanel').style.display = 'none';
        document.getElementById('tranchesSection').style.display = 'none';
        document.getElementById('liveCalcPreview').style.display = 'none';
        document.getElementById('pay_amount').value = '';
        currentFeeData = null;
        return;
    }
    
    fetch(`fees.php?ajax_action=get_fee_details&student_id=${studentId}&fee_type_id=${feeTypeId}&year=${schoolYear}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            
            currentFeeData = data;
            
            document.getElementById('paymentSummaryPanel').style.display = 'block';
            document.getElementById('lblTotalDue').textContent = Math.round(data.total_due).toLocaleString('fr') + ' FC';
            document.getElementById('lblTotalPaid').textContent = Math.round(data.total_paid).toLocaleString('fr') + ' FC';
            document.getElementById('lblRemaining').textContent = Math.round(data.remaining).toLocaleString('fr') + ' FC';
            
            document.getElementById('pay_amount').value = Math.round(data.remaining);
            
            renderPaymentTranches();
            updateLiveCalculations();
        })
        .catch(err => {
            console.error('Error fetching fee details:', err);
        });
}

function renderPaymentTranches() {
    const container = document.getElementById('trancheBadgesContainer');
    container.innerHTML = '';
    document.getElementById('tranchesSection').style.display = 'block';
    
    let tranches = [];
    if (currentFeeData.fee_type.tranche_note) {
        try {
            tranches = JSON.parse(currentFeeData.fee_type.tranche_note);
        } catch(e) {
            tranches = parseTextTranches(currentFeeData.fee_type.tranche_note, currentFeeData.fee_type.amount, currentFeeData.fee_type.nb_tranches);
        }
    } else {
        tranches = getDefaultTranches(currentFeeData.fee_type.amount, currentFeeData.fee_type.nb_tranches || 3);
    }
    
    let accumulatedPaid = currentFeeData.total_paid;
    const paidTrancheNames = currentFeeData.payments.map(p => (p.tranche_paid || '').trim().toLowerCase());
    
    tranches.forEach((tr, index) => {
        const isNamePaid = paidTrancheNames.includes(tr.name.trim().toLowerCase());
        
        let isPaid = isNamePaid;
        let remainingAmount = tr.amount;
        
        if (!isPaid) {
            if (accumulatedPaid >= tr.amount) {
                isPaid = true;
                accumulatedPaid -= tr.amount;
                remainingAmount = 0;
            } else if (accumulatedPaid > 0) {
                remainingAmount = tr.amount - accumulatedPaid;
                accumulatedPaid = 0;
            }
        } else {
            remainingAmount = 0;
        }
        
        const card = document.createElement('div');
        card.className = 'tranche-payment-badge';
        card.style.display = 'flex';
        card.style.justifyContent = 'space-between';
        card.style.alignItems = 'center';
        card.style.padding = '8px 12px';
        card.style.background = isPaid ? 'rgba(16,185,129,0.06)' : 'rgba(255,255,255,0.02)';
        card.style.border = isPaid ? '1px solid rgba(16,185,129,0.3)' : '1px solid var(--border)';
        card.style.borderRadius = '8px';
        card.style.cursor = isPaid ? 'not-allowed' : 'pointer';
        card.style.transition = 'all 0.2s';
        
        if (!isPaid) {
            card.onclick = () => selectTrancheForPayment(tr.name, remainingAmount, card);
            card.onmouseenter = () => {
                if (!card.classList.contains('selected')) {
                    card.style.borderColor = 'var(--primary)';
                    card.style.background = 'rgba(212,147,13,0.04)';
                }
            };
            card.onmouseleave = () => {
                if (!card.classList.contains('selected')) {
                    card.style.borderColor = 'var(--border)';
                    card.style.background = 'rgba(255,255,255,0.02)';
                }
            };
        }
        
        const badgeLabel = isPaid ? '✅ Payé' : (remainingAmount < tr.amount ? '🟡 Partiel' : '⭕ À payer');
        const badgeColor = isPaid ? '#10b981' : (remainingAmount < tr.amount ? '#f59e0b' : 'var(--text-muted)');
        
        card.innerHTML = `
            <div>
                <div style="font-weight:600; font-size:12px; color:${isPaid ? '#10b981' : 'var(--text)'};">${tr.name}</div>
                <div style="font-size:10px; color:var(--text-muted); margin-top:2px;">
                    Montant : ${Math.round(tr.amount).toLocaleString('fr')} FC
                    ${(remainingAmount < tr.amount && remainingAmount > 0) ? ` (Reste : ${Math.round(remainingAmount).toLocaleString('fr')} FC)` : ''}
                </div>
            </div>
            <span style="font-size:9px; font-weight:700; padding:2px 6px; border-radius:12px; background:rgba(255,255,255,0.04); color:${badgeColor}; border:1px solid ${isPaid?'rgba(16,185,129,0.2)':'rgba(255,255,255,0.08)'};">
                ${badgeLabel}
            </span>
        `;
        container.appendChild(card);
    });
}

function parseTextTranches(note, totalAmount, nbTranches) {
    const parts = note.split(/[,;·]/);
    const tranches = [];
    const amountPerTranche = Math.round(totalAmount / (parts.length || nbTranches || 1));
    
    parts.forEach((p, idx) => {
        const name = p.trim() || `${idx+1}ème tranche`;
        tranches.push({ name: name, amount: amountPerTranche });
    });
    return tranches;
}

function getDefaultTranches(totalAmount, nbTranches) {
    const tranches = [];
    const amountPerTranche = Math.round(totalAmount / (nbTranches || 1));
    if (nbTranches >= 1) tranches.push({ name: 'Acompte', amount: amountPerTranche });
    if (nbTranches >= 2) tranches.push({ name: 'Deuxième tranche', amount: amountPerTranche });
    if (nbTranches >= 3) tranches.push({ name: 'Troisième tranche', amount: amountPerTranche });
    for (let i = 4; i <= nbTranches; i++) {
        tranches.push({ name: `${i}ème tranche`, amount: amountPerTranche });
    }
    return tranches;
}

function selectTrancheForPayment(name, amount, element) {
    document.querySelectorAll('#trancheBadgesContainer .tranche-payment-badge').forEach(el => {
        el.classList.remove('selected');
        el.style.borderColor = 'var(--border)';
        el.style.background = 'rgba(255,255,255,0.02)';
    });
    
    element.classList.add('selected');
    element.style.borderColor = 'var(--primary)';
    element.style.background = 'rgba(212,147,13,0.06)';
    
    document.getElementById('pay_amount').value = Math.round(amount);
    document.getElementById('pay_tranche_paid').value = name;
    document.getElementById('pay_notes').value = 'Paiement : ' + name;
    
    updateLiveCalculations();
}

function addCustomPaymentTranche() {
    const name = prompt('Nom de la tranche personnalisée (ex: Quatrième tranche, Tranche de rattrapage) :');
    if (!name || name.trim() === '') return;
    
    const amountStr = prompt('Montant de cette tranche en FC :');
    const amount = parseFloat(amountStr);
    if (isNaN(amount) || amount <= 0) {
        alert('Montant invalide.');
        return;
    }
    
    const container = document.getElementById('trancheBadgesContainer');
    const card = document.createElement('div');
    card.className = 'tranche-payment-badge';
    card.style.display = 'flex';
    card.style.justifyContent = 'space-between';
    card.style.alignItems = 'center';
    card.style.padding = '8px 12px';
    card.style.background = 'rgba(255,255,255,0.02)';
    card.style.border = '1px solid var(--border)';
    card.style.borderRadius = '8px';
    card.style.cursor = 'pointer';
    card.style.transition = 'all 0.2s';
    
    card.onclick = () => selectTrancheForPayment(name, amount, card);
    card.onmouseenter = () => {
        if (!card.classList.contains('selected')) {
            card.style.borderColor = 'var(--primary)';
            card.style.background = 'rgba(212,147,13,0.04)';
        }
    };
    card.onmouseleave = () => {
        if (!card.classList.contains('selected')) {
            card.style.borderColor = 'var(--border)';
            card.style.background = 'rgba(255,255,255,0.02)';
        }
    };
    
    card.innerHTML = `
        <div>
            <div style="font-weight:600; font-size:12px; color:var(--text);">${name}</div>
            <div style="font-size:10px; color:var(--text-muted); margin-top:2px;">Montant : ${Math.round(amount).toLocaleString('fr')} FC</div>
        </div>
        <span style="font-size:9px; font-weight:700; padding:2px 6px; border-radius:12px; background:rgba(255,255,255,0.04); color:var(--text-muted); border:1px solid rgba(255,255,255,0.08);">
            ⭕ À payer
        </span>
    `;
    container.appendChild(card);
    
    selectTrancheForPayment(name, amount, card);
}

// ── Devise & Taux de change ──────────────────────────────────────────────────
let currentCurrency = 'FC';
let currentRate = 2800;

function setCurrency(c) {
    currentCurrency = c;
    document.getElementById('pay_currency').value = c;
    document.getElementById('pay_exchange_rate').value = currentRate;
    const btnFC  = document.getElementById('btnCurrencyFC');
    const btnUSD = document.getElementById('btnCurrencyUSD');
    if (c === 'USD') {
        btnFC.style.background  = 'transparent'; btnFC.style.color = 'var(--text-muted)';
        btnUSD.style.background = 'var(--primary)'; btnUSD.style.color = '#000';
        document.getElementById('pay_amount_label').textContent = 'Montant payé (USD) *';
    } else {
        btnFC.style.background  = 'var(--primary)'; btnFC.style.color = '#000';
        btnUSD.style.background = 'transparent'; btnUSD.style.color = 'var(--text-muted)';
        document.getElementById('pay_amount_label').textContent = 'Montant payé (FC) *';
    }
    updateLiveCalculations();
    updateUSDEquiv();
}

function onRateChange() {
    currentRate = parseFloat(document.getElementById('pay_rate_input').value || 2800);
    document.getElementById('pay_exchange_rate').value = currentRate;
    updateLiveCalculations();
    updateUSDEquiv();
}

function updateUSDEquiv() {
    const row    = document.getElementById('usdEquivRow');
    const disp   = document.getElementById('usdEquivDisplay');
    const amount = parseFloat(document.getElementById('pay_amount').value || 0);
    if (amount <= 0 || currentRate <= 0) { row.style.display = 'none'; return; }
    row.style.display = 'block';
    if (currentCurrency === 'USD') {
        disp.textContent = `$${amount} USD = ${Math.round(amount * currentRate).toLocaleString('fr')} FC`;
    } else {
        disp.textContent = `${amount.toLocaleString('fr')} FC ≈ $${(amount / currentRate).toFixed(2)} USD`;
    }
}

// ── Calculs en temps réel ────────────────────────────────────────────────────
function updateLiveCalculations() {
    if (!currentFeeData) return;
    const raw = parseFloat(document.getElementById('pay_amount').value || 0);
    const amountFC = currentCurrency === 'USD' ? raw * currentRate : raw;
    const newPaid = currentFeeData.total_paid + amountFC;
    const newRemaining = Math.max(0, currentFeeData.total_due - newPaid);

    document.getElementById('liveCalcPreview').style.display = raw > 0 ? 'block' : 'none';
    document.getElementById('lblNewPaid').textContent      = Math.round(newPaid).toLocaleString('fr') + ' FC';
    document.getElementById('lblNewRemaining').textContent = Math.round(newRemaining).toLocaleString('fr') + ' FC';

    // Hint de conversion USD→FC
    const hint = document.getElementById('pay_amount_hint');
    if (currentCurrency === 'USD' && raw > 0) {
        hint.textContent = `= ${Math.round(raw * currentRate).toLocaleString('fr')} FC`;
    } else {
        hint.textContent = '';
    }
    updateUSDEquiv();
}

function toggleRef(val) {
    document.getElementById('refRow').style.display =
        ['orange_money','mpesa','airtel_money','cheque','virement'].includes(val) ? 'block' : 'none';
}

// ── Gestion de la Configuration des Frais (Modal split) ────────────────────────────────
let trancheIndex = 0;

function addTrancheRow(name = '', amount = '') {
    const container = document.getElementById('tranchesContainer');
    const div = document.createElement('div');
    div.className = 'tranche-row';
    div.style.display = 'flex';
    div.style.gap = '8px';
    div.style.alignItems = 'center';
    div.id = 'tranche_row_' + trancheIndex;
    
    div.innerHTML = `
        <input type="text" name="tranche_names[]" class="form-control tranche-name-input" placeholder="Nom de tranche (ex: Acompte)" value="${name}" required style="flex: 2; font-size: 13px; padding: 6px 10px;">
        <input type="number" name="tranche_amounts[]" class="form-control tranche-amount-input" placeholder="Montant (FC)" value="${amount}" required min="0" step="500" style="flex: 1.5; font-size: 13px; padding: 6px 10px;" oninput="calculateTotalFromTranches()">
        <button type="button" class="btn btn-sm btn-outline" style="color:var(--danger); border-color:rgba(239,68,68,0.2); padding: 6px 10px;" onclick="removeTrancheRow(${trancheIndex})">✕</button>
    `;
    container.appendChild(div);
    trancheIndex++;
    calculateTotalFromTranches();
}

function removeTrancheRow(index) {
    const row = document.getElementById('tranche_row_' + index);
    if (row) row.remove();
    calculateTotalFromTranches();
}

function calculateTotalFromTranches() {
    const amountInputs = document.querySelectorAll('.tranche-amount-input');
    let total = 0;
    amountInputs.forEach(input => {
        total += parseFloat(input.value || 0);
    });
    document.getElementById('ft_amount').value = total;
}

function editFeeType(ft, tranchesList) {
    document.getElementById('feeTypeAction').value = 'edit_fee_type';
    document.getElementById('ft_id_hidden').value = ft.id;
    document.getElementById('ft_name').value = ft.name;
    document.getElementById('ft_amount').value = ft.amount;
    document.getElementById('ft_year').value = ft.school_year;
    document.getElementById('ft_desc').value = ft.description || '';
    
    document.getElementById('feeTypeSubmitBtn').textContent = '💾 Modifier le type de frais';
    document.getElementById('feeTypeCancelBtn').style.display = 'inline-flex';
    document.getElementById('formTitle').textContent = '✏️ Modifier le type de frais';
    
    const container = document.getElementById('tranchesContainer');
    container.innerHTML = '';
    
    if (tranchesList && tranchesList.length > 0) {
        tranchesList.forEach(tr => {
            addTrancheRow(tr.name, tr.amount);
        });
    } else {
        addTrancheRow('Acompte', ft.amount);
    }
    openModal('feeConfigModal');
}

function resetFeeTypeForm() {
    document.getElementById('feeTypeAction').value = 'add_fee_type';
    document.getElementById('feeTypeForm').reset();
    document.getElementById('feeTypeSubmitBtn').textContent = '➕ Ajouter le type de frais';
    document.getElementById('feeTypeCancelBtn').style.display = 'none';
    document.getElementById('formTitle').textContent = '➕ Nouveau type de frais';
    
    const container = document.getElementById('tranchesContainer');
    container.innerHTML = '';
    addTrancheRow('Acompte', '');
    addTrancheRow('Deuxième tranche', '');
    addTrancheRow('Troisième tranche', '');
}

// Initialiser les tranches par défaut dans le formulaire de config
document.addEventListener('DOMContentLoaded', () => {
    resetFeeTypeForm();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

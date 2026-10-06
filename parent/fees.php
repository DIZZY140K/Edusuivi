<?php require_once __DIR__ . '/../includes/header.php'; requireRole('parent');

$stmt = $pdo->prepare("SELECT s.id, CONCAT(s.first_name,' ',s.last_name) as name, s.matricule, c.name as class_name FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.parent_id=?");
$stmt->execute([$currentUser['id']]);
$children = $stmt->fetchAll();
$childIds = array_column($children, 'id');
?>

<style>
/* ===== Mobile Money — Styles ===== */

/* FIX CRITIQUE : chaque step doit être une colonne flex pour que les actions restent visibles */
.mm-step-panel {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
.mm-step-panel .modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 18px 22px;
    min-height: 0;
}
.mm-step-panel .modal-actions {
    flex-shrink: 0;
    padding: 14px 22px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    background: var(--card);
    border-radius: 0 0 16px 16px;
}

/* Grille opérateurs */
.mm-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
    margin-bottom: 20px;
}
@media(min-width:480px){ .mm-grid { grid-template-columns: repeat(4,1fr); } }

.mm-operator {
    border: 2.5px solid var(--border);
    border-radius: 16px;
    padding: 14px 10px 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.4,0,0.2,1);
    position: relative;
    background: var(--card);
    user-select: none;
    overflow: hidden;
}
.mm-operator:hover {
    transform: translateY(-5px);
    box-shadow: 0 14px 32px rgba(0,0,0,0.13);
    border-color: var(--op-color, var(--primary));
}
.mm-operator.selected {
    border-color: var(--op-color, var(--primary));
    box-shadow: 0 0 0 3px var(--op-color, var(--primary));
    transform: translateY(-4px);
}
.mm-operator.selected::after {
    content: '✓';
    position: absolute;
    top: -10px; right: -10px;
    width: 24px; height: 24px;
    background: var(--op-color, var(--primary));
    color: #fff;
    border-radius: 50%;
    font-size: 12px; font-weight: 900;
    display: flex; align-items: center; justify-content: center;
    border: 2px solid var(--card);
    animation: scaleIn .2s cubic-bezier(0.34,1.56,0.64,1);
}

/* Image logo opérateur */
.op-img-wrap {
    width: 70px; height: 70px;
    border-radius: 14px;
    overflow: hidden;
    margin: 0 auto 10px;
    display: flex; align-items: center; justify-content: center;
    transition: transform .22s ease;
    box-shadow: 0 4px 14px rgba(0,0,0,0.12);
}
.mm-operator:hover .op-img-wrap { transform: scale(1.06) rotate(-2deg); }
.op-img-wrap img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}
.mm-operator .op-name {
    font-size: 11px; font-weight: 800;
    margin-bottom: 2px; letter-spacing: .3px;
}
.mm-operator .op-tag {
    font-size: 10px; color: var(--text-muted);
}

/* Couleurs par opérateur */
.mm-mpesa    { --op-color: #cc0000; }
.mm-airtel   { --op-color: #e2001a; }
.mm-orange   { --op-color: #ff6600; }
.mm-afrimoney{ --op-color: #b5007d; }

/* Step bar */
.mm-steps {
    display: flex;
    margin-bottom: 20px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--border);
}
.mm-step {
    flex: 1; padding: 10px 6px;
    text-align: center; font-size: 10px; font-weight: 700;
    color: var(--text-muted); background: var(--bg);
    border-right: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center; gap: 5px;
}
.mm-step:last-child { border-right: none; }
.mm-step.active { background: var(--primary); color: #fff; }
.mm-step.done   { background: linear-gradient(135deg,#10b981,#059669); color: #fff; }
.mm-step .sn    { width: 16px; height: 16px; border-radius: 50%; background:rgba(255,255,255,.25); font-size:9px; display:inline-flex; align-items:center; justify-content:center; }

/* Chip opérateur sélectionné */
.op-chip {
    display: inline-flex; align-items: center; gap: 10px;
    padding: 8px 16px; border-radius: 30px; margin-bottom: 18px;
    font-size: 13px; font-weight: 700; color: #fff;
}
.op-chip img { width: 28px; height: 28px; border-radius: 8px; object-fit: cover; }

/* Champ téléphone */
.phone-wrap { position:relative; display:flex; align-items:center; }
.phone-prefix { position:absolute; left:14px; font-weight:700; font-size:14px; color:var(--text-muted); pointer-events:none; }
.phone-field  { padding-left:60px !important; letter-spacing:1px; font-size:15px !important; font-weight:600 !important; }

/* Montants rapides */
.quick-amounts { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
.qa-btn {
    padding: 6px 14px; border: 2px solid var(--border); border-radius: 20px;
    background: transparent; font-size: 12px; font-weight: 700;
    cursor: pointer; transition: all .18s; font-family: inherit; color: var(--text);
}
.qa-btn:hover  { border-color:var(--primary); color:var(--primary); background:rgba(26,54,93,0.05); }
.qa-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }

/* Récap */
.pay-recap {
    background: linear-gradient(135deg,rgba(26,54,93,0.05),rgba(212,147,13,0.05));
    border: 1px solid var(--border); border-radius:14px; padding:16px; margin-top:16px;
}
.recap-row {
    display:flex; justify-content:space-between; font-size:13px;
    padding:5px 0; border-bottom:1px dashed var(--border);
}
.recap-row:last-child { border-bottom:none; padding-top:10px; }
.recap-total { font-weight:800; font-size:17px; color:var(--primary); }

/* Bouton payer */
.btn-pay {
    width:100%; padding:15px; border:none; border-radius:12px;
    font-size:15px; font-weight:800; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:10px;
    transition:all .22s; font-family:inherit; letter-spacing:.3px;
}
.btn-pay:disabled { opacity:.45; cursor:not-allowed !important; transform:none !important; filter:none !important; }
.btn-pay:not(:disabled):hover { transform:translateY(-2px); filter:brightness(1.07); }
.bp-mpesa    { background:linear-gradient(135deg,#cc0000,#990000); color:#fff; box-shadow:0 6px 20px rgba(204,0,0,.35); }
.bp-airtel   { background:linear-gradient(135deg,#e2001a,#a00013); color:#fff; box-shadow:0 6px 20px rgba(226,0,26,.35); }
.bp-orange   { background:linear-gradient(135deg,#ff6600,#cc5200); color:#fff; box-shadow:0 6px 20px rgba(255,102,0,.35); }
.bp-afrimoney{ background:linear-gradient(135deg,#b5007d,#80005a); color:#fff; box-shadow:0 6px 20px rgba(181,0,125,.35); }

/* Info box orange */
.info-coming {
    padding:12px 16px; background:rgba(245,158,11,0.08);
    border:1px solid rgba(245,158,11,0.25); border-radius:10px;
    display:flex; align-items:flex-start; gap:10px; font-size:12px; color:var(--text-muted);
}

/* Spinner */
.mm-spinner {
    width:60px; height:60px; border-radius:50%;
    border:5px solid var(--border); border-top-color:#00a651;
    animation:spin .8s linear infinite; margin:0 auto 16px;
}

/* Success icon */
.success-icon {
    width:70px; height:70px; border-radius:50%;
    background:linear-gradient(135deg,#10b981,#059669);
    display:flex; align-items:center; justify-content:center;
    font-size:30px; margin:0 auto 16px;
    animation:scaleIn .4s cubic-bezier(0.34,1.56,0.64,1);
    box-shadow:0 8px 30px rgba(16,185,129,.4);
}

/* Sécurité */
.security-banner {
    display:flex; align-items:center; gap:8px; font-size:11px;
    color:var(--text-muted); padding:10px 14px;
    background:rgba(16,185,129,0.05); border:1px solid rgba(16,185,129,.15);
    border-radius:10px; margin-top:14px;
}

/* Confirm logo */
.confirm-logo {
    width:56px; height:56px; border-radius:14px; overflow:hidden;
    box-shadow:0 4px 16px rgba(0,0,0,.15);
}
.confirm-logo img { width:100%; height:100%; object-fit:cover; }
</style>

<div class="page-header">
    <div><h2>💰 Frais scolaires</h2><p>État des paiements et paiement via Mobile Money</p></div>
</div>

<?php if(empty($childIds)): ?>
<div class="card" style="padding:40px;text-align:center;color:var(--text-muted);">Aucun enfant enregistré.</div>
<?php else: ?>

<?php foreach($children as $child):
    $fees = $pdo->prepare("SELECT sf.*, ft.name as fee_name FROM student_fees sf JOIN fee_types ft ON sf.fee_type_id=ft.id WHERE sf.student_id=?");
    $fees->execute([$child['id']]); $feeList = $fees->fetchAll();

    $payments = $pdo->prepare("SELECT fp.*, ft.name as fee_name FROM fee_payments fp JOIN student_fees sf ON fp.student_fee_id=sf.id JOIN fee_types ft ON sf.fee_type_id=ft.id WHERE fp.student_id=? ORDER BY fp.payment_date DESC");
    $payments->execute([$child['id']]); $payList = $payments->fetchAll();

    $totalDue  = array_sum(array_column($feeList,'amount_due'));
    $totalPaid = array_sum(array_column($payList,'amount_paid'));
    $balance   = max(0, $totalDue - $totalPaid);
    $pct       = $totalDue > 0 ? round($totalPaid/$totalDue*100) : 0;
?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--gold-light));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:18px;color:#fff;"><?= strtoupper(substr($child['name'],0,1)) ?></div>
            <div>
                <div style="font-size:16px;font-weight:700;"><?= escape($child['name']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= escape($child['class_name']??'') ?> · <?= escape($child['matricule']??'') ?></div>
            </div>
        </div>
        <?php if($balance > 0): ?>
        <button class="btn btn-gold" onclick="openPayModal(<?= $child['id'] ?>,'<?= addslashes(escape($child['name'])) ?>',<?= $balance ?>,<?= $totalDue ?>)">
            📱 Payer via Mobile Money
        </button>
        <?php else: ?>
        <span class="badge badge-success" style="font-size:13px;padding:8px 16px;">✅ Tout soldé</span>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid var(--border);">
        <div style="padding:16px;text-align:center;border-right:1px solid var(--border);">
            <div style="font-size:20px;font-weight:700;color:var(--primary);"><?= number_format($totalDue,0,',',' ') ?> FC</div>
            <div style="font-size:12px;color:var(--text-muted);">Total dû</div>
        </div>
        <div style="padding:16px;text-align:center;border-right:1px solid var(--border);">
            <div style="font-size:20px;font-weight:700;color:#10b981;"><?= number_format($totalPaid,0,',',' ') ?> FC</div>
            <div style="font-size:12px;color:var(--text-muted);">Total payé</div>
        </div>
        <div style="padding:16px;text-align:center;">
            <div style="font-size:20px;font-weight:700;color:<?= $balance>0?'#ef4444':'#10b981' ?>;"><?= number_format($balance,0,',',' ') ?> FC</div>
            <div style="font-size:12px;color:var(--text-muted);">Reste à payer</div>
        </div>
    </div>

    <!-- Progress -->
    <div style="padding:12px 20px;border-bottom:1px solid var(--border);">
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted);margin-bottom:6px;">
            <span>Progression</span><span><?= $pct ?>%</span>
        </div>
        <div style="background:var(--bg);border-radius:8px;height:10px;overflow:hidden;">
            <div style="height:100%;background:<?= $pct>=100?'#10b981':($pct>=50?'#f0b429':'#ef4444') ?>;width:<?= $pct ?>%;transition:width .5s;border-radius:8px;"></div>
        </div>
    </div>

    <!-- Détail frais -->
    <?php if($feeList): ?>
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);">
        <h4 style="margin:0 0 12px;font-size:13px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Détail des frais</h4>
        <div style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach($feeList as $f):
            $paid_for  = array_sum(array_column(array_filter($payList,fn($p)=>$p['student_fee_id']==$f['id']),'amount_paid'));
            $remaining = max(0,$f['amount_due']-$paid_for);
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:var(--bg);border-radius:8px;">
            <span style="font-size:13px;"><?= escape($f['fee_name']) ?></span>
            <div style="text-align:right;">
                <div style="font-size:12px;color:var(--text-muted);"><?= number_format($f['amount_due'],0,',',' ') ?> FC</div>
                <?php if($remaining>0): ?><span style="font-size:11px;color:#ef4444;">Reste : <?= number_format($remaining,0,',',' ') ?> FC</span>
                <?php else: ?><span class="badge badge-success" style="font-size:10px;">✅ Soldé</span><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Historique -->
    <?php if($payList): ?>
    <div style="padding:16px 20px;">
        <h4 style="margin:0 0 12px;font-size:13px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;">Historique des paiements</h4>
        <table style="width:100%;font-size:13px;">
            <thead><tr><th>Date</th><th>Type</th><th>Montant</th><th>Mode</th><th>N° Reçu</th></tr></thead>
            <tbody>
            <?php foreach($payList as $p):
                $method = strtolower($p['payment_method']??'');
                $mmMap  = ['mpesa'=>['🟢','M-Pesa'],'airtel_money'=>['🔴','Airtel Money'],'orange_money'=>['🟠','Orange Money'],'afrimoney'=>['🟣','Afrimoney']];
            ?>
            <tr>
                <td><?= date('d/m/Y',strtotime($p['payment_date'])) ?></td>
                <td><?= escape($p['fee_name']) ?></td>
                <td style="font-weight:700;color:#10b981;"><?= number_format($p['amount_paid'],0,',',' ') ?> FC</td>
                <td><?php if(isset($mmMap[$method])): ?><span style="font-size:11px;font-weight:600;"><?= $mmMap[$method][0].' '.$mmMap[$method][1] ?></span><?php else: ?><span style="font-size:12px;"><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></span><?php endif; ?></td>
                <td><code style="color:var(--primary);font-size:11px;"><?= escape($p['receipt_number']??'—') ?></code></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="padding:16px 20px;text-align:center;color:var(--text-muted);font-size:13px;">Aucun paiement enregistré.</div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>


<!-- ======================================================
     MODAL MOBILE MONEY
     ====================================================== -->
<div class="modal-overlay" id="mmModal">
    <div class="modal" id="mmModalBox" style="max-width:560px;">
        <button class="modal-close-btn" onclick="closePayModal()" title="Fermer">✕</button>

        <!-- ===== ÉTAPE 1 : Choix opérateur ===== -->
        <div id="mm-s1" class="mm-step-panel">
            <h3 style="padding:20px 48px 16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0;">
                <span style="font-size:22px;">📱</span> Paiement Mobile Money
            </h3>
            <div class="modal-body">
                <div class="mm-steps">
                    <div class="mm-step active"><span class="sn">1</span> Opérateur</div>
                    <div class="mm-step"><span class="sn">2</span> Numéro & Montant</div>
                    <div class="mm-step"><span class="sn">3</span> Confirmation</div>
                </div>

                <!-- Résumé enfant -->
                <div style="padding:12px 16px;background:rgba(26,54,93,0.05);border-radius:12px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:12px;color:var(--text-muted);">Paiement pour</div>
                        <div style="font-weight:700;font-size:15px;" id="mm-child-name">—</div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:12px;color:var(--text-muted);">Solde restant</div>
                        <div style="font-size:20px;font-weight:800;color:#ef4444;" id="mm-balance-display">—</div>
                    </div>
                </div>

                <p style="font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:14px;">Choisissez votre opérateur :</p>

                <div class="mm-grid">
                    <div class="mm-operator mm-mpesa" id="op-mpesa" onclick="selectOp('mpesa','M-Pesa','+243','#cc0000')">
                        <div class="op-img-wrap">
                            <img src="/edusuivi/images/operators/mpesa.png" alt="M-Pesa" onerror="this.parentElement.innerHTML='<span style=font-size:24px;font-weight:900;color:#cc0000>M</span>'">
                        </div>
                        <div class="op-name" style="color:#cc0000;">M-Pesa</div>
                        <div class="op-tag">Vodacom Congo</div>
                    </div>
                    <div class="mm-operator mm-airtel" id="op-airtel" onclick="selectOp('airtel','Airtel Money','+243','#e2001a')">
                        <div class="op-img-wrap">
                            <img src="/edusuivi/images/operators/airtel.png" alt="Airtel Money" onerror="this.parentElement.innerHTML='<span style=font-size:14px;font-weight:900;color:#fff;background:#e2001a;padding:4px 8px;border-radius:6px>Airtel</span>'">
                        </div>
                        <div class="op-name" style="color:#e2001a;">Airtel Money</div>
                        <div class="op-tag">Airtel Congo</div>
                    </div>
                    <div class="mm-operator mm-orange" id="op-orange" onclick="selectOp('orange','Orange Money','+243','#ff6600')">
                        <div class="op-img-wrap">
                            <img src="/edusuivi/images/operators/orange.png" alt="Orange Money" onerror="this.parentElement.innerHTML='<span style=font-size:20px>◉</span>'">
                        </div>
                        <div class="op-name" style="color:#ff6600;">Orange Money</div>
                        <div class="op-tag">Orange RDC</div>
                    </div>
                    <div class="mm-operator mm-afrimoney" id="op-afrimoney" onclick="selectOp('afrimoney','Afrimoney','+243','#b5007d')">
                        <div class="op-img-wrap">
                            <img src="/edusuivi/images/operators/afrimoney.png" alt="Afrimoney" onerror="this.parentElement.innerHTML='<span style=font-size:12px;font-weight:900;color:#b5007d>afrimoney</span>'">
                        </div>
                        <div class="op-name" style="color:#b5007d;">Afrimoney</div>
                        <div class="op-tag">Africell RDC</div>
                    </div>
                </div>

                <div class="info-coming">
                    <span style="font-size:18px;flex-shrink:0;">🔔</span>
                    <span><strong style="color:var(--text);">Intégration en cours.</strong> Le paiement en ligne sera disponible très prochainement. En attendant, vous pouvez noter la référence et payer directement via votre téléphone.</span>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closePayModal()">Annuler</button>
                <button class="btn btn-primary" id="btn-next1" onclick="goStep2()" disabled style="min-width:130px;">
                    Continuer →
                </button>
            </div>
        </div>

        <!-- ===== ÉTAPE 2 : Numéro & Montant ===== -->
        <div id="mm-s2" class="mm-step-panel" style="display:none;">
            <h3 style="padding:20px 48px 16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0;">
                <img id="s2-op-img" src="" style="width:30px;height:30px;border-radius:8px;object-fit:cover;" alt="">
                Numéro &amp; Montant
            </h3>
            <div class="modal-body">
                <div class="mm-steps">
                    <div class="mm-step done">✓ Opérateur</div>
                    <div class="mm-step active"><span class="sn">2</span> Numéro & Montant</div>
                    <div class="mm-step"><span class="sn">3</span> Confirmation</div>
                </div>

                <div class="op-chip" id="s2-chip"></div>

                <!-- Téléphone -->
                <div style="margin-bottom:18px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:8px;">Numéro Mobile Money <span style="color:#ef4444;">*</span></label>
                    <div class="phone-wrap">
                        <span class="phone-prefix" id="mm-prefix">+243</span>
                        <input type="tel" id="mm-phone" class="form-control phone-field"
                               placeholder="8XX XXX XXX" maxlength="12"
                               oninput="fmtPhone(this);validateS2();">
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:5px;">Format : 8XX XXX XXX — sans le 0 ou l'indicatif</div>
                </div>

                <!-- Montant -->
                <div style="margin-bottom:18px;">
                    <label style="display:block;font-size:13px;font-weight:700;margin-bottom:8px;">Montant (FC) <span style="color:#ef4444;">*</span></label>
                    <div class="quick-amounts" id="qa-wrap"></div>
                    <div style="position:relative;">
                        <input type="number" id="mm-amount" class="form-control" placeholder="Montant en FC"
                               min="100" step="100"
                               oninput="syncQA(this.value);validateS2();"
                               style="padding-right:50px;font-size:15px;font-weight:700;">
                        <span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--text-muted);font-weight:700;">FC</span>
                    </div>
                </div>

                <!-- Récap live -->
                <div class="pay-recap" id="s2-recap" style="display:none;">
                    <div class="recap-row"><span>Opérateur</span><strong id="r-op">—</strong></div>
                    <div class="recap-row"><span>Numéro</span><strong id="r-phone">—</strong></div>
                    <div class="recap-row"><span>Bénéficiaire</span><strong id="r-child">—</strong></div>
                    <div class="recap-row"><span style="font-weight:700;">Total à payer</span><span class="recap-total" id="r-amount">—</span></div>
                </div>

                <div class="security-banner">
                    <span style="font-size:16px;">🔒</span>
                    <span>Connexion sécurisée · Aucune donnée bancaire stockée · Référence unique générée</span>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="goStep1()">← Retour</button>
                <button class="btn btn-primary" id="btn-next2" onclick="goStep3()" disabled style="min-width:160px;">
                    Valider →
                </button>
            </div>
        </div>

        <!-- ===== ÉTAPE 3 : Confirmation ===== -->
        <div id="mm-s3" class="mm-step-panel" style="display:none;">
            <h3 style="padding:20px 48px 16px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0;">
                <span style="font-size:22px;">✅</span> Confirmer le paiement
            </h3>
            <div class="modal-body">
                <div class="mm-steps">
                    <div class="mm-step done">✓ Opérateur</div>
                    <div class="mm-step done">✓ Numéro</div>
                    <div class="mm-step active"><span class="sn">3</span> Confirmation</div>
                </div>

                <div style="background:var(--bg);border-radius:16px;padding:20px;margin-bottom:18px;">
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;padding-bottom:16px;border-bottom:1px solid var(--border);">
                        <div class="confirm-logo">
                            <img id="c-op-img" src="" alt="" style="width:100%;height:100%;object-fit:cover;">
                        </div>
                        <div>
                            <div style="font-size:17px;font-weight:800;" id="c-op-name">—</div>
                            <div style="font-size:13px;color:var(--text-muted);" id="c-phone">—</div>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <div style="display:flex;justify-content:space-between;font-size:14px;">
                            <span style="color:var(--text-muted);">Bénéficiaire</span><strong id="c-child">—</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:14px;">
                            <span style="color:var(--text-muted);">Référence</span>
                            <code style="color:var(--primary);font-size:12px;" id="c-ref">—</code>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:16px;padding-top:12px;border-top:2px solid var(--border);">
                            <strong>Montant</strong>
                            <strong style="font-size:22px;color:var(--primary);" id="c-amount">—</strong>
                        </div>
                    </div>
                </div>

                <!-- Instructions USSD -->
                <div id="ussd-box" style="padding:14px 16px;border-radius:12px;border:1px solid;margin-bottom:16px;">
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;color:var(--text-muted);">Instructions USSD</div>
                    <div style="font-size:13px;line-height:1.7;" id="ussd-text"></div>
                </div>

                <div class="info-coming">
                    <span style="font-size:16px;flex-shrink:0;">⚠️</span>
                    <span><strong style="color:var(--text);">Activation prochaine.</strong> Une notification push sera envoyée sur votre téléphone pour approuver la transaction dès que l'API sera opérationnelle.</span>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="goStep2()">← Modifier</button>
                <button class="btn btn-pay" id="btn-pay-final" onclick="doSimulatedPay()">
                    📱 Envoyer la demande
                </button>
            </div>
        </div>

        <!-- ===== ÉTAPE 4 : Traitement ===== -->
        <div id="mm-s4" style="display:none;">
            <div style="text-align:center;padding:50px 30px;">
                <div class="mm-spinner" id="pay-spinner"></div>
                <h4 style="font-size:17px;font-weight:800;margin-bottom:8px;">Demande envoyée !</h4>
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">Vérifiez votre téléphone et <strong>approuvez la transaction</strong> avec votre code PIN.</p>
                <div id="mm-countdown" style="font-size:40px;font-weight:800;color:var(--primary);margin:16px 0;">60</div>
                <p style="font-size:12px;color:var(--text-muted);">secondes restantes</p>
                <button class="btn btn-outline" style="margin-top:20px;" onclick="cancelPay()">Annuler</button>
            </div>
        </div>

        <!-- ===== ÉTAPE 5 : Succès ===== -->
        <div id="mm-s5" style="display:none;">
            <div style="text-align:center;padding:50px 30px;">
                <div class="success-icon">✅</div>
                <h4 style="font-size:20px;font-weight:800;margin-bottom:8px;">Paiement initié !</h4>
                <p style="color:var(--text-muted);max-width:340px;margin:0 auto 20px;">Votre demande a été transmise. La page sera mise à jour dès réception de la confirmation.</p>
                <div style="background:var(--bg);border-radius:12px;padding:14px 20px;margin-bottom:20px;font-size:13px;text-align:left;max-width:300px;margin-left:auto;margin-right:auto;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                        <span style="color:var(--text-muted);">Référence</span>
                        <code style="color:var(--primary);font-weight:700;" id="s5-ref">—</code>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">Statut</span>
                        <span class="badge badge-warning">En attente</span>
                    </div>
                </div>
                <button class="btn btn-primary" onclick="closePayModal(true)">Fermer</button>
            </div>
        </div>

    </div>
</div>

<script>
// ========= Config opérateurs =========
const OPS = {
    mpesa:     { name:'M-Pesa',       color:'#cc0000', bg:'linear-gradient(135deg,#cc0000,#990000)', img:'/edusuivi/images/operators/mpesa.png',     ussd:'*150*00#', hint:'Composez <strong>*150*00#</strong> · Choisissez "Payer" · Entrez la référence : ' },
    airtel:    { name:'Airtel Money', color:'#e2001a', bg:'linear-gradient(135deg,#e2001a,#a00013)', img:'/edusuivi/images/operators/airtel.png',    ussd:'*500#',    hint:'Composez <strong>*500#</strong> · Choisissez "Paiement" · Entrez la référence : ' },
    orange:    { name:'Orange Money', color:'#ff6600', bg:'linear-gradient(135deg,#ff6600,#cc5200)', img:'/edusuivi/images/operators/orange.png',    ussd:'#144#',    hint:'Composez <strong>#144#</strong> · Choisissez "Envoyer argent" · Référence : ' },
    afrimoney: { name:'Afrimoney',    color:'#b5007d', bg:'linear-gradient(135deg,#b5007d,#80005a)', img:'/edusuivi/images/operators/afrimoney.png', ussd:'*888#',    hint:'Composez <strong>*888#</strong> · Sélectionnez "Payer" · Référence : ' }
};
const QUICK = [5000,10000,25000,50000,100000];

let S = { childId:null, childName:'', balance:0, totalDue:0, op:null, phone:'', amount:0, ref:'' };
let timer = null;

// ========= Ouvrir / Fermer =========
function openPayModal(id, name, bal, due) {
    S = { childId:id, childName:name, balance:bal, totalDue:due, op:null, phone:'', amount:0, ref:'' };
    document.getElementById('mm-child-name').textContent    = name;
    document.getElementById('mm-balance-display').textContent = fmt(bal) + ' FC';
    goStep1();
    document.getElementById('mmModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closePayModal(reload) {
    document.getElementById('mmModal').classList.remove('active');
    document.body.style.overflow = '';
    if(timer) clearInterval(timer);
    if(reload) window.location.reload();
}
document.getElementById('mmModal').addEventListener('click', e => { if(e.target===document.getElementById('mmModal')) closePayModal(); });

// ========= Navigation étapes =========
function showStep(n) {
    ['mm-s1','mm-s2','mm-s3','mm-s4','mm-s5'].forEach((id,i) => {
        document.getElementById(id).style.display = (i===n-1) ? '' : 'none';
    });
}
function goStep1() {
    showStep(1);
    document.querySelectorAll('.mm-operator').forEach(e => e.classList.remove('selected'));
    document.getElementById('btn-next1').disabled = true;
    S.op = null;
}
function goStep2() {
    if(!S.op) return;
    showStep(2);
    const op = OPS[S.op];
    // Header image
    document.getElementById('s2-op-img').src = op.img;
    // Chip
    const chip = document.getElementById('s2-chip');
    chip.style.background = op.bg;
    chip.innerHTML = `<img src="${op.img}" alt="${op.name}" onerror="this.style.display='none'"> ${op.name}`;
    // Prefix
    document.getElementById('mm-prefix').textContent = S.prefix;
    // Quick amounts
    buildQA();
    // Reset
    document.getElementById('mm-phone').value = '';
    document.getElementById('mm-amount').value = '';
    document.getElementById('s2-recap').style.display = 'none';
    document.getElementById('btn-next2').disabled = true;
    // Pay button class
    document.getElementById('btn-pay-final').className = 'btn btn-pay bp-' + S.op;
}
function goStep3() {
    const phone  = document.getElementById('mm-phone').value.trim();
    const amount = parseInt(document.getElementById('mm-amount').value);
    if(!phone || !amount || amount<=0) return;
    S.phone  = phone;
    S.amount = amount;
    S.ref    = 'EDU-' + Date.now().toString(36).toUpperCase().slice(-8);
    showStep(3);
    const op = OPS[S.op];
    document.getElementById('c-op-img').src  = op.img;
    document.getElementById('c-op-name').textContent     = op.name;
    document.getElementById('c-phone').textContent       = S.prefix + ' ' + phone;
    document.getElementById('c-child').textContent       = S.childName;
    document.getElementById('c-ref').textContent         = S.ref;
    document.getElementById('c-amount').textContent      = fmt(amount) + ' FC';
    const ussdBox  = document.getElementById('ussd-box');
    ussdBox.style.borderColor = op.color;
    document.getElementById('ussd-text').innerHTML = op.hint + `<strong style="color:${op.color}">${S.ref}</strong>`;
}

// ========= Sélection opérateur =========
function selectOp(key, name, prefix, color) {
    S.op = key; S.operatorName = name; S.prefix = prefix;
    document.querySelectorAll('.mm-operator').forEach(e => {
        e.classList.remove('selected');
        e.style.removeProperty('--op-color');
    });
    const el = document.getElementById('op-' + key);
    if(el) { el.classList.add('selected'); el.style.setProperty('--op-color', color); }
    document.getElementById('btn-next1').disabled = false;
}

// ========= Quick amounts =========
function buildQA() {
    const wrap = document.getElementById('qa-wrap');
    wrap.innerHTML = '';
    // Bouton "Tout payer"
    if(S.balance > 0) {
        const b = mkQA('💯 Tout payer — ' + fmt(S.balance) + ' FC', S.balance);
        b.style.borderColor = '#10b981'; b.style.color = '#10b981';
        wrap.appendChild(b);
    }
    QUICK.filter(a => a < S.balance || S.balance===0).forEach(a => wrap.appendChild(mkQA(fmt(a)+' FC', a)));
}
function mkQA(label, amount) {
    const b = document.createElement('button');
    b.className = 'qa-btn'; b.type = 'button'; b.innerHTML = label;
    b.onclick = () => { document.getElementById('mm-amount').value = amount; syncQA(amount); validateS2(); };
    return b;
}
function syncQA(val) {
    document.querySelectorAll('.qa-btn').forEach(b => {
        const raw = parseInt(b.textContent.replace(/[^\d]/g,''));
        b.classList.toggle('active', raw == val);
    });
}

// ========= Validation step 2 =========
function validateS2() {
    const phone  = document.getElementById('mm-phone').value.trim();
    const amount = parseInt(document.getElementById('mm-amount').value);
    const ok     = phone.length >= 7 && !isNaN(amount) && amount >= 100;
    document.getElementById('btn-next2').disabled = !ok;
    const recap = document.getElementById('s2-recap');
    if(ok) {
        recap.style.display = 'block';
        document.getElementById('r-op').textContent    = OPS[S.op].name;
        document.getElementById('r-phone').textContent = S.prefix + ' ' + phone;
        document.getElementById('r-child').textContent = S.childName;
        document.getElementById('r-amount').textContent= fmt(amount) + ' FC';
    } else {
        recap.style.display = 'none';
    }
}

// ========= Formatage téléphone =========
function fmtPhone(el) {
    let v = el.value.replace(/\D/g,'');
    if(v.startsWith('243')) v = v.slice(3);
    if(v.startsWith('0'))   v = v.slice(1);
    el.value = v;
}

// ========= Paiement simulé =========
function doSimulatedPay() {
    showStep(4);
    document.getElementById('pay-spinner').style.borderTopColor = OPS[S.op].color;
    let sec = 60;
    document.getElementById('mm-countdown').textContent = sec;
    timer = setInterval(() => {
        sec--;
        document.getElementById('mm-countdown').textContent = sec;
        if(sec <= 0) { clearInterval(timer); showStep(5); document.getElementById('s5-ref').textContent = S.ref; }
    }, 1000);

    /* ---- TODO API réelle ----
    fetch('/edusuivi/api/mobile_money.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ operator:S.op, phone:S.prefix+S.phone, amount:S.amount, child_id:S.childId, ref:S.ref })
    }).then(r=>r.json()).then(d => {
        clearInterval(timer);
        if(d.success) { showStep(5); document.getElementById('s5-ref').textContent = S.ref; }
        else           { goStep3(); alert(d.message); }
    });
    ---- Fin TODO ---- */
}
function cancelPay() { clearInterval(timer); goStep3(); }

// ========= Helper =========
function fmt(n) { return parseInt(n).toLocaleString('fr-FR').replace(/\u202f/g,' '); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

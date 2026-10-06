<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
$currentUser = getUser();

if (!in_array($currentUser['role'], ['admin', 'school', 'teacher', 'parent'])) {
    redirect('/edusuivi/login.php');
}

$isParent = ($currentUser['role'] === 'parent');
$studentId = intval($_GET['student_id'] ?? 0);

// ── Liste élèves selon rôle ────────────────────────────────────────────
if ($isParent) {
    $stmtAll = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, s.matricule, c.name as class_name, s.class_id FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.parent_id=? ORDER BY s.first_name");
    $stmtAll->execute([$currentUser['id']]);
    $allStudents = $stmtAll->fetchAll();
    $myChildIds = array_column($allStudents, 'id');
    if (!$studentId || !in_array($studentId, $myChildIds)) {
        $studentId = !empty($myChildIds) ? $myChildIds[0] : 0;
    }
} else {
    $stmtAll = $pdo->prepare("SELECT s.id, s.first_name, s.last_name, s.matricule, c.name as class_name, s.class_id FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE (c.school_level = ? OR s.class_id IS NULL) ORDER BY c.name, s.last_name");
    $stmtAll->execute([getSchoolMode()]);
    $allStudents = $stmtAll->fetchAll();
}

// ── Structure officielle primaire (Domaines → Groupes → Branches) ────
$PRIMAIRE_DOMAINES = [
    [
        'domaine' => 'DOMAINE DES LANGUES',
        'items' => [
            ['type'=>'group','name'=>'LANGUES CONGOLAISES','branches'=>[
                ['display'=>'Expression Orale',  'db_name'=>'Expression Orale Langues Congolaises',  'max_per'=>20],
                ['display'=>'Expression Écrite', 'db_name'=>'Expression Écrite Langues Congolaises', 'max_per'=>20],
            ]],
            ['type'=>'group','name'=>'FRANÇAIS','branches'=>[
                ['display'=>'Vocabulaire',      'db_name'=>'Vocabulaire Français',      'max_per'=>10],
                ['display'=>'Expression Orale', 'db_name'=>'Expression Orale Français', 'max_per'=>20],
            ]],
            ['type'=>'standalone','display'=>'Lect.-Écriture en Langues Congolaises',
             'db_name'=>'Lect.-Écriture en Langues Congolaises','max_per'=>30],
        ],
    ],
    [
        'domaine' => 'DOMAINE DES MATHÉMATIQUES, SCIENCES ET TECHNOLOGIE',
        'items' => [
            ['type'=>'group','name'=>'MATHÉMATIQUES','branches'=>[
                ['display'=>'Mesures des grandeurs','db_name'=>'Mesures des grandeurs','max_per'=>10],
                ['display'=>'Formes géométriques',  'db_name'=>'Formes géométriques',  'max_per'=>10],
                ['display'=>'Numération',            'db_name'=>'Numération',           'max_per'=>20],
                ['display'=>'Opérations',            'db_name'=>'Opérations',           'max_per'=>40],
                ['display'=>'Problèmes',             'db_name'=>'Problèmes',            'max_per'=>20],
            ]],
            ['type'=>'group','name'=>'SCIENCES','branches'=>[
                ['display'=>"Sciences d'éveil",'db_name'=>"Sciences d'éveil",'max_per'=>20],
            ]],
            ['type'=>'group','name'=>'TECHNOLOGIE','branches'=>[
                ['display'=>'Technologie','db_name'=>'Technologie Primaire','max_per'=>10],
            ]],
        ],
    ],
    [
        'domaine' => "DOMAINE DE L'UNIVERS SOCIAL ET ENVIRONNEMENT",
        'items' => [
            ['type'=>'group','name'=>'','branches'=>[
                ['display'=>'Ed. Civ. & Morale','db_name'=>'Ed. Civ. & Morale','max_per'=>10],
                ['display'=>'Ed. Santé & Env.', 'db_name'=>'Ed. Santé & Env.', 'max_per'=>10],
            ]],
        ],
    ],
    [
        'domaine' => 'DOMAINE DES ARTS',
        'items' => [
            ['type'=>'group','name'=>'ÉDUCATION ARTISTIQUE','branches'=>[
                ['display'=>'Arts plastiques', 'db_name'=>'Arts plastiques', 'max_per'=>10],
                ['display'=>'Arts dramatiques','db_name'=>'Arts dramatiques','max_per'=>10],
            ]],
        ],
    ],
    [
        'domaine' => 'DOMAINE DU DÉVELOPPEMENT PERSONNEL',
        'items' => [
            ['type'=>'group','name'=>'','branches'=>[
                ['display'=>'Ed. phys. & sportive','db_name'=>'Ed. phys. & sportive','max_per'=>10],
                ['display'=>'Init. Trav. Prod.',   'db_name'=>'Init. Trav. Prod.',   'max_per'=>10],
                ['display'=>'Religion (1)',         'db_name'=>'Religion Primaire',   'max_per'=>10],
            ]],
        ],
    ],
];

// Flatten all primaire branches
function flatBranches($domaines) {
    $out = [];
    foreach ($domaines as $d) {
        foreach ($d['items'] as $item) {
            if ($item['type'] === 'group') { foreach ($item['branches'] as $b) $out[] = $b; }
            else $out[] = $item;
        }
    }
    return $out;
}
$allPrimaireBranches = flatBranches($PRIMAIRE_DOMAINES);

// Periods per trimestre / semestre
$PRIMAIRE_PERIODS = [
    't1'=>['label'=>'PREMIER TRIMESTRE',   'p1'=>'1ère Période','p2'=>'2ème Période','ex'=>'Examen 1er Trimestre'],
    't2'=>['label'=>'DEUXIÈME TRIMESTRE',  'p1'=>'3ème Période','p2'=>'4ème Période','ex'=>'Examen 2ème Trimestre'],
    't3'=>['label'=>'TROISIÈME TRIMESTRE', 'p1'=>'5ème Période','p2'=>'6ème Période','ex'=>'Examen 3ème Trimestre'],
];
$SECONDAIRE_PERIODS = [
    's1'=>['label'=>'PREMIER SEMESTRE',   'p1'=>'1ère Période','p2'=>'2ème Période','ex'=>'Examen 1er Semestre'],
    's2'=>['label'=>'DEUXIÈME SEMESTRE',  'p1'=>'3ème Période','p2'=>'4ème Période','ex'=>'Examen 2ème Semestre'],
];

// ── Données élève ─────────────────────────────────────────────────────
$student = null;
$isBulletinEligible = false;
$missingSubjects = [];
$subjectIdByName = [];
$gradeMap = [];        // [subject_id][period] => grade value
$courses = [];         // secondaire courses
$isSecondary = false;
$classId = 0;
$schoolName = 'Complexe Scolaire de Kinshasa';
$rankingsAnnual = [];
$classmatesCountAnnual = 0;
$primaireBranchData = [];
$primaireTotals = [];
$secondaireCourseData = [];
$secondaireTotals = [];

if ($studentId) {
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as class_name, c.id as class_id_val, c.school_level as class_school_level,
               t.name as class_teacher_name, u.name as parent_name
        FROM students s
        LEFT JOIN classes c ON s.class_id=c.id
        LEFT JOIN users t ON c.teacher_id=t.id
        LEFT JOIN users u ON s.parent_id=u.id
        WHERE s.id=?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if ($student) {
        $studentLevel = $student['class_school_level'] ?: getSchoolMode();
        $isSecondary  = ($studentLevel === 'secondaire');
        $classId      = intval($student['class_id_val']);

        // All grades for student
        $stmtG = $pdo->prepare("SELECT g.subject_id, g.period, g.grade, g.max_grade FROM grades g WHERE g.student_id=?");
        $stmtG->execute([$studentId]);
        foreach ($stmtG->fetchAll() as $g) {
            $gradeMap[$g['subject_id']][$g['period']] = ['grade'=>floatval($g['grade']),'max'=>floatval($g['max_grade'])];
        }

        // Subject ID map
        foreach ($pdo->query("SELECT id, name FROM subjects")->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $subjectIdByName[$s['name']] = intval($s['id']);
        }

        if (!$isSecondary) {
            // ── PRIMAIRE ─────────────────────────────────────────────
            // Eligibility: each branch must have at least 1 grade
            foreach ($allPrimaireBranches as $b) {
                $sid = $subjectIdByName[$b['db_name']] ?? null;
                if (!$sid || empty($gradeMap[$sid])) {
                    $missingSubjects[] = ['subject_name'=>$b['display']];
                }
            }
            $isBulletinEligible = empty($missingSubjects);

            // Precompute branch data
            $grandTotals = array_fill_keys(array_keys($PRIMAIRE_PERIODS), [
                'sum_max_per'=>0,'sum_p1'=>0,'sum_p2'=>0,'sum_max_ex'=>0,'sum_exam'=>0,'sum_pts_trim'=>0,'sum_pts_obt'=>0,
                'has_p1'=>false,'has_p2'=>false,'has_exam'=>false
            ]);
            $grandTotals['ann_max'] = 0;
            $grandTotals['ann_obt'] = 0;

            foreach ($allPrimaireBranches as $b) {
                $dbName = $b['db_name'];
                $maxPer = $b['max_per'];
                $maxEx  = $maxPer * 2;
                $ptsTrimMax = $maxPer * 2 + $maxEx; // = max_per * 4
                $sid    = $subjectIdByName[$dbName] ?? null;

                $bd = ['max_per'=>$maxPer,'max_ex'=>$maxEx,'pts_trim_max'=>$ptsTrimMax,'ann_max'=>0,'ann_obt'=>0];

                foreach ($PRIMAIRE_PERIODS as $tk => $t) {
                    $p1g = $sid ? ($gradeMap[$sid][$t['p1']]['grade'] ?? null) : null;
                    $p2g = $sid ? ($gradeMap[$sid][$t['p2']]['grade'] ?? null) : null;
                    $exg = $sid ? ($gradeMap[$sid][$t['ex']]['grade'] ?? null)  : null;

                    $ptsObt = null;
                    if ($p1g !== null || $p2g !== null || $exg !== null) {
                        $ptsObt = floatval($p1g) + floatval($p2g) + floatval($exg);
                    }

                    $bd[$tk] = ['p1'=>$p1g,'p2'=>$p2g,'ex'=>$exg,'pts_obt'=>$ptsObt];
                    $bd['ann_max'] += $ptsTrimMax;
                    if ($ptsObt !== null) $bd['ann_obt'] += $ptsObt;

                    // Grand totals accumulation
                    $grandTotals[$tk]['sum_max_per']  += $maxPer;
                    $grandTotals[$tk]['sum_max_ex']   += $maxEx;
                    $grandTotals[$tk]['sum_pts_trim'] += $ptsTrimMax;
                    if ($p1g !== null) { $grandTotals[$tk]['sum_p1']  += $p1g; $grandTotals[$tk]['has_p1']=true; }
                    if ($p2g !== null) { $grandTotals[$tk]['sum_p2']  += $p2g; $grandTotals[$tk]['has_p2']=true; }
                    if ($exg !== null) { $grandTotals[$tk]['sum_exam'] += $exg; $grandTotals[$tk]['has_exam']=true; }
                    if ($ptsObt !== null) $grandTotals[$tk]['sum_pts_obt'] += $ptsObt;
                }

                $bd['ann_pct'] = $bd['ann_max'] > 0 ? round($bd['ann_obt']/$bd['ann_max']*100,1) : null;
                $primaireBranchData[$dbName] = $bd;
                $grandTotals['ann_max'] += $bd['ann_max'];
                $grandTotals['ann_obt'] += $bd['ann_obt'];
            }
            $grandTotals['ann_pct'] = $grandTotals['ann_max'] > 0
                ? round($grandTotals['ann_obt']/$grandTotals['ann_max']*100,1) : null;
            $primaireTotals = $grandTotals;

        } else {
            // ── SECONDAIRE ───────────────────────────────────────────
            $classCoursesStmt = $pdo->prepare("
                SELECT DISTINCT s.id as subject_id, s.name as subject_name, s.category, u.name as teacher_name
                FROM teacher_assignments ta
                JOIN subjects s ON ta.subject_id=s.id
                LEFT JOIN users u ON ta.teacher_id=u.id
                WHERE ta.class_id=?
                ORDER BY s.category, s.name
            ");
            $classCoursesStmt->execute([$classId]);
            $courses = $classCoursesStmt->fetchAll();
            if (empty($courses)) {
                $courses = $pdo->query("SELECT id as subject_id, name as subject_name, category, 'Non assigné' as teacher_name FROM subjects WHERE school_level IN ('secondaire','les_deux') ORDER BY category,name")->fetchAll();
            }

            // Eligibility
            foreach ($courses as $cs) {
                if (empty($gradeMap[$cs['subject_id']])) $missingSubjects[] = $cs;
            }
            $isBulletinEligible = empty($missingSubjects);

            // Precompute secondaire data
            $secGrand = array_fill_keys(array_keys($SECONDAIRE_PERIODS),[
                'sum_max_per'=>0,'sum_p1'=>0,'sum_p2'=>0,'sum_max_ex'=>0,'sum_exam'=>0,'sum_pts_sem'=>0,'sum_pts_obt'=>0,
                'has_p1'=>false,'has_p2'=>false,'has_exam'=>false
            ]);
            $secGrand['ann_max'] = 0;
            $secGrand['ann_obt'] = 0;

            $secCourseData = [];
            foreach ($courses as $c) {
                $sid = $c['subject_id'];
                $cd  = ['ann_max'=>0,'ann_obt'=>0];

                foreach ($SECONDAIRE_PERIODS as $sk => $sp) {
                    $p1g = $gradeMap[$sid][$sp['p1']]['grade'] ?? null;
                    $p2g = $gradeMap[$sid][$sp['p2']]['grade'] ?? null;
                    $exg = $gradeMap[$sid][$sp['ex']]['grade'] ?? null;

                    // max_per from grades or default 20
                    $mp1 = $gradeMap[$sid][$sp['p1']]['max'] ?? null;
                    $mp2 = $gradeMap[$sid][$sp['p2']]['max'] ?? null;
                    $maxPer = $mp1 ?? $mp2 ?? 20;
                    $maxEx  = $maxPer * 2;
                    $ptsSemMax = $maxPer * 4;

                    $ptsObt = null;
                    if ($p1g !== null || $p2g !== null || $exg !== null) {
                        $ptsObt = floatval($p1g) + floatval($p2g) + floatval($exg);
                    }

                    $cd[$sk] = ['max_per'=>$maxPer,'max_ex'=>$maxEx,'pts_sem_max'=>$ptsSemMax,
                                'p1'=>$p1g,'p2'=>$p2g,'ex'=>$exg,'pts_obt'=>$ptsObt];
                    $cd['ann_max'] += $ptsSemMax;
                    if ($ptsObt !== null) $cd['ann_obt'] += $ptsObt;

                    $secGrand[$sk]['sum_max_per']  += $maxPer;
                    $secGrand[$sk]['sum_max_ex']   += $maxEx;
                    $secGrand[$sk]['sum_pts_sem']  += $ptsSemMax;
                    if ($p1g !== null) { $secGrand[$sk]['sum_p1']  += $p1g;  $secGrand[$sk]['has_p1']=true; }
                    if ($p2g !== null) { $secGrand[$sk]['sum_p2']  += $p2g;  $secGrand[$sk]['has_p2']=true; }
                    if ($exg !== null) { $secGrand[$sk]['sum_exam'] += $exg; $secGrand[$sk]['has_exam']=true; }
                    if ($ptsObt !== null) $secGrand[$sk]['sum_pts_obt'] += $ptsObt;
                }

                $cd['ann_pct'] = $cd['ann_max'] > 0 ? round($cd['ann_obt']/$cd['ann_max']*100,1) : null;
                $secCourseData[$sid] = $cd;
                $secGrand['ann_max'] += $cd['ann_max'];
                $secGrand['ann_obt'] += $cd['ann_obt'];
            }
            $secGrand['ann_pct'] = $secGrand['ann_max'] > 0
                ? round($secGrand['ann_obt']/$secGrand['ann_max']*100,1) : null;
            $secondaireTotals  = $secGrand;
            $secondaireCourseData = $secCourseData;
        }

        // Rankings annuels
        if ($classId) {
            $stmtRank = $pdo->prepare("SELECT s.id, SUM(g.grade) as total FROM students s JOIN grades g ON g.student_id=s.id WHERE s.class_id=? GROUP BY s.id ORDER BY total DESC");
            $stmtRank->execute([$classId]);
            $rankRows = $stmtRank->fetchAll();
            $classmatesCountAnnual = count($rankRows);
            foreach ($rankRows as $i => $r) $rankingsAnnual[$r['id']] = $i + 1;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

// ── Helpers ────────────────────────────────────────────────────────────
function fsc($v) {
    if ($v === null || $v === '') return '';
    $f = (float)$v;
    return $f == (int)$f ? (int)$f : number_format($f, 1, ',', '');
}
// Compute group/sous-total data for primaire groups
function groupScores($branches, $primaireBranchData, $PRIMAIRE_PERIODS) {
    $g = [];
    foreach (array_keys($PRIMAIRE_PERIODS) as $tk) {
        $g[$tk] = ['sum_max_per'=>0,'sum_p1'=>0,'sum_p2'=>0,'sum_max_ex'=>0,'sum_exam'=>0,'sum_pts_trim'=>0,'sum_pts_obt'=>0,
                   'has_p1'=>false,'has_p2'=>false,'has_exam'=>false];
    }
    $g['ann_max'] = 0; $g['ann_obt'] = 0;

    foreach ($branches as $b) {
        $bd = $primaireBranchData[$b['db_name']] ?? null;
        if (!$bd) continue;
        foreach (array_keys($PRIMAIRE_PERIODS) as $tk) {
            $g[$tk]['sum_max_per']  += $bd['max_per'];
            $g[$tk]['sum_max_ex']   += $bd['max_ex'];
            $g[$tk]['sum_pts_trim'] += $bd['pts_trim_max'];
            if ($bd[$tk]['p1']  !== null) { $g[$tk]['sum_p1']  += $bd[$tk]['p1'];  $g[$tk]['has_p1']=true; }
            if ($bd[$tk]['p2']  !== null) { $g[$tk]['sum_p2']  += $bd[$tk]['p2'];  $g[$tk]['has_p2']=true; }
            if ($bd[$tk]['ex']  !== null) { $g[$tk]['sum_exam'] += $bd[$tk]['ex']; $g[$tk]['has_exam']=true; }
            if ($bd[$tk]['pts_obt'] !== null) $g[$tk]['sum_pts_obt'] += $bd[$tk]['pts_obt'];
        }
        $g['ann_max'] += $bd['ann_max'];
        $g['ann_obt'] += $bd['ann_obt'];
    }
    $g['ann_pct'] = $g['ann_max'] > 0 ? round($g['ann_obt']/$g['ann_max']*100,1) : null;
    return $g;
}

// ID chars for box grid
$matriculeRaw = preg_replace('/[^A-Za-z0-9]/', '', $student['matricule'] ?? '');
$idChars = str_split(str_pad(substr($matriculeRaw,0,12),12,' ',STR_PAD_RIGHT));
$permChars = str_split(str_pad('',15,' ',STR_PAD_RIGHT));

$passesClass = '';
if ($isBulletinEligible && isset($primaireTotals['ann_pct'])) {
    $annPct = $isSecondary ? ($secondaireTotals['ann_pct']??0) : ($primaireTotals['ann_pct']??0);
    $passesClass = $annPct >= 50 ? 'pass' : 'fail';
}
?>

<div class="page-header no-print">
    <div>
        <h2>📄 <?= t('bulletin') ?></h2>
        <p>Génération du bulletin officiel &bull; Système RDC</p>
    </div>
    <?php if ($student && $isBulletinEligible): ?>
    <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimer le bulletin</button>
    <?php endif; ?>
</div>

<!-- ── Sélecteur élève ────────────────────────────────────────────── -->
<div class="card no-print" style="padding:20px;margin-bottom:20px;position:relative;z-index:1050;overflow:visible;">
    <form method="GET" id="bulletinForm" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="flex:3;min-width:220px;margin:0;position:relative;z-index:1052;">
            <label><?= $isParent ? 'Mon enfant' : 'Élève' ?> <small style="color:var(--text-muted);">(<?= count($allStudents) ?> <?= $isParent ? 'enfant(s)' : 'élèves — mode '.getSchoolMode() ?>)</small></label>
            <input type="hidden" name="student_id" id="selectedStudentId" value="<?= $studentId ?>" required>
            <div class="student-search-container">
                <input type="text" id="studentSearchInput" class="form-control"
                    placeholder="<?= $isParent ? 'Choisir un enfant...' : 'Rechercher un élève...' ?>"
                    value="<?php
                        if ($studentId) foreach($allStudents as $s) {
                            if ($s['id']==$studentId) { echo escape($s['last_name'].' '.$s['first_name'].' — '.($s['class_name']??'?').' ['.$s['matricule'].']'); break; }
                        }
                    ?>"
                    autocomplete="off" onclick="this.select();toggleStudentDropdown(true);" oninput="filterStudents(this.value);">
                <div id="studentDropdown" class="student-dropdown" style="display:none;">
                    <?php foreach($allStudents as $s): ?>
                    <div class="student-option" data-id="<?= $s['id'] ?>"
                         data-search="<?= escape(mb_strtolower($s['last_name'].' '.$s['first_name'].' '.($s['class_name']??'').' '.($s['matricule']??''))) ?>"
                         onclick="selectStudent(<?= $s['id'] ?>,'<?= escape(addslashes($s['last_name'].' '.$s['first_name'].' — '.($s['class_name']??'?').' ['.$s['matricule'].']')) ?>')">
                        <div class="student-opt-name"><strong><?= escape($s['last_name'].' '.$s['first_name']) ?></strong></div>
                        <div class="student-opt-meta">
                            <span class="badge badge-primary" style="font-size:10px;"><?= escape($s['class_name']??'Sans classe') ?></span>
                            <span style="font-size:11px;color:var(--text-muted);"><?= escape($s['matricule']??'') ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div id="noStudentResult" class="student-option" style="display:none;color:var(--text-muted);text-align:center;cursor:default;">Aucun résultat</div>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Afficher</button>
    </form>
</div>
<style>
.student-search-container{position:relative;z-index:1053;}
.student-dropdown{position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:999999;background:var(--card,#fff);border:1.5px solid var(--border);border-radius:8px;box-shadow:0 12px 32px rgba(0,0,0,.22);max-height:280px;overflow-y:auto;}
.student-option{padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);background:var(--card,#fff);transition:background .15s;}
.student-option:hover{background:rgba(212,147,13,.12);}
.student-opt-name{font-size:13px;margin-bottom:2px;}
.student-opt-meta{display:flex;align-items:center;gap:8px;}
</style>
<script>
function toggleStudentDropdown(show){document.getElementById('studentDropdown').style.display=show?'block':'none';}
function filterStudents(q){q=q.toLowerCase().trim();const d=document.getElementById('studentDropdown');d.style.display='block';let found=0;d.querySelectorAll('.student-option[data-id]').forEach(o=>{const m=!q||o.getAttribute('data-search').includes(q);o.style.display=m?'':'none';if(m)found++;});document.getElementById('noStudentResult').style.display=found===0?'':'none';}
function selectStudent(id,label){document.getElementById('selectedStudentId').value=id;document.getElementById('studentSearchInput').value=label;toggleStudentDropdown(false);}
document.addEventListener('click',function(e){const c=document.querySelector('.student-search-container');if(c&&!c.contains(e.target))toggleStudentDropdown(false);});
</script>

<?php if ($student && !$isBulletinEligible): ?>
<!-- ── Alerte notes manquantes ─────────────────────────────────────── -->
<div class="card no-print" style="background:<?= $isParent?'#fefce8':'#fff1f2' ?>;border:1.5px solid <?= $isParent?'#fef08a':'#fecdd3' ?>;border-radius:14px;padding:26px;margin-bottom:24px;">
    <div style="display:flex;align-items:flex-start;gap:20px;">
        <div style="width:52px;height:52px;border-radius:12px;background:<?= $isParent?'#fef9c3':'#ffe4e6' ?>;color:<?= $isParent?'#854d0e':'#e11d48' ?>;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0;"><?= $isParent?'⏳':'⛔' ?></div>
        <div style="flex:1;">
            <h3 style="color:<?= $isParent?'#854d0e':'#9f1239' ?>;margin:0 0 8px 0;font-size:18px;font-weight:800;">
                <?= $isParent ? 'Bulletin en cours de clôture par l\'école' : 'Bulletin non générable — Matières non cotées ('.count($missingSubjects).')' ?>
            </h3>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                <?php foreach($missingSubjects as $ms): ?>
                <span style="background:<?= $isParent?'#fef9c3':'#fff1f2' ?>;color:<?= $isParent?'#854d0e':'#9f1239' ?>;border:1px solid <?= $isParent?'#fef08a':'#fecdd3' ?>;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:600;">
                    📚 <?= escape($ms['subject_name']) ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php elseif ($student && $isBulletinEligible): ?>

<!-- ══════════════════════════════════════════════════════════════════ -->
<!--                     BULLETIN IMPRIMABLE                           -->
<!-- ══════════════════════════════════════════════════════════════════ -->
<div class="bul-container">
<div class="bul-paper">

    <!-- ── EN-TÊTE NATIONAL ──────────────────────────────────────── -->
    <div class="bul-national-header">
        <div class="bul-flag">
            <div class="flag-blue"><span class="flag-star">★</span></div>
            <div class="flag-stripe"></div>
        </div>
        <div class="bul-header-center">
            <div class="bul-country">REPUBLIQUE DEMOCRATIQUE DU CONGO</div>
            <div class="bul-ministry">MINISTERE DE L'EDUCATION NATIONALE ET NOUVELLE CITOYENNETE</div>
            <div class="bul-secretariat">SECRETARIAT GENERAL A L'ENSEIGNEMENT PRIMAIRE, SECONDAIRE ET TECHNIQUE</div>
        </div>
        <div class="bul-emblem">
            <div class="bul-emblem-circle">
                <span style="font-size:18px;">★</span>
                <small>RDC</small>
            </div>
        </div>
    </div>

    <!-- ── MÉTADONNÉES ÉLÈVE / ÉCOLE ─────────────────────────────── -->
    <div class="bul-meta-section">
        <div class="bul-meta-left">
            <div class="bul-meta-row">
                <span class="bul-lbl">N° ID.</span>
                <div class="bul-boxes">
                    <?php foreach($idChars as $c): ?><span class="bul-box"><?= escape($c) ?></span><?php endforeach; ?>
                </div>
            </div>
            <div class="bul-meta-row"><span class="bul-lbl">PROVINCE ÉDUCATIONNELLE :</span><span class="bul-val-dot">KINSHASA</span></div>
            <div class="bul-meta-row"><span class="bul-lbl">VILLE :</span><span class="bul-val-dot">KINSHASA</span></div>
            <div class="bul-meta-row"><span class="bul-lbl">COMMUNE / TER. (1) :</span><span class="bul-val-dot">GOMBE</span></div>
            <div class="bul-meta-row"><span class="bul-lbl">ECOLE :</span><span class="bul-val-dot" style="font-weight:700;"><?= escape($schoolName) ?></span></div>
            <div class="bul-meta-row"><span class="bul-lbl">CODE :</span><span class="bul-val-dot"></span></div>
        </div>
        <div class="bul-meta-right">
            <div class="bul-meta-row"><span class="bul-lbl">ELEVE :</span><span class="bul-val-dot" style="font-weight:700;"><?= escape($student['last_name'].' '.$student['first_name']) ?></span><span class="bul-lbl" style="margin-left:8px;">SEXE :</span><span class="bul-val-dot" style="width:30px;"><?= $student['gender']==='M'?'M':'F' ?></span></div>
            <div class="bul-meta-row"><span class="bul-lbl">NE(E) A :</span><span class="bul-val-dot"></span><span class="bul-lbl" style="margin-left:8px;">LE</span><span class="bul-val-dot" style="width:80px;"><?= $student['date_of_birth']?date('d/m/Y',strtotime($student['date_of_birth'])):'…/…/……' ?></span></div>
            <div class="bul-meta-row"><span class="bul-lbl">CLASSE :</span><span class="bul-val-dot" style="font-weight:700;"><?= escape($student['class_name']??'—') ?></span></div>
            <div class="bul-meta-row">
                <span class="bul-lbl">N° PERM. :</span>
                <div class="bul-boxes">
                    <?php foreach($permChars as $c): ?><span class="bul-box"><?= escape($c) ?></span><?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TITRE ──────────────────────────────────────────────────── -->
    <div class="bul-title-bar">
        <div class="bul-title-text">
            <?= $isSecondary
                ? 'BULLETIN DE L\'ELEVE — ENSEIGNEMENT SECONDAIRE ET HUMANITÉS'
                : 'BULLETIN DE L\'ELEVE DEGRE ELEMENTAIRE (1<sup>ère</sup>, 2<sup>ème</sup> Année) <sup>(1)</sup>' ?>
        </div>
        <div class="bul-year-text">ANNEE SCOLAIRE <?= escape($student['school_year']??'2024 - 2025') ?></div>
    </div>

    <!-- ══════════════════════════════════════════════════════════ -->
    <?php if (!$isSecondary): ?>
    <!-- ── TABLEAU PRIMAIRE (3 Trimestres) ─────────────────────── -->
    <?php
    // 25 columns: 1 (BRANCHES) + 7×3 (trimestres) + 3 (TOTAL)
    $NC = 25;
    ?>
    <div class="bul-table-wrap">
    <table class="bul-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-branches">BRANCHES</th>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <th colspan="7" class="col-trim-hdr"><?= $t['label'] ?></th>
                <?php endforeach; ?>
                <th colspan="3" class="col-total-hdr">TOTAL</th>
            </tr>
            <tr class="sub-hdr-row">
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <th class="h-max-per">MAX<br>per</th>
                <th class="h-score">1<sup>ère</sup> P.</th>
                <th class="h-score">2<sup>ème</sup> P.</th>
                <th class="h-max-ex">MAX<br>EX.</th>
                <th class="h-score">MAX<br>OBT.</th>
                <th class="h-pts-trim">PTS<br>TRIM.</th>
                <th class="h-pts-obt">PTS<br>OBT.</th>
                <?php endforeach; ?>
                <th class="h-ann-max">MAX</th>
                <th class="h-ann-obt">PTS<br>OBT.</th>
                <th class="h-pct">%</th>
            </tr>
        </thead>
        <tbody>
        <?php
        foreach ($PRIMAIRE_DOMAINES as $domaine):
        ?>
            <!-- DOMAINE ROW -->
            <tr class="bul-domaine-row">
                <td colspan="<?= $NC ?>"><?= escape($domaine['domaine']) ?></td>
            </tr>

        <?php foreach ($domaine['items'] as $item):
            if ($item['type'] === 'group'):
                $grpBranches = $item['branches'];
                $gs = groupScores($grpBranches, $primaireBranchData, $PRIMAIRE_PERIODS);
        ?>
            <!-- GROUP HEADER -->
            <?php if ($item['name']): ?>
            <tr class="bul-group-row">
                <td colspan="<?= $NC ?>"><?= escape($item['name']) ?></td>
            </tr>
            <?php endif; ?>

            <!-- BRANCH ROWS -->
            <?php foreach ($grpBranches as $b):
                $bd = $primaireBranchData[$b['db_name']] ?? null;
                if (!$bd) continue;
            ?>
            <tr class="bul-branch-row">
                <td class="bul-branch-name"><?= escape($b['display']) ?></td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t):
                    $tdata = $bd[$tk];
                ?>
                <td class="bul-max"><?= $bd['max_per'] ?></td>
                <td class="bul-score"><?= fsc($tdata['p1']) ?></td>
                <td class="bul-score"><?= fsc($tdata['p2']) ?></td>
                <td class="bul-max"><?= $bd['max_ex'] ?></td>
                <td class="bul-score"><?= fsc($tdata['ex']) ?></td>
                <td class="bul-pts-trim"><?= $bd['pts_trim_max'] ?></td>
                <td class="bul-pts-obt"><?= fsc($tdata['pts_obt']) ?></td>
                <?php endforeach; ?>
                <td class="bul-ann-max"><?= $bd['ann_max'] ?></td>
                <td class="bul-ann-obt"><?= fsc($bd['ann_obt']) ?></td>
                <td class="bul-pct"><?= $bd['ann_pct']!==null?$bd['ann_pct'].'%':'' ?></td>
            </tr>
            <?php endforeach; ?>

            <!-- SOUS-TOTAL ROW -->
            <?php if (count($grpBranches) > 1 || $item['name']): ?>
            <tr class="bul-sous-total-row">
                <td class="bul-branch-name" style="font-style:italic;">Sous-total</td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <td class="bul-max"><?= $gs[$tk]['sum_max_per'] ?></td>
                <td class="bul-score"><?= $gs[$tk]['has_p1']?fsc($gs[$tk]['sum_p1']):'' ?></td>
                <td class="bul-score"><?= $gs[$tk]['has_p2']?fsc($gs[$tk]['sum_p2']):'' ?></td>
                <td class="bul-max"><?= $gs[$tk]['sum_max_ex'] ?></td>
                <td class="bul-score"><?= $gs[$tk]['has_exam']?fsc($gs[$tk]['sum_exam']):'' ?></td>
                <td class="bul-pts-trim"><?= $gs[$tk]['sum_pts_trim'] ?></td>
                <td class="bul-pts-obt"><?= $gs[$tk]['sum_pts_obt']>0?fsc($gs[$tk]['sum_pts_obt']):'' ?></td>
                <?php endforeach; ?>
                <td class="bul-ann-max"><?= $gs['ann_max'] ?></td>
                <td class="bul-ann-obt"><?= $gs['ann_obt']>0?fsc($gs['ann_obt']):'' ?></td>
                <td class="bul-pct"><?= $gs['ann_pct']!==null?$gs['ann_pct'].'%':'' ?></td>
            </tr>
            <?php endif; ?>

        <?php elseif ($item['type'] === 'standalone'):
            $bd = $primaireBranchData[$item['db_name']] ?? null;
            if (!$bd) continue;
        ?>
            <!-- STANDALONE BRANCH -->
            <tr class="bul-branch-row bul-standalone-row">
                <td class="bul-branch-name" style="font-weight:700;"><?= escape($item['display']) ?></td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t):
                    $tdata = $bd[$tk];
                ?>
                <td class="bul-max"><?= $bd['max_per'] ?></td>
                <td class="bul-score"><?= fsc($tdata['p1']) ?></td>
                <td class="bul-score"><?= fsc($tdata['p2']) ?></td>
                <td class="bul-max"><?= $bd['max_ex'] ?></td>
                <td class="bul-score"><?= fsc($tdata['ex']) ?></td>
                <td class="bul-pts-trim"><?= $bd['pts_trim_max'] ?></td>
                <td class="bul-pts-obt"><?= fsc($tdata['pts_obt']) ?></td>
                <?php endforeach; ?>
                <td class="bul-ann-max"><?= $bd['ann_max'] ?></td>
                <td class="bul-ann-obt"><?= fsc($bd['ann_obt']) ?></td>
                <td class="bul-pct"><?= $bd['ann_pct']!==null?$bd['ann_pct'].'%':'' ?></td>
            </tr>
        <?php endif; endforeach; endforeach; ?>

            <!-- MAXIMA GÉNÉRAUX -->
            <tr class="bul-maxima-row">
                <td class="bul-branch-name">Maxima généraux</td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <td class="bul-max"><?= $primaireTotals[$tk]['sum_max_per'] ?></td>
                <td class="bul-score bul-hatch"></td>
                <td class="bul-score bul-hatch"></td>
                <td class="bul-max"><?= $primaireTotals[$tk]['sum_max_ex'] ?></td>
                <td class="bul-score bul-hatch"></td>
                <td class="bul-pts-trim"><?= $primaireTotals[$tk]['sum_pts_trim'] ?></td>
                <td class="bul-pts-obt bul-hatch"></td>
                <?php endforeach; ?>
                <td class="bul-ann-max"><?= $primaireTotals['ann_max'] ?></td>
                <td class="bul-ann-obt bul-hatch"></td>
                <td class="bul-pct bul-hatch"></td>
            </tr>

            <!-- POURCENTAGE -->
            <tr class="bul-eval-row">
                <td class="bul-branch-name">POURCENTAGE</td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <td class="bul-hatch" colspan="5"></td>
                <td class="bul-pts-trim bul-hatch"></td>
                <td class="bul-pts-obt"><?= $primaireTotals[$tk]['sum_pts_obt']>0?round($primaireTotals[$tk]['sum_pts_obt']/$primaireTotals[$tk]['sum_pts_trim']*100,1).'%':'' ?></td>
                <?php endforeach; ?>
                <td class="bul-ann-max bul-hatch"></td>
                <td class="bul-ann-obt" colspan="2" style="font-weight:900;font-size:9px;"><?= $primaireTotals['ann_pct']!==null?$primaireTotals['ann_pct'].'%':'' ?></td>
            </tr>

            <!-- PLACE -->
            <tr class="bul-eval-row">
                <td class="bul-branch-name">PLACE</td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <td class="bul-hatch" colspan="6"></td>
                <td class="bul-pts-obt bul-hatch"></td>
                <?php endforeach; ?>
                <td class="bul-ann-max bul-hatch"></td>
                <td class="bul-ann-obt" colspan="2"><?= isset($rankingsAnnual[$studentId])?$rankingsAnnual[$studentId].'<sup>'.(($rankingsAnnual[$studentId]===1)?'er':'e').'</sup>':'' ?></td>
            </tr>

            <!-- NBRE D'ÉLÈVES -->
            <tr class="bul-eval-row">
                <td class="bul-branch-name">NBRE D'ÉLÈVES</td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <td class="bul-hatch" colspan="7"></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td>
                <td class="bul-ann-obt" colspan="2"><?= $classmatesCountAnnual?:'' ?></td>
            </tr>

            <!-- APPLICATION -->
            <tr class="bul-eval-row">
                <td class="bul-branch-name">APPLICATION</td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <td class="bul-hatch" colspan="6"></td><td></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td><td colspan="2"></td>
            </tr>

            <!-- CONDUITE -->
            <tr class="bul-eval-row">
                <td class="bul-branch-name">CONDUITE</td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <td class="bul-hatch" colspan="6"></td><td></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td><td colspan="2"></td>
            </tr>

            <!-- SIGNAT. DE L'INST. -->
            <tr class="bul-eval-row bul-sig-row">
                <td class="bul-branch-name">SIGNAT. DE L'INST.</td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <td class="bul-sig-space" colspan="7"></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td><td colspan="2"></td>
            </tr>

            <!-- SIGNAT. DU RESP. -->
            <tr class="bul-eval-row bul-sig-row">
                <td class="bul-branch-name">SIGNAT. DU RESP.</td>
                <?php foreach ($PRIMAIRE_PERIODS as $tk => $t): ?>
                <td class="bul-sig-space" colspan="7"></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td><td colspan="2"></td>
            </tr>

        </tbody>
    </table>
    </div>

    <?php else: ?>
    <!-- ── TABLEAU SECONDAIRE (2 Semestres) ────────────────────── -->
    <?php
    // 19 cols: 1 (BRANCHES) + 1 (PROF) + 7×2 (semestres) + 3 (TOTAL)
    $NC2 = 19;
    $coursesByCategory = [];
    foreach ($courses as $c) {
        $cat = !empty($c['category']) ? $c['category'] : 'Cours Généraux';
        $coursesByCategory[$cat][] = $c;
    }
    ?>
    <div class="bul-table-wrap">
    <table class="bul-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-branches">COURS DISPENSÉS</th>
                <th rowspan="2" class="col-prof">PROFESSEUR</th>
                <?php foreach ($SECONDAIRE_PERIODS as $sk => $sp): ?>
                <th colspan="7" class="col-trim-hdr"><?= $sp['label'] ?></th>
                <?php endforeach; ?>
                <th colspan="3" class="col-total-hdr">TOTAL ANNUEL</th>
            </tr>
            <tr class="sub-hdr-row">
                <?php foreach ($SECONDAIRE_PERIODS as $sk => $sp): ?>
                <th class="h-max-per">MAX<br>per</th>
                <th class="h-score">1<sup>ère</sup> P.</th>
                <th class="h-score">2<sup>ème</sup> P.</th>
                <th class="h-max-ex">MAX<br>EX.</th>
                <th class="h-score">MAX<br>OBT.</th>
                <th class="h-pts-trim">PTS<br>SEM.</th>
                <th class="h-pts-obt">PTS<br>OBT.</th>
                <?php endforeach; ?>
                <th class="h-ann-max">MAX</th>
                <th class="h-ann-obt">PTS<br>OBT.</th>
                <th class="h-pct">%</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($coursesByCategory as $catName => $catCourses): ?>
            <tr class="bul-domaine-row">
                <td colspan="<?= $NC2 ?>"><?= escape($catName) ?></td>
            </tr>
            <?php foreach ($catCourses as $c):
                $sid = $c['subject_id'];
                $cd  = $secondaireCourseData[$sid] ?? null;
                if (!$cd) continue;
            ?>
            <tr class="bul-branch-row">
                <td class="bul-branch-name"><?= escape($c['subject_name']) ?></td>
                <td class="bul-prof-cell"><?= escape($c['teacher_name']??'—') ?></td>
                <?php foreach ($SECONDAIRE_PERIODS as $sk => $sp):
                    $sd = $cd[$sk];
                ?>
                <td class="bul-max"><?= $sd['max_per'] ?></td>
                <td class="bul-score"><?= fsc($sd['p1']) ?></td>
                <td class="bul-score"><?= fsc($sd['p2']) ?></td>
                <td class="bul-max"><?= $sd['max_ex'] ?></td>
                <td class="bul-score"><?= fsc($sd['ex']) ?></td>
                <td class="bul-pts-trim"><?= $sd['pts_sem_max'] ?></td>
                <td class="bul-pts-obt"><?= fsc($sd['pts_obt']) ?></td>
                <?php endforeach; ?>
                <td class="bul-ann-max"><?= $cd['ann_max'] ?></td>
                <td class="bul-ann-obt"><?= fsc($cd['ann_obt']) ?></td>
                <td class="bul-pct"><?= $cd['ann_pct']!==null?$cd['ann_pct'].'%':'' ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>

            <!-- MAXIMA GÉNÉRAUX -->
            <tr class="bul-maxima-row">
                <td class="bul-branch-name" colspan="2">Maxima généraux</td>
                <?php foreach ($SECONDAIRE_PERIODS as $sk => $sp): ?>
                <td class="bul-max"><?= $secondaireTotals[$sk]['sum_max_per'] ?></td>
                <td class="bul-hatch"></td><td class="bul-hatch"></td>
                <td class="bul-max"><?= $secondaireTotals[$sk]['sum_max_ex'] ?></td>
                <td class="bul-hatch"></td>
                <td class="bul-pts-trim"><?= $secondaireTotals[$sk]['sum_pts_sem'] ?></td>
                <td class="bul-hatch"></td>
                <?php endforeach; ?>
                <td class="bul-ann-max"><?= $secondaireTotals['ann_max'] ?></td>
                <td class="bul-hatch"></td><td class="bul-hatch"></td>
            </tr>

            <!-- TOTAL POINTS OBTENUS -->
            <tr class="bul-totaux-row">
                <td class="bul-branch-name" colspan="2">Total des points obtenus</td>
                <?php foreach ($SECONDAIRE_PERIODS as $sk => $sp): ?>
                <td class="bul-hatch"></td>
                <td class="bul-score"><?= $secondaireTotals[$sk]['has_p1']?fsc($secondaireTotals[$sk]['sum_p1']):'' ?></td>
                <td class="bul-score"><?= $secondaireTotals[$sk]['has_p2']?fsc($secondaireTotals[$sk]['sum_p2']):'' ?></td>
                <td class="bul-hatch"></td>
                <td class="bul-score"><?= $secondaireTotals[$sk]['has_exam']?fsc($secondaireTotals[$sk]['sum_exam']):'' ?></td>
                <td class="bul-hatch"></td>
                <td class="bul-pts-obt"><?= $secondaireTotals[$sk]['sum_pts_obt']>0?fsc($secondaireTotals[$sk]['sum_pts_obt']):'' ?></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td>
                <td class="bul-ann-obt"><?= $secondaireTotals['ann_obt']>0?fsc($secondaireTotals['ann_obt']):'' ?></td>
                <td class="bul-hatch"></td>
            </tr>

            <!-- POURCENTAGE -->
            <tr class="bul-eval-row">
                <td class="bul-branch-name" colspan="2">POURCENTAGE</td>
                <?php foreach ($SECONDAIRE_PERIODS as $sk => $sp): ?>
                <td class="bul-hatch" colspan="5"></td>
                <td class="bul-hatch"></td>
                <td class="bul-pts-obt"><?= $secondaireTotals[$sk]['sum_pts_obt']>0&&$secondaireTotals[$sk]['sum_pts_sem']>0?round($secondaireTotals[$sk]['sum_pts_obt']/$secondaireTotals[$sk]['sum_pts_sem']*100,1).'%':'' ?></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td>
                <td class="bul-ann-obt" colspan="2"><?= $secondaireTotals['ann_pct']!==null?$secondaireTotals['ann_pct'].'%':'' ?></td>
            </tr>

            <!-- PLACE -->
            <tr class="bul-eval-row">
                <td class="bul-branch-name" colspan="2">PLACE</td>
                <?php foreach ($SECONDAIRE_PERIODS as $sk => $sp): ?>
                <td class="bul-hatch" colspan="7"></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td>
                <td class="bul-ann-obt" colspan="2"><?= isset($rankingsAnnual[$studentId])?$rankingsAnnual[$studentId].'<sup>'.(($rankingsAnnual[$studentId]===1)?'er':'e').'</sup>':'' ?></td>
            </tr>

            <!-- NBRE D'ÉLÈVES -->
            <tr class="bul-eval-row">
                <td class="bul-branch-name" colspan="2">NBRE D'ÉLÈVES</td>
                <?php foreach ($SECONDAIRE_PERIODS as $sk => $sp): ?>
                <td class="bul-hatch" colspan="7"></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td><td colspan="2"><?= $classmatesCountAnnual?:'' ?></td>
            </tr>

            <!-- APPLICATION -->
            <tr class="bul-eval-row">
                <td class="bul-branch-name" colspan="2">APPLICATION</td>
                <?php foreach ($SECONDAIRE_PERIODS as $sk => $sp): ?>
                <td class="bul-hatch" colspan="6"></td><td></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td><td colspan="2"></td>
            </tr>

            <!-- CONDUITE -->
            <tr class="bul-eval-row">
                <td class="bul-branch-name" colspan="2">CONDUITE</td>
                <?php foreach ($SECONDAIRE_PERIODS as $sk => $sp): ?>
                <td class="bul-hatch" colspan="6"></td><td></td>
                <?php endforeach; ?>
                <td class="bul-hatch"></td><td colspan="2"></td>
            </tr>

        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <!-- ── BAS DE PAGE ────────────────────────────────────────── -->
    <div class="bul-footer">
        <div class="bul-decision-box">
            <div class="bul-checkbox-line">
                <span class="bul-checkbox <?= $passesClass==='pass'?'bul-checked':'' ?>"></span>
                <span>- L'élève passe dans la classe supérieure <sup>(1)</sup></span>
            </div>
            <div class="bul-checkbox-line" style="margin-top:5px;">
                <span class="bul-checkbox <?= $passesClass==='fail'?'bul-checked':'' ?>"></span>
                <span>- L'élève double la classe <sup>(1)</sup></span>
            </div>
            <div class="bul-legal" style="margin-top:10px;">
                <p>(1) Biffer la mention inutile.</p>
                <p><strong>NOTE IMPORTANTE :</strong> Le bulletin est sans valeur s'il est raturé ou surchargé.</p>
                <p><em>Interdiction formelle de reproduire ce bulletin sous peine des sanctions prévues par la loi.</em></p>
            </div>
        </div>

        <div class="bul-signature-box">
            <div class="bul-date-line">
                Fait à <span class="bul-dot-line" style="width:100px;">KINSHASA</span>,
                le <span class="bul-dot-line" style="width:90px;"><?= date('d/m/Y') ?></span>
            </div>
            <div class="bul-sigs-grid">
                <div class="bul-sig-col">
                    <div class="bul-sig-label">Signature de l'élève</div>
                    <div class="bul-sig-space"></div>
                </div>
                <div class="bul-sig-col">
                    <div class="bul-sig-label">Sceau de l'Ecole</div>
                    <div class="bul-sig-space"></div>
                </div>
                <div class="bul-sig-col">
                    <div class="bul-sig-label" style="font-weight:700;">Chef d'Établissement</div>
                    <div style="font-size:7.5px;">Noms &amp; Signature</div>
                    <div class="bul-sig-space"></div>
                </div>
            </div>
            <div class="bul-ige-tag">IGE/P.S/001</div>
        </div>
    </div>

</div><!-- .bul-paper -->
</div><!-- .bul-container -->

<?php elseif ($studentId): ?>
<div class="card" style="padding:32px;text-align:center;color:var(--text-muted);">Élève introuvable.</div>
<?php else: ?>
<div class="card" style="padding:48px;text-align:center;color:var(--text-muted);">
    <div style="font-size:52px;margin-bottom:16px;">📄</div>
    <p style="font-size:16px;">Sélectionnez un élève pour générer son bulletin officiel.</p>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════════════════════════ -->
<!--                           CSS                                   -->
<!-- ════════════════════════════════════════════════════════════════ -->
<style>
/* ── Conteneur papier ───────────────────────────────────────────── */
.bul-container {
    background: #525659;
    padding: 20px 10px;
    display: flex;
    justify-content: center;
    overflow-x: auto;
}
.bul-paper {
    background: #ffffff;
    color: #000;
    width: 297mm; /* landscape A4 for wide tables */
    min-height: 210mm;
    padding: 10mm 8mm;
    box-shadow: 0 4px 20px rgba(0,0,0,.4);
    font-family: Arial, sans-serif;
    font-size: 9px;
    line-height: 1.2;
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ── En-tête national ───────────────────────────────────────────── */
.bul-national-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid #000;
    padding-bottom: 6px;
    margin-bottom: 6px;
}
.bul-flag {
    width: 55px;
    height: 36px;
    border: 1.2px solid #000;
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
}
.flag-blue {
    width: 100%;
    height: 100%;
    background: #007fff;
    position: relative;
}
.flag-blue::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(130deg, transparent 42%, #fcd116 42%, #fcd116 46%, #ce1126 46%, #ce1126 54%, #fcd116 54%, #fcd116 58%, transparent 58%);
}
.flag-star {
    position: absolute;
    top: 1px;
    left: 3px;
    color: #fcd116;
    font-size: 11px;
    z-index: 2;
    line-height: 1;
}
.flag-stripe { display: none; }
.bul-header-center {
    flex: 1;
    text-align: center;
    padding: 0 8px;
}
.bul-country {
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 0.3px;
    margin-bottom: 2px;
}
.bul-ministry {
    font-size: 9px;
    font-weight: 700;
    margin-bottom: 1px;
}
.bul-secretariat {
    font-size: 8px;
    color: #333;
}
.bul-emblem { flex-shrink: 0; }
.bul-emblem-circle {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 1.5px solid #000;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f8f8f8;
    color: #333;
    font-size: 7px;
    font-weight: 700;
}

/* ── Métadonnées ────────────────────────────────────────────────── */
.bul-meta-section {
    display: flex;
    gap: 10px;
    border-bottom: 1.5px solid #000;
    padding-bottom: 6px;
    margin-bottom: 6px;
}
.bul-meta-left, .bul-meta-right {
    flex: 1;
}
.bul-meta-left { border-right: 1px solid #ccc; padding-right: 10px; }
.bul-meta-right { padding-left: 10px; }
.bul-meta-row {
    display: flex;
    align-items: flex-end;
    margin-bottom: 4px;
    font-size: 9px;
    flex-wrap: wrap;
    gap: 2px;
}
.bul-lbl {
    font-weight: 700;
    white-space: nowrap;
    margin-right: 3px;
}
.bul-val-dot {
    border-bottom: 1.2px dotted #000;
    flex: 1;
    font-size: 10px;
    padding-bottom: 1px;
    min-width: 40px;
}
.bul-boxes {
    display: flex;
    gap: 1px;
}
.bul-box {
    width: 13px;
    height: 14px;
    border: 1px solid #000;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 9px;
    background: #fff;
}

/* ── Titre ──────────────────────────────────────────────────────── */
.bul-title-bar {
    text-align: center;
    border: 1.5px solid #000;
    padding: 4px 8px;
    margin-bottom: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.bul-title-text {
    font-size: 10px;
    font-weight: 900;
    text-decoration: underline;
    text-transform: uppercase;
}
.bul-year-text {
    font-size: 9.5px;
    font-weight: 700;
}

/* ── Tableau bulletin ───────────────────────────────────────────── */
.bul-table-wrap {
    overflow-x: visible;
    flex: 1;
}
.bul-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 8px;
    color: #000;
    table-layout: fixed;
}
.bul-table th,
.bul-table td {
    border: 1px solid #000;
    padding: 2px 1px;
    text-align: center;
    vertical-align: middle;
    word-break: break-word;
    overflow: hidden;
}
.bul-table th {
    background: #e8e8e8;
    font-weight: 700;
    font-size: 7.5px;
    line-height: 1.1;
}

/* Column widths */
.col-branches { width: 14%; text-align: left !important; padding-left: 4px !important; }
.col-prof     { width: 8%;  font-size: 7px; }
.col-trim-hdr, .col-total-hdr { font-size: 8px; font-weight: 900; }
.sub-hdr-row th { font-size: 7px; padding: 1px; }
.h-max-per  { background: #d4d4d4 !important; width: 3.2%; }
.h-score    { width: 3%; }
.h-max-ex   { background: #d4d4d4 !important; width: 3.2%; }
.h-pts-trim { background: #c8c8c8 !important; width: 3.5%; font-weight: 900; }
.h-pts-obt  { background: #f0f0f0 !important; width: 3.5%; font-weight: 900; }
.h-ann-max  { background: #c0c0c0 !important; width: 3.5%; }
.h-ann-obt  { background: #e0e0e0 !important; width: 3.5%; font-weight: 900; }
.h-pct      { width: 3%; }

/* Domain/group rows */
.bul-domaine-row td {
    background: #b0b0b0;
    font-weight: 900;
    font-size: 8px;
    text-align: center !important;
    letter-spacing: 0.5px;
    padding: 3px 0;
    border: 1px solid #000;
}
.bul-group-row td {
    background: #d8d8d8;
    font-weight: 800;
    font-size: 7.5px;
    text-align: left !important;
    padding-left: 6px !important;
}

/* Branch rows */
.bul-branch-row td { background: #fff; }
.bul-branch-name {
    text-align: left !important;
    padding-left: 8px !important;
    font-size: 8px;
}
.bul-standalone-row .bul-branch-name {
    font-weight: 700;
    padding-left: 4px !important;
}
.bul-prof-cell { font-size: 7px; font-weight: 600; }

/* Score & max cells */
.bul-max      { background: #e0e0e0; font-weight: 700; font-size: 8px; }
.bul-score    { font-size: 8.5px; font-weight: 700; }
.bul-pts-trim { background: #d0d0d0; font-weight: 900; }
.bul-pts-obt  { background: #f5f5f5; font-weight: 900; }
.bul-ann-max  { background: #c8c8c8; font-weight: 900; }
.bul-ann-obt  { background: #ebebeb; font-weight: 900; }
.bul-pct      { font-weight: 900; font-size: 8px; }

/* Sous-total rows */
.bul-sous-total-row td {
    background: #eeeeee;
    font-weight: 800;
    border-top: 1.5px solid #000;
}
.bul-sous-total-row .bul-branch-name {
    padding-left: 4px !important;
    font-style: italic;
}

/* Maxima généraux */
.bul-maxima-row td {
    background: #cccccc;
    font-weight: 900;
}
.bul-maxima-row .bul-branch-name { padding-left: 4px !important; }

/* Totaux secondaire */
.bul-totaux-row td {
    background: #e8e8e8;
    font-weight: 800;
    border-top: 1.5px solid #000;
}

/* Eval rows (pourcentage, place, etc.) */
.bul-eval-row td {
    font-weight: 700;
    font-size: 8px;
}
.bul-eval-row .bul-branch-name { padding-left: 4px !important; }
.bul-sig-row td { height: 24px; }
.bul-sig-space { height: 22px; }

/* Hachures */
.bul-hatch {
    background: repeating-linear-gradient(
        45deg,
        #d8d8d8,
        #d8d8d8 2px,
        #fff 2px,
        #fff 5px
    ) !important;
}

/* ── Bas de page ────────────────────────────────────────────────── */
.bul-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-top: 8px;
    border-top: 2px solid #000;
    padding-top: 8px;
    font-size: 8.5px;
}
.bul-decision-box { width: 44%; }
.bul-checkbox-line { display: flex; align-items: center; gap: 5px; }
.bul-checkbox {
    width: 11px;
    height: 11px;
    border: 1.2px solid #000;
    display: inline-block;
    position: relative;
    flex-shrink: 0;
}
.bul-checked::after {
    content: '✓';
    position: absolute;
    top: -2px;
    left: 0;
    font-size: 11px;
    font-weight: 900;
}
.bul-legal p { margin: 2px 0; font-size: 7.5px; }

.bul-signature-box {
    width: 54%;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}
.bul-date-line {
    font-size: 9px;
    font-weight: 700;
    margin-bottom: 8px;
    align-self: flex-start;
}
.bul-dot-line {
    border-bottom: 1px dotted #000;
    display: inline-block;
    text-align: center;
    padding-bottom: 1px;
}
.bul-sigs-grid {
    display: flex;
    justify-content: space-between;
    width: 100%;
    gap: 6px;
}
.bul-sig-col { flex: 1; text-align: center; }
.bul-sig-label { font-size: 8px; font-weight: 600; margin-bottom: 3px; }
.bul-sig-space {
    height: 40px;
    border: 1px dashed #999;
    margin-top: 3px;
    background: #fafafa;
}
.bul-ige-tag {
    margin-top: 4px;
    font-size: 7.5px;
    font-weight: 700;
    color: #555;
    align-self: flex-end;
}

/* ── IMPRESSION A4 ───────────────────────────────────────────────── */
@media print {
    .sidebar, .mobile-toggle, .mobile-overlay, .header,
    .no-print, .page-header, .page-header button { display: none !important; }

    body, html {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .app-layout { display: block !important; }
    .main-wrapper, .main-content { margin: 0 !important; padding: 0 !important; }

    .bul-container {
        background: none !important;
        padding: 0 !important;
        display: block !important;
    }
    .bul-paper {
        box-shadow: none !important;
        width: 100% !important;
        padding: 5mm 4mm !important;
        margin: 0 !important;
        page-break-inside: avoid;
    }
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .bul-hatch {
        background: repeating-linear-gradient(
            45deg, #d8d8d8, #d8d8d8 2px, #fff 2px, #fff 5px
        ) !important;
    }
    .bul-sig-space { border: 1px solid #000 !important; background: none !important; }

    @page { size: A4 landscape; margin: 8mm; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

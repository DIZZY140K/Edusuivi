<?php
// =============================================
// EduSuivi — Page de configuration initiale
// Accessible via http://localhost/edusuivi/setup.php
// =============================================
require_once __DIR__ . '/includes/config.php';

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    try {
        // Hash des mots de passe
        $adminPass  = password_hash('admin123', PASSWORD_DEFAULT);
        $teacherPass = password_hash('prof123', PASSWORD_DEFAULT);
        $parentPass  = password_hash('parent123', PASSWORD_DEFAULT);

        // Vérifier si les utilisateurs existent déjà
        $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

        if ($count == 0) {
            // Créer les utilisateurs demo
            $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)")
                ->execute(['Directeur Kalumba', 'admin@edusuivi.cd', $adminPass, 'admin']);
            $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)")
                ->execute(['Prof. Ngozi Mutombo', 'ngozi@edusuivi.cd', $teacherPass, 'teacher']);
            $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)")
                ->execute(['Marie Kabongo', 'marie@edusuivi.cd', $parentPass, 'parent']);
            $messages[] = "✅ Utilisateurs demo créés.";
        } else {
            // Mettre à jour les mots de passe
            $pdo->prepare("UPDATE users SET password=? WHERE email=?")->execute([$adminPass, 'admin@edusuivi.cd']);
            $pdo->prepare("UPDATE users SET password=? WHERE email=?")->execute([$teacherPass, 'ngozi@edusuivi.cd']);
            $pdo->prepare("UPDATE users SET password=? WHERE email=?")->execute([$parentPass, 'marie@edusuivi.cd']);
            $messages[] = "✅ Mots de passe mis à jour.";
        }

        // Classes
        $classCount = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
        if ($classCount == 0) {
            $pdo->exec("INSERT INTO classes (name, level) VALUES ('6ème A','Secondaire'),('5ème B','Secondaire'),('4ème A','Secondaire')");
            $messages[] = "✅ Classes créées.";
        }

        // Matières
        $subCount = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
        if ($subCount == 0) {
            $pdo->exec("INSERT INTO subjects (name) VALUES ('Mathématiques'),('Français'),('Sciences'),('Histoire-Géographie'),('Anglais'),('Éducation Civique'),('Biologie'),('Chimie'),('Physique')");
            $messages[] = "✅ Matières créées.";
        }

        // Récupérer IDs
        $adminId  = $pdo->query("SELECT id FROM users WHERE email='admin@edusuivi.cd'")->fetchColumn();
        $teachId  = $pdo->query("SELECT id FROM users WHERE email='ngozi@edusuivi.cd'")->fetchColumn();
        $parentId = $pdo->query("SELECT id FROM users WHERE email='marie@edusuivi.cd'")->fetchColumn();
        $class1   = $pdo->query("SELECT id FROM classes WHERE name='6ème A'")->fetchColumn();
        $class2   = $pdo->query("SELECT id FROM classes WHERE name='5ème B'")->fetchColumn();

        // Élèves
        $stuCount = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
        if ($stuCount == 0 && $class1 && $parentId) {
            $matricule = function($n) { return 'MAT-2025-' . str_pad($n, 4, '0', STR_PAD_LEFT); };
            $stmtS = $pdo->prepare("INSERT INTO students (matricule,first_name,last_name,date_of_birth,gender,class_id,parent_id,option_filiere) VALUES (?,?,?,?,?,?,?,?)");
            $stmtS->execute([$matricule(1),'Jean','Kabongo','2010-03-15','M',$class1,$parentId,'Sciences']);
            $stmtS->execute([$matricule(2),'Sarah','Kabongo','2012-07-22','F',$class2,$parentId,'Commerce']);
            $stmtS->execute([$matricule(3),'Pierre','Mwamba','2011-01-10','M',$class1,null,'Sciences']);
            $stmtS->execute([$matricule(4),'Grace','Lukusa','2010-11-05','F',$class1,null,'Lettres']);
            $stmtS->execute([$matricule(5),'David','Tshimanga','2011-06-18','M',$class2,null,'Sciences']);
            $messages[] = "✅ Élèves demo créés.";
        }

        // Affectations enseignant
        $taCount = $pdo->query("SELECT COUNT(*) FROM teacher_assignments")->fetchColumn();
        if ($taCount == 0 && $teachId && $class1) {
            $sub1 = $pdo->query("SELECT id FROM subjects WHERE name='Mathématiques'")->fetchColumn();
            $sub2 = $pdo->query("SELECT id FROM subjects WHERE name='Sciences'")->fetchColumn();
            $sub3 = $pdo->query("SELECT id FROM subjects WHERE name='Français'")->fetchColumn();
            if ($sub1) $pdo->prepare("INSERT IGNORE INTO teacher_assignments (teacher_id,class_id,subject_id) VALUES (?,?,?)")->execute([$teachId,$class1,$sub1]);
            if ($sub2) $pdo->prepare("INSERT IGNORE INTO teacher_assignments (teacher_id,class_id,subject_id) VALUES (?,?,?)")->execute([$teachId,$class1,$sub2]);
            if ($sub3) $pdo->prepare("INSERT IGNORE INTO teacher_assignments (teacher_id,class_id,subject_id) VALUES (?,?,?)")->execute([$teachId,$class2,$sub1]);
            $messages[] = "✅ Affectations enseignant créées.";
        }

        // Notes demo
        $grCount = $pdo->query("SELECT COUNT(*) FROM grades")->fetchColumn();
        if ($grCount == 0 && $teachId) {
            $sub1 = $pdo->query("SELECT id FROM subjects WHERE name='Mathématiques'")->fetchColumn();
            $sub2 = $pdo->query("SELECT id FROM subjects WHERE name='Sciences'")->fetchColumn();
            $sub3 = $pdo->query("SELECT id FROM subjects WHERE name='Français'")->fetchColumn();
            $stu1 = $pdo->query("SELECT id FROM students WHERE last_name='Kabongo' AND first_name='Jean'")->fetchColumn();
            $stu2 = $pdo->query("SELECT id FROM students WHERE last_name='Kabongo' AND first_name='Sarah'")->fetchColumn();
            $stu3 = $pdo->query("SELECT id FROM students WHERE last_name='Mwamba'")->fetchColumn();
            $stu4 = $pdo->query("SELECT id FROM students WHERE last_name='Lukusa'")->fetchColumn();
            if ($stu1 && $sub1) {
                $gr = $pdo->prepare("INSERT INTO grades (student_id,subject_id,teacher_id,grade,max_grade,period,comment) VALUES (?,?,?,?,?,?,?)");
                $gr->execute([$stu1,$sub1,$teachId,16,20,'1er Trimestre','Très bon travail']);
                $gr->execute([$stu1,$sub2,$teachId,14,20,'1er Trimestre','Bon effort']);
                $gr->execute([$stu1,$sub3,$teachId,12,20,'1er Trimestre','Peut mieux faire']);
                $gr->execute([$stu1,$sub1,$teachId,14,20,'2ème Trimestre','Légère baisse']);
                $gr->execute([$stu1,$sub1,$teachId,17,20,'3ème Trimestre','Excellente progression']);
                if($stu2) { $gr->execute([$stu2,$sub1,$teachId,18,20,'1er Trimestre','Excellent']); $gr->execute([$stu2,$sub1,$teachId,19,20,'2ème Trimestre','Parfait']); }
                if($stu3) { $gr->execute([$stu3,$sub1,$teachId,10,20,'1er Trimestre','Doit travailler']); $gr->execute([$stu3,$sub1,$teachId,13,20,'2ème Trimestre','Amélioration']); }
                if($stu4) { $gr->execute([$stu4,$sub1,$teachId,15,20,'1er Trimestre','Bien']); }
            }
            $messages[] = "✅ Notes demo créées.";
        }

        // Absences demo
        $abCount = $pdo->query("SELECT COUNT(*) FROM absences")->fetchColumn();
        if ($abCount == 0 && $teachId) {
            $stu1 = $pdo->query("SELECT id FROM students WHERE last_name='Kabongo' AND first_name='Jean'")->fetchColumn();
            if ($stu1) {
                $pdo->prepare("INSERT INTO absences (student_id,date,justified,reason,recorded_by) VALUES (?,?,?,?,?)")->execute([$stu1,'2025-01-15',1,'Maladie',$teachId]);
                $pdo->prepare("INSERT INTO absences (student_id,date,justified,reason,recorded_by) VALUES (?,?,?,?,?)")->execute([$stu1,'2025-02-03',0,null,$teachId]);
                $pdo->prepare("INSERT INTO absences (student_id,date,justified,reason,recorded_by) VALUES (?,?,?,?,?)")->execute([$stu1,'2025-03-10',0,null,$teachId]);
            }
            $messages[] = "✅ Absences demo créées.";
        }

        // Préférences admin
        try {
            $pdo->prepare("INSERT IGNORE INTO user_preferences (user_id, lang, theme) VALUES (?,?,?)")->execute([$adminId,'fr','dark']);
        } catch(Exception $e) {}

    } catch(PDOException $e) {
        $errors[] = "❌ Erreur: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduSuivi — Configuration initiale</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css">
<link rel="stylesheet" href="/edusuivi/css/style.css">
<style>
.setup-container { max-width:700px; margin:40px auto; padding:24px; }
.setup-card { background:var(--card); border-radius:16px; padding:32px; border:1px solid var(--border); }
.setup-card h1 { font-size:28px; font-weight:700; margin-bottom:8px; }
.setup-card .desc { color:var(--text-muted); margin-bottom:24px; }
.msg-box { padding:12px 16px; border-radius:8px; margin-bottom:8px; font-size:14px; }
.msg-ok { background:rgba(16,185,129,.15); color:#10b981; border:1px solid rgba(16,185,129,.3); }
.msg-err { background:rgba(239,68,68,.15); color:#ef4444; border:1px solid rgba(239,68,68,.3); }
.credentials { background:rgba(212,147,13,.1); border:1px solid var(--primary); border-radius:12px; padding:20px; margin:20px 0; }
.credentials h3 { color:var(--primary); margin-bottom:12px; }
.cred-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--border); font-size:14px; }
.cred-row:last-child { border:none; }
.cred-label { color:var(--text-muted); }
.cred-val { font-family:monospace; color:var(--text); }
.btn-setup { background:linear-gradient(135deg,var(--primary),var(--gold-light)); color:#000; border:none; padding:14px 32px; border-radius:10px; font-size:16px; font-weight:700; cursor:pointer; width:100%; margin-top:16px; }
.btn-setup:hover { opacity:.9; transform:translateY(-1px); }
.warn { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); border-radius:8px; padding:12px; margin-bottom:16px; font-size:13px; color:#ef4444; }
</style>
</head>
<body style="background:var(--bg); color:var(--text); font-family:'Inter',sans-serif;">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<div class="setup-container">
    <div class="setup-card">
        <h1>⚙️ EduSuivi — Configuration</h1>
        <p class="desc">Initialise la base de données avec les données de démonstration.</p>

        <div class="warn">⚠️ À exécuter UNE SEULE FOIS. Supprimer ce fichier après utilisation en production.</div>

        <?php foreach($messages as $m): ?>
        <div class="msg-box msg-ok"><?= $m ?></div>
        <?php endforeach; ?>
        <?php foreach($errors as $e): ?>
        <div class="msg-box msg-err"><?= $e ?></div>
        <?php endforeach; ?>

        <?php if (!$messages && !$errors): ?>
        <div class="credentials">
            <h3>🔑 Comptes de démonstration</h3>
            <div class="cred-row"><span class="cred-label">Administrateur</span><span class="cred-val">admin@edusuivi.cd / admin123</span></div>
            <div class="cred-row"><span class="cred-label">Enseignant</span><span class="cred-val">ngozi@edusuivi.cd / prof123</span></div>
            <div class="cred-row"><span class="cred-label">Parent</span><span class="cred-val">marie@edusuivi.cd / parent123</span></div>
        </div>
        <?php endif; ?>

        <?php if ($messages): ?>
        <div class="credentials">
            <h3>🔑 Comptes actifs</h3>
            <div class="cred-row"><span class="cred-label">Administrateur</span><span class="cred-val">admin@edusuivi.cd / admin123</span></div>
            <div class="cred-row"><span class="cred-label">Enseignant</span><span class="cred-val">ngozi@edusuivi.cd / prof123</span></div>
            <div class="cred-row"><span class="cred-label">Parent</span><span class="cred-val">marie@edusuivi.cd / parent123</span></div>
        </div>
        <div style="text-align:center;margin-top:20px;">
            <a href="/edusuivi/login.php" style="display:inline-block;background:var(--primary);color:#000;padding:12px 32px;border-radius:10px;font-weight:700;text-decoration:none;">→ Aller à la connexion</a>
        </div>
        <?php else: ?>
        <form method="POST">
            <button type="submit" name="setup" value="1" class="btn-setup">🚀 Initialiser EduSuivi</button>
        </form>
        <?php endif; ?>
    </div>
</div>
<script src="/edusuivi/js/fontawesome-icons.js"></script>
</body>
</html>

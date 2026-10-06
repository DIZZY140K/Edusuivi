<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();
requireRole('admin');

$backupDir = __DIR__ . '/../backups/';
if (!is_dir($backupDir)) { mkdir($backupDir, 0777, true); }

// ── Télécharger une sauvegarde ──────────────────────────
if (isset($_GET['download'])) {
    $file = basename($_GET['download']);
    $path = $backupDir . $file;
    if (file_exists($path) && is_file($path) && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$file.'"');
        header('Content-Length: ' . filesize($path));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($path);
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';

$success = $error = '';

// ── Créer une sauvegarde SQL ────────────────────────────
if (($_POST['action']??'') === 'backup') {
    $filename = 'edusuivi_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . $filename;
    
    // Cette méthode utilise mysqldump si disponible, sinon on fait un dump PHP basique
    // Pour XAMPP Windows, mysqldump est généralement accessible si configuré
    $mysqldump = 'c:\xampp\mysql\bin\mysqldump.exe'; // Chemin typique XAMPP Windows
    
    if (file_exists($mysqldump)) {
        $cmd = "\"$mysqldump\" -u ".DB_USER.(DB_PASS?" -p".DB_PASS:"")." ".DB_NAME." > \"$filepath\" 2>&1";
        exec($cmd, $output, $returnVar);
        
        if ($returnVar === 0 && file_exists($filepath) && filesize($filepath) > 0) {
            $pdo->prepare("INSERT INTO activity_logs (user_id,action,details) VALUES (?,?,?)")->execute([$currentUser['id'], 'backup', 'Création de la sauvegarde '.$filename]);
            $success = "Sauvegarde créée avec succès : $filename";
        } else {
            // Fallback PHP dump if mysqldump fails
            $dumpSuccess = false;
            try {
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $sql = "-- EduSuivi Backup SQL\n-- Date: " . date('Y-m-d H:i:s') . "\n-- Database: " . DB_NAME . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
                foreach ($tables as $table) {
                    $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                    $sql .= $create['Create Table'] . ";\n\n";
                    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), array_values($row));
                        $cols = array_map(fn($c) => "`$c`", array_keys($row));
                        $sql .= "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
                    }
                    $sql .= "\n";
                }
                $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
                file_put_contents($filepath, $sql);
                $pdo->prepare("INSERT INTO activity_logs (user_id,action,details) VALUES (?,?,?)")->execute([$currentUser['id'], 'backup', 'Création de la sauvegarde '.$filename]);
                $success = "Sauvegarde créée avec succès : $filename";
                $dumpSuccess = true;
            } catch(Exception $ex) {
                $error = "Erreur lors de la sauvegarde : " . $ex->getMessage();
            }
        }
    } else {
        // Fallback dump PHP direct
        try {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $sql = "-- EduSuivi Backup SQL\n-- Date: " . date('Y-m-d H:i:s') . "\n-- Database: " . DB_NAME . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
            foreach ($tables as $table) {
                $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $create['Create Table'] . ";\n\n";
                $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), array_values($row));
                    $cols = array_map(fn($c) => "`$c`", array_keys($row));
                    $sql .= "INSERT INTO `$table` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
                }
                $sql .= "\n";
            }
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            file_put_contents($filepath, $sql);
            $pdo->prepare("INSERT INTO activity_logs (user_id,action,details) VALUES (?,?,?)")->execute([$currentUser['id'], 'backup', 'Création de la sauvegarde '.$filename]);
            $success = "Sauvegarde créée avec succès : $filename";
        } catch(Exception $ex) {
            $error = "Erreur lors de la sauvegarde : " . $ex->getMessage();
        }
    }
}

// ── Supprimer une sauvegarde ────────────────────────────
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $path = $backupDir . $file;
    if (file_exists($path) && is_file($path)) {
        unlink($path);
        $success = "Sauvegarde supprimée.";
    }
}

// Lister les sauvegardes
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..' && pathinfo($f, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'name' => $f,
                'size' => round(filesize($backupDir . $f) / 1024, 2) . ' KB',
                'date' => date('d/m/Y H:i:s', filemtime($backupDir . $f)),
                'timestamp' => filemtime($backupDir . $f)
            ];
        }
    }
    // Trier par date décroissante
    usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
}

// Infos système
$dbSize = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = '".DB_NAME."'")->fetchColumn();
$dbTables = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '".DB_NAME."'")->fetchColumn();
?>

<div class="page-header">
    <div>
        <h2>💾 Sauvegarde & Export</h2>
        <p>Sécurisez vos données et téléchargez les backups</p>
    </div>
    <form method="POST" style="margin:0;">
        <input type="hidden" name="action" value="backup">
        <button type="submit" class="btn btn-primary" onclick="this.innerHTML='⏳ Création en cours...';">🔄 Créer une sauvegarde SQL</button>
    </form>
</div>

<?php if($success): ?><div class="alert alert-success"><?= escape($success) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?= escape($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 350px;gap:24px;">

<!-- Liste des backups -->
<div class="card">
    <div class="card-header"><h3>📁 Fichiers de sauvegarde SQL</h3></div>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr><th>Nom du fichier</th><th>Taille</th><th>Date de création</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($backups as $b): ?>
        <tr>
            <td><strong><?= escape($b['name']) ?></strong></td>
            <td style="color:var(--text-muted);"><?= $b['size'] ?></td>
            <td style="color:var(--text-muted);font-size:13px;"><?= $b['date'] ?></td>
            <td style="white-space:nowrap;">
                <a href="?download=<?= urlencode($b['name']) ?>" class="btn btn-sm btn-primary">⬇️ Télécharger</a>
                <a href="?delete=<?= urlencode($b['name']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer définitivement ce fichier ?')">🗑</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($backups)): ?><tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:30px;">Aucune sauvegarde locale trouvée.<br><br>Cliquez sur "Créer une sauvegarde" pour générer votre premier backup.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Outils d'export / infos système -->
<div>
    <!-- Export CSV divers -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h3>📥 Exportations Rapides CSV</h3></div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:12px;">
            <a href="students.php?export=csv" class="btn btn-outline" style="justify-content:flex-start;">👥 Exporter la liste des élèves</a>
            <a href="reports.php?export=csv" class="btn btn-outline" style="justify-content:flex-start;">📈 Exporter les moyennes (Rapport)</a>
            <a href="fees.php?export=csv" class="btn btn-outline" style="justify-content:flex-start;">💰 Exporter l'état des frais</a>
        </div>
    </div>

    <!-- Infos système -->
    <div class="card">
        <div class="card-header"><h3>⚙️ Informations Système</h3></div>
        <div style="padding:16px;">
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                <span style="color:var(--text-muted);font-size:13px;">Base de données</span>
                <strong style="color:var(--primary);"><?= DB_NAME ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                <span style="color:var(--text-muted);font-size:13px;">Taille totale</span>
                <strong><?= $dbSize ?> MB</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                <span style="color:var(--text-muted);font-size:13px;">Nombre de tables</span>
                <strong><?= $dbTables ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                <span style="color:var(--text-muted);font-size:13px;">Dossier Backups</span>
                <strong style="font-size:11px;"><?= count($backups) ?> fichier(s)</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:8px 0;">
                <span style="color:var(--text-muted);font-size:13px;">Version PHP</span>
                <strong><?= phpversion() ?></strong>
            </div>
        </div>
    </div>
</div>

</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

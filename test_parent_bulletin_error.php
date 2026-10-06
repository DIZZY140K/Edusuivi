<?php
session_start();
$_SESSION['user_id'] = 3; // Marie Kabongo (parent)
$_SESSION['user_name'] = 'Marie Kabongo';
$_SESSION['user_email'] = 'marie@edusuivi.cd';
$_SESSION['user_role'] = 'parent';
$_GET['student_id'] = 2; // Her child Kabongo Sarah

ob_start();
try {
    include __DIR__ . '/admin/bulletin.php';
    $out = ob_get_clean();
    echo "Output: " . substr($out, 0, 300) . "...\n";
} catch(Throwable $e) {
    ob_end_clean();
    echo "Caught Exception/Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

unlink(__FILE__);

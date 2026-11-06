<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "--- TEST 1: index.php?page=voter ---\n";
$_GET['page'] = 'voter';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    require 'index.php';
    $out = ob_get_clean();
    echo "SUCCESS: voter output length " . strlen($out) . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR in voter: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n--- TEST 2: index.php?page=resultats ---\n";
$_GET['page'] = 'resultats';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    require 'index.php';
    $out = ob_get_clean();
    echo "SUCCESS: public resultats output length " . strlen($out) . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR in public resultats: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n--- TEST 3: index.php?page=resultats_admin ---\n";
$_SESSION['admin_user'] = [
    'id' => 1,
    'nom' => 'KOUADIO',
    'prenoms' => 'Ferdinand',
    'telephone' => '0700000001',
    'role_id' => 1,
    'role_code' => 'super_admin',
    'role_nom' => 'Super Administrateur',
    'statut' => 'actif'
];
$_GET['page'] = 'resultats_admin';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
try {
    require 'index.php';
    $out = ob_get_clean();
    echo "SUCCESS: admin resultats output length " . strlen($out) . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "ERROR in admin resultats: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

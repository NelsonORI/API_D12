<?php
// Load configuration FIRST
require_once __DIR__ . "/conf.php"; // Use absolute path

// Check if $conf is properly loaded
if (!isset($conf) || !is_array($conf)) {
    die("Configuration not loaded properly. Check conf.php");
}

function getDBConnection() {
    global $conf; // Access the global $conf variable
    
    try {
        $dsn = "mysql:host={$conf['db_host']};dbname={$conf['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $conf['db_user'], $conf['db_pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// For backward compatibility
try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    die($e->getMessage());
}
?>
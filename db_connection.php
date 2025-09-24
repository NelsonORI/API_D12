<?php
require_once __DIR__ . "/conf.php"; // load $conf settings

try {
    // Change the DSN to 'mysql' and update the host, dbname, user, and password
    $dsn = "mysql:host={$conf['db_host']};dbname={$conf['db_name']};user={$conf['db_user']};password={$conf['db_pass']}";
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ DB connected!";
} catch (PDOException $e) {
    die("❌ Connection failed: " . $e->getMessage());
}
?>
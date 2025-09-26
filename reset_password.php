<?php
require_once 'ClassAutoLoad.php';

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$conf['db_host']};dbname={$conf['db_name']}", 
        $conf['db_user'], 
        $conf['db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: forgot_password.php?error=" . urlencode("Invalid reset link"));
    exit;
}

// Validate token using password_resets table
try {
    $stmt = $pdo->prepare("
        SELECT pr.*, p.username 
        FROM password_resets pr 
        JOIN project p ON pr.user_id = p.id 
        WHERE pr.reset_token = ? AND pr.used = 0
    ");
    $stmt->execute([$token]);
    $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRequest) {
        header("Location: forgot_password.php?error=" . urlencode("Invalid or expired reset link"));
        exit;
    }

    // Check if token is expired
    if (strtotime($resetRequest['token_expires']) < time()) {
        header("Location: forgot_password.php?error=" . urlencode("Reset link has expired"));
        exit;
    }

    // Token is valid, show reset form
    $layoutsInstance->heading($conf);
    $formsInstance->reset_password($token);
    $layoutsInstance->footer($conf);

} catch (PDOException $e) {
    header("Location: forgot_password.php?error=" . urlencode("System error. Please try again."));
    exit;
}
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
        header("Location: reset_password.php?token=" . $token . "&error=" . urlencode("All fields are required"));
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        header("Location: reset_password.php?token=" . $token . "&error=" . urlencode("Passwords do not match"));
        exit;
    }

    if (strlen($newPassword) < 8) {
        header("Location: reset_password.php?token=" . $token . "&error=" . urlencode("Password must be at least 8 characters long"));
        exit;
    }

    try {
        // Verify token is still valid
        $stmt = $pdo->prepare("
            SELECT pr.user_id, pr.token_expires 
            FROM password_resets pr 
            WHERE pr.reset_token = ? AND pr.used = 0
        ");
        $stmt->execute([$token]);
        $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetRequest) {
            header("Location: forgot_password.php?error=" . urlencode("Invalid or expired reset link"));
            exit;
        }

        if (strtotime($resetRequest['token_expires']) < time()) {
            header("Location: forgot_password.php?error=" . urlencode("Reset link has expired"));
            exit;
        }

        // Update user's password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE project SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $resetRequest['user_id']]);

        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE reset_token = ?");
        $stmt->execute([$token]);

        // Redirect to success page
        header("Location: login.php?success=" . urlencode("Password reset successfully!"));
        exit;

    } catch (PDOException $e) {
        header("Location: reset_password.php?token=" . $token . "&error=" . urlencode("System error. Please try again."));
        exit;
    }
} else {
    header("Location: forgot_password.php");
    exit;
}
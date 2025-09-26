<?php
require_once 'ClassAutoLoad.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    $email = $_POST['email'];

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: forgot_password.php?error=Invalid email format");
        exit;
    }

    // Check if user exists
    try {
        $stmt = $pdo->prepare("SELECT id, username FROM project WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $createdAt = date('Y-m-d H:i:s');

            // Store token in password_resets table (NOT project table)
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, email, reset_token, token_expires, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $email, $token, $expires, $createdAt]);

            // Create reset link
            $resetLink = $conf['site_url'] . "/reset_password.php?token=" . $token;

            // Send email
            $mail = new PHPMailer(true);

            try {
                // SMTP Configuration
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'nelson.mecha@strathmore.edu';
                $mail->Password = 'tptj smnr miua fnqx';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Email content
                $mail->setFrom('no-reply@tickyfii.com', 'Tickyfii');
                $mail->addAddress($email, $user['username']);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body = "Hello {$user['username']},<br><br>"
                            . "You requested a password reset. Click the link below to reset your password:<br><br>"
                            . "<a href='$resetLink'>Reset Password</a><br><br>"
                            . "This link will expire in 1 hour.<br><br>"
                            . "If you didn't request this, please ignore this email.<br><br>"
                            . "Regards,<br>Tickyfii Team";

                $mail->send();
                
                header("Location: forgot_password.php?success=1&message=" . urlencode("Password reset link sent to your email!"));
                exit;
                
            } catch (Exception $e) {
                // Fallback to debug mode
                header("Location: forgot_password.php?message=" . urlencode("Debug mode: Reset link would be: $resetLink"));
                exit;
            }
        } else {
            // User doesn't exist, but don't reveal that
            header("Location: forgot_password.php?success=1&message=" . urlencode("If an account with that email exists, a reset link has been sent."));
            exit;
        }
    } catch (PDOException $e) {
        header("Location: forgot_password.php?error=" . urlencode("System error. Please try again."));
        exit;
    }
} else {
    header("Location: forgot_password.php");
    exit;
}
<?php
require_once 'ClassAutoLoad.php';
session_start(); // Start session at the beginning

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

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please enter both username and password.";
        header("Location: login.php");
        exit;
    }

    // Prepare SQL statement to prevent SQL injection
    try {
        $stmt = $pdo->prepare("SELECT id, username, password, email, role FROM project WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        // Check if user exists
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Password is correct - GENERATE 2FA OTP
                $otp = random_int(100000, 999999); // 6-digit code
                
                // Store user data and OTP in session for 2FA
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'] ?? 'user'; // Added role to session
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
                $_SESSION['loggedin'] = false; // Not fully logged in until 2FA
                
                // SEND OTP VIA EMAIL
                require_once 'vendor/autoload.php';
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                try {
                    // SMTP Configuration
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'nelson.mecha@strathmore.edu';
                    $mail->Password = 'tptj smnr miua fnqx';
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    
                    // Email content
                    $mail->setFrom('no-reply@tickyfii.com', 'Tickyfii');
                    $mail->addAddress($user['email'], $user['username']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Your Tickyfii Verification Code';
                    $mail->Body = "Hello {$user['username']},<br><br>"
                                . "Your verification code is: <b>$otp</b><br><br>"
                                . "This code is valid for 5 minutes.<br><br>"
                                . "Regards,<br>Tickyfii Team";
                    
                    $mail->send();
                    
                    // REDIRECT TO 2FA VERIFICATION PAGE
                    header("Location: verify_2fa.php");
                    exit;
                    
                } catch (Exception $e) {
                    // Email failed - fallback to debug mode
                    $_SESSION['debug_mode'] = true;
                    $_SESSION['debug_otp'] = $otp;
                    header("Location: verify_2fa.php?debug=1");
                    exit;
                }
                
            } else {
                // Invalid password
                $_SESSION['error'] = "Invalid password.";
                header("Location: login.php");
                exit;
            }
        } else {
            // User doesn't exist
            $_SESSION['error'] = "User not found.";
            header("Location: login.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "System error. Please try again.";
        header("Location: login.php");
        exit;
    }
} else {
    // Redirect if accessed directly
    header("Location: login.php");
    exit;
}
?>
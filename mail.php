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

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash the password

    // 1. Validate the email address
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Handle invalid email case
        echo "Invalid email format.";
        exit;
    }

    // 2. Check if user already exists
    try {
        $stmt = $pdo->prepare("SELECT id FROM project WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo "User with this email already exists.";
            exit;
        }
    } catch (PDOException $e) {
        die("Error checking user: " . $e->getMessage());
    }

    // 3. Save user to database
    try {
        $stmt = $pdo->prepare("INSERT INTO project (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $password]);
    } catch (PDOException $e) {
        die("Error saving user: " . $e->getMessage());
    }

    // 4. Send the email notification
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'nelson.mecha@strathmore.edu';
        $mail->Password   = 'tptj smnr miua fnqx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        //Recipients
        $mail->setFrom('no-reply@your-app.com', 'Tickyfii');
        $mail->addAddress($email, $username); // Add a recipient

        //Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to Tickyfii';
        $mail->Body    = "Hello {$username},<br><br>You requested an account on Tickyfii. In order to use this account you need to <a href='#'>Click Here</a> to complete the registration process.<br><br>Regards,<br>Systems Admin<br>Tickyfii";
        
        $mail->send();
        echo 'Message has been sent successfully. Please check your email.';

    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
?>
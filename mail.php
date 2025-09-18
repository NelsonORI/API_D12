<?php
require_once 'ClassAutoLoad.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];

    // 1. Validate the email address
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Handle invalid email case
        echo "Invalid email format.";
        exit;
    }

    // 2. Send the email notification
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
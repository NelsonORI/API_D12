<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $enteredOtp = $_POST['otp'];

    // Check both regular OTP and debug OTP
    $validOtp = $_SESSION['otp'] ?? null;
    $debugOtp = $_SESSION['debug_otp'] ?? null;

    if (($validOtp && time() > $_SESSION['otp_expiry']) || ($debugOtp && time() > ($_SESSION['otp_expiry'] ?? 0))) {
        $_SESSION['error'] = "OTP expired. Please log in again.";
        session_destroy();
        header("Location: login.php");
        exit;
    }

    if ($enteredOtp == $validOtp || $enteredOtp == $debugOtp) {
        $_SESSION['authenticated'] = true;
        unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['debug_otp'], $_SESSION['debug_mode']);

        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['error'] = "Invalid OTP. Try again.";
        header("Location: verify_2fa.php");
        exit;
    }
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: login.php");
    exit;
}
?>
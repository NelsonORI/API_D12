<?php
session_start();

// Redirect if no OTP was generated (user didn't pass login step)
if (!isset($_SESSION['otp']) && !isset($_SESSION['debug_otp'])) {
    header("Location: login.php");
    exit;
}

// Grab any error messages
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

// Check if in debug mode
$debugMode = isset($_SESSION['debug_mode']) || isset($_GET['debug']);
$debugOtp = $_SESSION['debug_otp'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify 2FA | Tickyfii</title>
</head>
<body>
<div>
    <div>
        <div>
            <div>
                <h2>Two-Factor Authentication</h2>

                <?php if ($error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($debugMode && $debugOtp): ?>
                    <div>
                        <strong>Debug Mode:</strong> Your OTP is: <b><?= $debugOtp ?></b>
                    </div>
                <?php endif; ?>

                <p>
                    We sent a 6-digit code to your email. Please enter it below:
                </p>

                <form action="process_2fa.php" method="post">
                    <div>
                        <label for="otp">Verification Code</label>
                        <input type="text" id="otp" name="otp" maxlength="6" placeholder="123456" required>
                    </div>
                    <div>
                        <button type="submit">Verify</button>
                    </div>
                </form>

                <div>
                    <small>Code expires in 5 minutes.</small>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
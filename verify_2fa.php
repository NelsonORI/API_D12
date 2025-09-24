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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Two-Factor Authentication</h2>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($debugMode && $debugOtp): ?>
                        <div class="alert alert-warning text-center">
                            <strong>Debug Mode:</strong> Your OTP is: <b><?= htmlspecialchars($debugOtp) ?></b>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted text-center">
                        We sent a 6-digit code to your email. Please enter it below:
                    </p>

                    <form action="process_2fa.php" method="post">
                        <div class="mb-3">
                            <label for="otp" class="form-label">Verification Code</label>
                            <input type="text" id="otp" name="otp" maxlength="6"
                                   class="form-control text-center" placeholder="123456" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Verify</button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <small class="text-muted">Code expires in 5 minutes.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

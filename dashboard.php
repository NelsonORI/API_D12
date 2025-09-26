<?php
session_start();

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | Tickyfii</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <!-- Header with Logout Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Welcome to Dashboard, <?php echo $_SESSION['username']; ?>!</h1>
            <a href="logout.php" class="btn btn-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <p>Email: <?php echo $_SESSION['user_email']; ?></p>
                <!-- Your dashboard content here -->
            </div>
        </div>
    </div>
</body>
</html>
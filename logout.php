<?php
// Start the session to access session variables
session_start();

// Unset all session variables
// This is important to ensure no residual data remains
$_SESSION = array();

// Destroy the session cookie
// This ensures the browser no longer recognizes the session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session data on the server
session_destroy();

// Redirect to the login page
// Assuming your login page is named 'login.php'
header("Location: login.php");
exit;

?>
<?php
// customers/logout.php
session_name('ParlourPOS_Customer'); // Target the isolated customer session ONLY
session_start();

// Unset all session variables associated with the customer
$_SESSION = array();

// Kill the specific customer session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the customer session completely
session_destroy();

// Redirect back to the customer login screen
header("Location: login.php");
exit;
?>
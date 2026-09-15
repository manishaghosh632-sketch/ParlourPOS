<?php
// auth/logout.php
session_start();

// Determine redirect URL based on the user's role BEFORE destroying the session
// Fallback to the main directory if no session role exists
$redirect_url = '../index.php'; 

if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    
    // Route to the specific login pages based on the session role
    if ($role === 'Super Admin') {
        $redirect_url = 'login_admin.php';
    } elseif ($role === 'Manager') {
        $redirect_url = 'login_manager.php';
    } else {
        // Catches 'Beautician', 'Receptionist', and any other staff roles
        $redirect_url = 'login_staff.php'; 
    }
}

// Unset all of the session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session completely
session_destroy();

// Redirect to the appropriate login portal
header("Location: " . $redirect_url);
exit;
?>
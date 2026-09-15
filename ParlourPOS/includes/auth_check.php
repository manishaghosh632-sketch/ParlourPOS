<?php
// includes/auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamically determine the base path of the project for XAMPP routing
$app_dir = str_replace('\\', '/', dirname(__DIR__));
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_url = str_replace($doc_root, '', $app_dir);

// Fallback in case of document root mismatch (e.g., aliased directories in XAMPP)
if (!str_starts_with($app_dir, $doc_root)) {
    $base_url = '/parlourpos'; 
}

function require_login() {
    global $base_url;
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . $base_url . "/customers/login.php");
        exit;
    }
}

function require_role(array $allowed_roles) {
    require_login();
    $user_role = $_SESSION['role'] ?? '';
    
    if (!in_array($user_role, $allowed_roles)) {
        route_user_to_dashboard($user_role);
    }
}

function route_user_to_dashboard($role) {
    global $base_url;
    switch ($role) {
        case 'Super Admin':
            header("Location: " . $base_url . "/super_admin/dashboard.php");
            break;
        case 'Manager':
            header("Location: " . $base_url . "/manager/dashboard.php");
            break;
        case 'Receptionist':
            header("Location: " . $base_url . "/staff/cashier/dashboard.php");
            break;
        case 'Beautician':
            header("Location: " . $base_url . "/staff/beautician/dashboard.php");
            break;
        case 'Customer':
            header("Location: " . $base_url . "/customers/dashboard.php");
            break;
        default:
            // Destroy stale/poisoned sessions to prevent ERR_TOO_MANY_REDIRECTS loops
            session_unset();
            session_destroy();
            header("Location: " . $base_url . "/customers/login.php");
            break;
    }
    exit;
}
?>
<?php
// index.php
require_once 'includes/auth_check.php';

if (isset($_SESSION['user_id'])) {
    // If already logged in, route to the correct dashboard securely
    route_user_to_dashboard($_SESSION['role']);
} else {
    // If not logged in, send to the luxury login page using dynamic URL
    header("Location: " . $base_url . "/auth/login.php");
    exit;
}
?>
<?php
// customers/index.php
session_name('ParlourPOS_Customer'); // Target the isolated customer session
session_start();

// Check if the user is already logged in securely as a Customer
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'Customer') {
    // Redirect active customers directly to their dashboard
    header("Location: dashboard.php");
    exit;
} else {
    // Redirect unauthenticated users to the customer login portal
    header("Location: login.php");
    exit;
}
?>
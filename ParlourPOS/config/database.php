<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'parlour_pos');

// Initialize connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log("Database Connection Failed: " . $conn->connect_error);
    die("A system error occurred. Please contact support.");
}

// Set charset to handle specialized characters and emojis
$conn->set_charset("utf8mb4");
?>
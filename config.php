<?php

// -----------------------------------------------------------
// Environment
// -----------------------------------------------------------
// Set to true only on your local dev machine. Keep false on any
// server real users can reach — showing raw PHP errors leaks
// database structure and file paths.
define('APP_DEBUG', false);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/php-error.log');
}

// -----------------------------------------------------------
// Session security
// -----------------------------------------------------------
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
// Uncomment when serving over HTTPS in production:
// ini_set('session.cookie_secure', 1);

// -----------------------------------------------------------
// Database connection
// -----------------------------------------------------------
$host = "localhost";
$username = "root";
$password = "";
$database = "taxi_management";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// -----------------------------------------------------------
// Shared security helpers (CSRF tokens, output escaping, password hashing)
// -----------------------------------------------------------
require_once __DIR__ . "/security.php";

?>

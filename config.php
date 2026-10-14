<?php
<<<<<<< HEAD
/**
 * Application bootstrap and database connection.
 * Configure DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASSWORD as Replit
 * Secrets (or shell environment variables when running locally).
 */
$debug = filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN);
define('APP_DEBUG', $debug);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-error.log');

// These settings must be applied before each page calls session_start().
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
if (filter_var(getenv('SESSION_COOKIE_SECURE') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
    ini_set('session.cookie_secure', '1');
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3307);
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$database = getenv('DB_NAME') ?: 'taxi_management';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();
if ($conn === false || !mysqli_real_connect($conn, $host, $username, $password, $database, $port)) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    http_response_code(503);
    die(APP_DEBUG
        ? 'Database connection failed. Check DB_HOST, DB_PORT, DB_NAME, DB_USER, and DB_PASSWORD.'
        : 'The application is temporarily unavailable. Please check the database configuration.');
}
mysqli_set_charset($conn, 'utf8mb4');

require_once __DIR__ . '/security.php';
=======

// -----------------------------------------------------------
// Environment
// -----------------------------------------------------------

define('APP_DEBUG', true);

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
$password = "shivam@123";
$database = "taxi_management";

$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $database
);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}


// -----------------------------------------------------------
// Shared security helpers
// -----------------------------------------------------------

require_once __DIR__ . "/security.php";

?>
>>>>>>> 21d202221995cc0ce9792faca7d37b1ccb23b3b4

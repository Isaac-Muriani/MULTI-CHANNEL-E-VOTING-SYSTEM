<?php
// =============================================================
//  DATABASE CONFIGURATION
//  evoting/includes/config.php
// =============================================================

define('DB_HOST',     '127.0.0.1');
define('DB_USER',     'root');
define('DB_PASS',     '');           // change if you set a MySQL password
define('DB_NAME',     'evoting_db');
define('DB_PORT',     3306);

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Set character set
$conn->set_charset('utf8mb4');

// =============================================================
//  GLOBAL HELPERS
// =============================================================

/**
 * Sanitize input to prevent XSS
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Hash a password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify a password against its hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if voter is logged in
 */
function isVoterLoggedIn() {
    return isset($_SESSION['voter_id']);
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

/**
 * Get client IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

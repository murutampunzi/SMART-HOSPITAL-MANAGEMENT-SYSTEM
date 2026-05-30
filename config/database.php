<?php
// Security settings
define('SECURE', true);
define('TOKEN_LENGTH', 32);
define('HASH_COST', 12);

// Session settings - MUST be set before session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', SECURE);

    // Session settings must be set before any session activity
    session_start();
}

// Timezone
date_default_timezone_set('UTC');

// Database configuration
// Detect if running locally or in production environment
$is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || ($_SERVER['HTTP_HOST'] ?? '') === 'localhost';

if ($is_local) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'smart_hospital');
    define('DB_USER', 'root');
    define('DB_PASS', '44836059');
} else {
    // Production database configuration (InfinityFree)
    define('DB_HOST', 'sql301.infinityfree.com'); // Check your hosting control panel for the exact MySQL Hostname
    define('DB_NAME', 'if0_41934550_XXX');
    define('DB_USER', 'if0_41934550');
    define('DB_PASS', '44836059CSC');
}

// Create database connection
if ($is_local) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database if not exists (only allowed/supported on local development environment)
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $conn->select_db(DB_NAME);
} else {
    // Connect directly to the database in production.
    // Shared hosting databases must be pre-created through the control panel.
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Database connection failed. Please verify your DB_HOST and credentials in config/database.php. Error: " . $conn->connect_error);
    }
}

// Set charset
$conn->set_charset("utf8mb4");

// Application settings
define('APP_NAME', 'Smart Hospital Management System');
define('APP_VERSION', '1.0.0');
define('SUPPORT_EMAIL', 'admin@shms.com');

// Calculate base path relative to document root
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $project_root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    $base_path = str_replace($doc_root, '', $project_root);
    $base_path = '/' . trim($base_path, '/') . '/';
    $base_path = str_replace('//', '/', $base_path);
    define('BASE_PATH', $base_path);
} else {
    define('BASE_PATH', '/SMART/');
}

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif']);

// Pagination
define('ITEMS_PER_PAGE', 10);

// API settings
define('API_TIMEOUT', 30);

// Error reporting
error_reporting(E_ALL);
if (SECURE) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
} else {
    ini_set('display_errors', 1);
}

// Custom error handler
function customError($errno, $errstr, $errfile, $errline) {
    $error_message = "Error [$errno]: $errstr in $errfile on line $errline";
    error_log($error_message);
    
    if (!SECURE) {
        echo "<div class='alert alert-danger'>$error_message</div>";
    }
}

set_error_handler("customError");

// Include functions
require_once __DIR__ . '/../includes/functions.php';


// Security headers
if (SECURE) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// CSRF token generation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

// CSRF token validation
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Auto include CSRF token in forms
function getCSRFInput() {
    $token = generateCSRFToken();
    return "<input type='hidden' name='csrf_token' value='$token'>";
}

// Database helper functions
function escape($string) {
    global $conn;
    return $conn->real_escape_string($string);
}

function query($sql) {
    global $conn;
    $result = $conn->query($sql);
    if (!$result) {
        error_log("SQL Error: " . $conn->error . " Query: " . $sql);
        return false;
    }
    return $result;
}

function fetchAssoc($result) {
    return $result->fetch_assoc();
}

function numRows($result) {
    return $result->num_rows;
}

function insertId() {
    global $conn;
    return $conn->insert_id;
}

function prepare($sql) {
    global $conn;
    return $conn->prepare($sql);
}

// Close connection on script end
register_shutdown_function(function() {
    global $conn;
    if ($conn) {
        $conn->close();
    }
});
?>

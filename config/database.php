<?php
/**
 * Database Configuration
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'scan');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('SITE_NAME', 'TechDetector');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST']);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple database connection function
function getDbConnection() {
    static $pdo;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Helper function for queries
function query($sql, $params = []) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Helper function to get single row
function fetch($sql, $params = []) {
    return query($sql, $params)->fetch();
}

// Helper function to get all rows
function fetchAll($sql, $params = []) {
    return query($sql, $params)->fetchAll();
}

// Helper function to insert data
function insert($table, $data) {
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    
    $pdo = getDbConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    return $pdo->lastInsertId();
}

// Helper function to update data
function update($table, $data, $where, $whereParams = []) {
    $set = [];
    foreach (array_keys($data) as $key) {
        $set[] = "$key = :$key";
    }
    $setClause = implode(', ', $set);
    
    $sql = "UPDATE $table SET $setClause WHERE $where";
    $params = array_merge($data, $whereParams);
    
    return query($sql, $params)->rowCount();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $db = db();
        return $db->fetch(
            "SELECT id, username, email, created_at, last_login, is_admin 
             FROM users 
             WHERE id = ?", 
            [$_SESSION['user_id']]
        );
    } catch (Exception $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

// Require login for protected pages
function requireLogin($redirectTo = 'login.php') {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $redirectTo);
        exit();
    }
}

// Redirect if already logged in
function redirectIfLoggedIn($location = 'dashboard.php') {
    if (isLoggedIn()) {
        header("Location: $location");
        exit();
    }
}

// Check if user is admin
function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['is_admin'] == 1;
}

// Require admin access
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        $_SESSION['error'] = 'You do not have permission to access this page.';
        header('Location: dashboard.php');
        exit();
    }
}

// Set flash message
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Get and clear flash message
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Generate CSRF token
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Generate a secure random string
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length));
}

// Sanitize output
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Redirect helper
function redirect($url) {
    header("Location: $url");
    exit();
}
?>

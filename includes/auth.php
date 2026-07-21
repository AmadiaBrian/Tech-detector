<?php
/**
 * Authentication and User Management
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/verification.php';

/**
 * Register a new user
 * 
 * @param string $username (optional) If not provided, will use the email's local part
 * @param string $email
 * @param string $password
 * @return array [success, message/errors]
 */
function registerUser($username, $email, $password) {
    // Validate input
    $errors = [];
    
    // Generate username from email (first part before @)
    $username = strstr($email, '@', true);
    // Clean up the username to be URL-safe
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
    // Ensure it's not empty after cleaning
    if (empty($username)) {
        $username = 'user' . time();
    }
    
    // Make sure the username is unique
    $baseUsername = $username;
    $counter = 1;
    while (fetch("SELECT id FROM users WHERE username = ?", [$username])) {
        $username = $baseUsername . $counter;
        $counter++;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!empty($errors)) {
        return [false, $errors];
    }
    
    try {
        // Check if email already exists
        $existingEmail = fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existingEmail) {
            return [false, ['email' => 'Email already registered']];
        }
        
        // Hash the password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate email verification code (6 digits)
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Insert the new user
        $userId = insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'verification_code' => $verificationCode,
            'verification_expires' => $verificationExpires,
            'verification_sent_at' => date('Y-m-d H:i:s'),
            'is_verified' => 0, // Email not verified yet
            'created_at' => date('Y-m-d H:i:s'),
            'is_admin' => 0 // Default to not admin
        ]);
        
        // For testing: Log verification code to file
        $logMessage = date('Y-m-d H:i:s') . " - Verification code for $email: $verificationCode\n";
        file_put_contents(__DIR__ . '/verification_codes.log', $logMessage, FILE_APPEND);
        
        // Send verification email
        require_once __DIR__ . '/verification.php';
        $emailSent = sendVerificationEmail($email, $verificationCode, $username);
        
        if (!$emailSent) {
            // Log email sending failure
            $errorLog = date('Y-m-d H:i:s') . " - Failed to send verification email to: $email\n";
            file_put_contents(__DIR__ . '/email_errors.log', $errorLog, FILE_APPEND);
            // Continue anyway as the user can request a new verification email
        }
        
        // Don't log in the user yet - they need to verify their email first
        return [true, "Registration successful! Please check your email to verify your account."];
        
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        return [false, ["An error occurred during registration. Please try again."]];
    }
}

/**
 * Login user
 * 
 * @param string $username Username or email
 * @param string $password User's password
 * @param bool $remember Whether to remember the user
 * @return array [success, message/errors]
 */
function loginUser($username, $password, $remember = false) {
    // Enable error logging to file
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    $logFile = __DIR__ . '/../error_log';
    ini_set('error_log', $logFile);
    
    // Clear the error log at the start of each login attempt
    if (file_exists($logFile) && is_writable($logFile)) {
        file_put_contents($logFile, '');
    }
    
    error_log("\n=== Login attempt started ===");
    error_log("Login function called with username: " . $username);
    error_log("PHP Version: " . phpversion());
    error_log("Current directory: " . __DIR__);
    
    try {
        // Include database configuration
        require_once __DIR__ . '/../config/database.php';
        
        // First try to find user by username
        $query = "SELECT id, username, email, password_hash, is_verified FROM users WHERE username = ?";
        $user = fetch($query, [$username]);
        
        // If not found by username, try email
        if (!$user) {
            $query = "SELECT id, username, email, password_hash, is_verified FROM users WHERE email = ?";
            $user = fetch($query, [$username]);
        }
        
        error_log("User lookup completed. User found: " . ($user ? 'Yes' : 'No'));
        
        if ($user) {
            error_log("User data: " . print_r($user, true));
            
            // Check if password hash is valid
            if (!isset($user['password_hash']) || empty($user['password_hash'])) {
                $error = "No password hash found for user";
                error_log($error);
                return [false, [$error], $user];
            }
            
            $passwordMatch = password_verify($password, $user['password_hash']);
            error_log("Password verification: " . ($passwordMatch ? 'Success' : 'Failed'));
            error_log("Account verified: " . ($user['is_verified'] ? 'Yes' : 'No'));
            
            if ($passwordMatch) {
                // Check if account is verified
                if (empty($user['is_verified'])) {
                    $message = "Your account is not verified. Please check your email for the verification link or <a href='../register/resend-verification?email=" . urlencode($user['email']) . "'>resend verification email</a>.";
                    error_log("Login failed: Account not verified");
                    return [false, [$message], $user];
                }
                
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
                
                // Update last login
                update('users', 
                    ['last_login' => date('Y-m-d H:i:s')], 
                    'id = :id', 
                    ['id' => $user['id']]
                );
                
                // Handle remember me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    // Store token in database
                    insert('user_sessions', [
                        'user_id' => $user['id'],
                        'token' => $token,
                        'expires_at' => $expires,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    // Set cookie (30 days)
                    setcookie(
                        'remember_token',
                        $token,
                        time() + (30 * 24 * 60 * 60),
                        '/',
                        '',
                        isset($_SERVER['HTTPS']),
                        true // HttpOnly
                    );
                }
                
                return [true, "Login successful!", $user];
            }
        }
        
        return [false, ["Invalid username/email or password"], []];
        
    } catch (Exception $e) {
        // Log detailed error information
        $errorMessage = "\n=== LOGIN ERROR ===\n";
        $errorMessage .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $errorMessage .= "Error: " . $e->getMessage() . "\n";
        $errorMessage .= "File: " . $e->getFile() . "\n";
        $errorMessage .= "Line: " . $e->getLine() . "\n";
        $errorMessage .= "Code: " . $e->getCode() . "\n";
        $errorMessage .= "\nStack Trace:\n" . $e->getTraceAsString() . "\n";
        
        // Add request information
        $errorMessage .= "\n=== REQUEST DATA ===\n";
        $errorMessage .= "POST Data: " . print_r($_POST, true) . "\n";
        $errorMessage .= "SERVER Data: " . print_r([
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'N/A',
            'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
            'HTTP_REFERER' => $_SERVER['HTTP_REFERER'] ?? 'N/A'
        ], true) . "\n";
        
        // Log the error
        error_log($errorMessage);
        
        // Log the last SQL query if available
        if (isset($db)) {
            try {
                if (method_exists($db, 'getLastQuery')) {
                    $lastQuery = $db->getLastQuery();
                    error_log("Last SQL query: " . $lastQuery);
                }
                
                // Try to get PDO error info if available
                if (method_exists($db, 'getPdo') && $pdo = $db->getPdo()) {
                    $errorInfo = $pdo->errorInfo();
                    if (!empty($errorInfo[2])) {
                        error_log("PDO Error: " . print_r($errorInfo, true));
                    }
                }
            } catch (Exception $dbError) {
                error_log("Error while trying to get database error info: " . $dbError->getMessage());
            }
        }
        
        // For debugging, you might want to see the actual error in development
        if (in_array(strtolower(ini_get('display_errors')), ['1', 'on', 'true'])) {
            return [false, ["Login Error: " . $e->getMessage()], []];
        }
        
        return [false, ["An error occurred during login. Please try again."], []];
    }
}

/**
 * Logout the current user
 */
function logoutUser() {
    // Delete remember me token if exists
    if (isset($_COOKIE['remember_token'])) {
        delete('user_sessions', 'token = ?', [$_COOKIE['remember_token']]);
        
        // Delete the cookie
        setcookie('remember_token', '', time() - 3600, '/');
        unset($_COOKIE['remember_token']);
    }
    
    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

/**
 * Save scan to user's history
 * 
 * @param int $userId
 * @param string $url
 * @param array $scanData
 * @return int|bool Scan ID or false on failure
 */
function saveScanToHistory($userId, $url, $scanData) {
    try {
        return insert('scan_history', [
            'user_id' => $userId,
            'url' => $url,
            'scan_data' => json_encode($scanData),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Error saving scan history: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user's scan history
 * 
 * @param int $userId
 * @param int $limit Number of results to return
 * @return array
 */
function getUserScanHistory($userId, $limit = 10) {
    try {
        $scans = fetchAll(
            "SELECT id, url, created_at, scan_data 
             FROM scan_history 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ?", 
            [$userId, $limit]
        );
        
        // Decode the scan data
        if (is_array($scans)) {
            foreach ($scans as &$scan) {
                $scan['scan_data'] = json_decode($scan['scan_data'], true);
            }
        }
        
        return $scans;
        
    } catch (Exception $e) {
        error_log("Error getting scan history: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if user has a valid remember me token
 * 
 * @return bool|int User ID if valid, false otherwise
 */
function checkRememberMe() {
    if (!empty($_COOKIE['remember_token'])) {
        try {
            // Include database configuration
            require_once __DIR__ . '/../config/database.php';
            
            $session = fetch(
                "SELECT user_id FROM user_sessions 
                 WHERE token = ? AND expires_at > NOW()",
                [$_COOKIE['remember_token']]
            );
            
            if ($session) {
                // Log the user in
                $_SESSION['user_id'] = $session['user_id'];
                
                // Update session data
                update('user_sessions', 
                    [
                        'last_used' => date('Y-m-d H:i:s'),
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ],
                    'token = ?',
                    [$_COOKIE['remember_token']]
                );
                
                return $session['user_id'];
            }
            
        } catch (Exception $e) {
            error_log("Remember me error: " . $e->getMessage());
        }
    }
    
    return false;
}

/**
 * Check if the current request is authenticated
 * 
 * @return bool|array User data if authenticated, false otherwise
 */
function checkAuth() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include database configuration
    require_once __DIR__ . '/../config/database.php';
    
    // Check if user is logged in via session
    if (isset($_SESSION['user_id'])) {
        $user = fetch('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
        if ($user) {
            return $user;
        }
    }
    
    // Check remember me token
    if (($userId = checkRememberMe()) !== false) {
        $user = fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        if ($user) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            return $user;
        }
    }
    
    return false;
}

// Check authentication on each request
checkAuth();
?>

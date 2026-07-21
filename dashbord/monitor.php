<?php
// monitor.php - handles website monitoring with full scan

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/monitor_errors.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/lib.php';
require_once __DIR__ . '/../api/analysis_functions.php';
require_once __DIR__ . '/../includes/website_monitor_functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Function to redirect with message
function redirectWithMessage($success, $message) {
    $_SESSION['monitor_success'] = $success;
    $_SESSION['monitor_message'] = $message;
    header('Location: ../dashbord');
    exit;
}

// Handle direct form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['monitor_submit'])) {
    try {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            throw new Exception('You must be logged in to monitor a website');
        }

        $userId = (int)$_SESSION['user_id'];
        $url = trim($_POST['url'] ?? '');
        
        // Validate URL
        if (empty($url)) {
            throw new Exception('Please enter a URL');
        }

        // Normalize URL
        $url = normalizeUrl($url);
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception('Please enter a valid URL (e.g., https://example.com)');
        }

        // Block private/reserved IPs
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || isPrivateHost($host)) {
            throw new Exception('Cannot monitor private or reserved IP addresses');
        }
        
        $db = Database::getInstance();
        
        // Check if already being monitored by this user
        $existing = $db->fetch(
            "SELECT id FROM website_monitors WHERE user_id = ? AND LOWER(url) = LOWER(?)",
            [$userId, $url]
        );
        
        if ($existing) {
            throw new Exception('This website is already being monitored');
        }
        
        // Add to monitoring
        $result = $db->insert('website_monitors', [
            'user_id' => $userId,
            'url' => $url,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$result) {
            throw new Exception('Failed to add website to monitoring. Please try again.');
        }
        
        // Log successful addition
        error_log("Successfully added website to monitoring. ID: $result, URL: $url");
        
        // Redirect with success message
        redirectWithMessage(true, 'Website added to monitoring successfully!');
        
    } catch (Exception $e) {
        // Log the error
        error_log('Error in monitor.php: ' . $e->getMessage());
        
        // Redirect with error message
        redirectWithMessage(false, $e->getMessage());
    }
}

// If not a POST request or invalid action, redirect to dashboard
header('Location: ../dashbord');
exit;

// Function to send JSON response
function sendResponse($success, $message = '', $data = []) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data) $response = array_merge($response, $data);
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    sendResponse(false, 'Unauthorized');
}

$userId = (int)$_SESSION['user_id'];
$db = Database::getInstance();

// Log the incoming request
error_log('[' . date('Y-m-d H:i:s') . '] Request received: ' . print_r($_REQUEST, true));

// Handle remove action
if (isset($_POST['action']) && $_POST['action'] === 'remove' && !empty($_POST['monitor_id'])) {
    $monitorId = (int)$_POST['monitor_id'];
    
    try {
        // Verify ownership
        $monitor = $db->fetch(
            "SELECT * FROM website_monitors WHERE id = ? AND user_id = ?", 
            [$monitorId, $userId]
        );
        
        if (!$monitor) {
            throw new Exception('Monitor not found or access denied');
        }
        
        // Remove the monitor
        $result = $db->delete('website_monitors', 'id = ?', [$monitorId]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Website removed from monitoring']);
        } else {
            throw new Exception('Failed to remove website from monitoring');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle add website
try {
    // Get and validate URL
    $url = trim($_POST['url'] ?? '');
    if (empty($url)) {
        throw new Exception('Please enter a URL');
    }

    // Normalize URL
    $url = normalizeUrl($url);
    
    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new Exception('Please enter a valid URL (e.g., https://example.com)');
    }

    // Block private/reserved IPs
    $host = parse_url($url, PHP_URL_HOST);
    if ($host === false || isPrivateHost($host)) {
        throw new Exception('Cannot monitor private or reserved IP addresses');
    }
    
    // Check if already being monitored by this user
    $existing = $db->fetch(
        "SELECT id FROM website_monitors WHERE user_id = ? AND LOWER(url) = LOWER(?)",
        [$userId, $url]
    );
    
    if ($existing) {
        throw new Exception('This website is already being monitored.');
    }
    
    // Begin transaction for data integrity
    $db->beginTransaction();
    
    try {
        // Add to monitoring
        $result = $db->insert('website_monitors', [
            'user_id' => $userId,
            'url' => $url,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$result) {
            throw new Exception('Failed to add website to monitoring. Database error.');
        }
        
        // Get the inserted monitor
        $monitor = $db->fetch("SELECT * FROM website_monitors WHERE id = ?", [$result]);
        
        if (!$monitor) {
            throw new Exception('Failed to retrieve the added monitor.');
        }
        
        // Commit the transaction
        $db->commit();
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => 'Website added to monitoring successfully',
            'monitor' => [
                'id' => $monitor['id'],
                'url' => $monitor['url'],
                'is_active' => $monitor['is_active'],
                'created_at' => $monitor['created_at'],
                'updated_at' => $monitor['updated_at']
            ]
        ];
        
        // Log successful addition
        error_log("Successfully added website to monitoring. ID: {$monitor['id']}, URL: $url");
        
        // For FastCGI servers, we can close the connection and continue processing
        if (function_exists('fastcgi_finish_request')) {
            session_write_close();
            fastcgi_finish_request();
        }
        
        // Start background scan in a separate process if needed
        if (file_exists(__DIR__ . '/background_scan.php')) {
            $logFile = __DIR__ . '/logs/scan_' . $monitor['id'] . '_' . time() . '.log';
            $cmd = sprintf(
                'php %s %d > %s 2>&1 &',
                escapeshellarg(__DIR__ . '/background_scan.php'),
                $monitor['id'],
                escapeshellarg($logFile)
            );
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                pclose(popen('start /B ' . $cmd, 'r'));
            } else {
                exec($cmd . ' > /dev/null &');
            }
        }
        
        // Return the response
        sendResponse(true, 'Website added to monitoring successfully', [
            'monitor' => $response['monitor']
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Error in monitor.php: ' . $e->getMessage() . '\n' . $e->getTraceAsString());
    http_response_code(400);
    sendResponse(false, $e->getMessage());
}

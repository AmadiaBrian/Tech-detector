<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/website_monitor_functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get current user from session
$user = fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

// Store user data in session for easy access
if ($user) {
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'] ?? explode('@', $user['email'])[0];
}

// Allowed routes
$allowed_routes = [
    'overview',
    'profile',
    'settings',
    'link-analyzer',
    'domain-check',
    'image-metadata',
    'view-source',
    'domain-dashboard'
];

// Get route from query parameter or URL path
$route = $_GET['route'] ?? 'overview';

// If accessing via clean URL (e.g., /dashbord/profile), extract route from path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/dashbord/', '', $path);
$path = trim($path, '/');

if (!empty($path) && $path !== 'index.php') {
    $route = $path;
}

// Route to appropriate page
if (in_array($route, $allowed_routes)) {
    switch ($route) {
        case 'overview':
            // Default dashboard overview - continue with this file
            break;
        case 'profile':
            require_once __DIR__ . '/profile.php';
            exit;
        case 'settings':
            require_once __DIR__ . '/settings.php';
            exit;
        case 'link-analyzer':
            require_once __DIR__ . '/link-analyzer.php';
            exit;
        case 'domain-check':
            require_once __DIR__ . '/domain-check.php';
            exit;
        case 'image-metadata':
            require_once __DIR__ . '/image-metadata.php';
            exit;
        case 'view-source':
            require_once __DIR__ . '/view_source.php';
            exit;
        case 'domain-dashboard':
            require_once __DIR__ . '/domain-dashboard/index.php';
            exit;
    }
} else {
    // Invalid route, default to overview
    $route = 'overview';
}

// Handle view source request (legacy support)
if (isset($_GET['action']) && $_GET['action'] === 'view_source' && isset($_GET['url'])) {
    // Decode the URL first
    $sourceUrl = urldecode($_GET['url']);
    
    // Ensure URL is properly formatted
    if (!preg_match('~^https?://~i', $sourceUrl)) {
        $sourceUrl = 'https://' . $sourceUrl;
    }
    
    // Validate URL
    if (!filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
        die('Invalid URL provided');
    }
    
    $sourceResult = fetchCompletePageSource($sourceUrl);
    
    if ($sourceResult['success']) {
        // Output buffering to prevent any accidental output
        ob_clean();
        
        // Set proper headers
        
        // Start output
        ?><!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Source: <?php echo htmlspecialchars($sourceUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></title>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism-tomorrow.min.css" rel="stylesheet" />
            <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/prism.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-markup.min.js"></script>
            <style>
{{ ... }}
                body { margin: 0; padding: 20px; background: #2d2d2d; color: #f8f8f2; }
                pre { margin: 0; }
                .toolbar { position: fixed; top: 0; right: 0; padding: 10px; background: #2d2d2d; z-index: 1000; }
                button {
                    padding: 8px 15px;
                    margin-left: 10px;
                    cursor: pointer;
                    background: #ff6b00;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    font-size: 14px;
                }
                button:hover { background: #e65a00; }
                .code-container { 
                    margin-top: 50px; 
                    background: #1e1e1e; 
                    padding: 20px; 
                    border-radius: 4px; 
                    overflow-x: auto;
                }
                .source-url {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    background: #2d2d2d;
                    padding: 10px 20px;
                    border-bottom: 1px solid #444;
                    font-family: monospace;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
            </style>
        </head>
        <body>
            <div class="source-url">
                Source: <?php echo htmlspecialchars($sourceUrl); ?>
                <div class="toolbar">
                    <button onclick="copyToClipboard()">Copy to Clipboard</button>
                    <button onclick="window.close()">Close</button>
                </div>
            </div>
            <div class="code-container">
                <?php
                // Properly escape the HTML for display
                $escapedHtml = htmlspecialchars($sourceResult['html'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                ?>
                <pre><code class="language-html"><?php echo $escapedHtml; ?></code></pre>
            </div>
            <script>
                function copyToClipboard() {
                    const el = document.createElement("textarea");
                    el.value = <?php echo json_encode($sourceResult['html']); ?>;
                    document.body.appendChild(el);
                    el.select();
                    document.execCommand("copy");
                    document.body.removeChild(el);
                    
                    // Show feedback
                    const button = document.querySelector('button:first-child');
                    const originalText = button.textContent;
                    button.textContent = 'Copied!';
                    button.style.background = '#10b981';
                    
                    setTimeout(() => {
                        button.textContent = originalText;
                        button.style.background = '';
                    }, 2000);
                }
                
                // Auto-copy to clipboard after a short delay
                setTimeout(copyToClipboard, 500);
            </script>
        </body>
        </html>
        <?php
        exit;
    } else {
        $errorMessage = 'Failed to fetch source: ' . ($sourceResult['error'] ?? 'Unknown error');
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle scan website form submission
    if (isset($_POST['scan_website'])) {
        $url = trim($_POST['scan_url'] ?? '');
        
        if (empty($url)) {
            $errorMessage = 'Please enter a URL to scan';
        } else {
            // Add https:// if missing
            if (!preg_match('~^https?://~i', $url)) {
                $url = 'https://' . $url;
            }
            
            // Validate URL format
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errorMessage = 'Please enter a valid URL (e.g., https://example.com)';
            } else {
                // Block private/reserved IPs
                $host = parse_url($url, PHP_URL_HOST);
                if ($host === false || isPrivateHost($host)) {
                    $errorMessage = 'Cannot scan private or reserved IP addresses';
                } else {
                    // If we get here, redirect to scan.php
                    header('Location: scan.php?url=' . urlencode($url));
                    exit;
                }
            }
        }
    } 
    // Handle monitor website form submission
    else if (isset($_POST['monitor_website'])) {
        $url = trim($_POST['website_url'] ?? '');
        
        // Debug: Log the form submission
        error_log("Monitor website form submitted. URL: $url, User ID: " . ($user['id'] ?? 'not set'));
        
        if (empty($url)) {
            $errorMessage = 'Please enter a URL to monitor';
        } else {
            // Clean and validate the URL
            $url = filter_var($url, FILTER_SANITIZE_URL);
            
            // Add https:// if missing
            if (!preg_match('~^https?://~i', $url)) {
                $url = 'https://' . $url;
            }
            
            // Validate URL format
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errorMessage = 'Please enter a valid URL (e.g., https://example.com)';
            } else {
                // Block private/reserved IPs
                $host = parse_url($url, PHP_URL_HOST);
                if ($host === false || isPrivateHost($host)) {
                    $errorMessage = 'Cannot monitor private or reserved IP addresses';
                } else {
                    // Add the website to monitoring
                    $result = addWebsiteToMonitor($user['id'], $url);
                    
                    if ($result['success']) {
                        $successMessage = $result['message'];
                        // Refresh the monitored websites list
                        $monitoredWebsites = getUserMonitoredWebsites($user['id']);
                    } else {
                        $errorMessage = $result['message'];
                    }
                }
            }
        }
    } elseif (isset($_POST['remove_website'])) {
        $monitorId = filter_input(INPUT_POST, 'monitor_id', FILTER_VALIDATE_INT);
        if ($monitorId) {
            $result = removeWebsiteFromMonitor($user['id'], $monitorId);
            if ($result['success']) {
                $successMessage = 'Website removed from monitoring';
            } else {
                $errorMessage = $result['message'];
            }
        }
    }
}

// Get user's monitored websites
$monitoredWebsites = getUserMonitoredWebsites($user['id']);
$monitoringStats = getMonitoringStats($user['id']);

// START OF CORRECTION: Check if user was successfully fetched.
if (!$user) {
    // User ID in session is invalid or user was deleted.
    // Invalidate the session and redirect to login.
    unset($_SESSION['user_id']);
    header('Location: login.php');
    exit;
}
// END OF CORRECTION

// Get user's scan history
$scanHistory = [];
// Check if scan_history table exists and get data
$tableCheck = fetch("SHOW TABLES LIKE 'scan_history'");
if ($tableCheck) {
    $scanHistory = fetchAll(
        "SELECT * FROM scan_history WHERE user_id = ? ORDER BY created_at DESC", 
        [$_SESSION['user_id']]
    );
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-black">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TechDetector</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/themes/prism-tomorrow.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/ntsa-style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Mobile Menu */
        #sidebar {
            transition: transform 0.3s ease-in-out;
        }
        #sidebar.show {
            transform: translateX(0) !important;
        }
        
        /* Professional Hamburger Menu Button - Hidden on desktop */
        .hamburger {
            display: none; /* Hidden by default */
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: 44px;
            height: 44px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            padding: 10px;
            z-index: 1000;
            position: fixed;
            top: 20px;
            right: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Hide hamburger on mobile */
        @media (max-width: 767px) {
            .hamburger {
                display: none;
            }
        }
        
        .hamburger:hover {
            background: #f9fafb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transform: translateY(-1px);
        }
        
        .hamburger:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .hamburger-line {
            display: block;
            width: 20px;
            height: 2px;
            background-color: #374151; /* gray-700 */
            border-radius: 2px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .hamburger-line:not(:last-child) {
            margin-bottom: 4px;
        }
        
        .hamburger:hover .hamburger-line {
            background-color: #1f2937; /* gray-800 */
        }
        
        /* Animated X when active */
        .hamburger.active .hamburger-line:nth-child(1) {
            transform: translateY(6px) rotate(45deg);
        }
        
        .hamburger.active .hamburger-line:nth-child(2) {
            opacity: 0;
            transform: translateX(-10px);
        }
        
        .hamburger.active .hamburger-line:nth-child(3) {
            transform: translateY(-6px) rotate(-45deg);
        }
        
        /* Smooth transition for active state */
        .hamburger.active {
            background: #f3f4f6;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 90;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .overlay.active {
            display: block;
            opacity: 1;
        }
        
        @media (max-width: 992px) {
            .hamburger {
                display: none;
            }
            
            #sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                z-index: 100;
            }
            
            #sidebar.mobile-active {
                transform: translateX(0);
            }
            
            .gsc-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                position: fixed;
                left: 0;
                top: 0;
                height: 100vh;
                z-index: 1000;
            }
            
            .gsc-sidebar.gsc-sidebar-mobile-open {
                transform: translateX(0);
            }
            
            .gsc-main-wrapper {
                margin-left: 0;
            }
            
            .gsc-topbar {
                display: flex;
            }
            
            .gsc-sidebar-toggle {
                display: flex !important;
                z-index: 1002;
            }
            
            body.menu-open {
                overflow: hidden;
            }
        }
        
        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 2147483647; /* Maximum z-index */
            opacity: 1;
            visibility: visible;
            transition: opacity 0.3s;
            pointer-events: auto;
        }
        
        .loading-overlay.hidden {
            opacity: 0;
            pointer-events: none;
        }
            font-weight: 500;
            text-align: center;
            max-width: 300px;
            line-height: 1.4;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Prevent scrolling when loading */
        body.loading {
            overflow: hidden !important;
            height: 100vh !important;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        },
                        success: {
                            50: '#f0fdf4',
                            500: '#10b981',
                            600: '#059669',
                        },
                        warning: {
                            50: '#fffbeb',
                            500: '#f59e0b',
                            600: '#d97706',
                        },
                        danger: {
                            50: '#fef2f2',
                            500: '#ef4444',
                            600: '#dc2626',
                        },
                        info: {
                            50: '#ecfdf5',
                            500: '#10b981',
                            600: '#059669',
                        },
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    boxShadow: {
                        card: '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
                        'card-hover': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
                    },
                },
            },
        }
    </script>
    <style>
        :root {
            --primary: #ff6b00;
            --primary-dark: #e65a00;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-700: #374151;
            --gray-900: #111827;
            --light: #000000;
            --dark-bg: #000000;
            --dark-card: #000000;
            --dark-text: #ffffff;
            --dark-border: #3c4043;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000000;
            color: #ffffff;
            margin: 0;
            padding: 0;
            line-height: 1.5;
            transition: background-color 0.3s, color 0.3s;
        }
        
        /* Dark mode styles */
        .dark body {
            background-color: #000000;
            color: #ffffff;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .header {
            background-color: #000000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            transition: background-color 0.3s;
            border-bottom: 1px solid #3c4043;
        }
        .dark .header {
            background-color: #000000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            border-bottom: 1px solid #3c4043;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background-color: var(--primary);
            color: white;
            border: 1px solid var(--primary);
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .main-content {
            padding: 2rem 0;
        }
        .card {
            background: #000000;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: background-color 0.3s, box-shadow 0.3s;
            border: 1px solid #3c4043;
        }
        .dark .card {
            background-color: #000000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            border: 1px solid #3c4043;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            transition: border-color 0.3s;
        }
        .dark .card-header {
            border-bottom-color: var(--dark-border);
        }
        .card-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            transition: color 0.3s;
        }
        .dark .card-title {
            color: var(--dark-text);
        }
        table {
            width: 100%;
            color: var(--gray-900);
            transition: color 0.3s;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            transition: border-color 0.3s, background-color 0.3s, color 0.3s;
        }
        
        th {
            background-color: var(--gray-100);
            font-weight: 500;
            color: var(--gray-700);
            text-transform: uppercase;
            font-size: 0.75rem;
        }
        
        tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        /* Dark mode table styles */
        .dark table {
            color: var(--dark-text);
        }
        
        .dark th,
        .dark td {
            border-bottom-color: var(--dark-border);
        }
        
        .dark th {
            background-color: var(--dark-card);
            color: var(--dark-text);
        }
        
        .dark tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .no-scans {
            text-align: center;
            padding: 2rem;
            color: var(--gray-500);
            transition: color 0.3s;
        }
        
        .dark .no-scans {
            color: var(--gray-400);
        }
        }
        .sidebar {
            width: 250px;
            background-color: #000000;
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding-top: 4rem;
            border-right: 1px solid #3c4043;
        }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }
        .nav-link {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #d1d5db;
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active {
            background-color: #1a1a1a;
            color: white;
        }
        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
            color: #ff6b00;
        }
        .sidebar-item.active {
            background-color: #1a1a1a;
            border-left: 3px solid #ff6b00;
        }
        .sidebar-item:hover:not(.active) {
            background-color: #1a1a1a;
        }
        .status-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 9999px;
            font-weight: 500;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .transition-all {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-black font-sans antialiased">
    <!-- Hamburger Menu Button -->
    <button class="hamburger" id="hamburger" aria-label="Menu" aria-expanded="false">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
    </button>
    
    <!-- Overlay for mobile menu -->
    <div class="overlay"></div>

    <!-- Google Search Console-style Sidebar -->
    <aside class="gsc-sidebar" id="gscSidebar">
        <div class="gsc-sidebar-header">
            <a href="../index.php" class="gsc-logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </a>
        </div>
        
        <nav class="gsc-sidebar-nav">
            <div class="gsc-nav-section">
                <div class="gsc-nav-section-title">Dashboard</div>
                <a href="overview" class="gsc-nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Overview</span>
                </a>
                <a href="domain-check" class="gsc-nav-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Domain Check</span>
                </a>
                <a href="link-analyzer" class="gsc-nav-item">
                    <i class="fas fa-link"></i>
                    <span>Link Analyzer</span>
                </a>
            </div>
            
            <div class="gsc-nav-section">
                <div class="gsc-nav-section-title">Account</div>
                <a href="profile" class="gsc-nav-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="settings" class="gsc-nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="../api/logout" class="gsc-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="gsc-main-wrapper">
        <!-- Desktop/Mobile Top Bar -->
        <header class="gsc-topbar">
            <button class="gsc-sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <a href="overview" class="gsc-topbar-logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </a>
        </header>

        <!-- Page Content -->
        <div class="gsc-content-wrapper">
            <!-- Domain Check Section -->
            <div id="domain-check-section" class="bg-black rounded-lg shadow overflow-hidden mb-8 border border-[#3c4043]">
                <div class="px-6 py-5 border-b border-[#3c4043]">
                    <h3 class="text-lg leading-6 font-medium text-white">
                        <i class="fas fa-globe mr-2 text-[#ff6b00]"></i> Domain Check
                    </h3>
                </div>
                <div class="px-6 py-5">
                    <form id="domainCheckForm" method="POST" action="domain-check" class="space-y-4 js-domain-form">
                        <input type="hidden" name="check_domain" value="1">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="flex-grow">
                                <label for="domain" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Domain Name</label>
                                <input type="text" 
                                       id="domain" 
                                       name="domain" 
                                       required
                                       placeholder="example.com" 
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:text-white"
                                       pattern="^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$"
                                       title="Please enter a valid domain (e.g., example.com)">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="px-6 py-2 bg-[#ff6b00] text-white rounded-md hover:bg-[#e65a00] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#ff6b00] whitespace-nowrap h-[42px] flex items-center justify-center min-w-[120px]" id="checkDomainBtn">
                                    <span id="buttonText">
                                        <i class="fas fa-search mr-2"></i>Check Domain
                                    </span>
                                    <span id="buttonSpinner" class="hidden">
                                        <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Processing...
                                    </span>
                                </button>
                                <input type="hidden" name="redirect" value="dashboard">
                            </div>
                        </div>
                    </form>
                    
                    <!-- Toast Notification -->
                    <div id="toast" class="hidden fixed top-4 right-4 z-50">
                        <div class="bg-[#ff6b00] text-white px-4 py-3 rounded-lg shadow-lg flex items-center">
                            <span id="toastMessage"></span>
                            <button onclick="hideToast()" class="ml-4 text-white">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div id="domainCheckResult" class="mt-6 hidden">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white">Domain Analysis</h4>
                                <div class="flex items-center">
                                    <span id="domainStatusBadge" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium">
                                        <span id="domainStatusIcon" class="w-2 h-2 rounded-full mr-2"></span>
                                        <span id="domainStatusText">Checking...</span>
                                    </span>
                                </div>
                            </div>
                            
                            <div id="domainCheckLoading" class="text-center py-8">
                                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-500 mx-auto"></div>
                                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Analyzing domain. This may take a moment...</p>
                            </div>
                            
                            <div id="domainCheckContent" class="hidden">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Domain Information -->
                                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                                        <h5 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Domain Information</h5>
                                        <dl class="space-y-2">
                                            <div class="flex justify-between">
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Domain</dt>
                                                <dd id="domainName" class="text-sm text-gray-900 dark:text-gray-200">-</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                                <dd id="domainStatus" class="text-sm">-</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IP Address</dt>
                                                <dd id="domainIp" class="text-sm text-gray-900 dark:text-gray-200">-</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Registrar</dt>
                                                <dd id="domainRegistrar" class="text-sm text-gray-900 dark:text-gray-200">-</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                                                <dd id="domainCreated" class="text-sm text-gray-900 dark:text-gray-200">-</dd>
                                            </div>
                                            <div class="flex justify-between">
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expires</dt>
                                                <dd id="domainExpires" class="text-sm text-gray-900 dark:text-gray-200">-</dd>
                                            </div>
                                        </dl>
                                    </div>
                                    
                                    <!-- DNS Information -->
                                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                                        <h5 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">DNS Records</h5>
                                        <div class="space-y-3">
                                            <div>
                                                <h6 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Name Servers</h6>
                                                <ul id="nameServers" class="text-sm text-gray-900 dark:text-gray-200 space-y-1">
                                                    <li>Loading...</li>
                                                </ul>
                                            </div>
                                            <div>
                                                <h6 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">MX Records</h6>
                                                <ul id="mxRecords" class="text-sm text-gray-900 dark:text-gray-200 space-y-1">
                                                    <li>Loading...</li>
                                                </ul>
                                            </div>
                                            <div>
                                                <h6 class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">TXT Records</h6>
                                                <ul id="txtRecords" class="text-sm text-gray-900 dark:text-gray-200 space-y-1">
                                                    <li>Loading...</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Additional Information -->
                                <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                                    <h5 class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Additional Information</h5>
                                    <div id="additionalInfo" class="text-sm text-gray-900 dark:text-gray-200">
                                        Loading additional domain information...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($successMessage)): ?>
                <div class="mb-6 p-4 bg-[#ff6b00] bg-opacity-10 border border-[#ff6b00] rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-[#ff6b00]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-[#ff6b00]"><?php echo htmlspecialchars($successMessage); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage)): ?>
                <div class="mb-6 p-4 bg-[#ff6b00] bg-opacity-10 border border-[#ff6b00] rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-[#ff6b00]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-[#ff6b00]"><?php echo htmlspecialchars($errorMessage); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.24.1/components/prism-markup.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('gscSidebar');
        
        console.log('Sidebar toggle:', sidebarToggle);
        console.log('Sidebar:', sidebar);
        
        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Check if we're on mobile or desktop
                if (window.innerWidth <= 992) {
                    // Mobile: toggle mobile-open class
                    sidebar.classList.toggle('gsc-sidebar-mobile-open');
                    
                    if (sidebar.classList.contains('gsc-sidebar-mobile-open')) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                } else {
                    // Desktop: toggle collapsed class
                    sidebar.classList.toggle('gsc-sidebar-collapsed');
                }
            });
        }

        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('gscSidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (sidebar && sidebarToggle) {
                if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target) && sidebar.classList.contains('gsc-sidebar-mobile-open')) {
                    sidebar.classList.remove('gsc-sidebar-mobile-open');
                    document.body.style.overflow = '';
                }
            }
        });
    });
    </script>
</body>
</html>

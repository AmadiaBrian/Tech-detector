<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle settings update here
    $success = false;
    $message = '';
    
    // Example: Update user's timezone
    if (isset($_POST['timezone'])) {
        $timezone = filter_var($_POST['timezone'], FILTER_SANITIZE_STRING);
        query("UPDATE users SET timezone = ? WHERE id = ?", [$timezone, $user['id']]);
        $success = true;
        $message = 'Settings updated successfully!';
    }
    
    // Handle email preferences
    if (isset($_POST['email_notifications'])) {
        $email_notifications = $_POST['email_notifications'] === '1' ? 1 : 0;
        query("UPDATE users SET email_notifications = ? WHERE id = ?", [$email_notifications, $user['id']]);
        $success = true;
        $message = 'Notification preferences updated!';
    }
    
    // Handle password change
    if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        if (password_verify($_POST['current_password'], $user['password'])) {
            $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            query("UPDATE users SET password = ? WHERE id = ?", [$new_password_hash, $user['id']]);
            $success = true;
            $message = 'Password updated successfully!';
        } else {
            $success = false;
            $message = 'Current password is incorrect.';
        }
    }
    
    // Refresh user data
    $user = fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

// Get available timezones
$timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - TechDetector</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/ntsa-style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #000000;
            color: #ffffff;
        }
        .settings-section {
            background: #000000;
            border: 1px solid #3c4043;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .settings-section-header {
            padding: 1.25rem 1.5rem;
            background-color: #1a1a1a;
            border-bottom: 1px solid #3c4043;
            font-weight: 600;
            color: #ffffff;
        }
        .settings-section-body {
            padding: 1.5rem;
        }
        input, select {
            background-color: #1a1a1a;
            border-color: #3c4043;
            color: #ffffff;
        }
        input:focus, select:focus {
            border-color: #ff6b00;
            outline: none;
        }
        input:disabled {
            background-color: #2a2a2a;
            color: #6b7280;
        }
        label {
            color: #d1d5db;
        }
    </style>
</head>
<body class="bg-black font-sans antialiased">
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
                <a href="overview" class="gsc-nav-item">
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
                <a href="settings" class="gsc-nav-item active">
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
            <?php if (isset($message)): ?>
                <div class="mb-6 p-4 rounded-md bg-[#ff6b00] bg-opacity-10 border border-[#ff6b00] text-[#ff6b00]">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Account Settings -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="text-lg font-medium">Account Settings</h2>
                </div>
                <div class="settings-section-body">
                    <form method="POST" class="space-y-6">
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#ff6b00] focus:border-[#ff6b00]" disabled>
                            <p class="mt-1 text-xs text-gray-500">Username cannot be changed.</p>
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#ff6b00] focus:border-[#ff6b00]" disabled>
                            <p class="mt-1 text-xs text-gray-500">Contact support to change your email address.</p>
                        </div>

                        <div>
                            <label for="timezone" class="block text-sm font-medium text-gray-300 mb-1">Timezone</label>
                            <select id="timezone" name="timezone" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#ff6b00] focus:border-[#ff6b00]">
                                <?php foreach ($timezones as $tz): ?>
                                    <option value="<?php echo htmlspecialchars($tz); ?>" <?php echo ($user['timezone'] ?? 'UTC') === $tz ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tz); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pt-4 border-t border-[#3c4043]">
                            <button type="submit" class="px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-[#ff6b00] hover:bg-[#e65a00] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#ff6b00]">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="text-lg font-medium">Change Password</h2>
                </div>
                <div class="settings-section-body">
                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-300 mb-1">Current Password</label>
                            <input type="password" id="current_password" name="current_password" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#ff6b00] focus:border-[#ff6b00]" 
                                   placeholder="Enter your current password">
                        </div>

                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-300 mb-1">New Password</label>
                            <input type="password" id="new_password" name="new_password" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#ff6b00] focus:border-[#ff6b00]" 
                                   placeholder="Enter a new password">
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-[#ff6b00] hover:bg-[#e65a00] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#ff6b00]">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notification Preferences -->
            <div class="settings-section">
                <div class="settings-section-header">
                    <h2 class="text-lg font-medium">Notification Preferences</h2>
                </div>
                <div class="settings-section-body">
                    <form method="POST" class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="email_notifications" name="email_notifications" type="checkbox" 
                                       class="focus:ring-[#ff6b00] h-4 w-4 text-[#ff6b00] border-gray-300 rounded"
                                       <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?> value="1">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="email_notifications" class="font-medium text-gray-300">Email Notifications</label>
                                <p class="text-gray-500">Receive email notifications for important updates and alerts.</p>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-[#ff6b00] hover:bg-[#e65a00] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#ff6b00]">
                                Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </main>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('gscSidebar');
        
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

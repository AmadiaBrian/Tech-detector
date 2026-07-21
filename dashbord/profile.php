<?php
// Include necessary files
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login');
    exit;
}

// Get current user from session
$user = fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    $message = '';
    
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        // Use htmlspecialchars for output encoding
        $username = isset($_POST['username']) ? htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8') : '';
        $bio = isset($_POST['bio']) ? htmlspecialchars(trim($_POST['bio']), ENT_QUOTES, 'UTF-8') : '';
        
        // Update only the fields that exist in the users table
        $updateFields = [];
        $params = [];
        
        if (!empty($username)) {
            // Trim the username
            $username = trim($username);
            
            // Check if the new username is different from the current one
            if ($username !== $user['username']) {
                // Basic username validation - letters and spaces between names
                $username = trim($username); // Trim any leading/trailing spaces
                if (strlen($username) < 3) {
                    $message = 'Name must be at least 3 characters long.';
                    $success = false;
                } else if (!preg_match('/^[a-zA-Z]+(?: [a-zA-Z]+)*$/', $username)) {
                    $message = 'Name can only contain letters and single spaces between words.';
                    $success = false;
                } else {
                    $updateFields[] = 'username = ?';
                    $params[] = $username;
                }
            }
        }
        
        if (!empty($bio)) {
            $updateFields[] = 'bio = ?';
            $params[] = $bio;
        }
        
        if (!empty($updateFields)) {
            try {
                $params[] = $user['id'];
                $query = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                
                // Get database instance and execute the update query
                $db = Database::getInstance();
                $stmt = $db->getConnection()->prepare($query);
                $stmt->execute($params);
                
                // Update the session username if it was changed
                if (in_array('username = ?', $updateFields)) {
                    $_SESSION['username'] = $username;
                    // Also update the current user data
                    $user['username'] = $username;
                }
                
                $message = 'Profile updated successfully!';
                $success = true;
                
                // Force a page reload to ensure all data is fresh
                header('Location: profile?updated=1');
                exit;
                
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = 'The username is already taken. Please choose a different one.';
                } else {
                    $message = 'An error occurred while updating your profile.';
                    error_log('Profile update error: ' . $e->getMessage());
                }
                $success = false;
            }
        
            // Handle avatar upload
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        throw new Exception('Failed to create upload directory');
                    }
                }
                
                // Validate file type
                $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($fileInfo, $_FILES['avatar']['tmp_name']);
                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp'
                ];
                
                if (!in_array($mimeType, array_keys($allowedTypes))) {
                    throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP images are allowed.');
                }
                
                // Validate file size (max 2MB)
                $maxFileSize = 2 * 1024 * 1024; // 2MB
                if ($_FILES['avatar']['size'] > $maxFileSize) {
                    throw new Exception('File is too large. Maximum size is 2MB.');
                }
                
                // Generate unique filename
                $fileExt = $allowedTypes[$mimeType];
                $fileName = 'user_' . $user['id'] . '_' . uniqid() . '.' . $fileExt;
                $targetPath = $uploadDir . $fileName;
                
                // Delete old avatar if exists
                if (!empty($user['avatar']) && file_exists(__DIR__ . '/..' . $user['avatar'])) {
                    @unlink(__DIR__ . '/..' . $user['avatar']);
                }
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetPath)) {
                    // Update avatar path in database (store relative to web root)
                    $avatarPath = '/uploads/avatars/' . $fileName;
                    $db = Database::getInstance();
                    $stmt = $db->getConnection()->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$avatarPath, $user['id']]);
                    
                    // Update user session with full URL if needed
                    $_SESSION['user_avatar'] = $avatarPath;
                    $user['avatar'] = $avatarPath;
                    
                    $message = 'Profile and avatar updated successfully!';
                    $success = true;
                } else {
                    throw new Exception('Failed to upload file. Please try again.');
                }
                
                finfo_close($fileInfo);
            } else if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Handle upload errors
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
                    UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
                ];
                
                $error = $_FILES['avatar']['error'];
                $message = $uploadErrors[$error] ?? 'Unknown upload error';
                $success = false;
            } else {
                $success = true;
                $message = 'Profile updated successfully!';
            }
            
            // Refresh user data
            $user = fetch("SELECT * FROM users WHERE id = ?", [$user['id']]);
        }
    }
}


// Get user's scan statistics
$stats = fetch(
    "SELECT 
        COUNT(*) as total_scans,
        COUNT(*) as completed_scans,  -- Assuming all scans in history are completed
        (SELECT COUNT(*) FROM scan_history WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as weekly_scans,
        (SELECT COUNT(*) FROM scan_history WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as monthly_scans,
        (SELECT COUNT(DISTINCT url) FROM scan_history WHERE user_id = ?) as unique_domains
    FROM scan_history 
    WHERE user_id = ?", 
    [$user['id'], $user['id'], $user['id'], $user['id']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - TechDetector</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/ntsa-style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #000000;
            color: #ffffff;
        }
        .profile-section {
            background: #000000;
            border: 1px solid #3c4043;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .profile-header {
            padding: 1.5rem;
            background: #1a1a1a;
            color: white;
            text-align: center;
            border-bottom: 1px solid #3c4043;
        }
        .profile-body {
            padding: 1.5rem;
        }
        .avatar-upload {
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .avatar-upload img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ff6b00;
        }
        .avatar-upload label {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #ff6b00;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .avatar-upload input[type="file"] {
            display: none;
        }
        .stat-card {
            background: #1a1a1a;
            border: 1px solid #3c4043;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ff6b00;
            margin-bottom: 0.25rem;
        }
        .stat-card p {
            color: #9ca3af;
            font-size: 0.875rem;
        }
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #3c4043;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        input, textarea {
            background-color: #1a1a1a;
            border-color: #3c4043;
            color: #ffffff;
        }
        input:focus, textarea:focus {
            border-color: #ff6b00;
            outline: none;
        }
        input:disabled {
            background-color: #2a2a2a;
            color: #6b7280;
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
                <a href="profile" class="gsc-nav-item active">
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
            <?php if (isset($message)): ?>
                <div class="mb-6 p-4 rounded-md bg-[#ff6b00] bg-opacity-10 border border-[#ff6b00] text-[#ff6b00]">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Profile Information -->
                    <div class="profile-section">
                        <div class="profile-header">
                            <div class="avatar-upload">
                                <?php
                                $avatarUrl = '';
                                if (!empty($user['avatar'])) {
                                    // Remove any existing relative paths and get just the filename
                                    $avatarFile = basename($user['avatar']);
                                    
                                    // Try direct path first (as stored in database)
                                    $directPath = '/clon/uploads/avatars/' . $avatarFile;
                                    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $directPath;
                                    
                                    if (file_exists($fullPath)) {
                                        $avatarUrl = $directPath;
                                    } 
                                    // Try alternative path (relative to web root)
                                    else {
                                        $altPath = '/clon/uploads/avatars/' . $avatarFile;
                                        $altFullPath = $_SERVER['DOCUMENT_ROOT'] . $altPath;
                                        if (file_exists($altFullPath)) {
                                            $avatarUrl = $altPath;
                                        } 
                                        // Fallback to default if file doesn't exist
                                        else {
                                            $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&size=200';
                                        }
                                    }
                                } else {
                                    // Fallback to default avatar
                                    $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&size=200';
                                }
                                ?>
                                <img id="avatar-preview" 
                                     src="<?php echo htmlspecialchars($avatarUrl); ?>" 
                                     alt="Profile Picture" 
                                     class="w-32 h-32 rounded-full object-cover" 
                                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&size=200';">
                                <label for="avatar-upload">
                                    <i class="fas fa-camera text-gray-700"></i>
                                </label>
                            </div>
                            <h2 class="text-xl font-bold mt-2"><?php echo htmlspecialchars($user['name'] ?? $user['username']); ?></h2>
                            <p class="text-indigo-100"><?php echo htmlspecialchars($user['email']); ?></p>
                            <?php if (!empty($user['company'])): ?>
                                <p class="text-indigo-100 mt-1">
                                    <i class="fas fa-building mr-1"></i> 
                                    <?php echo htmlspecialchars($user['company']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" class="profile-body">
                            <input type="file" id="avatar-upload" name="avatar" accept="image/*" class="hidden" onchange="previewAvatar(this)">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-300 mb-1">Email Address</label>
                                    <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 bg-gray-100 rounded-md shadow-sm" disabled>
                                    <p class="mt-1 text-xs text-gray-500">Contact support to change your email</p>
                                </div>
                                
                                <div>
                                    <label for="username" class="block text-sm font-medium text-gray-300 mb-1">Username</label>
                                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#ff6b00] focus:border-[#ff6b00]"
                                           oninput="checkUsernameAvailability(this.value)">
                                    <div id="username-feedback" class="text-sm mt-1"></div>
                                    <input type="hidden" id="current_user_id" value="<?php echo $user['id']; ?>">
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="bio" class="block text-sm font-medium text-gray-300 mb-1">Bio</label>
                                <textarea id="bio" name="bio" rows="3" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-[#ff6b00] focus:border-[#ff6b00]"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="update_profile" 
                                        class="px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-[#ff6b00] hover:bg-[#e65a00] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#ff6b00]">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    
                
                
                    
                    <!-- Quick Actions -->
                    <div class="profile-section">
                        <div class="px-6 py-4 border-b border-[#3c4043]">
                            <h3 class="text-lg font-medium text-white">Quick Actions</h3>
                        </div>
                        <div class="p-4 space-y-2">
                            <a href="../scan.php" class="block w-full px-4 py-2 text-left text-sm font-medium text-gray-300 hover:bg-[#1a1a1a] hover:text-[#ff6b00] rounded-md transition-colors">
                                <i class="fas fa-plus-circle mr-2 text-[#ff6b00]"></i> New Scan
                           
                            <a href="settings" class="block w-full px-4 py-2 text-left text-sm font-medium text-gray-300 hover:bg-[#1a1a1a] hover:text-[#ff6b00] rounded-md transition-colors">
                                <i class="fas fa-cog mr-2 text-[#ff6b00]"></i> Account Settings
                            </a>
                            <a href="../api/logout" class="block w-full px-4 py-2 text-left text-sm font-medium text-[#ff6b00] hover:bg-[#1a1a1a] hover:text-[#ff6b00] rounded-md transition-colors">
                                <i class="fas fa-sign-out-alt mr-2"></i> Sign Out
                            </a>
                        </div>
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

        // Function to validate username format
        function isValidUsername(username) {
            if (username.length < 3) {
                return { valid: false, message: 'Username must be at least 3 characters long' };
            }
            if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                return { valid: false, message: 'Only letters, numbers, and underscores allowed' };
            }
            return { valid: true };
        }

        // Function to validate username format only (no availability check)
        function validateUsername(username) {
            const feedbackElement = document.getElementById('username-feedback');
            const usernameInput = document.getElementById('username');
            
            // Reset states
            usernameInput.classList.remove('border-red-500', 'border-green-500');
            
            if (!username) {
                feedbackElement.textContent = '';
                return true;
            }
            
            // Validate the format - letters and spaces between names
            username = username.trim();
            if (username.length < 3) {
                usernameInput.classList.add('border-red-500');
                feedbackElement.textContent = 'Name must be at least 3 characters long';
                feedbackElement.className = 'text-red-600 text-sm mt-1';
                return false;
            }
            
            if (!/^[a-zA-Z]+(?: [a-zA-Z]+)*$/.test(username)) {
                usernameInput.classList.add('border-red-500');
                feedbackElement.textContent = 'Only letters and single spaces between words are allowed';
                feedbackElement.className = 'text-red-600 text-sm mt-1';
                return false;
            }
            
            // Check for multiple consecutive spaces
            if (username.includes('  ')) {
                usernameInput.classList.add('border-red-500');
                feedbackElement.textContent = 'Only single spaces between words are allowed';
                feedbackElement.className = 'text-red-600 text-sm mt-1';
                return false;
            }
            
            // Name is valid
            usernameInput.classList.remove('border-red-500');
            usernameInput.classList.add('border-green-500');
            feedbackElement.textContent = 'Name is valid';
            feedbackElement.className = 'text-green-600 text-sm mt-1';
            return true;
        }
        
        // Update the oninput handler for the username field
        document.getElementById('username').addEventListener('input', function(e) {
            validateUsername(e.target.value);
        });

        // Preview avatar before upload
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('avatar-preview').src = e.target.result;
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Clicking the avatar opens the file dialog
        document.querySelector('.avatar-upload').addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT') {
                document.getElementById('avatar-upload').click();
            }
        });
    });
    </script>
</body>
</html>

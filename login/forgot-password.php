<?php
error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$message = '';
$error = '';
$email = '';

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Get database connection
        $pdo = getDbConnection();
        
        // Check if email exists in database
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate 6-digit code
            $resetCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Store reset code in users table
            $stmt = $pdo->prepare("UPDATE users SET reset_code = ?, reset_code_expires = ? WHERE id = ?");
            $stmt->execute([$resetCode, $expires, $user['id']]);
            
            require_once __DIR__ . '/../includes/verification.php';
            
            // Send password reset code email
            if (sendPasswordResetCodeEmail($email, $resetCode, $user['username'])) {
                // Store email in session for verification
                $_SESSION['reset_email'] = $email;
                header('Location: verify-reset-code');
                exit();
            } else {
                $error = 'Failed to send password reset code. Please try again later.';
                error_log('Failed to send password reset code to: ' . $email);
            }
        } else {
            // For security, don't reveal if the email exists or not
            $message = 'If an account with that email exists, we have sent a password reset link.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - TechDetector</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #ff6b00;
            --primary-dark: #e65a00;
            --gray-300: #3c4043;
            --gray-700: #9aa0a6;
            --gray-900: #ffffff;
            --light: #000000;
            --dark: #1e1e1e;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light);
            color: var(--gray-900);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            line-height: 1.5;
        }
        .auth-container {
            width: 100%;
            max-width: 400px;
            background: #000000;
            border-radius: 24px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.5);
            overflow: hidden;
        }
        .auth-header {
            background-color: #000000;
            color: white;
            text-align: center;
            padding: 1.5rem;
            border-bottom: 1px solid #3c4043;
        }
        .logo {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .auth-body {
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 24px;
            font-size: 1rem;
            box-sizing: border-box;
            background-color: #2d2d2d;
            color: #ffffff;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(255, 107, 0, 0.2);
        }
        .btn {
            display: inline-block;
            background-color: var(--primary);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 24px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
            width: 100%;
            text-decoration: none;
        }
        .btn:hover {
            background-color: var(--primary-dark);
        }
        .text-center {
            text-align: center;
        }
        .mt-3 {
            margin-top: 1rem;
        }
        .message-box {
            padding: 1rem;
            border-radius: 24px;
            margin-bottom: 1.5rem;
        }
        .success {
            background-color: #1e3a1e;
            color: #34a853;
            border: 1px solid #34a853;
        }
        .error {
            background-color: #3a1e1e;
            color: #ea4335;
            border: 1px solid #ea4335;
        }
        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            color: var(--gray-700);
        }
        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        .auth-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </div>
            <h1>Reset Password</h1>
            <p>Enter your email to receive a reset code</p>
        </div>
        
        <div class="auth-body">
            <?php if (!empty($message)): ?>
                <div class="message-box success">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="message-box error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($email); ?>" required>
                </div>

                <button type="submit" class="btn">Send Reset code</button>
            </form>

            <div class="auth-footer">
                <p>Remember your password? <a href="../login">Sign In</a></p>
            </div>
        </div>
    </div>
</body>
</html>

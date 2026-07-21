<?php
error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';

// Redirect if no email in session
if (empty($_SESSION['reset_email'])) {
    header('Location: forgot-password.php');
    exit();
}

$email = $_SESSION['reset_email'];
$message = '';
$error = '';

// Handle code verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    
    if (empty($code)) {
        $error = 'Please enter the verification code.';
    } else {
        $db = db();
        
        // Debug: Log the input values
        error_log("Verification attempt - Email: '$email', Code: '$code'");
        
        // First, get the user with all necessary fields
        $user = $db->fetch("
            SELECT id, reset_code, reset_code_expires 
            FROM users 
            WHERE email = ?
            LIMIT 1
        ", [$email]);

        if ($user) {
            // Debug log the stored values
            error_log(sprintf(
                "Verification attempt - Email: %s, Stored Code: %s, Input Code: %s, Expires: %s, Current Time: %s",
                $email,
                $user['reset_code'],
                $code,
                $user['reset_code_expires'],
                date('Y-m-d H:i:s')
            ));

            // Check code and expiration
            if (!empty($user['reset_code']) && 
                $user['reset_code'] === $code && 
                strtotime($user['reset_code_expires']) > time()) {
                
                // Code is valid, mark as verified in session
                $_SESSION['reset_verified'] = true;
                header('Location: reset-password.php');
                exit();
            } else {
                // Log why verification failed
                if (empty($user['reset_code'])) {
                    error_log("Verification failed: No reset code found for user");
                } elseif ($user['reset_code'] !== $code) {
                    error_log(sprintf(
                        "Verification failed: Code mismatch. Stored: %s, Provided: %s",
                        $user['reset_code'],
                        $code
                    ));
                } else {
                    error_log(sprintf(
                        "Verification failed: Code expired. Expires: %s, Current: %s",
                        $user['reset_code_expires'],
                        date('Y-m-d H:i:s')
                    ));
                }
            }
        } else {
            error_log("Verification failed: No user found with email: $email");
        }

        // If we get here, verification failed
        $error = 'Invalid or expired verification code. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Reset Code - TechDetector</title>
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
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 0.5em;
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
            <h1>Verify Your Identity</h1>
            <p>Enter the 6-digit code sent to your email</p>
        </div>
        
        <div class="auth-body">
            <?php if (!empty($message)): ?>
                <div class="message-box success">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="message-box error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="code">Verification Code</label>
                    <input type="text" 
                           id="code" 
                           name="code" 
                           class="form-control" 
                           maxlength="6" 
                           pattern="\d{6}" 
                           required 
                           autofocus
                           inputmode="numeric"
                           placeholder="000000">
                </div>

                <button type="submit" class="btn">Verify Code</button>
            </form>

            <div class="auth-footer">
                <p>Didn't receive a code? <a href="forgot-password">Request a new one</a></p>
                <p><a href="index">Back to Login</a></p>
            </div>
        </div>
    </div>

    <script>
        // Auto-advance to next input
        document.addEventListener('DOMContentLoaded', function() {
            const codeInput = document.getElementById('code');
            
            // Format code with spaces as user types
            codeInput.addEventListener('input', function(e) {
                // Remove any non-digit characters
                let value = this.value.replace(/\D/g, '');
                
                // Update the input value
                this.value = value;
            });
            
            // Auto-submit when 6 digits are entered
            codeInput.addEventListener('keyup', function(e) {
                if (this.value.length === 6) {
                    this.form.submit();
                }
            });
        });
    </script>
</body>
</html>
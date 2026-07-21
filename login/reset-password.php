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

$message = '';
$error = '';
$validSession = false;

// Check if user is coming from verification
if (!empty($_SESSION['reset_verified']) && !empty($_SESSION['reset_email'])) {
    $validSession = true;
    $email = $_SESSION['reset_email'];
} else {
    header('Location: forgot-password');
    exit();
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validSession) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please enter and confirm your new password.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            $db = db();
            
            // Start transaction
            $db->beginTransaction();
            
            // Update user's password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->update(
                'users',
                [
                    'password_hash' => $hashedPassword,
                    'reset_code' => null,
                    'reset_code_expires' => null,
                    'reset_token' => null,
                    'reset_expires' => null,
                    'reset_used' => 1
                ],
                'email = ?',
                [$email]
            );
            
            // Clear the session
            unset($_SESSION['reset_verified']);
            unset($_SESSION['reset_email']);
            
            // Commit transaction
            $db->commit();
            
            // Set success message
            $message = 'Your password has been reset successfully. You can now <a href="../login">login</a> with your new password.';
            $validSession = false; // Hide the form after successful reset
            
        } catch (Exception $e) {
            $db->rollBack();
            
            // For development - show detailed error
            $error = 'Error: ' . $e->getMessage() . ' (Code: ' . $e->getCode() . ')';
            
            // For production - use a generic message
            // $error = 'An error occurred while resetting your password. Please try again.';
            
            // Log detailed error information
            error_log('Password reset error: ' . $e->getMessage());
            error_log('Error in file: ' . $e->getFile() . ' on line ' . $e->getLine());
            error_log('Stack trace: ' . $e->getTraceAsString());
        }
    } // Close the else block for password validation
} // Close the if ($_SERVER['REQUEST_METHOD'] === 'POST') block
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - TechDetector</title>
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
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        .strength-weak {
            color: #ea4335;
        }
        .strength-medium {
            color: #fbbc04;
        }
        .strength-strong {
            color: #34a853;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </div>
            <h1>Reset Your Password</h1>
            <p>Choose a new password</p>
        </div>
        
        <div class="auth-body">
            <?php if (!empty($message)): ?>
                <div class="message-box success">
                    <?php echo $message; ?>
                </div>
            <?php elseif (!empty($error)): ?>
                <div class="message-box error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($validSession): ?>
                <form method="POST" action="" id="resetPasswordForm">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               required 
                               minlength="8"
                               autocomplete="new-password">
                        <div id="password-strength" class="password-strength"></div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-control" 
                               required 
                               minlength="8"
                               autocomplete="new-password">
                        <div id="password-match" class="password-strength"></div>
                    </div>

                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>

            <div class="auth-footer">
                <p>Remember your password? <a href="../login">Sign in</a></p>
            </div>
        </div>
    </div>

    <script>
        // Client-side password strength and match validation
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthText = document.getElementById('password-strength');
            const matchText = document.getElementById('password-match');
            const form = document.getElementById('resetPasswordForm');

            function checkPasswordStrength(password) {
                // Reset
                let strength = 0;
                let messages = [];

                // Length check
                if (password.length >= 8) strength++;
                else messages.push('At least 8 characters');

                // Contains numbers
                if (/\d/.test(password)) strength++;
                else messages.push('At least one number');

                // Contains letters
                if (/[a-zA-Z]/.test(password)) strength++;
                else messages.push('At least one letter');

                // Contains special chars
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                else messages.push('At least one special character');

                // Update UI
                let strengthClass = '';
                let strengthTextContent = '';

                if (password.length === 0) {
                    strengthText.textContent = '';
                    return;
                }

                if (strength <= 2) {
                    strengthClass = 'strength-weak';
                    strengthTextContent = 'Weak password';
                } else if (strength === 3) {
                    strengthClass = 'strength-medium';
                    strengthTextContent = 'Medium strength';
                } else {
                    strengthClass = 'strength-strong';
                    strengthTextContent = 'Strong password';
                }

                strengthText.textContent = strengthTextContent;
                strengthText.className = 'password-strength ' + strengthClass;
            }

            function checkPasswordMatch() {
                if (confirmPasswordInput.value.length === 0) {
                    matchText.textContent = '';
                    return;
                }

                if (passwordInput.value !== confirmPasswordInput.value) {
                    matchText.textContent = 'Passwords do not match';
                    matchText.className = 'password-strength strength-weak';
                } else {
                    matchText.textContent = 'Passwords match';
                    matchText.className = 'password-strength strength-strong';
                }
            }

            // Event listeners
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
                if (confirmPasswordInput.value.length > 0) {
                    checkPasswordMatch();
                }
            });

            confirmPasswordInput.addEventListener('input', checkPasswordMatch);

            // Form submission
            form.addEventListener('submit', function(e) {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    e.preventDefault();
                    matchText.textContent = 'Passwords do not match';
                    matchText.className = 'password-strength strength-weak';
                }
            });
        });
    </script>
</body>
</html>
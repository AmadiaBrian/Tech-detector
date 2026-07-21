<?php
error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Google OAuth has been removed

$errors = [];
$username = '';
$email = '';

// --- Redirect if already logged in ---
if (function_exists('redirectIfLoggedIn')) {
    redirectIfLoggedIn();
}

// --- Handle registration form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    } elseif (empty($username) || empty($email) || empty($password)) {
        $errors[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    } else {
        if (!function_exists('registerUser')) {
            $errors[] = "Registration function not found.";
        } else {
            list($success, $result) = registerUser($username, $email, $password);
            if ($success) {
                // Redirect to verification page with the user's email
                header('Location: verify?email=' . urlencode($email) . '&verification_sent=1');
                exit;
            } else {
                $errors = is_array($result) ? $result : [$result];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TechDetector</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #ff6b00;
            --primary-hover: #e65a00;
            --primary-light: #EEF2FF;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #3c4043;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #9aa0a6;
            --gray-800: #1F2937;
            --gray-900: #ffffff;
            --red-500: #EF4444;
            --green-500: #10B981;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --shadow-inner: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
            --shadow-outline: 0 0 0 3px rgba(255, 107, 0, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #000000;
            color: var(--gray-900);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
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
            padding: 1.5rem;
            text-align: center;
            background-color: #000000;
            color: white;
            border-bottom: 1px solid #3c4043;
        }

        .logo {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .auth-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .auth-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9375rem;
            position: relative;
            z-index: 2;
        }


        .auth-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.9375rem;
            line-height: 1.5;
            color: #ffffff;
            background-color: #2d2d2d;
            background-clip: padding-box;
            border: 1px solid var(--gray-300);
            border-radius: 24px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(255, 107, 0, 0.2);
        }
        .btn {
            display: inline-block;
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 24px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        .btn:hover { background: var(--primary-hover); }
        .error-box {
            background: #3a1e1e;
            color: #ea4335;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border-radius: 24px;
            border: 1px solid #ea4335;
        }
        .text-center { text-align: center; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: #000;
            border: 1px solid #ccc;
            border-radius: 24px;
            padding: 0.75rem;
            font-weight: 600;
            text-decoration: none;
        }
        .google-btn img {
            width: 18px;
            height: 18px;
            margin-right: 8px;
        }
        .google-btn:hover { background: #f8f8f8; }
        .password-wrapper { position: relative; }
        .password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9aa0a6;
            cursor: pointer;
            padding: 4px;
            font-size: 12px;
            transition: color 0.2s;
        }
        .password-toggle:hover { color: var(--primary); }
        .password-toggle:focus { outline: none; }
        .form-control.pr-10 { padding-right: 28px; }
    </style>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggleIcon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
                field.nextElementSibling.setAttribute('aria-label', 'Hide password');
            } else {
                field.type = 'password';
                toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
                field.nextElementSibling.setAttribute('aria-label', 'Show password');
            }
        }

        function validateForm(event) {
            const form = event.target;
            const inputs = form.querySelectorAll('input[required]');
            let isValid = true;
            let firstInvalidInput = null;

            // Reset previous error states
            document.querySelectorAll('.error-message').forEach(el => el.remove());
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            // Check each required input
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    
                    // Add error message
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'error-message';
                    errorMsg.textContent = 'This field is required';
                    input.parentNode.insertBefore(errorMsg, input.nextSibling);
                    
                    // Track first invalid input for focus
                    if (!firstInvalidInput) {
                        firstInvalidInput = input;
                    }
                }
            });

            // Check password match
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                isValid = false;
                password.classList.add('is-invalid');
                confirmPassword.classList.add('is-invalid');
                
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message';
                errorMsg.textContent = 'Passwords do not match';
                confirmPassword.parentNode.insertBefore(errorMsg, confirmPassword.nextSibling);
                
                if (!firstInvalidInput) {
                    firstInvalidInput = confirmPassword;
                }
            }

            if (!isValid && event) {
                event.preventDefault();
                if (firstInvalidInput) {
                    firstInvalidInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalidInput.focus();
                }
            }

            return isValid;
        }

        // Add form validation on submit
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', validateForm);
                
                // Remove error state when user starts typing
                form.querySelectorAll('input').forEach(input => {
                    input.addEventListener('input', function() {
                        if (this.value.trim()) {
                            this.classList.remove('is-invalid');
                            const errorMsg = this.parentNode.querySelector('.error-message');
                            if (errorMsg) {
                                errorMsg.remove();
                            }
                        }
                    });
                });
            }
        });
    </script>
    <script>
        function validateForm(event) {
            const form = event.target || document.querySelector('form');
            const inputs = form.querySelectorAll('input[required]');
            let isValid = true;
            let firstInvalidInput = null;

            // Reset previous error states
            document.querySelectorAll('.error-message').forEach(el => el.remove());
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            // Check each required input
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                    
                    // Add error message
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'error-message text-red-500 text-xs mt-1';
                    errorMsg.textContent = 'This field is required';
                    input.parentNode.insertBefore(errorMsg, input.nextSibling);
                    
                    // Track first invalid input for focus
                    if (!firstInvalidInput) {
                        firstInvalidInput = input;
                    }
                }
            });

            // Check password match
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password.value !== confirmPassword.value) {
                isValid = false;
                password.classList.add('is-invalid');
                confirmPassword.classList.add('is-invalid');
                
                const errorMsg = document.createElement('div');
                errorMsg.className = 'error-message text-red-500 text-xs mt-1';
                errorMsg.textContent = 'Passwords do not match';
                confirmPassword.parentNode.insertBefore(errorMsg, confirmPassword.nextSibling);
                
                if (!firstInvalidInput) {
                    firstInvalidInput = confirmPassword;
                }
            }

            if (!isValid) {
                event.preventDefault();
                if (firstInvalidInput) {
                    firstInvalidInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalidInput.focus();
                }
            }
            return isValid;
        }
    </script>
    <style>
        .is-invalid {
            border-color: #ef4444 !important;
        }
        .error-message {
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
    </style>
    <div class="auth-container">
        <div class="auth-header">
            <div class="logo">
                <i class="fas fa-search" style="color: #ff6b00;"></i> <span style="color: #ff6b00;">Tech</span><span style="color: #FFD700;">Detector</span>
            </div>
            <h1>Create Account</h1>
            <p>Join TechDetector today</p>
        </div>
        <div class="auth-body">
            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" onsubmit="return validateForm(event)" novalidate>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($username) ?>" 
                           placeholder="Choose a username" required>
                    <?php if (isset($errors['username'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['username']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($email) ?>" required
                           placeholder="Your email will be your login">
                    <?php if (isset($errors['email'])): ?>
                        <div class="error-message"><?= htmlspecialchars($errors['email']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="password">Password (min. 8 characters)</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control pr-10"
                               minlength="8" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password')" 
                                aria-label="Toggle password visibility">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="form-control pr-10" minlength="8" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')"
                                aria-label="Toggle password visibility">
                            <i class="far fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn w-full"><i class="fas fa-user-plus"></i> Register</button>
            </form>

            <div class="text-center" style="margin-top:1rem;">
                <span>Already have an account? <a href="../login">Login here</a></span>
            </div>
            </div>
           
        </div>
    </div>
</body>
</html>

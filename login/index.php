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

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashbord/overview');
    exit;
}

$errors = [];
$username = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Basic validation
    if (empty($username) || empty($password)) {
        $errors[] = "Both username/email and password are required.";
    } else {
        // Attempt to log in
        $loginResult = loginUser($username, $password, $remember);
        
        if (isset($loginResult[0]) && $loginResult[0] === true) {
            // Login successful
            $_SESSION['user_id'] = $loginResult[2]['id']; // Store user ID in session
            $_SESSION['username'] = $loginResult[2]['username'];
            
            // Redirect to dashboard or intended page
            header('Location: ../dashbord/overview');
            exit;
        } else {
            // Login failed
            $errorMsg = $loginResult[1] ?? 'Invalid username/email or password.';
            $errors = is_array($errorMsg) ? $errorMsg : [$errorMsg];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TechDetector</title>
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
        .error-box {
            background-color: #3a1e1e;
            color: #ea4335;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            border: 1px solid #ea4335;
        }
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 1.25rem;
        }
        .remember-me input {
            margin-right: 0.5rem;
        }
        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            color: var(--gray-700);
        }
        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
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
            <h1>Welcome Back</h1>
            <p>Sign in to your account</p>
        </div>
        
        <div class="auth-body">
            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($username); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember" style="margin: 0;">Remember me</label>
                </div>

                <button type="submit" class="btn">Sign In</button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="../register">Sign up</a></p>
                <p><a href="forgot-password">Forgot your password?</a></p>
            </div>
        </div>
    </div>
</body>
</html>
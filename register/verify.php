<?php
error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/verification.php';

$errors = [];
$success = false;
$email = $_GET['email'] ?? '';
$code = $_GET['code'] ?? '';

// If code is provided in URL, verify it directly
if (!empty($email) && !empty($code)) {
    try {
        error_log("Starting verification for email: $email with code: $code");
        $db = db();
        
        // Check if user exists and is already verified
        $user = $db->fetch("SELECT id, is_verified, verification_code, verification_expires FROM users WHERE email = ?", [$email]);
        
        if (!$user) {
            $errorMsg = 'No account found with this email address.';
            $errors[] = $errorMsg;
            error_log("Verification failed: $errorMsg");
        } elseif ($user['is_verified']) {
            // User is already verified, redirect to login with success message
            $_SESSION['verification_success'] = 'Your email is already verified. You can log in now.';
            header('Location: ../login');
            exit;
        } else {
            // User exists but not verified, proceed with verification
            $result = verifyUser($email, $code, $db);
            error_log("Verification result for $email: " . ($result ? 'Success' : 'Failed'));
            
            if ($result) {
                $success = true;
                $_SESSION['verification_success'] = 'Your email has been verified successfully! You can now log in.';
                error_log("Verification successful for $email, redirecting to login");
                header('Location: ../login');
                exit;
            } else {
                $errorMsg = 'Invalid or expired verification code. Please try again or request a new code.';
                $errors[] = $errorMsg;
                error_log("Verification failed: $errorMsg");
            }
        }
    } catch (PDOException $e) {
        $errorMsg = 'Database error: ' . $e->getMessage();
        error_log("Database Error: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        error_log("Stack trace: " . $e->getTraceAsString());
        $errors[] = 'A database error occurred. Please try again later. Error code: ' . $e->getCode();
    } catch (Exception $e) {
        $errorMsg = 'Error: ' . $e->getMessage();
        error_log("Verification Error: " . $errorMsg);
        error_log("Stack trace: " . $e->getTraceAsString());
        $errors[] = 'Error: ' . $e->getMessage() . '. Please try again or contact support.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is an AJAX request
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    
    if (empty($email) || empty($code)) {
        $message = 'Please enter both email and verification code.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        $errors[] = $message;
    } else {
        try {
            $db = db();
            if (verifyUser($email, $code, $db)) {
                $message = 'Your email has been verified successfully! You can now log in.';
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $message]);
                    exit;
                }
                
                $_SESSION['verification_success'] = $message;
                header('Location: ../login');
                exit;
            } else {
                $message = 'Invalid or expired verification code. Please try again or request a new code.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $message]);
                    exit;
                }
                $errors[] = $message;
            }
        } catch (PDOException $e) {
            $errorMsg = 'Database error during verification: ' . $e->getMessage();
            error_log("Database Error: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            error_log("Stack trace: " . $e->getTraceAsString());
            $message = 'A database error occurred. Please try again later. Error code: ' . $e->getCode();
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            }
            $errors[] = $message;
        } catch (Exception $e) {
            $errorMsg = 'Error during verification: ' . $e->getMessage();
            error_log("Verification Error: " . $errorMsg);
            error_log("Stack trace: " . $e->getTraceAsString());
            $message = 'Error: ' . $e->getMessage() . '. Please try again or contact support.';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            }
            $errors[] = $message;
        }
    }
}
?>
<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - TechDetector</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary: #ff6b00;
            --primary-dark: #e65a00;
            --gray-300: #3c4043;
            --gray-700: #9aa0a6;
            --gray-900: #ffffff;
            --light: #000000;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light);
            color: var(--gray-900);
            margin: 0;
            padding: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .auth-container {
            background: #000000;
            border-radius: 24px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 400px;
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
            margin-bottom: 1rem;
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
            display: block;
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 24px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            margin-top: 1rem;
        }
        .btn:hover {
            background: var(--primary-dark);
        }
        .error-box {
            background: #3a1e1e;
            color: #ea4335;
            padding: 0.75rem;
            border-radius: 24px;
            margin-bottom: 1rem;
            border: 1px solid #ea4335;
        }
        .success-box {
            background: #1e3a1e;
            color: #34a853;
            padding: 0.75rem;
            border-radius: 24px;
            margin-bottom: 1rem;
            border: 1px solid #34a853;
        }
        .text-center {
            text-align: center;
        }
        .resend-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--primary);
            text-decoration: none;
        }
        .resend-link:hover {
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
            <h1>Verify Your Email</h1>
        </div>
        <div class="auth-body">
            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-box">
                    <p>Your email has been verified successfully! You can now <a href="../login">log in</a>.</p>
                </div>
            <?php else: ?>
                <p>Please enter the verification code sent to your email address.</p>
                
                <form id="verificationForm" method="post" action="verify">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($email) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="code">Verification Code</label>
                        <input type="text" id="code" name="code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary w-full">Verify Email</button>
                    </div>
                </form>
                
                <div class="mt-4 text-center">
                    <p class="text-sm text-gray-600">
                        Didn't receive a code? 
                        <a href="#" id="resendCode" class="text-primary-600 hover:text-primary-800 font-medium">
                            Resend Code
                        </a>
                        <span id="resendTimer" class="text-gray-500 text-xs ml-2"></span>
                    </p>
                    <div id="resendMessage" class="mt-2 text-sm"></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
// Handle verification form submission
$('#verificationForm').on('submit', function(e) {
    e.preventDefault();
    
    const form = $(this);
    const submitBtn = form.find('button[type="submit"]');
    const originalBtnText = submitBtn.html();
    
    // Show loading state
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Verifying...');
    
    // Submit form via AJAX
    $.ajax({
        url: 'verify',
        type: 'POST',
        data: form.serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                window.location.href = '../login';
            } else {
                alert(response.message || 'Verification failed. Please try again.');
            }
        },
        error: function() {
            alert('An error occurred. Please try again.');
        },
        complete: function() {
            submitBtn.prop('disabled', false).html(originalBtnText);
        }
    });
});

// Resend verification code functionality
$(document).ready(function() {
    let canResend = true;
    let resendTimer;
    let timeLeft = 60; // 60 seconds cooldown
    
    // Function to update the resend timer
    function updateResendTimer() {
        const $resendLink = $('#resendCode');
        const $timerSpan = $('#resendTimer');
        
        if (timeLeft <= 0) {
            clearInterval(resendTimer);
            canResend = true;
            $resendLink.removeClass('text-gray-400').addClass('text-primary-600 hover:text-primary-800');
            $timerSpan.text('');
        } else {
            canResend = false;
            $resendLink.removeClass('text-primary-600 hover:text-primary-800').addClass('text-gray-400');
            $timerSpan.text(`(Resend in ${timeLeft}s)`);
            timeLeft--;
        }
    }
    
    // Start the initial timer
    resendTimer = setInterval(updateResendTimer, 1000);
    updateResendTimer();
    
    // Handle resend code click
    $('#resendCode').click(function(e) {
        e.preventDefault();
        
        if (!canResend) return;
        
        const email = $('input[name="email"]').val();
        if (!email) {
            alert('Please enter your email address first.');
            return;
        }
        
        // Ensure email is validated
        if (!isValidEmail(email)) {
            alert('Please enter a valid email address.');
            return;
        }
        
        // Show loading state
        const $resendBtn = $(this);
        const $resendMessage = $('#resendMessage');
        const originalText = $resendBtn.html();
        
        $resendBtn.html('<i class="fas fa-spinner fa-spin"></i> Sending...');
        $resendMessage.removeClass('text-red-600 text-green-600').text('');
        
        // Send AJAX request to resend verification code
        $.ajax({
            url: 'resend-verification',
            type: 'POST',
            data: { email: email },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $resendMessage.removeClass('text-red-600').addClass('text-green-600').text(response.message);
                    
                    // Reset the resend timer
                    timeLeft = 60;
                    clearInterval(resendTimer);
                    resendTimer = setInterval(updateResendTimer, 1000);
                    updateResendTimer();
                } else {
                    $resendMessage.removeClass('text-green-600').addClass('text-red-600').text(response.message || 'Failed to resend verification code.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $resendMessage.removeClass('text-green-600').addClass('text-red-600').text('An error occurred. Please try again.');
            },
            complete: function() {
                $resendBtn.html(originalText);
                
                // Hide the message after 5 seconds
                setTimeout(function() {
                    $resendMessage.fadeOut(500, function() {
                        $(this).text('').removeClass('text-red-600 text-green-600').show();
                    });
                }, 5000);
            }
        });
    });
    
    // Email validation helper
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});
</script>

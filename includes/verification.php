<?php
/**
 * Verification functions for user registration
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Verify user's email with the provided code
 * 
 * @param string $email User's email
 * @param string $code Verification code
 * @param Database $db Database instance
 * @return bool True if verification is successful, false otherwise
 */
function verifyUser($email, $code, $db) {
    try {
        error_log("Starting verification for email: $email");
        
        // First, get the user by email and code
        $user = $db->fetch(
            "SELECT id, is_verified, verification_code, verification_expires FROM users WHERE email = ?",
            [$email]
        );

        if (!$user) {
            error_log("Verification failed: No user found with email: $email");
            return false;
        }

        error_log(sprintf(
            "User found - ID: %d, Verified: %s, Code: %s, Expires: %s",
            $user['id'],
            $user['is_verified'] ? 'Yes' : 'No',
            $user['verification_code'],
            $user['verification_expires']
        ));

        // Check if already verified
        if ($user['is_verified']) {
            error_log("User already verified");
            return true;
        }

        // Check verification code and expiration
        if (empty($user['verification_code']) || 
            $user['verification_code'] !== $code || 
            (strtotime($user['verification_expires']) < time())) {
            error_log("Verification failed: Invalid or expired code");
            return false;
        }

        // Update user as verified
        $result = $db->update(
            'users',
            [
                'is_verified' => 1,
                'verification_code' => null,
                'verification_expires' => null
            ],
            'id = ?',
            [$user['id']]
        );

        if ($result) {
            error_log("User {$user['id']} successfully verified email: $email");
            return true;
        }

        error_log("Failed to update user verification status");
        return false;
    } catch (Exception $e) {
        error_log("Error during verification: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Send verification email using PHPMailer
 * 
 * @param string $email User's email address
 * @param string $verificationCode The verification code to send
 * @param string $username User's username
 * @return bool True if email was sent successfully, false otherwise
 */
function sendVerificationEmail($email, $verificationCode, $username) {
    // Log the attempt to send email
    error_log("Sending verification email to: $email");
    
    // Load PHPMailer classes
    require_once __DIR__ . '/../PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'otienobrian029@gmail.com';
        $mail->Password = 'dwuunoftzkodeome';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        // Debugging
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            file_put_contents('verification_debug.log', "$level: $str\n", FILE_APPEND);
        };
        
        // Recipients
        $mail->setFrom('otienobrian029@gmail.com', 'TechDetector');
        $mail->addAddress($email, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email Address';
        $mail->Body = "
            <h2>Verify Your Email</h2>
            <p>Your verification code is:</p>
            <h3 style='font-size: 24px; letter-spacing: 5px; background: #f5f5f5; padding: 10px; display: inline-block;'>
                $verificationCode
            </h3>
            <p>This code will expire in 24 hours.</p>
            <p>If you didn't request this, please ignore this email.</p>
        ";
        $mail->AltBody = "Your verification code is: $verificationCode\n\nThis code will expire in 24 hours.";
        
        // Set character set and encoding
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Timeout = 30;  // 30 seconds timeout

        $mail->send();
        error_log("Verification email sent successfully to: $email");
        return true;
    } catch (Exception $e) {
        $errorMsg = "Error sending verification email to $email: " . $e->getMessage();
        error_log($errorMsg);
        error_log("PHPMailer Error Info: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send password reset email using PHPMailer
 * 
 * @param string $email User's email address
 * @param string $resetLink The password reset link to send
 * @param string $username User's username
 * @return bool True if email was sent successfully, false otherwise
 */
function sendPasswordResetEmail($email, $resetLink, $username) {
    // Log the attempt to send email
    error_log("Sending password reset email to: $email");
    
    // Load PHPMailer classes
    require_once __DIR__ . '/../PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

    $mail = new PHPMailer(true);

    try {
        // Server settings (using same as verification email)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'otienobrian029@gmail.com';
        $mail->Password = 'dwuunoftzkodeome';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        // Debugging
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            file_put_contents('password_reset_debug.log', "$level: $str\n", FILE_APPEND);
        };
        
        // Recipients
        $mail->setFrom('otienobrian029@gmail.com', 'TechDetector');
        $mail->addAddress($email, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body = '
            <h2>Password Reset Request</h2>
            <p>Hello ' . $username . ',</p>
            <p>We received a request to reset your password. Click the button below to reset it:</p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="' . $resetLink . '" style="background-color: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 500;">
                    Reset Password
                </a>
            </p>
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all;">' . $resetLink . '</p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn\'t request a password reset, please ignore this email or contact support.</p>
            <p>Thanks,<br>TechDetector Team</p>
        ';
        
        $mail->AltBody = "Password Reset Request\n\n" .
            "Hello $username,\n\n" .
            "We received a request to reset your password. Please visit the following link to reset your password:\n\n" .
            "$resetLink\n\n" .
            "This link will expire in 1 hour.\n\n" .
            "If you didn't request a password reset, please ignore this email or contact support.\n\n" .
            "Thanks,\nTechDetector Team";
        
        // Set character set and encoding
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Timeout = 30;  // 30 seconds timeout

        $mail->send();
        error_log("Password reset email sent successfully to: $email");
        return true;
    } catch (Exception $e) {
        $errorMsg = "Error sending password reset email to $email: " . $e->getMessage();
        error_log($errorMsg);
        error_log("PHPMailer Error Info: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send password reset code email using PHPMailer
 * 
 * @param string $email User's email address
 * @param string $resetCode The 6-digit reset code
 * @param string $username User's username
 * @return bool True if email was sent successfully, false otherwise
 */
function sendPasswordResetCodeEmail($email, $resetCode, $username) {
    // Log the attempt to send email
    error_log("Sending password reset code to: $email");
    
    // Load PHPMailer classes
    require_once __DIR__ . '/../PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

    $mail = new PHPMailer(true);

    try {
        // Server settings (using same as verification email)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'otienobrian029@gmail.com';
        $mail->Password = 'dwuunoftzkodeome';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        // Debugging
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            file_put_contents('password_reset_debug.log', "$level: $str\n", FILE_APPEND);
        };
        
        // Recipients
        $mail->setFrom('otienobrian029@gmail.com', 'TechDetector');
        $mail->addAddress($email, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Code';
        $mail->Body = '
            <h2>Password Reset Code</h2>
            <p>Hello ' . $username . ',</p>
            <p>We received a request to reset your password. Use the following code to verify your identity:</p>
            <div style="text-align: center; margin: 30px 0; font-size: 24px; font-weight: bold; letter-spacing: 5px; ">
                ' . $resetCode . '
            </div>
            <p>This code will expire in 15 minutes.</p>
            <p>If you didn\'t request a password reset, please ignore this email or contact support.</p>
            <p>Thanks,<br>TechDetector Team</p>
        ';
        
        $mail->AltBody = "Password Reset Code\n\n" .
            "Hello $username,\n\n" .
            "We received a request to reset your password. Use the following code to verify your identity:\n\n" .
            "$resetCode\n\n" .
            "This code will expire in 15 minutes.\n\n" .
            "If you didn't request a password reset, please ignore this email or contact support.\n\n" .
            "Thanks,\nTechDetector Team";
        
        // Set character set and encoding
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Timeout = 30;  // 30 seconds timeout

        $mail->send();
        error_log("Password reset code email sent successfully to: $email");
        return true;
    } catch (Exception $e) {
        $errorMsg = "Error sending password reset code to $email: " . $e->getMessage();
        error_log($errorMsg);
        error_log("PHPMailer Error Info: " . $mail->ErrorInfo);
        return false;
    }
}
?>

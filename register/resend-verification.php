<?php
error_reporting(-1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Set JSON header
header('Content-Type: application/json');

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/verification.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

try {
    // Check if request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    // Get and validate email
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        throw new Exception('Please provide a valid email address.');
    }

    // Get database connection
    $pdo = getDbConnection();

    // Check if user exists and is not verified
    $stmt = $pdo->prepare("SELECT id, username, is_verified FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('No account found with this email address.');
    }

    if ($user['is_verified']) {
        throw new Exception('This email is already verified. Please log in.');
    }

    // Generate new verification code
    $verificationCode = str_pad(random_int(0, 999999), 6, '0', STRIP_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Update user with new verification code
    $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE id = ?");
    $result = $stmt->execute([$verificationCode, $expiresAt, $user['id']]);

    if (!$result) {
        throw new Exception('Failed to update verification code. Please try again.');
    }

    // Send verification email
    if (sendVerificationEmail($email, $verificationCode, $user['username'])) {
        $response['success'] = true;
        $response['message'] = 'A new verification code has been sent to your email address.';
    } else {
        throw new Exception('Failed to send verification email. Please try again later.');
    }

} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
    $response['message'] = 'A database error occurred. Please try again later.';
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Return JSON response
echo json_encode($response);

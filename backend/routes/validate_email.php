<?php
require_once '../db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

require_once '../controllers/authController.php';
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed.']);
    exit();
}

// Retrieve and decode JSON payload
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid JSON payload.']);
    exit();
}

// Validate email
if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
    exit();
}
$email = trim($data['email']);

try {
    // Check if the email already exists
    if (emailExists($email)) {
        echo json_encode(['status' => false, 'message' => 'This email is already registered.']);
        exit();
    }

    // Generate verification code
    $verificationCode = generateVerificationCode(6); // 6-digit code
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Log the verification code (for debugging - remove in production)
    error_log("Generated verification code for $email: $verificationCode");

    // Store verification code in session
    $_SESSION['email_verification'] = [
        'email' => $email,
        'code' => $verificationCode,
        'expiry' => $expiry,
        'attempts' => 0
    ];

    // Send verification email
    if (sendEmailVerification($email, $verificationCode)) {
        echo json_encode([
            'status' => true,
            'message' => 'Verification code sent to your email. Please check your inbox and enter the code below.',
            'requiresVerification' => true
        ]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Failed to send verification email. Please try again.']);
    }
} catch (Exception $e) {
    error_log('Email validation error: ' . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'An error occurred during email validation.']);
}

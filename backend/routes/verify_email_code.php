<?php
require_once '../db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

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

// Validate verification code
if (empty($data['verificationCode']) || !is_numeric($data['verificationCode'])) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Verification code is required.']);
    exit();
}
$enteredCode = trim($data['verificationCode']);

// Check if verification session exists
if (!isset($_SESSION['email_verification'])) {
    echo json_encode(['status' => false, 'message' => 'No verification session found. Please start the signup process again.']);
    exit();
}

$verification = $_SESSION['email_verification'];

// Check if code has expired
if (strtotime($verification['expiry']) < time()) {
    unset($_SESSION['email_verification']);
    echo json_encode(['status' => false, 'message' => 'Verification code has expired. Please request a new one.']);
    exit();
}

// Check attempts limit
if ($verification['attempts'] >= 3) {
    unset($_SESSION['email_verification']);
    echo json_encode(['status' => false, 'message' => 'Too many failed attempts. Please request a new verification code.']);
    exit();
}

// Verify the code
if ($enteredCode === $verification['code']) {
    // Code is correct, mark email as verified
    $_SESSION['email_verified'] = [
        'email' => $verification['email'],
        'verified_at' => date('Y-m-d H:i:s')
    ];

    // Clear verification session
    unset($_SESSION['email_verification']);

    echo json_encode([
        'status' => true,
        'message' => 'Email verified successfully! You can now complete your registration.',
        'verified' => true
    ]);
} else {
    // Code is incorrect, increment attempts
    $_SESSION['email_verification']['attempts']++;

    $remainingAttempts = 3 - $_SESSION['email_verification']['attempts'];

    if ($remainingAttempts > 0) {
        echo json_encode([
            'status' => false,
            'message' => "Invalid verification code. {$remainingAttempts} attempts remaining."
        ]);
    } else {
        unset($_SESSION['email_verification']);
        echo json_encode(['status' => false, 'message' => 'Too many failed attempts. Please request a new verification code.']);
    }
}

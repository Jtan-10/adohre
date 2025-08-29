<?php
// Debug endpoint for testing email verification
require_once '../db/db_connect.php';
require_once '../controllers/authController.php';

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

// Get test email from request
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!isset($data['testEmail']) || !filter_var($data['testEmail'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => false, 'message' => 'Valid test email required.']);
    exit();
}

$testEmail = trim($data['testEmail']);

try {
    // Generate verification code
    $verificationCode = generateVerificationCode(6);
    error_log("Test: Generated verification code for $testEmail: $verificationCode");

    // Send verification email
    $result = sendEmailVerification($testEmail, $verificationCode);

    if ($result) {
        echo json_encode([
            'status' => true,
            'message' => 'Test email sent successfully!',
            'debug' => [
                'email' => $testEmail,
                'code_generated' => $verificationCode,
                'email_sent' => true
            ]
        ]);
    } else {
        echo json_encode([
            'status' => false,
            'message' => 'Failed to send test email. Check server logs for details.',
            'debug' => [
                'email' => $testEmail,
                'code_generated' => $verificationCode,
                'email_sent' => false
            ]
        ]);
    }
} catch (Exception $e) {
    error_log('Test email error: ' . $e->getMessage());
    echo json_encode([
        'status' => false,
        'message' => 'An error occurred during testing.',
        'debug' => ['error' => $e->getMessage()]
    ]);
}

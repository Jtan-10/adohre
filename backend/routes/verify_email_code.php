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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['code']) || !isset($input['email'])) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Missing required fields']);
    exit();
}

$code = trim($input['code']);
$email = trim($input['email']);

// Verify email and code match session data
if (!isset($_SESSION['email_verification']) || 
    $_SESSION['email_verification']['email'] !== $email || 
    $_SESSION['email_verification']['code'] !== $code ||
    strtotime($_SESSION['email_verification']['expiry']) < time()) {
    
    echo json_encode([
        'status' => false,
        'message' => 'Invalid or expired verification code.'
    ]);
    exit();
}

// If we get here, the verification was successful
$_SESSION['email_verified'] = true;
$_SESSION['verified_email'] = $email;

// Clear verification data
unset($_SESSION['email_verification']);

echo json_encode([
    'status' => true,
    'message' => 'Email verified successfully.'
]);

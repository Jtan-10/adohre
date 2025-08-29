<?php
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

// Check if we have a valid OTP session
if (!isset($_SESSION['otp_email'])) {
    echo json_encode(['status' => false, 'message' => 'No active OTP session.']);
    exit();
}

$email = $_SESSION['otp_email'];

// Check if user exists
$user = emailExists($email);
if (!$user) {
    echo json_encode(['status' => false, 'message' => 'User not found.']);
    exit();
}

// Generate new OTP
$otp = generateVerificationCode(6);
$expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Store OTP in database
$stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?");
$stmt->bind_param("sss", $otp, $expiry, $email);
$result = $stmt->execute();
$stmt->close();

if (!$result) {
    echo json_encode(['status' => false, 'message' => 'Failed to generate OTP.']);
    exit();
}

// Send OTP email
if (sendEmailOTP($email, $otp)) {
    echo json_encode([
        'status' => true,
        'message' => 'New OTP sent successfully to your email.'
    ]);
} else {
    echo json_encode(['status' => false, 'message' => 'Failed to send OTP email.']);
}

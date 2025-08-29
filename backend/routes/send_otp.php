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

// Check if user exists
$user = emailExists($email);
if (!$user) {
    echo json_encode(['status' => false, 'message' => 'User not found.']);
    exit();
}

// Generate OTP
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
    // Store session data for OTP verification
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_action'] = 'login';

    echo json_encode([
        'status' => true,
        'message' => 'OTP sent successfully to your email.',
        'requiresOTP' => true
    ]);
} else {
    echo json_encode(['status' => false, 'message' => 'Failed to send OTP email.']);
}
?>

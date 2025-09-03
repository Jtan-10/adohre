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

// Validate OTP (ensure it's provided; further validation may be added as needed)
if (empty($data['otp']) || empty(trim($data['otp']))) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'OTP is required.']);
    exit();
}
$otp = trim($data['otp']);

// (Optional) Implement rate limiting here to prevent brute-force OTP attempts

// Verify OTP using the function defined in authController.php
if (verifyOTP($email, $otp)) {
    global $conn;
    if (!$conn) {
        error_log('Database connection not initialized');
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Internal server error.']);
        exit();
    }

    $stmt = $conn->prepare("SELECT user_id, first_name, last_name, profile_image, role FROM users WHERE email = ?");
    if (!$stmt) {
        error_log('DB prepare error: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Internal server error.']);
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        // Set session variables for the authenticated user
        $_SESSION['user_id']       = $user['user_id'];
        $_SESSION['first_name']    = $user['first_name'];
        $_SESSION['last_name']     = $user['last_name'];
        $_SESSION['profile_image'] = $user['profile_image'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['otp_pending']   = false; // Clear OTP pending status

        // Determine action: if session indicates reset flow, mark otp_verified and allow reset page
        if (isset($_SESSION['action']) && $_SESSION['action'] === 'reset') {
            $_SESSION['otp_verified'] = true;
            recordAuditLog($user['user_id'], 'OTP Verified', 'OTP verified for password setup.');
            echo json_encode(['status' => true, 'message' => 'OTP verified. You can now set your password.', 'redirect' => 'reset_password.php']);
        } else {
            // Normal login success
            recordAuditLog($user['user_id'], 'Login Successful', 'User logged in successfully.');
            echo json_encode(['status' => true, 'message' => 'OTP verified! Login successful.']);
        }
    } else {
        // Do not reveal details about whether the email exists
        echo json_encode(['status' => false, 'message' => 'Invalid credentials.']);
    }
} else {
    recordAuditLog(0, 'OTP verification failed', 'Failed OTP verification attempt for email: ' . $email);

    echo json_encode(['status' => false, 'message' => 'Invalid OTP.']);
}

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../controllers/authController.php';
require_once '../db/db_connect.php'; // Ensure this is included for database connection



header('Content-Type: application/json');

// Allow OPTIONS method for preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    exit();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session security based on environment
    configureSessionSecurity();
    session_start();
}

// Determine action; default to 'finalize'
$action = $_POST['action'] ?? 'finalize';

// First, try to decode JSON input
$data = json_decode(file_get_contents("php://input"), true);
// If JSON decoding fails, fallback to $_POST (for multipart/form-data requests)
if (!$data) {
    $data = $_POST;
}

// Check the request method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {


        // -------------------------
        // Email Login with OTP
        // -------------------------
        $email = $data['email'] ?? null;
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("login.php backend: Invalid or missing email address");
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid or missing email address.']);
            exit();
        }
        if (emailExists($email)) {
            if (generateOTP($email)) {
                echo json_encode(['status' => true, 'message' => 'OTP sent to your email.']);
            } else {
                error_log("login.php backend: Failed to send OTP to email: " . $email);
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Failed to send OTP.']);
            }
        } else {
            error_log("login.php backend: Email not registered: " . $email);
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Email not registered.']);
        }
        exit();
    } catch (Exception $e) {
        error_log('Error in login.php: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Internal server error.']);
        exit();
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method Not Allowed.']);
}

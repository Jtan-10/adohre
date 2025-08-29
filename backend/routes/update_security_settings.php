<?php
require_once '../db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['otpEnabled'])) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    $otpEnabled = $input['otpEnabled'] ? 1 : 0;

    // Update or insert user settings
    $stmt = $conn->prepare("
        INSERT INTO user_settings (user_id, otp_enabled, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            otp_enabled = VALUES(otp_enabled),
            updated_at = NOW()
    ");

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("ii", $userId, $otpEnabled);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'status' => true,
        'message' => 'Security settings updated successfully'
    ]);
} catch (Exception $e) {
    error_log('Error updating security settings: ' . $e->getMessage());
    echo json_encode([
        'status' => false,
        'message' => 'An error occurred while updating security settings'
    ]);
}

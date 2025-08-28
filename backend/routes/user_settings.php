<?php
require_once '../db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Verify that the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get user's settings
            $stmt = $conn->prepare("SELECT otp_enabled FROM user_settings WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                echo json_encode(['status' => true, 'settings' => $row]);
            } else {
                // If no settings exist, create default settings
                $stmt = $conn->prepare("INSERT INTO user_settings (user_id, otp_enabled) VALUES (?, 1)");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();

                echo json_encode(['status' => true, 'settings' => ['otp_enabled' => 1]]);
            }
            break;

        case 'POST':
            // Update user's settings
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['otp_enabled'])) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Missing otp_enabled parameter']);
                exit;
            }

            $otp_enabled = (int)!!$data['otp_enabled']; // Convert to 0 or 1

            $stmt = $conn->prepare("
                INSERT INTO user_settings (user_id, otp_enabled) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE otp_enabled = VALUES(otp_enabled)
            ");
            $stmt->bind_param("ii", $user_id, $otp_enabled);

            if ($stmt->execute()) {
                // Record the change in audit log
                recordAuditLog(
                    $user_id,
                    "Update OTP Settings",
                    "User " . ($otp_enabled ? "enabled" : "disabled") . " OTP verification"
                );

                echo json_encode([
                    'status' => true,
                    'message' => 'OTP settings updated successfully',
                    'settings' => ['otp_enabled' => $otp_enabled]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Failed to update OTP settings']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['status' => false, 'message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("Error in user_settings.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Internal server error']);
}

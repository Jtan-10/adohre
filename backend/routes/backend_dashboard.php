<?php
// Disable error display for production
ini_set('display_errors', 0);
error_reporting(0);

require_once '../controllers/authController.php';
require_once '../db/db_connect.php';
header('Content-Type: application/json');

// Add secure headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");


// Start the session securely
session_start();

// Ensure the user is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => false, 'message' => 'Access denied.']);
    exit;
}

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Use sanitized input
    $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? null;

    if ($action === 'stats') {
        // Fetch dashboard stats
        $users_count = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
        $pending_otps = $conn->query("SELECT COUNT(*) AS total FROM users WHERE otp_code IS NOT NULL")->fetch_assoc()['total'];

        echo json_encode([
            'status' => true,
            'data' => [
                'total_users' => $users_count,
                'pending_otps' => $pending_otps,
            ],
        ]);
    } elseif ($action === 'fetch_users') {
        // Fetch all users
        $users = [];
        $result = $conn->query("SELECT user_id, first_name, last_name, email, role FROM users");
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(['status' => true, 'data' => $users]);
    } elseif ($action === 'fetch_otps') {
        // Fetch all pending OTPs
        $otps = [];
        $result = $conn->query("SELECT email, otp_code, otp_expiry FROM users WHERE otp_code IS NOT NULL");
        while ($row = $result->fetch_assoc()) {
            $otps[] = $row;
        }
        echo json_encode(['status' => true, 'data' => $otps]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    // Validate JSON input
    if (!is_array($data) || !isset($data['action'])) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid request data.']);
        exit;
    }

    $adminId = $_SESSION['user_id'];

    if ($data['action'] === 'delete_user') {
        // Validate and cast user_id
        $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        if ($user_id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid user ID.']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // Audit log: record user deletion
            recordAuditLog($adminId, "Delete User", "Deleted user with ID $user_id.");
            echo json_encode(['status' => true, 'message' => 'User deleted successfully.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to delete user.']);
        }
    } elseif ($data['action'] === 'reset_otp') {
        // Validate email
        $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid email.']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            // Audit log: record OTP reset
            recordAuditLog($adminId, "Reset OTP", "Reset OTP for email $email.");
            echo json_encode(['status' => true, 'message' => 'OTP reset successfully.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to reset OTP.']);
        }
    }
}
?>
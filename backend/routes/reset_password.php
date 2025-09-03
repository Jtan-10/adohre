<?php
require_once '../db/db_connect.php';
require_once '../utils/password_policy.php';
header('Content-Type: application/json');

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed.']);
    exit();
}

// Must be coming from verified OTP reset flow
if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true || !isset($_SESSION['action']) || $_SESSION['action'] !== 'reset' || empty($_SESSION['email'])) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Session expired or invalid. Please restart the reset process.']);
    exit();
}

$email = $_SESSION['email'];
$newPassword = $_POST['password'] ?? '';

// Validate input
if (empty($newPassword)) {
    echo json_encode(['status' => false, 'message' => 'New password is required.']);
    exit();
}

// Validate password strength
$passwordValidation = validatePassword($newPassword);
if ($passwordValidation !== true) {
    echo json_encode([
        'status' => false,
        'message' => 'Password does not meet requirements: ' . implode(', ', $passwordValidation)
    ]);
    exit();
}

try {
    // Update password
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("ss", $passwordHash, $email);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('No user found with this email.');
    }

    $stmt->close();

    // Clear any existing remember me tokens for security
    $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = (SELECT user_id FROM users WHERE email = ?)");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();

    // Record password reset in audit log
    $userStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $userStmt->bind_param("s", $email);
    $userStmt->execute();
    $userStmt->bind_result($userId);
    $userStmt->fetch();
    $userStmt->close();

    recordAuditLog($userId, 'Password Reset', 'Password was reset successfully.');

    // Clear OTP and related session flags
    unset($_SESSION['otp_verified']);
    unset($_SESSION['otp_pending']);
    unset($_SESSION['otp_email']);
    unset($_SESSION['otp_action']);
    unset($_SESSION['temp_user']);
    unset($_SESSION['action']);

    echo json_encode([
        'status' => true,
        'message' => 'Password has been reset successfully. You can now log in with your new password.',
        'redirect' => 'login.php'
    ]);
} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    echo json_encode([
        'status' => false,
        'message' => 'An error occurred during password reset. Please try again later.'
    ]);
}

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

// Get input
$email = $_POST['email'] ?? '';
$newPassword = $_POST['password'] ?? '';
$otp = $_POST['otp'] ?? '';

// Validate input
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
    exit();
}

if (empty($newPassword)) {
    echo json_encode(['status' => false, 'message' => 'New password is required.']);
    exit();
}

if (empty($otp)) {
    echo json_encode(['status' => false, 'message' => 'OTP is required.']);
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
    // Verify OTP first
    if (!verifyOTP($email, $otp)) {
        echo json_encode(['status' => false, 'message' => 'Invalid or expired OTP.']);
        exit();
    }

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

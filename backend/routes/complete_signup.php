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
$firstName = $_POST['firstName'] ?? '';
$lastName = $_POST['lastName'] ?? '';
$password = $_POST['password'] ?? '';

// Validate input
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
    exit();
}

if (empty($firstName) || empty($lastName)) {
    echo json_encode(['status' => false, 'message' => 'Name fields are required.']);
    exit();
}

if (empty($password)) {
    echo json_encode(['status' => false, 'message' => 'Password is required.']);
    exit();
}

// Validate password strength
$passwordValidation = validatePassword($password);
if ($passwordValidation !== true) {
    echo json_encode([
        'status' => false,
        'message' => 'Password does not meet requirements: ' . implode(', ', $passwordValidation)
    ]);
    exit();
}

try {
    // Check if email has been verified
    if (!isset($_SESSION['email_verified']) || !isset($_SESSION['verified_email']) || $_SESSION['verified_email'] !== $email) {
        echo json_encode(['status' => false, 'message' => 'Email verification required.']);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert or update user
    $stmt = $conn->prepare("
        INSERT INTO users (email, first_name, last_name, password_hash, is_profile_complete, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            password_hash = VALUES(password_hash),
            is_profile_complete = 1,
            updated_at = NOW()
    ");

    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("ssss", $email, $firstName, $lastName, $passwordHash);
    $stmt->execute();
    $userId = $stmt->insert_id ?: $conn->query("SELECT user_id FROM users WHERE email = '$email'")->fetch_object()->user_id;
    $stmt->close();

    // Set session variables
    $_SESSION['user_id'] = $userId;
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;
    $_SESSION['role'] = 'member'; // Default role for new users

    // Record signup in audit log
    recordAuditLog($userId, 'User Registration', 'New user registered successfully.');

    // Clear email verification session data
    unset($_SESSION['email_verified']);
    unset($_SESSION['verified_email']);

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => true,
        'message' => 'Registration completed successfully.',
        'redirect' => 'index.php'
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        'status' => false,
        'message' => 'An error occurred during registration. Please try again later.'
    ]);
}

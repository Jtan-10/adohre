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

// Check if email has been verified
if (
    !isset($_SESSION['email_verified']) ||
    !isset($_SESSION['email_verified']['email']) ||
    !isset($_SESSION['email_verified']['verified_at']) ||
    $_SESSION['email_verified']['email'] !== $email ||
    strtotime($_SESSION['email_verified']['verified_at']) < (time() - 3600)
) { // 1 hour expiry
    echo json_encode(['status' => false, 'message' => 'Email verification required. Please verify your email first.']);
    exit();
}

try {
    // Verify database connection
    if (!$conn || $conn->connect_error) {
        throw new Exception('Database connection failed: ' . ($conn ? $conn->connect_error : 'Connection not established'));
    }

    // Start transaction
    $conn->begin_transaction();

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert or update user - handle missing columns gracefully
    $stmt = $conn->prepare("
        INSERT INTO users (email, first_name, last_name, password_hash, is_profile_complete, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            password_hash = VALUES(password_hash),
            is_profile_complete = 1,
            updated_at = NOW()
    ");

    if (!$stmt) {
        // Try without the missing columns if they don't exist
        $stmt = $conn->prepare("
            INSERT INTO users (email, first_name, last_name, password_hash, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                password_hash = VALUES(password_hash)
        ");

        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error . '. Please ensure the database schema is up to date.');
        }
    }

    $stmt->bind_param("ssss", $email, $firstName, $lastName, $passwordHash);
    $result = $stmt->execute();

    if (!$result) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }

    // Get the user ID safely
    $userId = $stmt->insert_id;
    if ($userId === 0) {
        // User was updated (duplicate key), get existing user ID
        $idStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $idStmt->bind_param("s", $email);
        $idStmt->execute();
        $idResult = $idStmt->get_result();
        $userRow = $idResult->fetch_assoc();
        $userId = $userRow['user_id'] ?? 0;
        $idStmt->close();
    }

    if ($userId === 0) {
        throw new Exception('Failed to get user ID after insertion/update');
    }

    $stmt->close();

    // Set session variables
    $_SESSION['user_id'] = $userId;
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;
    $_SESSION['role'] = 'member'; // Default role for new users

    // Clear email verification session
    unset($_SESSION['email_verified']);

    // Record signup in audit log (don't fail if this fails)
    try {
        recordAuditLog($userId, 'User Registration', 'New user registered successfully.');
    } catch (Exception $auditError) {
        error_log("Audit log failed but continuing: " . $auditError->getMessage());
        // Continue with registration even if audit log fails
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => true,
        'message' => 'Registration completed successfully.',
        'redirect' => 'index.php'
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn) {
        $conn->rollback();
    }

    // Log the detailed error
    error_log("Registration error for email $email: " . $e->getMessage());

    // Provide specific error messages based on the error type
    $errorMessage = 'Registration failed. Please check your information and try again.';

    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        $errorMessage = 'An account with this email already exists. Please try logging in instead.';
    } elseif (strpos($e->getMessage(), 'database schema') !== false) {
        $errorMessage = 'Database configuration issue. Please contact support.';
    } elseif (strpos($e->getMessage(), 'connection') !== false) {
        $errorMessage = 'Database connection issue. Please try again later.';
    }

    // Return user-friendly error message
    echo json_encode([
        'status' => false,
        'message' => $errorMessage
    ]);
}

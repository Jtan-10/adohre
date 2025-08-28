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
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) && $_POST['remember'] === 'true';

// Validate input
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
    exit();
}

if (empty($password)) {
    echo json_encode(['status' => false, 'message' => 'Password is required.']);
    exit();
}

try {
    // Check if user exists and verify password
    $stmt = $conn->prepare("SELECT user_id, password_hash, first_name, last_name, profile_image, role, is_profile_complete FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['status' => false, 'message' => 'Invalid email or password.']);
        exit();
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        // Record failed login attempt
        recordAuditLog(0, 'Login Failed', "Failed password login attempt for email: {$email}");

        echo json_encode(['status' => false, 'message' => 'Invalid email or password.']);
        exit();
    }

    // Check if profile is complete
    if (!$user['is_profile_complete']) {
        // Store necessary information in session for profile completion
        $_SESSION['temp_user_id'] = $user['user_id'];
        echo json_encode([
            'status' => false,
            'message' => 'Please complete your profile.',
            'redirect' => 'complete_profile.php'
        ]);
        exit();
    }

    // Set session variables for the authenticated user
    session_regenerate_id(true); // Prevent session fixation attacks
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['profile_image'] = $user['profile_image'];
    $_SESSION['role'] = $user['role'];

    // Handle remember me functionality
    if ($remember) {
        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $token = hash('sha256', $validator);
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        // Store token in database
        $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, selector, token, expires) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user['user_id'], $selector, $token, $expires);
        $stmt->execute();
        $stmt->close();

        // Set cookie with selector and validator
        setcookie(
            'remember',
            $selector . ':' . $validator,
            strtotime('+30 days'),
            '/',
            '',
            true, // Secure
            true  // HttpOnly
        );
    }

    // Record successful login
    recordAuditLog($user['user_id'], 'Login Successful', 'User logged in successfully with password.');

    echo json_encode([
        'status' => true,
        'message' => 'Login successful.',
        'redirect' => 'index.php'
    ]);
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode([
        'status' => false,
        'message' => 'An error occurred during login. Please try again later.'
    ]);
}

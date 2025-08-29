<?php
require_once '../db/db_connect.php';
require_once '../utils/password_policy.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    $stmt = $conn->prepare("
        SELECT u.user_id, u.password_hash, u.first_name, u.last_name, u.profile_image, u.role, u.is_profile_complete,
               COALESCE(us.otp_enabled, 0) as otp_enabled
        FROM users u
        LEFT JOIN user_settings us ON u.user_id = us.user_id
        WHERE u.email = ?
    ");
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
        recordAuditLog($user['user_id'], 'Failed Login Attempt', 'Failed password login attempt');
        echo json_encode(['status' => false, 'message' => 'Invalid email or password.']);
        exit();
    }

    // If OTP is enabled for this user, initiate OTP flow
    if ($user['otp_enabled']) {
        // Store user data in session temporarily
        $_SESSION['temp_user'] = [
            'user_id' => $user['user_id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'profile_image' => $user['profile_image'],
            'role' => $user['role'],
            'is_profile_complete' => $user['is_profile_complete'],
            'remember' => $remember
        ];

        // Generate and send OTP
        $otp = generateOTP();
        $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $otp, $expiry, $user['user_id']);
        $stmt->execute();
        $stmt->close();

        // Send OTP email
        if (sendOTPEmail($email, $otp)) {
            $_SESSION['action'] = 'login';
            $_SESSION['otp_pending'] = true; // Mark OTP as pending
            echo json_encode([
                'status' => true,
                'message' => 'OTP sent successfully.',
                'redirect' => 'otp.php',
                'requiresOTP' => true
            ]);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to send OTP email.']);
        }
        exit();
    }

    // If OTP is not enabled, complete login immediately
    completeLogin($user['user_id'], $user['first_name'], $user['last_name'], $user['profile_image'], $user['role'], $user['is_profile_complete'], $remember);
    recordAuditLog($user['user_id'], 'User Login', 'User logged in using password');

    echo json_encode([
        'status' => true,
        'message' => 'Login successful.',
        'redirect' => 'index.php'
    ]);
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'An error occurred during login.']);
}

function generateOTP($length = 6)
{
    return str_pad(strval(mt_rand(0, pow(10, $length) - 1)), $length, '0', STR_PAD_LEFT);
}

function sendOTPEmail($email, $otp)
{
    // Load PHPMailer
    require_once '../../vendor/autoload.php';

    $mail = new PHPMailer(true);
    try {
        // SMTP setup
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port       = $_ENV['SMTP_PORT'];

        // Set sender and recipient
        $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($email);
        $mail->Subject = 'Your OTP Code for Login';
        $mail->Body = "Your OTP for login is: $otp. This code will expire in 5 minutes.";

        // Attempt to send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

function completeLogin($userId, $firstName, $lastName, $profileImage, $role, $isProfileComplete, $remember)
{
    $_SESSION['user_id'] = $userId;
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;
    $_SESSION['profile_image'] = $profileImage;
    $_SESSION['role'] = $role;
    $_SESSION['is_profile_complete'] = $isProfileComplete;

    // Handle remember me functionality if needed
    if ($remember) {
        // Implementation for remember me tokens
        // This should be implemented securely
    }
}

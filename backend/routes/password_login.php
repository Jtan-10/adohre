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
    // Check database connection
    if (!$conn || $conn->connect_error) {
        error_log('Database connection failed: ' . ($conn ? $conn->connect_error : 'Connection not established'));
        echo json_encode(['status' => false, 'message' => 'Database connection failed.']);
        exit();
    }

    // Check if user exists and verify password
    $stmt = $conn->prepare("
        SELECT user_id, password_hash, first_name, last_name, profile_image, role, is_profile_complete
        FROM users 
        WHERE email = ?
    ");

    if (!$stmt) {
        error_log('Database prepare error: ' . $conn->error);
        echo json_encode(['status' => false, 'message' => 'Database error. Please try again.']);
        exit();
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        error_log('User not found: ' . $email);
        echo json_encode(['status' => false, 'message' => 'Invalid email or password.']);
        exit();
    }

    // Debug: Log user data
    error_log('User found: ' . $email . ', user_id: ' . $user['user_id'] . ', password_hash exists: ' . (!empty($user['password_hash']) ? 'yes' : 'no'));

    // If user has no password set (created by admin/import), trigger password setup via OTP flow
    if (empty($user['password_hash'])) {
        // Set session for password reset flow via OTP
        $_SESSION['temp_user'] = [
            'user_id' => $user['user_id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'profile_image' => $user['profile_image'],
            'role' => $user['role'],
            'is_profile_complete' => $user['is_profile_complete'],
            'remember' => $remember
        ];
        $_SESSION['email'] = $email;
        $_SESSION['action'] = 'reset';
        // For resend support, set otp session identifiers
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_action'] = 'reset';

        // Generate and send OTP
        $otp = generateOTP();
        $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $otp, $expiry, $user['user_id']);
        $stmt->execute();
        $stmt->close();

        if (sendOTPEmail($email, $otp)) {
            $_SESSION['otp_pending'] = true;
            // Ensure session data is written to disk
            session_write_close();
            session_start();
            echo json_encode([
                'status' => true,
                'message' => 'Please set your password. We sent an OTP to your email.',
                'requiresOTP' => true,
                'needsPasswordSetup' => true,
                'redirect' => 'otp.php'
            ]);
        } else {
            error_log('Failed to send OTP email for password setup to: ' . $email);
            echo json_encode(['status' => false, 'message' => 'Failed to send OTP email.']);
        }
        exit();
    }

    if (!password_verify($password, $user['password_hash'])) {
        error_log('Password verification failed for user: ' . $email);
        echo json_encode(['status' => false, 'message' => 'Invalid email or password.']);
        exit();
    }

    error_log('Password verification successful for user: ' . $email);

    // Check if OTP is enabled for this user
    $otp_enabled = 0;
    $stmt = $conn->prepare("SELECT otp_enabled FROM user_settings WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $settings = $result->fetch_assoc();
        $stmt->close();
        $otp_enabled = $settings ? $settings['otp_enabled'] : 0;
    }

    // Debug log OTP status
    error_log('OTP enabled for user ' . $email . ': ' . ($otp_enabled ? 'Yes' : 'No'));

    // If OTP is enabled for this user, initiate OTP flow
    if ($otp_enabled) {
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

        // Store email in session for OTP verification
        $_SESSION['email'] = $email;
        // For resend support, set otp session identifiers
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_action'] = 'login';

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

            // Ensure session data is written to disk
            session_write_close();
            session_start();

            error_log('OTP flow initiated for user: ' . $email . '. Session ID: ' . session_id());
            error_log('Session data before redirect: ' . json_encode($_SESSION));

            echo json_encode([
                'status' => true,
                'message' => 'OTP sent successfully.',
                'redirect' => 'otp.php',
                'requiresOTP' => true,
                'debug_session' => session_id()
            ]);
        } else {
            error_log('Failed to send OTP email to: ' . $email);
            echo json_encode(['status' => false, 'message' => 'Failed to send OTP email.']);
        }
        exit();
    }

    // If OTP is not enabled, complete login immediately
    completeLogin($user['user_id'], $user['first_name'], $user['last_name'], $user['profile_image'], $user['role'], $user['is_profile_complete'], $remember);
    recordAuditLog($user['user_id'], 'User Login', 'User logged in using password');

    // Clear any existing OTP session data
    unset($_SESSION['otp_pending']);
    unset($_SESSION['temp_user']);
    unset($_SESSION['action']);

    // Ensure session data is written to disk
    session_write_close();

    // Log the session status before responding
    error_log('Login complete for user_id: ' . $user['user_id'] . ', Session ID: ' . session_id());

    // Debug output to log user state
    $debug_info = [
        'user_id' => $user['user_id'],
        'session_id' => session_id(),
        'session_data' => isset($_SESSION) ? array_keys($_SESSION) : []
    ];
    error_log('DEBUG SESSION: ' . json_encode($debug_info));

    echo json_encode([
        'status' => true,
        'message' => 'Login successful.',
        'redirect' => 'index.php',
        'debug' => $debug_info // Include debug info in response
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
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    // Log session status before setting data
    error_log('Login: Setting session variables. SessionID: ' . session_id());

    // Set all session variables
    $_SESSION['user_id'] = $userId;
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;
    $_SESSION['profile_image'] = $profileImage;
    $_SESSION['role'] = $role;
    $_SESSION['is_profile_complete'] = $isProfileComplete;
    $_SESSION['logged_in_time'] = time(); // Track login time

    // Debug: Log the new session data
    error_log('Login: Session variables set. user_id=' . $userId);

    // Clear any existing OTP session data
    unset($_SESSION['otp_pending']);
    unset($_SESSION['temp_user']);
    unset($_SESSION['action']);

    // Ensure session data is written to disk
    session_write_close();
    session_start();

    // Log session after closing and reopening
    error_log('Login: Session after write_close and restart: ' . (isset($_SESSION['user_id']) ? 'User ID exists' : 'No user ID'));

    // Handle remember me functionality if needed
    if ($remember) {
        // Implementation for remember me tokens - future feature
        error_log('Remember me functionality requested but not implemented yet');
    }
}

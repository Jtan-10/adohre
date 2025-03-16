<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
require_once __DIR__ . '/../db/db_connect.php';

function login($email, $password)
{
    global $conn;

    // Sanitize input
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    $stmt = $conn->prepare('SELECT user_id, first_name, profile_image, role, password FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            // Log successful login
            recordAuditLog($user['user_id'], "User Login", "User logged in successfully using email: $email");
            return [
                'status' => true,
                'data' => [
                    'user_id' => $user['user_id'],
                    'first_name' => $user['first_name'],
                    'profile_image' => $user['profile_image'],
                    'role' => $user['role']
                ]
            ];
        }
    }
    // Log failed login attempt
    recordAuditLog(0, "Failed Login Attempt", "Failed login attempt for email: $email");
    return ['status' => false, 'message' => 'Invalid email or password.'];
}

function register($first_name, $last_name, $email, $password, $confirm_password)
{
    global $conn;

    // Sanitize input
    $first_name = htmlspecialchars($first_name);
    $last_name = htmlspecialchars($last_name);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    // Input validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        return ['status' => false, 'message' => 'All fields are required.'];
    }

    if ($password !== $confirm_password) {
        return ['status' => false, 'message' => 'Passwords do not match.'];
    }

    // Check if email is already registered
    $stmt = $conn->prepare('SELECT email FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        return ['status' => false, 'message' => 'Email is already registered.'];
    }

    // Insert user into database
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO users (first_name, last_name, email, password) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $first_name, $last_name, $email, $hashed_password);

    if ($stmt->execute()) {
        $newUserId = $conn->insert_id;
        // Log the successful registration
        recordAuditLog($newUserId, "User Registration", "Account created for email: $email");
        return ['status' => true, 'message' => 'Account created successfully.'];
    } else {
        recordAuditLog(0, "User Registration Failed", "Registration failed for email: $email");
        return ['status' => false, 'message' => 'An error occurred. Please try again.'];
    }
}

function generateOTP($email)
{
    global $conn;

    // Sanitize input
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("Email not found for OTP generation: $email");
        return false; // Email not found
    }

    // Use cryptographically secure random_int instead of rand
    $otp = random_int(100000, 999999);
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?");
    $stmt->bind_param("sss", $otp, $expiry, $email);

    if ($stmt->execute()) {
        // Log OTP generation without revealing the OTP
        $userRow = $conn->query("SELECT user_id FROM users WHERE email = '$email'")->fetch_assoc();
        $userId = $userRow ? $userRow['user_id'] : 0;
        recordAuditLog($userId, "OTP Generation", "OTP generated for email: $email, expires at $expiry");
        // Removed detailed OTP logging for security
        return sendEmailOTP($email, $otp);
    }

    error_log("Failed to generate OTP for email: $email");
    return false;
}

function verifyOTP($email, $enteredOtp)
{
    global $conn;

    // Sanitize input
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    // Check OTP and expiry in the database
    $stmt = $conn->prepare("SELECT otp_code, otp_expiry, user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        // Use hash_equals for secure comparison of OTP codes
        if (hash_equals((string)$row['otp_code'], (string)$enteredOtp) && strtotime($row['otp_expiry']) > time()) {
            // Clear OTP after successful verification
            $stmt = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            // Log successful OTP verification
            recordAuditLog($row['user_id'], "OTP Verification", "OTP verified successfully for email: $email");
            return true;
        }
    }
    // Log failed OTP verification
    recordAuditLog(0, "OTP Verification Failed", "Failed OTP verification for email: $email");
    return false;
}

function emailExists($email)
{
    global $conn;

    // Sanitize input
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    error_log("Checking if email exists: $email, Result: " . ($result->num_rows > 0 ? "Found" : "Not Found"));
    return $result->fetch_assoc();
}

function sendEmailOTP($email, $otp)
{
    // Ensure session is started.
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Log the OTP request (do not log the OTP itself).
    error_log("sendEmailOTP request for email: " . $email . " at " . date('Y-m-d H:i:s'));

    // Rate limiting: allow a maximum of 5 OTP requests per email within 10 minutes.
    $window = 600;           // 10 minutes in seconds
    $maxRequests = 5;        // Maximum allowed requests per window
    $now = time();

    if (!isset($_SESSION['otp_requests'])) {
        $_SESSION['otp_requests'] = [];
    }

    if (!isset($_SESSION['otp_requests'][$email])) {
        $_SESSION['otp_requests'][$email] = ['count' => 0, 'first_request_time' => $now];
    }

    // Reset count if the window has expired.
    if ($now - $_SESSION['otp_requests'][$email]['first_request_time'] > $window) {
        $_SESSION['otp_requests'][$email]['count'] = 0;
        $_SESSION['otp_requests'][$email]['first_request_time'] = $now;
    }

    // Check if the rate limit has been reached.
    if ($_SESSION['otp_requests'][$email]['count'] >= $maxRequests) {
        error_log("Rate limit exceeded for email: " . $email);
        return false;
    }

    // Increment the OTP request count.
    $_SESSION['otp_requests'][$email]['count']++;

    $mail = new PHPMailer(true);
    try {
        // SMTP setup.
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port       = $_ENV['SMTP_PORT'];

        // Set sender and recipient.
        $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($email);
        $mail->Subject = 'Your OTP Code';
        $mail->Body = "Your OTP for login/signup is: $otp. This code will expire in 10 minutes.";

        // Attempt to send the email.
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

function generateVirtualId($length = 16)
{
    return bin2hex(random_bytes($length / 2)); // Generates a random hex string
}

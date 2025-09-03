<?php
require_once '../db/db_connect.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Configure session security based on environment
configureSessionSecurity();
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed.']);
    exit();
}

$email = trim($_POST['email'] ?? '');
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => false, 'message' => 'Invalid email.']);
    exit();
}

$stmt = $conn->prepare('SELECT user_id, first_name, last_name, profile_image, role, is_profile_complete, password_hash FROM users WHERE email = ?');
if (!$stmt) {
    echo json_encode(['status' => false, 'message' => 'DB error']);
    exit();
}
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['status' => false, 'message' => 'Email not found']);
    exit();
}

if (!empty($user['password_hash'])) {
    echo json_encode(['status' => false, 'message' => 'Account already has a password.']);
    exit();
}

// Create OTP and session context for reset
$otp = str_pad(strval(mt_rand(0, 999999)), 6, '0', STR_PAD_LEFT);
$expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
$stmt = $conn->prepare('UPDATE users SET otp_code = ?, otp_expiry = ? WHERE user_id = ?');
$stmt->bind_param('ssi', $otp, $expiry, $user['user_id']);
$stmt->execute();
$stmt->close();

$_SESSION['temp_user'] = [
    'user_id' => $user['user_id'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'profile_image' => $user['profile_image'],
    'role' => $user['role'],
    'is_profile_complete' => $user['is_profile_complete']
];
$_SESSION['email'] = $email;
$_SESSION['action'] = 'reset';
$_SESSION['otp_pending'] = true;

// Send OTP mail
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'];
    $mail->Password = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
    $mail->Port = $_ENV['SMTP_PORT'];
    $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
    $mail->addAddress($email);
    $mail->Subject = 'Your OTP Code for Password Setup';
    $mail->Body = "Your OTP for password setup is: $otp. This code will expire in 5 minutes.";
    $mail->send();
} catch (Exception $e) {
    error_log('OTP Email Error: ' . $mail->ErrorInfo);
    echo json_encode(['status' => false, 'message' => 'Failed to send OTP email.']);
    exit();
}

echo json_encode(['status' => true, 'redirect' => 'otp.php', 'requiresOTP' => true, 'needsPasswordSetup' => true]);
exit();

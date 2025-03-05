<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../vendor/autoload.php';
require_once __DIR__ . '/../db/db_connect.php';

function login($email, $password) {
    global $conn;

    $stmt = $conn->prepare('SELECT user_id, first_name, profile_image, role, password FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            return [
                'status' => true,
                'data' => [
                    'user_id' => $user['user_id'],
                    'first_name' => $user['first_name'],
                    'profile_image' => $user['profile_image'], // Make sure this field is returned
                    'role' => $user['role']
                ]
            ];
        }
    }
    return ['status' => false, 'message' => 'Invalid email or password.'];
}

function register($first_name, $last_name, $email, $password, $confirm_password) {
    global $conn;

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
        return ['status' => true, 'message' => 'Account created successfully.'];
    } else {
        return ['status' => false, 'message' => 'An error occurred. Please try again.'];
    }
}

function generateOTP($email) {
    global $conn;

    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("Email not found for OTP generation: $email");
        return false; // Email not found
    }

    
    $otp = rand(100000, 999999);
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?");
    $stmt->bind_param("sss", $otp, $expiry, $email);

    if ($stmt->execute()) {
        error_log("OTP generated for email: $email, OTP: $otp");
        return sendEmailOTP($email, $otp);
    }

    error_log("Failed to generate OTP for email: $email");
    return false;
}



function verifyOTP($email, $enteredOtp) {
    global $conn;

    // Check OTP and expiry in the database
    $stmt = $conn->prepare("SELECT otp_code, otp_expiry FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        if ($row['otp_code'] === $enteredOtp && strtotime($row['otp_expiry']) > time()) {
            // Clear OTP after successful verification
            $stmt = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            return true;
        }
    }
    return false;
}

function emailExists($email) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    error_log("Checking if email exists: $email, Result: " . ($result->num_rows > 0 ? "Found" : "Not Found"));
    return $result->fetch_assoc();
}


function sendEmailOTP($email, $otp) {
    $mail = new PHPMailer(true);

    try {
        // Load SMTP settings from .env
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port       = $_ENV['SMTP_PORT'];

        // Email settings
        $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($email);
        $mail->Subject = 'Your OTP Code';
        $mail->Body = "Your OTP for login/signup is: $otp. This code will expire in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}


function generateVirtualId($length = 16) {
    return bin2hex(random_bytes($length / 2)); // Generates a random hex string
}

?>
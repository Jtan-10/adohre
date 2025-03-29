<?php
// backend/routes/complete_login.php
session_start();
header('Content-Type: application/json');

// Ensure that OTP was verified and temporary user data exists
if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['temp_user'])) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'OTP not verified or temporary session data missing.']);
    exit();
}

// (Optional: You can add additional checks here if needed)

// Promote temporary user data to full session
$user = $_SESSION['temp_user'];
$_SESSION['user_id']       = $user['user_id'];
$_SESSION['first_name']    = $user['first_name'];
$_SESSION['last_name']     = $user['last_name'];
$_SESSION['profile_image'] = $user['profile_image'];
$_SESSION['role']          = $user['role'];

// Mark that face validation is complete (i.e. full login)
$_SESSION['face_validated'] = true;

// Optionally, clear temporary data
unset($_SESSION['temp_user']);
unset($_SESSION['otp_verified']);

echo json_encode(['status' => true, 'message' => 'User fully authenticated.']);
?>
<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

echo "<h1>OTP Settings Test</h1>";

if (!$isLoggedIn) {
    echo "<p>You are not logged in. <a href='login.php'>Login here</a>.</p>";
    exit;
}

// Display current OTP settings
$stmt = $conn->prepare("SELECT otp_enabled FROM user_settings WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$settings = $result->fetch_assoc();
$stmt->close();

$otpEnabled = $settings ? (bool)$settings['otp_enabled'] : false;

echo "<h2>Current OTP Settings</h2>";
echo "<p>User ID: {$_SESSION['user_id']}</p>";
echo "<p>OTP Enabled: " . ($otpEnabled ? "Yes" : "No") . "</p>";

// Handle form submission to toggle OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_otp'])) {
    $newOtpStatus = !$otpEnabled;

    // Check if user settings exist
    if ($settings) {
        // Update existing settings
        $updateStmt = $conn->prepare("UPDATE user_settings SET otp_enabled = ? WHERE user_id = ?");
        $updateStmt->bind_param("ii", $newOtpStatus, $userId);
        $updateStmt->execute();
        $updateStmt->close();
    } else {
        // Create new settings
        $insertStmt = $conn->prepare("INSERT INTO user_settings (user_id, otp_enabled) VALUES (?, ?)");
        $insertStmt->bind_param("ii", $userId, $newOtpStatus);
        $insertStmt->execute();
        $insertStmt->close();
    }

    echo "<p>OTP settings updated successfully! New status: " . ($newOtpStatus ? "Enabled" : "Disabled") . "</p>";
    echo "<p>Refreshing...</p>";
    echo "<script>setTimeout(function() { window.location.reload(); }, 1500);</script>";
}

// Form to toggle OTP
echo "<form method='post'>";
echo "<input type='hidden' name='toggle_otp' value='1'>";
echo "<button type='submit'>" . ($otpEnabled ? "Disable OTP" : "Enable OTP") . "</button>";
echo "</form>";

// Link to test login
echo "<h2>Actions</h2>";
echo "<p><a href='logout.php'>Logout</a> (to test login with OTP)</p>";
echo "<p><a href='profile.php'>Go to Profile</a></p>";
echo "<p><a href='index.php'>Go to Homepage</a></p>";

// Check if user settings exist in database
echo "<h2>Database Check</h2>";
$checkStmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$checkStmt->bind_param("i", $userId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$hasSettings = $checkResult->num_rows > 0;
$checkStmt->close();

echo "<p>User settings record exists in database: " . ($hasSettings ? "Yes" : "No") . "</p>";

// Display session data
echo "<h2>Session Data</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

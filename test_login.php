<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Log the starting state
echo "<h1>Login Test</h1>";
echo "<pre>Starting Session ID: " . session_id() . "\n";
echo "Starting SESSION: \n";
print_r($_SESSION);
echo "</pre>";

// Simulate login with test user ID
$_SESSION['user_id'] = 999; // Test user ID
$_SESSION['first_name'] = 'Test';
$_SESSION['last_name'] = 'User';
$_SESSION['profile_image'] = 'default-profile.jpeg';
$_SESSION['role'] = 'member';
$_SESSION['is_profile_complete'] = 1;

// Save the session data
session_write_close();

// Log the session after setting values
session_start();
echo "<pre>Updated Session ID: " . session_id() . "\n";
echo "Updated SESSION: \n";
print_r($_SESSION);
echo "</pre>";

// Options for the user
echo '<p><a href="debug_session.php">View Session Details</a></p>';
echo '<p><a href="index.php">Go to Homepage</a></p>';
echo '<p><a href="#" onclick="doRedirect(); return false;">Test JavaScript Redirect</a></p>';
echo '<p><a href="logout.php">Logout</a></p>';
?>

<script>
    function doRedirect() {
        console.log("Performing JavaScript redirect...");
        window.location.href = 'index.php';
    }
</script>
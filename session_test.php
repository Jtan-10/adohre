<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Display current session state
echo "<h1>Session Test</h1>";
echo "<h2>Current Session Information</h2>";
echo "<pre>";
echo "SESSION ID: " . session_id() . "\n\n";
echo "SESSION DATA:\n";
print_r($_SESSION);
echo "</pre>";

// Display cookie information
echo "<h2>Cookie Information</h2>";
echo "<pre>";
echo "COOKIES:\n";
print_r($_COOKIE);
echo "\n\nSESSION COOKIE PARAMETERS:\n";
print_r(session_get_cookie_params());
echo "</pre>";

// Function to test setting session variables
function testSetSession()
{
    $_SESSION['test_var'] = 'Test value set at ' . date('Y-m-d H:i:s');
    session_write_close();
    session_start();
    return $_SESSION['test_var'] ?? 'Not set';
}

// Test setting and retrieving session variables
echo "<h2>Session Variable Test</h2>";
echo "<p>Setting test variable...</p>";
$testResult = testSetSession();
echo "<p>Result: $testResult</p>";

// Session regeneration test
echo "<h2>Session Regeneration Test</h2>";
$oldSessionId = session_id();
session_regenerate_id(true);
$newSessionId = session_id();
echo "<p>Old Session ID: $oldSessionId</p>";
echo "<p>New Session ID: $newSessionId</p>";
echo "<p>Session regenerated: " . ($oldSessionId !== $newSessionId ? "Yes" : "No") . "</p>";

// Database connection test
echo "<h2>Database Connection Test</h2>";
if ($conn && !$conn->connect_error) {
    echo "<p>Database connection: Success</p>";

    // Test a simple query
    $result = $conn->query("SELECT 1 as test");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Simple query result: " . ($row['test'] ?? 'No result') . "</p>";
    } else {
        echo "<p>Query failed: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Database connection failed: " . ($conn ? $conn->connect_error : 'Connection not established') . "</p>";
}

// Test user login function
echo "<h2>Login Function Test</h2>";
echo "<form method='post'>";
echo "<p><input type='email' name='test_email' placeholder='Email' required></p>";
echo "<p><input type='password' name='test_password' placeholder='Password' required></p>";
echo "<p><button type='submit' name='test_login'>Test Login</button></p>";
echo "</form>";

if (isset($_POST['test_login'])) {
    $email = $_POST['test_email'] ?? '';
    $password = $_POST['test_password'] ?? '';

    echo "<h3>Login Test Results</h3>";
    echo "<pre>";
    echo "Testing login with email: $email\n";

    // Find the user
    $stmt = $conn->prepare("SELECT user_id, password_hash, first_name, last_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo "User not found with email: $email\n";
    } else {
        echo "User found: ID=" . $user['user_id'] . ", Name=" . $user['first_name'] . " " . $user['last_name'] . "\n";

        if (empty($user['password_hash'])) {
            echo "No password hash found for user\n";
        } else {
            $passwordVerified = password_verify($password, $user['password_hash']);
            echo "Password verification: " . ($passwordVerified ? "Success" : "Failed") . "\n";

            if ($passwordVerified) {
                // Clean session
                session_unset();

                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];

                // Make sure session is saved
                session_write_close();
                session_start();

                echo "Session after login:\n";
                print_r($_SESSION);

                echo "\nLogin successful! You should now be logged in.\n";
            }
        }
    }
    echo "</pre>";
}

// Options for the user
echo "<h2>Actions</h2>";
echo "<p><a href='debug_login.php'>Debug Login</a></p>";
echo "<p><a href='test_login.php'>Test Login</a></p>";
echo "<p><a href='index.php'>Go to Homepage</a></p>";
echo "<p><a href='logout.php'>Logout</a></p>";

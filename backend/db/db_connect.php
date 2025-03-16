<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables from the .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Disable error display and enable silent error logging
ini_set('display_errors', '0');
error_reporting(0);

// Enable MySQLi exception reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Get database credentials from environment variables
$servername = $_ENV['DB_HOST'];
$username   = $_ENV['DB_USER'];
$password   = $_ENV['DB_PASS'];
$dbname     = $_ENV['DB_NAME'];

// Create a new database connection with error handling
$conn = new mysqli($servername, $username, $password, $dbname);

// Set charset to utf8mb4 for security and proper Unicode handling
$conn->set_charset('utf8mb4');

// Check connection
if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error); // Log the error
    die('Database connection failed. Please try again later.'); // Generic error message
}

// Set the time zone (from .env)
$timezone = $_ENV['TIMEZONE'] ?? '+08:00';
if (!$conn->query("SET time_zone = '$timezone'")) {
    error_log('Failed to set time zone: ' . $conn->error); // Log the error
    die('Failed to set time zone. Please try again later.'); // Generic error message
}

// ---------------------------
// Audit Logging Helper Function
// ---------------------------
if (!function_exists('recordAuditLog')) {
    /**
     * Record an audit log entry.
     *
     * @param int    $userId  The ID of the user performing the action.
     * @param string $action  A short description of the action.
     * @param string $details Additional details about the action.
     */
    function recordAuditLog($userId, $action, $details = '') {
        global $conn; // Ensure the database connection is available

        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $userId, $action, $details);
            if (!$stmt->execute()) {
                error_log("Audit log insert failed: " . $stmt->error);
            }
            $stmt->close();
        } else {
            error_log("Failed to prepare audit log statement: " . $conn->error);
        }
    }
}
?>
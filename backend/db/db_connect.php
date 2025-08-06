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
// Face Validation Helper Function
// ---------------------------
if (!function_exists('isFaceValidationEnabled')) {
    /**
     * Check if face validation is enabled.
     * First checks database settings, then falls back to .env file.
     *
     * @return bool True if face validation is enabled, false otherwise.
     */
    function isFaceValidationEnabled()
    {
        global $conn;

        // First try to get from database settings
        if ($conn) {
            $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'face_validation_enabled'");
            if ($stmt) {
                $stmt->execute();
                $stmt->bind_result($value);
                if ($stmt->fetch()) {
                    $stmt->close();
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                $stmt->close();
            }
        }

        // Fall back to .env file if not found in database
        return filter_var($_ENV['FACE_VALIDATION_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
    }
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
    function recordAuditLog($userId, $action, $details = '')
    {
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

// ---------------------------
// Session Configuration Helper Function
// ---------------------------
if (!function_exists('configureSessionSecurity')) {
    /**
     * Configure session security parameters based on environment.
     * Uses secure cookies only for HTTPS connections.
     */
    function configureSessionSecurity()
    {
        // Check if we're using HTTPS - more comprehensive check
        $isHttps = false;
        
        // Check various ways HTTPS might be indicated
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $isHttps = true;
        } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            $isHttps = true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $isHttps = true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            $isHttps = true;
        }
        
        // Allow override via environment variable for development
        if (isset($_ENV['FORCE_SECURE_COOKIES'])) {
            $isHttps = filter_var($_ENV['FORCE_SECURE_COOKIES'], FILTER_VALIDATE_BOOLEAN);
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,      // Only secure on HTTPS
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
}

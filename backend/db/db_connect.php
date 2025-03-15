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
$username = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS'];
$dbname = $_ENV['DB_NAME'];

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
?>
<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

echo "<h1>Header Settings Test</h1>";

// Get header settings from database
$headerName = 'ADOHRE';
$headerLogo = 'assets/logo.png';

$stmt = $conn->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('header_name', 'header_logo')");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['key'] === 'header_name' && !empty($row['value'])) {
            $headerName = $row['value'];
        }
        if ($row['key'] === 'header_logo' && !empty($row['value'])) {
            $headerLogo = $row['value'];
        }
    }
    $stmt->close();
}

echo "<h2>Current Header Settings</h2>";
echo "<p><strong>Header Name:</strong> " . htmlspecialchars($headerName) . "</p>";
echo "<p><strong>Header Logo:</strong> " . htmlspecialchars($headerLogo) . "</p>";

// Display the logo using both methods
echo "<h2>Logo Display Test</h2>";

echo "<h3>Using Direct Path (for assets folder):</h3>";
echo "<img src='$headerLogo' style='max-height: 50px;' alt='Direct Path'>";

echo "<h3>Using decrypt_image.php (for S3 proxy):</h3>";
echo "<img src='backend/routes/decrypt_image.php?image_url=" . urlencode($headerLogo) . "' style='max-height: 50px;' alt='Via Decrypt'>";

echo "<h3>Using Conditional Logic (recommended):</h3>";
if (strpos($headerLogo, 's3proxy') !== false) {
    echo "<img src='backend/routes/decrypt_image.php?image_url=" . urlencode($headerLogo) . "' style='max-height: 50px;' alt='S3 Image'>";
    echo "<p>Using decrypt_image.php (S3 proxy path detected)</p>";
} else {
    echo "<img src='$headerLogo' style='max-height: 50px;' alt='Local Image'>";
    echo "<p>Using direct path (local path detected)</p>";
}

// Actions
echo "<h2>Actions</h2>";
echo "<p><a href='admin/settings.php'>Go to Admin Settings</a></p>";
echo "<p><a href='index.php'>Go to Homepage</a></p>";

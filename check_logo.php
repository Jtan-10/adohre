<?php
// check_logo.php - Check what's stored in the database for the header logo

// Include database connection
require_once 'backend/db/db_connect.php';

// Set content type for proper HTML display
header('Content-Type: text/html; charset=utf-8');

// Query the settings table for header_logo
$headerLogo = 'Not found';
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'header_logo'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $headerLogo = $row['value'];
    }
    $stmt->close();
}

// Check if the header logo is an S3 URL
$isS3Url = strpos($headerLogo, 's3proxy') !== false;

// Create test image tags with different approaches
$directUrl = $headerLogo;
$decryptUrl = "/capstone-php/decrypt_image_simple.php?image_url=" . urlencode($headerLogo);
$alternateUrl = "/capstone-php/" . $headerLogo;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Header Logo Debug</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        h1 {
            color: #2c3e50;
        }

        .image-test {
            border: 1px solid #ddd;
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
        }

        .code {
            background: #f5f5f5;
            padding: 10px;
            font-family: monospace;
            border: 1px solid #ddd;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .error {
            color: red;
        }

        .success {
            color: green;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Header Logo Debug</h1>

        <h2>Database Information</h2>
        <p><strong>Logo value in database:</strong> <span class="code"><?= htmlspecialchars($headerLogo) ?></span></p>
        <p><strong>Is S3 URL:</strong> <?= $isS3Url ? '<span class="success">Yes</span>' : '<span class="error">No</span>' ?></p>

        <div class="image-test">
            <h3>Test 1: Direct URL (as stored in database)</h3>
            <div class="code"><?= htmlspecialchars($directUrl) ?></div>
            <img src="<?= htmlspecialchars($directUrl) ?>" alt="Logo Direct URL" style="max-height: 100px; border: 1px dashed #999;">
            <p>If this image doesn't show up and it's an S3 URL, the Apache proxy configuration might not be working.</p>
        </div>

        <div class="image-test">
            <h3>Test 2: Through decrypt_image_simple.php</h3>
            <div class="code"><?= htmlspecialchars($decryptUrl) ?></div>
            <img src="<?= htmlspecialchars($decryptUrl) ?>" alt="Logo via decrypt_image" style="max-height: 100px; border: 1px dashed #999;">
            <p>If this image doesn't show up, there might be an issue with the decrypt_image_simple.php script.</p>
        </div>

        <div class="image-test">
            <h3>Test 3: Assuming local path (prepending /capstone-php/)</h3>
            <div class="code"><?= htmlspecialchars($alternateUrl) ?></div>
            <img src="<?= htmlspecialchars($alternateUrl) ?>" alt="Logo Local Path" style="max-height: 100px; border: 1px dashed #999;">
            <p>If this image shows up, the URL might be relative and needs to be prefixed with the base path.</p>
        </div>

        <h2>Network Requests</h2>
        <p>Please open your browser's developer tools (F12) and look at the network tab when loading the page.
            Check for any 404 errors or other issues with image loading.</p>

        <h2>PHP Server Variables</h2>
        <div class="code">
            HTTP_HOST: <?= $_SERVER['HTTP_HOST'] ?? 'Not set' ?>

            DOCUMENT_ROOT: <?= $_SERVER['DOCUMENT_ROOT'] ?? 'Not set' ?>

            SCRIPT_FILENAME: <?= $_SERVER['SCRIPT_FILENAME'] ?? 'Not set' ?>

            REQUEST_URI: <?= $_SERVER['REQUEST_URI'] ?? 'Not set' ?>
        </div>
    </div>
</body>

</html>
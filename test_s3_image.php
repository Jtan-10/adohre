<?php
// test_s3_image.php - Test if S3 images are displaying correctly

// Include database connection
require_once 'backend/db/db_connect.php';

// Get current header logo
$headerLogo = 'assets/logo.png'; // Default
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'header_logo'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $headerLogo = $row['value'];
    }
    $stmt->close();
}

// Check if it's an S3 URL
$isS3 = strpos($headerLogo, 's3proxy') !== false;

// Create the image URL
$imageUrl = $isS3 ? "/capstone-php/backend/routes/decrypt_image.php?image_url=" . urlencode($headerLogo) : "/capstone-php/$headerLogo";

// Output a simple HTML page
echo "<!DOCTYPE html>
<html>
<head>
    <title>S3 Image Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .image-test {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
            text-align: center;
        }
        .code {
            background-color: #f0f0f0;
            padding: 10px;
            border: 1px solid #ddd;
            overflow-wrap: break-word;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>S3 Image Test</h1>
    
    <p><strong>Header Logo URL:</strong></p>
    <div class='code'>{$headerLogo}</div>
    
    <p><strong>Is S3 URL:</strong> " . ($isS3 ? "Yes" : "No") . "</p>
    
    <p><strong>Image URL:</strong></p>
    <div class='code'>{$imageUrl}</div>
    
    <div class='image-test'>
        <h3>Image Display Test:</h3>
        <img src='{$imageUrl}' alt='Header Logo Test' style='max-height: 100px; border: 1px solid #ddd;'>
    </div>
    
    <p>If the image displays correctly above, the header logo should work properly on your site.</p>
</body>
</html>";

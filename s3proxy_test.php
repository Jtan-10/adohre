<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include required files
require_once 'backend/db/db_connect.php';
require_once 'backend/s3config.php'; // This should load the AWS S3 client

echo "<h1>S3 Proxy Test</h1>";

// 1. Get current header logo from settings
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'header_logo'");
$stmt->execute();
$stmt->bind_result($headerLogo);
$stmt->fetch();
$stmt->close();

echo "<h2>Current Header Logo Setting</h2>";
echo "<p>$headerLogo</p>";

// 2. Check if it's an S3 proxy URL
$isS3Proxy = strpos($headerLogo, 's3proxy') !== false;
echo "<p>Is S3 proxy URL: " . ($isS3Proxy ? "Yes" : "No") . "</p>";

// 3. If it's an S3 proxy URL, try to get the actual S3 URL
if ($isS3Proxy) {
    $s3Key = urldecode(str_replace('/s3proxy/', '', $headerLogo));
    echo "<p>S3 Key: $s3Key</p>";

    try {
        // Generate a pre-signed URL for the S3 object
        $cmd = $s3->getCommand('GetObject', [
            'Bucket' => $bucketName,
            'Key'    => $s3Key
        ]);

        $request = $s3->createPresignedRequest($cmd, '+20 minutes');
        $presignedUrl = (string)$request->getUri();

        echo "<p>Presigned S3 URL: $presignedUrl</p>";
        echo "<p>Direct S3 URL: https://{$bucketName}.s3.{$_ENV['AWS_REGION']}.amazonaws.com/$s3Key</p>";
    } catch (Exception $e) {
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }
}

// 4. Test the decrypt_image.php script
echo "<h2>Testing decrypt_image.php</h2>";

if ($headerLogo) {
    echo "<p>Testing with URL: $headerLogo</p>";

    // Show what happens when we try to access the file directly
    echo "<h3>Direct Access (will probably fail):</h3>";
    echo "<img src='$headerLogo' style='max-height: 100px;' onerror=\"this.onerror=null; this.src='assets/logo.png'; this.style.border='2px solid red';\" alt='Direct Access Test'>";

    // Show what happens when we use the decrypt_image.php script
    echo "<h3>Through decrypt_image.php:</h3>";
    echo "<img src='backend/routes/decrypt_image.php?image_url=" . urlencode($headerLogo) . "' style='max-height: 100px;' onerror=\"this.onerror=null; this.src='assets/logo.png'; this.style.border='2px solid red';\" alt='Decrypt Image Test'>";
}

// 5. Check S3 proxy implementation
echo "<h2>S3 Proxy Implementation Check</h2>";

// See if there's an s3proxy.php file
$s3proxyFile = file_exists('s3proxy.php');
echo "<p>s3proxy.php exists: " . ($s3proxyFile ? "Yes" : "No") . "</p>";

// Check for .htaccess rules
$htaccessContent = @file_get_contents('.htaccess');
echo "<p>.htaccess contains s3proxy rules: " . (strpos($htaccessContent, 's3proxy') !== false ? "Yes" : "No") . "</p>";
if (strpos($htaccessContent, 's3proxy') !== false) {
    echo "<pre>" . htmlspecialchars($htaccessContent) . "</pre>";
}

<?php
// direct_s3_test.php - Test direct access to S3 objects using the server's proxy configuration

// Include necessary files
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/backend/db/db_connect.php';

// Set content type for HTML output
header('Content-Type: text/html; charset=utf-8');

// Get the header logo from settings
$headerLogo = 'assets/logo.png'; // Default fallback

$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'header_logo'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $headerLogo = $row['value'];
    }
    $stmt->close();
}

// Parse URL for testing
$isS3Image = strpos($headerLogo, 's3proxy') !== false;

// Create test URLs
$s3DirectUrl = $isS3Image ? $headerLogo : '';
$s3AwsUrl = '';

if ($isS3Image) {
    // Extract S3 key
    $s3Key = str_replace('/s3proxy/', '', $headerLogo);
    // Create AWS URL
    $s3AwsUrl = "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/" . $s3Key;
}

// Test function to check URL accessibility
function testUrl($url)
{
    if (empty($url)) return ['accessible' => false, 'status' => 'URL is empty'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'accessible' => ($httpCode >= 200 && $httpCode < 300),
        'status' => $httpCode,
        'error' => $error
    ];
}

// Test URLs
$s3DirectTest = $isS3Image ? testUrl('http://' . $_SERVER['HTTP_HOST'] . $s3DirectUrl) : null;
$s3AwsTest = $isS3Image ? testUrl($s3AwsUrl) : null;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Direct S3 Access Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .image-container {
            margin: 20px 0;
            padding: 10px;
            border: 1px dashed #aaa;
            background-color: #f9f9f9;
            text-align: center;
        }

        .code {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
            word-break: break-all;
        }

        h1,
        h2,
        h3 {
            color: #2c3e50;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Direct S3 Access Test</h1>

        <div class="test-section">
            <h2>Header Logo Configuration</h2>
            <p><strong>Logo Value:</strong> <span class="code"><?= htmlspecialchars($headerLogo) ?></span></p>
            <p><strong>Is S3 Image:</strong> <?= $isS3Image ? '<span class="success">Yes</span>' : '<span class="error">No</span>' ?></p>
        </div>

        <?php if ($isS3Image): ?>
            <div class="test-section">
                <h2>S3 Proxy URL Access Test</h2>
                <p><strong>S3 Proxy URL:</strong> <span class="code"><?= htmlspecialchars($s3DirectUrl) ?></span></p>
                <p><strong>Status:</strong>
                    <?php if ($s3DirectTest['accessible']): ?>
                        <span class="success">Accessible (HTTP <?= $s3DirectTest['status'] ?>)</span>
                    <?php else: ?>
                        <span class="error">Not Accessible (HTTP <?= $s3DirectTest['status'] ?>)</span>
                        <?php if (!empty($s3DirectTest['error'])): ?>
                            <br>Error: <?= htmlspecialchars($s3DirectTest['error']) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>

                <div class="image-container">
                    <h3>Direct S3 Proxy Image Test</h3>
                    <img src="<?= htmlspecialchars($s3DirectUrl) ?>" alt="S3 Proxy Image" style="max-height: 100px;">
                </div>
            </div>

            <div class="test-section">
                <h2>Direct AWS S3 URL Test</h2>
                <p><strong>AWS S3 URL:</strong> <span class="code"><?= htmlspecialchars($s3AwsUrl) ?></span></p>
                <p><strong>Status:</strong>
                    <?php if ($s3AwsTest['accessible']): ?>
                        <span class="success">Accessible (HTTP <?= $s3AwsTest['status'] ?>)</span>
                    <?php else: ?>
                        <span class="error">Not Accessible (HTTP <?= $s3AwsTest['status'] ?>)</span>
                        <?php if (!empty($s3AwsTest['error'])): ?>
                            <br>Error: <?= htmlspecialchars($s3AwsTest['error']) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </p>

                <div class="image-container">
                    <h3>Direct AWS S3 Image Test</h3>
                    <img src="<?= htmlspecialchars($s3AwsUrl) ?>" alt="AWS S3 Image" style="max-height: 100px;">
                </div>
            </div>

            <div class="test-section">
                <h2>Solutions to Try</h2>
                <ol>
                    <li>If both images above fail to load, your S3 object might not exist or might not be publicly accessible.</li>
                    <li>If the AWS S3 URL works but the S3 proxy URL doesn't, your Apache proxy configuration needs to be fixed.</li>
                    <li>Check that the Apache proxy directive is correctly pointing to your S3 bucket.</li>
                    <li>Make sure mod_proxy and mod_proxy_http modules are enabled in Apache.</li>
                    <li>Try directly using the AWS S3 URL in your header settings.</li>
                    <li>If the image is encrypted, use the decrypt_image_simple.php script with the AWS S3 URL.</li>
                </ol>
            </div>
        <?php else: ?>
            <div class="test-section">
                <h2>Not an S3 Image</h2>
                <p>The header logo is not stored in S3. It appears to be a local file. Make sure the path is correct and the file exists.</p>

                <div class="image-container">
                    <h3>Local Image Test</h3>
                    <img src="/capstone-php/<?= htmlspecialchars($headerLogo) ?>" alt="Local Image" style="max-height: 100px;">
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
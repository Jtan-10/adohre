<?php
// s3_image_test.php - Test S3 image loading and decryption

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/backend/db/db_connect.php';
require_once __DIR__ . '/backend/s3config.php';

// Set appropriate content type for HTML output
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
$imageUrlTest = $isS3Image ?
    '/capstone-php/backend/routes/decrypt_image.php?image_url=' . urlencode($headerLogo) :
    '/capstone-php/' . $headerLogo;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S3 Image Test</title>
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
        }

        h3 {
            margin-top: 0;
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
        <h1>S3 Image Test</h1>

        <div class="test-section">
            <h3>1. Header Logo Configuration</h3>
            <p><strong>Logo Value:</strong> <?php echo htmlspecialchars($headerLogo); ?></p>
            <p><strong>Is S3 Image:</strong> <?php echo $isS3Image ? 'Yes' : 'No'; ?></p>
            <p><strong>Image URL for Testing:</strong> <?php echo htmlspecialchars($imageUrlTest); ?></p>
        </div>

        <div class="test-section">
            <h3>2. Image Display Test</h3>
            <p>Testing image display from the source URL:</p>
            <div class="image-container">
                <img src="<?php echo htmlspecialchars($imageUrlTest); ?>" alt="Header Logo Test" style="max-width: 300px;">
            </div>
        </div>

        <div class="test-section">
            <h3>3. Direct S3 Proxy Test</h3>
            <?php if ($isS3Image): ?>
                <p>Testing direct S3 proxy access:</p>
                <div class="image-container">
                    <img src="<?php echo htmlspecialchars($headerLogo); ?>" alt="S3 Proxy Direct Test" style="max-width: 300px;">
                </div>
            <?php else: ?>
                <p class="info">This image is not stored in S3, so no direct S3 proxy test is applicable.</p>
            <?php endif; ?>
        </div>

        <div class="test-section">
            <h3>4. File Existence Check</h3>
            <?php
            $decryptScriptPath = __DIR__ . '/backend/routes/decrypt_image.php';
            $s3proxyPath = __DIR__ . '/s3proxy.php';
            $htaccessPath = __DIR__ . '/.htaccess';
            ?>

            <p><strong>decrypt_image.php:</strong>
                <?php echo file_exists($decryptScriptPath) ?
                    '<span class="success">File exists</span>' :
                    '<span class="error">File not found</span>'; ?>
            </p>

            <p><strong>s3proxy.php:</strong>
                <?php echo file_exists($s3proxyPath) ?
                    '<span class="success">File exists</span>' :
                    '<span class="error">File not found</span>'; ?>
            </p>

            <p><strong>.htaccess:</strong>
                <?php echo file_exists($htaccessPath) ?
                    '<span class="success">File exists</span>' :
                    '<span class="error">File not found</span>'; ?>
            </p>
        </div>

        <div class="test-section">
            <h3>5. HTTP Request Test</h3>
            <?php
            if ($isS3Image) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . $headerLogo);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HEADER, 1);
                curl_setopt($ch, CURLOPT_NOBODY, 1);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                echo "<p><strong>S3 Proxy HTTP Status:</strong> ";
                if ($httpCode == 200) {
                    echo "<span class=\"success\">$httpCode (OK)</span>";
                } else {
                    echo "<span class=\"error\">$httpCode (Failed)</span>";
                }
                echo "</p>";
            }

            // Test decrypt_image.php
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/capstone-php/backend/routes/decrypt_image.php?image_url=' . urlencode($headerLogo));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_NOBODY, 1);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            echo "<p><strong>decrypt_image.php HTTP Status:</strong> ";
            if ($httpCode == 200) {
                echo "<span class=\"success\">$httpCode (OK)</span>";
            } else {
                echo "<span class=\"error\">$httpCode (Failed)</span>";
            }
            echo "</p>";
            ?>
        </div>
    </div>
</body>

</html>
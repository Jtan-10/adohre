<?php
// fix_header_logo.php - Apply header logo fixes to the database

// Include database connection
require_once __DIR__ . '/backend/db/db_connect.php';

// Set content type for HTML output
header('Content-Type: text/html; charset=utf-8');

// Check if this is a POST request to update
$message = '';
$originalLogo = '';
$fixedLogo = '';

// Get the current header logo
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'header_logo'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $originalLogo = $row['value'];
    }
    $stmt->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'fix_s3') {
        // Fix S3 URL to use AWS direct URL
        if (strpos($originalLogo, '/s3proxy/') !== false) {
            $s3Key = str_replace('/s3proxy/', '', $originalLogo);
            $fixedLogo = "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/" . $s3Key;

            $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE `key` = 'header_logo'");
            if ($stmt) {
                $stmt->bind_param("s", $fixedLogo);
                if ($stmt->execute()) {
                    $message = "Successfully updated header logo to use direct AWS S3 URL.";
                } else {
                    $message = "Error updating header logo: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $message = "Current logo is not an S3 proxy URL.";
        }
    } elseif ($_POST['action'] === 'use_default') {
        // Reset to default logo
        $fixedLogo = "assets/logo.png";

        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE `key` = 'header_logo'");
        if ($stmt) {
            $stmt->bind_param("s", $fixedLogo);
            if ($stmt->execute()) {
                $message = "Successfully reset to default logo.";
            } else {
                $message = "Error resetting logo: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] === 'custom_update' && isset($_POST['custom_url'])) {
        // Update with custom URL
        $fixedLogo = $_POST['custom_url'];

        $stmt = $conn->prepare("UPDATE settings SET value = ? WHERE `key` = 'header_logo'");
        if ($stmt) {
            $stmt->bind_param("s", $fixedLogo);
            if ($stmt->execute()) {
                $message = "Successfully updated header logo with custom URL.";
            } else {
                $message = "Error updating logo: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Refresh the page to show updates
    header("Location: fix_header_logo.php?message=" . urlencode($message));
    exit;
}

// Get the message from URL if it exists
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Get the current header logo again (in case it was updated)
$stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = 'header_logo'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $currentLogo = $row['value'];
    } else {
        $currentLogo = "Not set";
    }
    $stmt->close();
}

// Determine the URL type
$isS3Proxy = strpos($currentLogo, '/s3proxy/') !== false;
$isAwsUrl = strpos($currentLogo, 's3.ap-southeast-1.amazonaws.com') !== false;
$isLocalFile = !$isS3Proxy && !$isAwsUrl && strpos($currentLogo, 'http') !== 0;

// Create test images
$directImageTag = '';
if ($isS3Proxy) {
    $directImageTag = '<img src="' . htmlspecialchars($currentLogo) . '" alt="Direct S3 Proxy Test" style="max-height: 100px;">';
} elseif ($isAwsUrl) {
    $directImageTag = '<img src="' . htmlspecialchars($currentLogo) . '" alt="Direct AWS URL Test" style="max-height: 100px;">';
} elseif ($isLocalFile) {
    $directImageTag = '<img src="/capstone-php/' . htmlspecialchars($currentLogo) . '" alt="Local File Test" style="max-height: 100px;">';
} else {
    $directImageTag = '<img src="' . htmlspecialchars($currentLogo) . '" alt="External URL Test" style="max-height: 100px;">';
}

// Decrypt image tag (works for any URL)
$decryptImageTag = '<img src="/capstone-php/decrypt_image_simple.php?image_url=' . urlencode($currentLogo) . '" alt="Via decrypt_image_simple.php" style="max-height: 100px;">';

// Default logo for comparison
$defaultLogoTag = '<img src="/capstone-php/assets/logo.png" alt="Default Logo" style="max-height: 100px;">';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Header Logo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        h1,
        h2,
        h3 {
            color: #2c3e50;
        }

        .image-test {
            border: 1px dashed #aaa;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
            background-color: #fff;
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

        .success {
            color: green;
        }

        .error {
            color: red;
        }

        .message {
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        form {
            margin: 15px 0;
        }

        input[type="text"] {
            padding: 8px;
            width: 100%;
            margin-bottom: 10px;
            box-sizing: border-box;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Fix Header Logo</h1>

        <?php if ($message): ?>
            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>Current Logo Information</h2>
            <p><strong>Logo URL/Path:</strong> <span class="code"><?= htmlspecialchars($currentLogo) ?></span></p>
            <p><strong>URL Type:</strong>
                <?php
                if ($isS3Proxy) echo "S3 Proxy URL";
                elseif ($isAwsUrl) echo "Direct AWS URL";
                elseif ($isLocalFile) echo "Local File Path";
                else echo "External URL";
                ?>
            </p>

            <div class="image-test">
                <h3>Current Logo Display Test</h3>
                <?= $directImageTag ?>
                <p><small>This is how the logo is currently displayed.</small></p>
            </div>

            <div class="image-test">
                <h3>Via decrypt_image_simple.php</h3>
                <?= $decryptImageTag ?>
                <p><small>This is how the logo would display using the decrypt_image_simple.php script.</small></p>
            </div>

            <div class="image-test">
                <h3>Default Logo</h3>
                <?= $defaultLogoTag ?>
                <p><small>This is the default logo for comparison.</small></p>
            </div>
        </div>

        <div class="section">
            <h2>Fix Options</h2>

            <?php if ($isS3Proxy): ?>
                <form method="post" action="">
                    <h3>Option 1: Convert S3 Proxy URL to Direct AWS URL</h3>
                    <p>This will replace the S3 proxy URL with a direct AWS S3 URL.</p>
                    <input type="hidden" name="action" value="fix_s3">
                    <button type="submit" class="btn">Convert to AWS URL</button>
                </form>
            <?php endif; ?>

            <form method="post" action="">
                <h3>Option 2: Use Default Logo</h3>
                <p>This will reset the header logo to the default logo in assets/logo.png.</p>
                <input type="hidden" name="action" value="use_default">
                <button type="submit" class="btn">Use Default Logo</button>
            </form>

            <form method="post" action="">
                <h3>Option 3: Set Custom URL</h3>
                <p>Enter a custom URL or path for the header logo:</p>
                <input type="text" name="custom_url" placeholder="Enter URL or path (e.g., assets/logo.png)">
                <input type="hidden" name="action" value="custom_update">
                <button type="submit" class="btn">Update Logo</button>
            </form>
        </div>

        <div class="section">
            <h2>Tips for Header Logo</h2>
            <ul>
                <li>If using an S3 image, prefer using a direct AWS URL.</li>
                <li>Make sure the image file exists at the path you specify.</li>
                <li>Local file paths should be relative to the capstone-php folder (e.g., assets/logo.png).</li>
                <li>For encrypted images, consider using the decrypt_image_simple.php script.</li>
                <li>After making changes, check the main site to verify the logo displays correctly.</li>
            </ul>
        </div>

        <p><a href="/capstone-php/admin/settings.php" class="btn btn-secondary">Return to Settings</a></p>
    </div>
</body>

</html>
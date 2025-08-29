<?php
// diagnostic.php - Comprehensive diagnostic tool for S3 and image issues

// Include necessary files
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/backend/db/db_connect.php';
require_once __DIR__ . '/backend/s3config.php';

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/diagnostic_errors.log');

// Start output buffering to catch any errors
ob_start();

// Set appropriate content type for HTML output
header('Content-Type: text/html; charset=utf-8');

// Function to check if a URL exists
function urlExists($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $responseCode < 400;
}

// Function to get file permissions
function getFilePermissions($path)
{
    if (!file_exists($path)) {
        return 'File does not exist';
    }
    return substr(sprintf('%o', fileperms($path)), -4);
}

// Function to format a URL for display
function formatUrl($url)
{
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

// Get header logo from settings
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
$decryptImageUrl = '/capstone-php/decrypt_image_simple.php?image_url=' . urlencode($headerLogo);
$fullS3Url = '';

if ($isS3Image) {
    // Extract the S3 key from the s3proxy URL
    $s3Key = str_replace('/s3proxy/', '', $headerLogo);

    // Construct the full S3 URL
    $fullS3Url = "https://{$bucketName}.s3." . $_ENV['AWS_REGION'] . ".amazonaws.com/" . $s3Key;
}

// Check if critical files exist
$filesChecks = [
    'decrypt_image.php' => [
        'path' => __DIR__ . '/backend/routes/decrypt_image.php',
        'exists' => file_exists(__DIR__ . '/backend/routes/decrypt_image.php'),
        'permissions' => getFilePermissions(__DIR__ . '/backend/routes/decrypt_image.php'),
        'url' => '/capstone-php/backend/routes/decrypt_image.php',
    ],
    'decrypt_image_simple.php' => [
        'path' => __DIR__ . '/decrypt_image_simple.php',
        'exists' => file_exists(__DIR__ . '/decrypt_image_simple.php'),
        'permissions' => getFilePermissions(__DIR__ . '/decrypt_image_simple.php'),
        'url' => '/capstone-php/decrypt_image_simple.php',
    ],
    's3proxy.php' => [
        'path' => __DIR__ . '/s3proxy.php',
        'exists' => file_exists(__DIR__ . '/s3proxy.php'),
        'permissions' => getFilePermissions(__DIR__ . '/s3proxy.php'),
        'url' => '/capstone-php/s3proxy.php',
    ],
    '.htaccess' => [
        'path' => __DIR__ . '/.htaccess',
        'exists' => file_exists(__DIR__ . '/.htaccess'),
        'permissions' => getFilePermissions(__DIR__ . '/.htaccess'),
    ],
];

// Check URL accessibility
$urlChecks = [
    'header_logo_direct' => [
        'description' => 'Direct Header Logo URL',
        'url' => $isS3Image ? 'http://' . $_SERVER['HTTP_HOST'] . $headerLogo : '/capstone-php/' . $headerLogo,
        'accessible' => false,
    ],
    'decrypt_image_url' => [
        'description' => 'Decrypt Image URL',
        'url' => 'http://' . $_SERVER['HTTP_HOST'] . $decryptImageUrl,
        'accessible' => false,
    ],
];

// Check URL accessibility
foreach ($urlChecks as $key => &$check) {
    $check['accessible'] = urlExists($check['url']);
}

// Check if S3 is properly configured
$s3ConfigChecks = [
    'bucket_exists' => false,
    'bucket_accessible' => false,
    'aws_key_set' => !empty($_ENV['AWS_ACCESS_KEY_ID']),
    'aws_secret_set' => !empty($_ENV['AWS_SECRET_ACCESS_KEY']),
    'aws_region_set' => !empty($_ENV['AWS_REGION']),
    'bucket_name_set' => !empty($bucketName),
];

if ($s3ConfigChecks['aws_key_set'] && $s3ConfigChecks['aws_secret_set'] && $s3ConfigChecks['aws_region_set'] && $s3ConfigChecks['bucket_name_set']) {
    try {
        $s3ConfigChecks['bucket_exists'] = $s3->doesBucketExist($bucketName);

        if ($s3ConfigChecks['bucket_exists'] && $isS3Image) {
            $s3Key = str_replace('/s3proxy/', '', $headerLogo);
            try {
                $s3ConfigChecks['bucket_accessible'] = $s3->doesObjectExist($bucketName, $s3Key);
            } catch (Exception $e) {
                error_log("S3 object check error: " . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log("S3 bucket check error: " . $e->getMessage());
    }
}

// Get any buffered errors
$errors = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Diagnostic Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
            color: #333;
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

        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        h2 {
            color: #2980b9;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .image-test {
            border: 1px dashed #aaa;
            padding: 10px;
            margin: 10px 0;
            text-align: center;
            background-color: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f2f2f2;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }

        .warning {
            color: orange;
        }

        .code {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            overflow-x: auto;
        }

        .url {
            word-break: break-all;
            font-family: monospace;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Comprehensive Diagnostic Tool</h1>

        <?php if ($errors): ?>
            <div class="section">
                <h2>PHP Errors</h2>
                <div class="code"><?php echo nl2br(htmlspecialchars($errors)); ?></div>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>1. Header Logo Information</h2>
            <table>
                <tr>
                    <td>Header Logo Value:</td>
                    <td class="url"><?php echo formatUrl($headerLogo); ?></td>
                </tr>
                <tr>
                    <td>Is S3 Image:</td>
                    <td><?php echo $isS3Image ? '<span class="success">Yes</span>' : 'No'; ?></td>
                </tr>
                <?php if ($isS3Image): ?>
                    <tr>
                        <td>S3 Key:</td>
                        <td class="url"><?php echo formatUrl(str_replace('/s3proxy/', '', $headerLogo)); ?></td>
                    </tr>
                    <tr>
                        <td>Full S3 URL:</td>
                        <td class="url"><?php echo formatUrl($fullS3Url); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td>Decrypt Image URL:</td>
                    <td class="url"><?php echo formatUrl($decryptImageUrl); ?></td>
                </tr>
            </table>

            <div class="image-test">
                <h3>Image Display Test</h3>
                <?php if ($isS3Image): ?>
                    <p>Using decrypt_image_simple.php:</p>
                    <img src="<?php echo $decryptImageUrl; ?>" alt="Header Logo via decrypt_image" style="max-height: 100px; border: 1px solid #ccc;">
                    <p>Using direct S3 proxy access:</p>
                    <img src="<?php echo htmlspecialchars($headerLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Header Logo Direct" style="max-height: 100px; border: 1px solid #ccc;">
                <?php else: ?>
                    <img src="/capstone-php/<?php echo htmlspecialchars($headerLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Header Logo" style="max-height: 100px; border: 1px solid #ccc;">
                <?php endif; ?>
            </div>
        </div>

        <div class="section">
            <h2>2. File System Check</h2>
            <table>
                <tr>
                    <th>File</th>
                    <th>Exists</th>
                    <th>Permissions</th>
                    <th>Path</th>
                </tr>
                <?php foreach ($filesChecks as $name => $check): ?>
                    <tr>
                        <td><?php echo $name; ?></td>
                        <td><?php echo $check['exists'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td>
                        <td><?php echo $check['permissions']; ?></td>
                        <td class="url"><?php echo $check['path']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php if (isset($filesChecks['.htaccess']) && $filesChecks['.htaccess']['exists']): ?>
                <h3>.htaccess Content</h3>
                <div class="code"><?php echo nl2br(htmlspecialchars(file_get_contents(__DIR__ . '/.htaccess'))); ?></div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>3. URL Accessibility Check</h2>
            <table>
                <tr>
                    <th>Description</th>
                    <th>URL</th>
                    <th>Accessible</th>
                </tr>
                <?php foreach ($urlChecks as $key => $check): ?>
                    <tr>
                        <td><?php echo $check['description']; ?></td>
                        <td class="url"><?php echo formatUrl($check['url']); ?></td>
                        <td><?php echo $check['accessible'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="section">
            <h2>4. AWS S3 Configuration Check</h2>
            <table>
                <tr>
                    <td>AWS Access Key Set:</td>
                    <td><?php echo $s3ConfigChecks['aws_key_set'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td>
                </tr>
                <tr>
                    <td>AWS Secret Key Set:</td>
                    <td><?php echo $s3ConfigChecks['aws_secret_set'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td>
                </tr>
                <tr>
                    <td>AWS Region Set:</td>
                    <td><?php echo $s3ConfigChecks['aws_region_set'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td>
                </tr>
                <tr>
                    <td>S3 Bucket Name Set:</td>
                    <td><?php echo $s3ConfigChecks['bucket_name_set'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td>
                </tr>
                <?php if ($s3ConfigChecks['bucket_name_set']): ?>
                    <tr>
                        <td>S3 Bucket Exists:</td>
                        <td><?php echo $s3ConfigChecks['bucket_exists'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($isS3Image): ?>
                    <tr>
                        <td>Logo Object Exists in Bucket:</td>
                        <td><?php echo $s3ConfigChecks['bucket_accessible'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?></td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="section">
            <h2>5. Server Environment Information</h2>
            <table>
                <tr>
                    <td>PHP Version:</td>
                    <td><?php echo PHP_VERSION; ?></td>
                </tr>
                <tr>
                    <td>Server Software:</td>
                    <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                </tr>
                <tr>
                    <td>Document Root:</td>
                    <td class="url"><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></td>
                </tr>
                <tr>
                    <td>Script Filename:</td>
                    <td class="url"><?php echo $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown'; ?></td>
                </tr>
                <tr>
                    <td>Current Script Path:</td>
                    <td class="url"><?php echo __FILE__; ?></td>
                </tr>
                <tr>
                    <td>GD Library:</td>
                    <td><?php echo function_exists('imagecreatefrompng') ? '<span class="success">Available</span>' : '<span class="error">Not Available</span>'; ?></td>
                </tr>
                <tr>
                    <td>OpenSSL:</td>
                    <td><?php echo function_exists('openssl_encrypt') ? '<span class="success">Available</span>' : '<span class="error">Not Available</span>'; ?></td>
                </tr>
                <tr>
                    <td>cURL:</td>
                    <td><?php echo function_exists('curl_init') ? '<span class="success">Available</span>' : '<span class="error">Not Available</span>'; ?></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h2>6. Recommendations</h2>
            <ul>
                <?php if (!$filesChecks['decrypt_image.php']['exists']): ?>
                    <li class="error">The decrypt_image.php file is missing. Please restore or create this file.</li>
                <?php endif; ?>

                <?php if (!$filesChecks['s3proxy.php']['exists']): ?>
                    <li class="error">The s3proxy.php file is missing. Please restore or create this file.</li>
                <?php endif; ?>

                <?php if (!$filesChecks['.htaccess']['exists']): ?>
                    <li class="error">The .htaccess file is missing. Please create this file to enable URL rewriting.</li>
                <?php endif; ?>

                <?php if ($isS3Image && !$urlChecks['header_logo_direct']['accessible']): ?>
                    <li class="error">The S3 proxy URL is not accessible. Check your s3proxy.php implementation and .htaccess configuration.</li>
                <?php endif; ?>

                <?php if (!$urlChecks['decrypt_image_url']['accessible']): ?>
                    <li class="error">The decrypt_image.php URL is not accessible. Check if the file exists and has correct permissions.</li>
                <?php endif; ?>

                <?php if ($isS3Image && (!$s3ConfigChecks['aws_key_set'] || !$s3ConfigChecks['aws_secret_set'] || !$s3ConfigChecks['aws_region_set'] || !$s3ConfigChecks['bucket_name_set'])): ?>
                    <li class="error">AWS S3 configuration is incomplete. Check your .env file for AWS credentials.</li>
                <?php endif; ?>

                <?php if ($isS3Image && $s3ConfigChecks['bucket_name_set'] && !$s3ConfigChecks['bucket_exists']): ?>
                    <li class="error">The specified S3 bucket does not exist or is not accessible with your credentials.</li>
                <?php endif; ?>

                <?php if ($isS3Image && !$s3ConfigChecks['bucket_accessible']): ?>
                    <li class="error">The logo object does not exist in the S3 bucket or is not accessible.</li>
                <?php endif; ?>

                <?php if ($filesChecks['decrypt_image.php']['exists'] && $filesChecks['s3proxy.php']['exists'] && $filesChecks['.htaccess']['exists'] && $urlChecks['decrypt_image_url']['accessible']): ?>
                    <li class="success">All necessary files exist and appear to be configured correctly.</li>
                <?php endif; ?>

                <?php if ($isS3Image && $s3ConfigChecks['bucket_exists'] && $s3ConfigChecks['bucket_accessible'] && $urlChecks['header_logo_direct']['accessible']): ?>
                    <li class="success">S3 configuration appears to be correct and the logo is accessible.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</body>

</html>
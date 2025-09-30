<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables to get the encryption key.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// We'll set the Content-Type header after decryption based on the actual image type.
header('X-Content-Type-Options: nosniff');

$cipher = "AES-256-CBC";
// Derive a 32-byte key from the raw key stored in the .env file.
$rawKey = getenv('ENCRYPTION_KEY');
$encryptionKey = hash('sha256', $rawKey, true);

// Accept url or image_url for backward compatibility.
$imageUrl = $_GET['url'] ?? $_GET['image_url'] ?? null;
if (!$imageUrl) {
    http_response_code(400);
    echo "Missing image URL parameter";
    exit;
}

// Fix any double slashes in the URL but keep the protocol (don't collapse 'https://')
$imageUrl = preg_replace('#(?<!:)//+#', '/', $imageUrl);
error_log("decrypt_image.php called with URL: " . $imageUrl);

// Track S3 key if determinable for private-bucket access via SDK
$detectedS3Key = null;

// Handle URLs with /s3proxy/ anywhere in the path by converting to the real S3 URL
if (strpos($imageUrl, '/s3proxy/') !== false) {
    // Determine S3 base URL from environment (prefer an override if provided)
    $bucket = $_ENV['AWS_BUCKET_NAME'] ?? getenv('AWS_BUCKET_NAME') ?? '';
    $region = $_ENV['AWS_REGION'] ?? getenv('AWS_REGION') ?? '';
    $customBase = $_ENV['AWS_S3_BASE_URL'] ?? getenv('AWS_S3_BASE_URL') ?? '';

    // Extract S3 key from the s3proxy path
    $parts = explode('/s3proxy/', $imageUrl, 2);
    $s3Key = isset($parts[1]) ? ltrim($parts[1], '/') : '';

    if (!empty($customBase)) {
        $baseUrl = rtrim($customBase, '/') . '/';
    } else {
        // Fallback to standard bucket URL
        $baseUrl = "https://{$bucket}.s3." . $region . ".amazonaws.com/";
    }
    $imageUrl = $baseUrl . $s3Key;
    $detectedS3Key = $s3Key ?: null;
    error_log("S3 proxy mapped to S3 URL: $imageUrl");
}
// Normalize to absolute URL without hardcoding project folder
else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (strpos($imageUrl, '/') === 0) {
        // Leading slash: root-relative to current host
        $imageUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $imageUrl;
        error_log("Relative URL processed: $imageUrl");
    } elseif (!preg_match('/^https?:\/\//', $imageUrl)) {
        // Plain relative path: relative to current script directory
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $imageUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/' . ltrim($imageUrl, '/');
        error_log("No protocol URL processed: $imageUrl");
    }
}

// Try to fetch via S3 SDK first if this looks like an S3 URL (supports private buckets)
$encryptedPngData = null;
try {
    // Use envs to determine bucket/base for key derivation
    $bucket = $_ENV['AWS_BUCKET_NAME'] ?? getenv('AWS_BUCKET_NAME') ?? '';
    $region = $_ENV['AWS_REGION'] ?? getenv('AWS_REGION') ?? '';
    $customBase = $_ENV['AWS_S3_BASE_URL'] ?? getenv('AWS_S3_BASE_URL') ?? '';

    // If key not already detected via /s3proxy/, attempt to derive from URL
    if ($detectedS3Key === null && !empty($bucket)) {
        $u = @parse_url($imageUrl);
        $host = $u['host'] ?? '';
        $path = isset($u['path']) ? ltrim($u['path'], '/') : '';

        // Virtual-hosted-style: bucket.s3.region.amazonaws.com/key
        $expectedHost = $bucket && $region ? ($bucket . '.s3.' . $region . '.amazonaws.com') : '';
        if ($expectedHost && strcasecmp($host, $expectedHost) === 0) {
            $detectedS3Key = $path;
        }
        // Path-style: s3.region.amazonaws.com/bucket/key or s3.amazonaws.com/bucket/key
        elseif (preg_match('/^s3[.-][a-z0-9-]+\.amazonaws\.com$/i', $host) || strcasecmp($host, 's3.amazonaws.com') === 0) {
            $parts = explode('/', $path, 2);
            if (!empty($parts[0]) && strcasecmp($parts[0], $bucket) === 0) {
                $detectedS3Key = $parts[1] ?? '';
            }
        }
        // Custom base (e.g., CloudFront or alternate domain). Derive key by removing base path.
        elseif (!empty($customBase)) {
            $cb = @parse_url(rtrim($customBase, '/') . '/');
            $cbHost = $cb['host'] ?? '';
            $cbPath = isset($cb['path']) ? trim($cb['path'], '/') : '';
            if ($cbHost && strcasecmp($host, $cbHost) === 0) {
                if ($cbPath && stripos($path, $cbPath . '/') === 0) {
                    $detectedS3Key = substr($path, strlen($cbPath) + 1);
                } else {
                    $detectedS3Key = $path; // assume 1:1 path mapping
                }
            }
        }
    }

    // If we have an S3 key and bucket, try authenticated getObject
    if (!empty($bucket) && !empty($detectedS3Key)) {
        // s3config.php defines $s3 client and $bucketName
        require_once __DIR__ . '/../s3config.php';
        if (isset($s3) && isset($bucketName) && strcasecmp($bucketName, $bucket) === 0) {
            $result = $s3->getObject([
                'Bucket' => $bucketName,
                'Key'    => $detectedS3Key,
            ]);
            if (isset($result['Body'])) {
                // Body is a GuzzleHttp\Psr7\StreamInterface
                $encryptedPngData = (string) $result['Body'];
                error_log('decrypt_image: fetched object via S3 SDK');
            }
        }
    }
} catch (Throwable $e) {
    // Swallow and fall back to HTTP
    error_log('decrypt_image S3 SDK fallback due to: ' . $e->getMessage());
}

// If not retrieved via SDK, try direct HTTP(S)
if ($encryptedPngData === null) {
    // Download the encrypted PNG data.
    $encryptedPngData = @file_get_contents($imageUrl);
}
if ($encryptedPngData === false) {
    // Fallback to cURL in case allow_url_fopen is disabled or other failures
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $imageUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    // Enforce SSL verification when using HTTPS
    if (stripos($imageUrl, 'https://') === 0) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    $encryptedPngData = curl_exec($ch);
    if ($encryptedPngData === false) {
        error_log('decrypt_image curl error: ' . curl_error($ch));
    }
    curl_close($ch);
}
if (!$encryptedPngData) {
    http_response_code(404);
    echo "Could not retrieve the PNG file.";
    exit;
}

// Create a temporary file for the downloaded PNG.
$tempPngFile = tempnam(sys_get_temp_dir(), 'enc_png_') . '.png';
file_put_contents($tempPngFile, $encryptedPngData);

// Extract the embedded data from the PNG.
$embeddedData = extractDataFromPng($tempPngFile);
@unlink($tempPngFile); // Clean up the temporary file.

if (!$embeddedData) {
    http_response_code(500);
    echo "Failed to extract data from PNG.";
    exit;
}

// Remove any trailing null bytes.
$embeddedData = rtrim($embeddedData, "\0");

$ivLength = openssl_cipher_iv_length($cipher);
if (strlen($embeddedData) < $ivLength) {
    http_response_code(500);
    echo "Invalid embedded data (too short).";
    exit;
}

$iv = substr($embeddedData, 0, $ivLength);
$ciphertext = substr($embeddedData, $ivLength);

$clearImageData = openssl_decrypt($ciphertext, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
if (!$clearImageData) {
    http_response_code(500);
    echo "Failed to decrypt data.";
    exit;
}

// Detect MIME type of decrypted image and set proper header
if (function_exists('finfo_open')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($clearImageData) ?: 'application/octet-stream';
} else {
    // Fallback: try getimagesizefromstring
    $info = @getimagesizefromstring($clearImageData);
    $mime = $info['mime'] ?? 'application/octet-stream';
}
header('Content-Type: ' . $mime);

// Output the clear image data directly.
echo $clearImageData;
exit();

/**
 * extractDataFromPng:
 * Reads every pixel’s R, G, B values from the given PNG file and reconstructs the binary data.
 * 
 * @param string $pngFilePath The path to the PNG file.
 * @return string The embedded binary data, or an empty string on error.
 */
function extractDataFromPng(string $pngFilePath): string
{
    $img = imagecreatefrompng($pngFilePath);
    if (!$img) {
        return '';
    }
    $width = imagesx($img);
    $height = imagesy($img);
    $binaryData = '';
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            // Extract R, G, B values and append them as binary.
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $binaryData .= chr($r) . chr($g) . chr($b);
        }
    }
    imagedestroy($img);
    return $binaryData;
}

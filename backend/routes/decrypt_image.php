<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables to get the encryption key.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

header('Content-Type: image/png');  // We'll output the decrypted image as PNG.
header('X-Content-Type-Options: nosniff');

$cipher = "AES-256-CBC";
// Derive a 32-byte key from the raw key stored in the .env file.
$rawKey = getenv('ENCRYPTION_KEY');
$encryptionKey = hash('sha256', $rawKey, true);

// Accept multiple possible parameter names: url, image_url, or face_url.
$imageUrl = $_GET['url'] ?? $_GET['image_url'] ?? $_GET['face_url'] ?? null;
if (!$imageUrl) {
    http_response_code(400);
    echo "Missing image URL parameter";
    exit;
}

// Fix any double slashes in the URL
$imageUrl = preg_replace('#/{2,}#', '/', $imageUrl);
error_log("decrypt_image.php called with URL: " . $imageUrl);

// Handle URLs with /s3proxy/ anywhere in the path
if (strpos($imageUrl, '/s3proxy/') !== false) {
    // Make sure it starts with the correct protocol and host
    if (!preg_match('/^https?:\/\//', $imageUrl)) {
        // Handle both paths with and without /capstone-php/ prefix
        if (strpos($imageUrl, '/capstone-php/') === 0) {
            $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . $imageUrl;
        } else {
            $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/capstone-php' . $imageUrl;
        }
    }
    error_log("S3 proxy URL processed: $imageUrl");
}
// If the URL is relative (starts with '/'), build an absolute URL based on the current host
elseif (strpos($imageUrl, '/') === 0) {
    $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . $imageUrl;
    error_log("Relative URL processed: $imageUrl");
}
// If the URL doesn't start with http:// or https://, assume it is relative
elseif (!preg_match('/^https?:\/\//', $imageUrl)) {
    $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/capstone-php/' . ltrim($imageUrl, '/');
    error_log("No protocol URL processed: $imageUrl");
}

// Download the encrypted PNG data.
$encryptedPngData = file_get_contents($imageUrl);
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

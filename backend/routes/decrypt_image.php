<?php
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment (Dotenv) to get the encryption key
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

header('Content-Type: image/png');  // We'll output the decrypted image as PNG
header('X-Content-Type-Options: nosniff');

$cipher = "AES-256-CBC";
// Derive a 32-byte key from your .env raw key
$rawKey = getenv('ENCRYPTION_KEY');
$encryptionKey = hash('sha256', $rawKey, true);

if (!isset($_GET['face_url'])) {
    http_response_code(400);
    echo "Missing face_url parameter";
    exit;
}
$faceUrl = $_GET['face_url'];
// If the URL is relative, build an absolute URL.
if (strpos($faceUrl, '/') === 0) {
    $faceUrl = 'http://' . $_SERVER['HTTP_HOST'] . $faceUrl;
}

// 1) Download the random-static PNG from S3 or your /s3proxy/ location.
$noisePngData = file_get_contents($faceUrl);
if (!$noisePngData) {
    http_response_code(404);
    echo "Could not retrieve the PNG file.";
    exit;
}

// 2) Create a temporary file for the downloaded PNG.
$tempNoisePng = tempnam(sys_get_temp_dir(), 'noise_') . '.png';
file_put_contents($tempNoisePng, $noisePngData);

// 3) Extract the embedded data from the PNG.
$embeddedData = extractDataFromPng($tempNoisePng);
@unlink($tempNoisePng); // Clean up temporary file.

if (!$embeddedData) {
    http_response_code(500);
    echo "Failed to extract data from PNG.";
    exit;
}

// --- Fix: Remove any trailing null bytes from the extracted data ---
$embeddedData = rtrim($embeddedData, "\0");

$cipherIvLen = openssl_cipher_iv_length($cipher);
if (strlen($embeddedData) < $cipherIvLen) {
    http_response_code(500);
    echo "Invalid embedded data (too short).";
    exit;
}
$iv = substr($embeddedData, 0, $cipherIvLen);
$ciphertext = substr($embeddedData, $cipherIvLen);

$clearImageData = openssl_decrypt($ciphertext, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
if (!$clearImageData) {
    http_response_code(500);
    echo "Failed to decrypt data.";
    exit;
}

// 6) Output the clear image directly.
echo $clearImageData;
exit;

/**
 * extractDataFromPng:
 * Reverse of embedDataInPng. Reads every pixel’s R, G, B, reconstructing the binary data.
 * 
 * @param string $pngFilePath The path to the random-static PNG file.
 * @return string The raw binary data embedded, or empty string on error.
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
            // Extract R, G, B values and append as binary.
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $binaryData .= chr($r) . chr($g) . chr($b);
        }
    }
    imagedestroy($img);
    return $binaryData;
}
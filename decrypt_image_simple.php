<?php
// Simple version of decrypt_image.php for debugging purposes

// Include required files
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set up error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/decrypt_image_errors.log');

// Set headers
header('Content-Type: image/png');
header('X-Content-Type-Options: nosniff');

// Get the image URL parameter
$imageUrl = $_GET['image_url'] ?? $_GET['url'] ?? $_GET['face_url'] ?? null;

// Log the request
error_log("decrypt_image_simple.php called with URL: " . ($imageUrl ?? 'NULL'));

// Check if image URL is provided
if (!$imageUrl) {
    http_response_code(400);
    die("Missing image URL parameter");
}

// Handle S3 proxy URLs
if (strpos($imageUrl, '/s3proxy/') === 0) {
    // Convert to full URL
    $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . $imageUrl;
    error_log("S3 proxy URL detected, converted to: $imageUrl");
}
// Handle relative URLs
elseif (strpos($imageUrl, '/') === 0) {
    $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . $imageUrl;
    error_log("Relative URL detected, converted to: $imageUrl");
}
// Handle URLs without protocol
elseif (!preg_match('/^https?:\/\//', $imageUrl)) {
    $imageUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($imageUrl, '/');
    error_log("URL without protocol detected, converted to: $imageUrl");
}

try {
    // Try to get the image content
    $imageData = @file_get_contents($imageUrl);

    if ($imageData === false) {
        error_log("Failed to fetch image from URL: $imageUrl");
        http_response_code(404);
        die("Failed to fetch image");
    }

    // For S3 proxy URLs, we need to decrypt the image
    if (strpos($imageUrl, '/s3proxy/') !== false) {
        // Set up decryption
        $cipher = "AES-256-CBC";
        $rawKey = getenv('ENCRYPTION_KEY');
        $encryptionKey = hash('sha256', $rawKey, true);

        // Create a temporary file
        $tempPngFile = tempnam(sys_get_temp_dir(), 'enc_png_') . '.png';
        file_put_contents($tempPngFile, $imageData);

        // Load the PNG
        $img = @imagecreatefrompng($tempPngFile);
        if (!$img) {
            error_log("Failed to create image from PNG: $tempPngFile");
            @unlink($tempPngFile);
            http_response_code(500);
            die("Invalid PNG file");
        }

        // Extract binary data from PNG
        $width = imagesx($img);
        $height = imagesy($img);
        $binaryData = '';

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $binaryData .= chr($r) . chr($g) . chr($b);
            }
        }

        imagedestroy($img);
        @unlink($tempPngFile);

        // Remove trailing null bytes
        $binaryData = rtrim($binaryData, "\0");

        // Decrypt the data
        $ivLength = openssl_cipher_iv_length($cipher);
        if (strlen($binaryData) < $ivLength) {
            error_log("Invalid embedded data (too short)");
            http_response_code(500);
            die("Invalid embedded data (too short)");
        }

        $iv = substr($binaryData, 0, $ivLength);
        $ciphertext = substr($binaryData, $ivLength);

        $clearImageData = openssl_decrypt($ciphertext, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
        if (!$clearImageData) {
            error_log("Failed to decrypt data");
            http_response_code(500);
            die("Failed to decrypt data");
        }

        // Output the clear image data
        echo $clearImageData;
    } else {
        // For regular images, just pass through the data
        echo $imageData;
    }
} catch (Exception $e) {
    error_log("Exception in decrypt_image_simple.php: " . $e->getMessage());
    http_response_code(500);
    die("Error processing image: " . $e->getMessage());
}

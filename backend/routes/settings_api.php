<?php
// Production best practices: disable error display and log errors instead.
// (Ensure in your php.ini, display_errors is Off.)
ini_set('display_errors', '0');  // Do not display errors to users
ini_set('log_errors', '1');      // Enable error logging
error_reporting(E_ALL);          // Report all errors (they will be logged, not displayed)

// Set secure session cookie parameters.
session_set_cookie_params([
    'lifetime' => 0,
    'secure'   => true,   // Ensure HTTPS is used.
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Set Content-Type to JSON.
header('Content-Type: application/json');

// Only allow access to authenticated admin users.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Access denied.']);
    exit;
}

// Include database connection.
require_once __DIR__ . '/../db/db_connect.php';

// Include S3 configuration and AWS SDK initialization.
require_once __DIR__ . '/../s3config.php';


// =====================
// STEGANOGRAPHY HELPER FUNCTIONS
// =====================

// Define a secret key for steganography encryption (store this securely in production, e.g. via an environment variable)
define('STEGANOGRAPHY_KEY', 'my-very-strong-secret-key');

/**
 * Encrypt secret data using AES-256-CBC.
 *
 * @param string $data The plain secret data.
 * @param string $key  The encryption key.
 * @return string The concatenated IV and ciphertext.
 */
function encryptSecret($data, $key)
{
    $cipher = "AES-256-CBC";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertext_raw = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    return $iv . $ciphertext_raw;
}

/**
 * Decrypt secret data using AES-256-CBC.
 *
 * @param string $encryptedData The concatenated IV and ciphertext.
 * @param string $key           The encryption key.
 * @return string The decrypted plain data.
 */
function decryptSecret($encryptedData, $key)
{
    $cipher = "AES-256-CBC";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($encryptedData, 0, $ivlen);
    $ciphertext_raw = substr($encryptedData, $ivlen);
    return openssl_decrypt($ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Embed secret data into an image using a basic LSB steganography method.
 *
 * @param string $inputPath  Path to the original image.
 * @param string $secretData The plain secret data to embed.
 * @param string $outputPath Path to save the modified image.
 * @return string The output path.
 * @throws Exception if the image cannot be processed.
 */
/**
 * Embed secret data into an image using a basic LSB steganography method.
 * Preserves alpha for PNG images with transparent background.
 */
function steganographyEncryptImage($inputPath, $secretData, $outputPath)
{
    // Encrypt the secret data.
    $encryptedSecret = encryptSecret($secretData, STEGANOGRAPHY_KEY);
    // Convert encrypted secret to a binary string.
    $binarySecret = '';
    for ($i = 0; $i < strlen($encryptedSecret); $i++) {
        $binarySecret .= str_pad(decbin(ord($encryptedSecret[$i])), 8, '0', STR_PAD_LEFT);
    }
    // Append a null terminator (8 zeros) to mark the end.
    $binarySecret .= '00000000';
    $secretLength = strlen($binarySecret);

    // Load the image from file.
    $imgData = file_get_contents($inputPath);
    if ($imgData === false) {
        throw new Exception("Failed to read input image.");
    }
    $img = imagecreatefromstring($imgData);
    if (!$img) {
        throw new Exception("Failed to create image from input.");
    }

    // IMPORTANT: If this is a PNG with transparency, preserve alpha channel:
    imagealphablending($img, false);
    imagesavealpha($img, true);

    $width  = imagesx($img);
    $height = imagesy($img);
    if ($secretLength > ($width * $height)) {
        imagedestroy($img);
        throw new Exception("Secret data is too large to embed in this image.");
    }

    $bitIndex = 0;
    // Loop through each pixel and embed secret bits into the least-significant bit of the blue channel.
    for ($y = 0; $y < $height && $bitIndex < $secretLength; $y++) {
        for ($x = 0; $x < $width && $bitIndex < $secretLength; $x++) {
            $rgba = imagecolorat($img, $x, $y);
            
            // Extract RGBA components:
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            // For truecolor images, the alpha is in the top 7 bits:
            $alpha = ($rgba & 0x7F000000) >> 24;

            // Set the LSB of the blue channel to the next secret bit.
            $bit = (int) $binarySecret[$bitIndex];
            $b   = ($b & 0xFE) | $bit;
            $bitIndex++;

            // Allocate the color with alpha preserved:
            $newColor = imagecolorallocatealpha($img, $r, $g, $b, $alpha);
            imagesetpixel($img, $x, $y, $newColor);
        }
    }

    // Save the modified image as PNG (preserving alpha).
    if (!imagepng($img, $outputPath)) {
        imagedestroy($img);
        throw new Exception("Failed to save encrypted image.");
    }
    imagedestroy($img);

    return $outputPath;
}


// =====================
// END STEGANOGRAPHY HELPER FUNCTIONS
// =====================


// Sanitize the "action" parameter.
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? '';

switch ($action) {

    case 'update_header_settings':
        $headerName = trim($_POST['header_name'] ?? '');
        $message = "";
        $headerLogoUrl = null;
    
        if (!empty($headerName)) {
            $stmt = $conn->prepare("
                INSERT INTO settings (`key`, value) 
                VALUES ('header_name', ?) 
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            if ($stmt) {
                $stmt->bind_param("s", $headerName);
                if ($stmt->execute()) {
                    $message .= "Header name updated. ";
                    $_SESSION['header_name'] = $headerName;
                } else {
                    error_log("Failed to update header name: " . $stmt->error);
                    $message .= "Failed to update header name. ";
                }
                $stmt->close();
            }
        }
    
        if (isset($_FILES['header_logo']) && $_FILES['header_logo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['header_logo']['tmp_name']);
    
            if (!in_array($fileType, $allowedTypes)) {
                $message .= "Invalid logo file type. ";
            } else {
                $ext = pathinfo($_FILES['header_logo']['name'], PATHINFO_EXTENSION);
                $s3Key = 'uploads/settings/header_logo_' . time() . '.png';
    
                // Encrypt and embed the image into a PNG
                $clearImageData = file_get_contents($_FILES['header_logo']['tmp_name']);
    
                // Encryption setup
                $cipher = "AES-256-CBC";
                $ivlen = openssl_cipher_iv_length($cipher);
                $iv = openssl_random_pseudo_bytes($ivlen);
                $rawKey = getenv('ENCRYPTION_KEY'); // Same method as your existing code
                $encryptionKey = hash('sha256', $rawKey, true);
    
                // Encrypt the image
                $encryptedData = openssl_encrypt($clearImageData, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
                $encryptedImageData = $iv . $encryptedData;
    
                // Use your existing embedDataInPng function
                $pngImage = embedDataInPng($encryptedImageData, 100);
                $encryptedTempPath = tempnam(sys_get_temp_dir(), 'enc_logo_') . '.png';
                imagepng($pngImage, $encryptedTempPath);
                imagedestroy($pngImage);
    
                try {
                    $result = $s3->putObject([
                        'Bucket' => $bucketName,
                        'Key' => $s3Key,
                        'Body' => fopen($encryptedTempPath, 'rb'),
                        'ACL' => 'public-read',
                        'ContentType' => 'image/png'
                    ]);
                    @unlink($encryptedTempPath);
    
                    $headerLogoUrl = str_replace(
                        "https://{$bucketName}.s3." . $_ENV['AWS_REGION'] . ".amazonaws.com/",
                        "/s3proxy/",
                        $result['ObjectURL']
                    );
    
                    $stmt = $conn->prepare("
                        INSERT INTO settings (`key`, value) 
                        VALUES ('header_logo', ?) 
                        ON DUPLICATE KEY UPDATE value = VALUES(value)
                    ");
                    if ($stmt) {
                        $stmt->bind_param("s", $headerLogoUrl);
                        if ($stmt->execute()) {
                            $message .= "Header logo updated. ";
                            $_SESSION['header_logo'] = $headerLogoUrl;
                        } else {
                            error_log("Failed to update header logo: " . $stmt->error);
                            $message .= "Failed to update header logo. ";
                        }
                        $stmt->close();
                    }
                } catch (Exception $e) {
                    error_log("S3 upload error: " . $e->getMessage());
                    $message .= "S3 upload error. ";
                }
            }
        }
    
        recordAuditLog($_SESSION['user_id'], 'Update Header Settings', $message);
    
        echo json_encode([
            'status' => true,
            'message' => $message,
            'header_name' => $headerName,
            'header_logo' => $headerLogoUrl
        ]);
        break;    

    case 'backup_database':
        // Remove any previously set Content-Type header so we can output file data.
        header_remove('Content-Type');
    
        // Use credentials from db_connect.php.
        $dbHost = $servername;
        $dbUser = $username;
        $dbPass = $password;
        $dbName = $dbname;
    
        // Define backup directory (adjust the path as needed for your server).
        $backupDir = __DIR__ . '/../../backups/';
        if (!is_dir($backupDir)) {
            if (!mkdir($backupDir, 0755, true)) {
                $error = error_get_last();
                error_log("Failed to create backup directory: " . print_r($error, true));
                header('Content-Type: text/plain');
                echo "Failed to create backup directory. Check permissions on: $backupDir";
                exit;
            }
        }
    
        // Generate a backup file name.
        $backupFile = $backupDir . "backup_" . date('Ymd_His') . ".sql";
    
        // Determine the mysqldump command path.
        $mysqldumpPath = '/opt/bitnami/mysql/bin/mysqldump';
        if (!file_exists($mysqldumpPath)) {
            $mysqldumpPath = '/opt/lampp/bin/mysqldump';
            if (!file_exists($mysqldumpPath)) {
                // Fallback to system command; ensure that mysqldump is in the server's PATH.
                $mysqldumpPath = 'mysqldump';
            }
        }
    
        // Build the command using proper escaping.
        $command = $mysqldumpPath .
                    " --host=" . escapeshellarg($dbHost) .
                    " --user=" . escapeshellarg($dbUser) .
                    " --password=" . escapeshellarg($dbPass) .
                    " " . escapeshellarg($dbName) .
                    " > " . escapeshellarg($backupFile);
        error_log("Starting database backup. Command: $command");
    
        // Execute the command.
        exec($command, $output, $returnVar);
    
        if ($returnVar === 0 && file_exists($backupFile)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="backup_' . date('Ymd_His') . '.sql"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($backupFile));
            flush();
            readfile($backupFile);
            unlink($backupFile);
            exit();
        } else {
            if (file_exists($backupFile)) {
                unlink($backupFile);
            }
            header('Content-Type: text/plain');
            http_response_code(500);
            echo "Database backup failed.";
            exit();
        }
        break;

    case 'restore_database':
        if (isset($_FILES['restore_file']) && $_FILES['restore_file']['error'] === UPLOAD_ERR_OK) {
            $dbHost = $servername;
            $dbUser = $username;
            $dbPass = $password;
            $dbName = $dbname;

            // Move the uploaded file to a temporary location.
            $tempRestore = tempnam(sys_get_temp_dir(), 'restore_') . '.sql';
            if (!move_uploaded_file($_FILES['restore_file']['tmp_name'], $tempRestore)) {
                error_log("Failed to move uploaded restore file.");
                echo json_encode([
                    'status'  => false,
                    'message' => "Failed to move uploaded restore file."
                ]);
                exit;
            }

            error_log("Starting database restore from file: $tempRestore");

            // Escape shell arguments.
            $dbHostEscaped      = escapeshellarg($dbHost);
            $dbUserEscaped      = escapeshellarg($dbUser);
            $dbPassEscaped      = escapeshellarg($dbPass); // added
            $dbNameEscaped      = escapeshellarg($dbName);
            $tempRestoreEscaped = escapeshellarg($tempRestore);

            // Use the full path to the mysql client.
            $mysqlPath = '/opt/lampp/bin/mysql';
            $command = "$mysqlPath --host={$dbHostEscaped} --user={$dbUserEscaped} --password={$dbPassEscaped} {$dbNameEscaped} < $tempRestoreEscaped";
            error_log("Restore command: $command");

            $output = [];
            exec($command, $output, $returnVar);
            unlink($tempRestore); // Remove the temporary file.

            if ($returnVar === 0) {
                error_log("Database restore successful.");
                // Record the restore action using the audit log helper.
                recordAuditLog($_SESSION['user_id'], 'Database Restore', 'Database restored from uploaded file');

                echo json_encode([
                    'status'  => true,
                    'message' => "Database restore successful."
                ]);
            } else {
                $errorMessage = implode("\n", $output);
                error_log("Database restore failed: " . $errorMessage);
                echo json_encode([
                    'status'  => false,
                    'message' => "Database restore failed."
                ]);
            }
        } else {
            error_log("No restore file uploaded.");
            echo json_encode([
                'status'  => false,
                'message' => "No restore file uploaded."
            ]);
        }
        break;

    case 'get_audit_logs':
        $auditLogs = [];
        $result = $conn->query("
            SELECT al.*, u.first_name, u.last_name 
            FROM audit_logs al 
            LEFT JOIN users u ON al.user_id = u.user_id 
            ORDER BY al.created_at DESC 
            LIMIT 100
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $auditLogs[] = $row;
            }
            echo json_encode(['status' => true, 'audit_logs' => $auditLogs]);
        } else {
            error_log("Failed to fetch audit logs: " . $conn->error);
            echo json_encode([
                'status'  => false,
                'message' => "Failed to fetch audit logs."
            ]);
        }
        break;

    default:
        error_log("Invalid action requested: " . $action);
        echo json_encode([
            'status'  => false,
            'message' => "Invalid action."
        ]);
        break;
}

// Close the database connection.
$conn->close();
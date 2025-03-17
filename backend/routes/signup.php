<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once '../controllers/authController.php';
// Include the S3 configuration file (ensure it initializes $s3 and $bucketName)
require_once '../s3config.php';

header('Content-Type: application/json');
// Added security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// =====================
// STEGANOGRAPHY HELPER FUNCTIONS
// =====================

// Define a secret key for steganography encryption.
// In production, store this securely (e.g. in an environment variable)
define('STEGANOGRAPHY_KEY', 'my-very-strong-secret-key');

/**
 * Encrypt secret data using AES-256-CBC.
 *
 * @param string $data The plain secret data.
 * @param string $key  The encryption key.
 * @return string The concatenated IV and ciphertext.
 */
function encryptSecret($data, $key) {
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
function decryptSecret($encryptedData, $key) {
    $cipher = "AES-256-CBC";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($encryptedData, 0, $ivlen);
    $ciphertext_raw = substr($encryptedData, $ivlen);
    return openssl_decrypt($ciphertext_raw, $cipher, $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Embed secret data into an image using a simple LSB steganography method.
 *
 * @param string $inputPath  Path to the original image.
 * @param string $secretData The plain secret data to embed.
 * @param string $outputPath Path to save the modified image.
 * @return string The output path.
 * @throws Exception if the image cannot be processed.
 */
function steganographyEncryptImage($inputPath, $secretData, $outputPath) {
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

    // Load the image.
    $imgData = file_get_contents($inputPath);
    if ($imgData === false) {
        throw new Exception("Failed to read input image.");
    }
    $img = imagecreatefromstring($imgData);
    if (!$img) {
        throw new Exception("Failed to create image from input.");
    }

    $width = imagesx($img);
    $height = imagesy($img);
    if ($secretLength > ($width * $height)) {
        throw new Exception("Secret data is too large to embed in this image.");
    }

    $bitIndex = 0;
    // Loop through each pixel and embed secret bits into the least-significant bit of the blue channel.
    for ($y = 0; $y < $height && $bitIndex < $secretLength; $y++) {
        for ($x = 0; $x < $width && $bitIndex < $secretLength; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $bit = intval($binarySecret[$bitIndex]);
            $newB = ($b & 0xFE) | $bit;
            $newColor = imagecolorallocate($img, $r, $g, $newB);
            imagesetpixel($img, $x, $y, $newColor);
            $bitIndex++;
        }
    }

    // Save the modified image as PNG.
    if (!imagepng($img, $outputPath)) {
        imagedestroy($img);
        throw new Exception("Failed to save encrypted image.");
    }
    imagedestroy($img);
    return $outputPath;
}

/**
 * Extract and decrypt secret data from an image that was processed with steganographyEncryptImage().
 *
 * @param string $inputPath Path to the image with embedded secret.
 * @return string The decrypted secret data.
 * @throws Exception if extraction fails.
 */
function steganographyDecryptImage($inputPath) {
    $imgData = file_get_contents($inputPath);
    if ($imgData === false) {
        throw new Exception("Failed to read input image.");
    }
    $img = imagecreatefromstring($imgData);
    if (!$img) {
        throw new Exception("Failed to create image from input.");
    }
    $width = imagesx($img);
    $height = imagesy($img);
    $binaryData = '';

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $b = $rgb & 0xFF;
            $binaryData .= ($b & 1) ? '1' : '0';
            if (strlen($binaryData) >= 8 && substr($binaryData, -8) === '00000000') {
                break 2;
            }
        }
    }
    imagedestroy($img);
    // Remove the null terminator.
    $binaryData = substr($binaryData, 0, -8);
    $encryptedSecret = '';
    for ($i = 0; $i < strlen($binaryData); $i += 8) {
        $byte = substr($binaryData, $i, 8);
        $encryptedSecret .= chr(bindec($byte));
    }
    return decryptSecret($encryptedSecret, STEGANOGRAPHY_KEY);
}

// =====================
// END STEGANOGRAPHY HELPER FUNCTIONS
// =====================

// Continue with your existing code:

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    // Validate JSON decoding
    if (is_null($data)) {
        error_log("Invalid JSON input received.");
        echo json_encode(['status' => false, 'message' => 'Invalid input.']);
        exit;
    }

    if (empty($data)) {
        echo json_encode(['status' => false, 'message' => 'No data received.']);
        exit;
    }

    // Initial step of signup: Handling email input
    if (isset($data['email']) && !isset($data['otp']) && !isset($data['first_name'])) {
        $email = trim($data['email']);

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        global $conn;
        // Add a new record if the email does not exist
        if (!emailExists($email)) {
            // Retrieve the visually impaired flag from the session (defaulting to 0 if not set)
            $visually_impaired = (isset($_SESSION['visually_impaired']) && $_SESSION['visually_impaired']) ? 1 : 0;
            $virtual_id = generateVirtualId(); // Generate virtual ID
            $role = 'user'; // Default role

            $stmt = $conn->prepare("INSERT INTO users (email, role, virtual_id, visually_impaired) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $email, $role, $virtual_id, $visually_impaired);

            if (!$stmt->execute()) {
                error_log("Failed to create a temporary user record for email: $email");
                echo json_encode(['status' => false, 'message' => 'Error creating user record.']);
                exit;
            }
            // Get the newly created user ID and record audit log.
            $newUserId = $conn->insert_id;
            recordAuditLog($newUserId, "Signup Initiated", "Temporary user record created for email: $email");
            $stmt->close();
        } else {
            // If the email already exists, retrieve its user_id
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                recordAuditLog($row['user_id'], "Signup Initiated", "Existing user attempted signup with email: $email");
            }
            $stmt->close();
        }

        // Generate OTP and send email
        if (generateOTP($email)) {
            // Retrieve user ID for audit logging
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                recordAuditLog($row['user_id'], "OTP Sent", "OTP generated and sent to email: $email");
            }
            $stmt->close();
            echo json_encode(['status' => true, 'message' => 'OTP sent to your email.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to send OTP.']);
        }
        exit;
    }

    // OTP verification step
    elseif (isset($data['otp'], $data['email']) && !isset($data['first_name'])) {
        $email = trim($data['email']);
        $otp = trim($data['otp']);

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // Verify OTP
        if (verifyOTP($email, $otp)) {
            // Retrieve user ID for audit logging
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                recordAuditLog($row['user_id'], "OTP Verified", "OTP verified for email: $email");
            }
            $stmt->close();
            echo json_encode(['status' => true, 'message' => 'OTP verified. Proceed to enter details.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Invalid or expired OTP.']);
        }
        exit;
    }

    // Final signup step: Collecting additional details (first name, last name) and optional faceData.
    elseif (isset($data['first_name'], $data['last_name'], $data['email'])) {
        $email = trim($data['email']);
        $first_name = trim($data['first_name']);
        $last_name = trim($data['last_name']);

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // Ensure first name and last name are not empty
        if (empty($first_name) || empty($last_name)) {
            echo json_encode(['status' => false, 'message' => 'First name and last name are required.']);
            exit;
        }

        global $conn;
        $visually_impaired = (isset($_SESSION['visually_impaired']) && $_SESSION['visually_impaired']) ? 1 : 0;

        // Check if a face image (Base64) was submitted.
        if (isset($data['faceData']) && !empty($data['faceData'])) {
            $faceData = $data['faceData'];
            // Remove the prefix if it exists (e.g., "data:image/png;base64,")
            if (strpos($faceData, 'base64,') !== false) {
                $faceData = explode('base64,', $faceData)[1];
            }
            $decodedFaceData = base64_decode($faceData);
            
            // Write the decoded face data to a temporary file.
            $tempFaceFile = tempnam(sys_get_temp_dir(), 'face_') . '.png';
            file_put_contents($tempFaceFile, $decodedFaceData);
            
            // Define secret data to embed (for example, embed the email and current timestamp)
            $secretData = "UserEmail:{$email};Timestamp:" . time();
            // Encrypt the face image with steganography.
            $encryptedFaceFile = tempnam(sys_get_temp_dir(), 'enc_face_') . '.png';
            try {
                steganographyEncryptImage($tempFaceFile, $secretData, $encryptedFaceFile);
            } catch (Exception $e) {
                error_log("Steganography encryption error: " . $e->getMessage());
                // Fallback: use the original file if encryption fails.
                $encryptedFaceFile = $tempFaceFile;
            }
            
            // Generate a unique S3 key.
            $s3Key = 'uploads/faces/' . uniqid() . '.png';

            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => fopen($encryptedFaceFile, 'rb'),
                    'ACL'         => 'public-read',
                    'ContentType' => 'image/png'
                ]);
            } catch (Aws\Exception\AwsException $e) {
                error_log("S3 upload error: " . $e->getMessage());
                echo json_encode(['status' => false, 'message' => 'Internal server error.']);
                exit;
            }
            
            // Clean up temporary files.
            @unlink($tempFaceFile);
            @unlink($encryptedFaceFile);

            // Convert S3 URL to local proxy URL if needed.
            $relativeFileName = str_replace(
                "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
                "/s3proxy/",
                $result['ObjectURL']
            );

            // Update the user details including first name, last name, face image, and visually impaired flag.
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, face_image = ?, visually_impaired = ? WHERE email = ?");
            $stmt->bind_param("sssis", $first_name, $last_name, $relativeFileName, $visually_impaired, $email);
        } else {
            // Update without face image.
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, visually_impaired = ? WHERE email = ?");
            $stmt->bind_param("ssis", $first_name, $last_name, $visually_impaired, $email);
        }

        if ($stmt->execute()) {
            // Retrieve the user ID for audit logging.
            $stmt2 = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            if ($row = $result2->fetch_assoc()) {
                recordAuditLog($row['user_id'], "Signup Completed", "User details updated for email: $email");
            }
            $stmt2->close();

            unset($_SESSION['user_id']);
            echo json_encode(['status' => true, 'message' => 'Signup successful! You can now log in.']);
        } else {
            error_log("Failed to update user details for email: $email");
            echo json_encode(['status' => false, 'message' => 'Failed to update account details.']);
        }
        $stmt->close();
        $conn->close();
        exit;
    }

    // If request doesn't match any of the above
    else {
        echo json_encode(['status' => false, 'message' => 'Invalid request.']);
        exit;
    }
} else {
    // Handle invalid HTTP methods
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
}
?>

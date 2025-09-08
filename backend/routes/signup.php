<?php
// Load Composer autoloader and .env variables
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

require_once '../db/db_connect.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    configureSessionSecurity();
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
// HELPER FUNCTIONS
// =====================

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
 * embedDataInPng:
 * Converts binary data into a valid PNG image by mapping every 3 bytes to a pixel (R, G, B).
 * If the data does not fill the image completely, remaining pixels are padded with black.
 *
 * @param string $binaryData The binary data to embed.
 * @param int    $width      Desired width of the PNG image.
 * @return GdImage          A GD image object.
 */
function embedDataInPng($binaryData): GdImage
{
    $dataLen = strlen($binaryData);
    // Each pixel holds 3 bytes.
    $numPixels = ceil($dataLen / 3);

    // Make the image roughly square:
    // e.g., width ~ height ~ sqrt(numPixels)
    $width = (int) floor(sqrt($numPixels));
    if ($width < 1) {
        $width = 1;
    }
    $height = (int) ceil($numPixels / $width);

    $img = imagecreatetruecolor($width, $height);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefill($img, 0, 0, $black);

    $pos = 0;
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if ($pos < $dataLen) {
                $r = ord($binaryData[$pos++]) ?: 0;
                $g = ($pos < $dataLen) ? ord($binaryData[$pos++]) : 0;
                $b = ($pos < $dataLen) ? ord($binaryData[$pos++]) : 0;
                $color = imagecolorallocate($img, $r, $g, $b);
                imagesetpixel($img, $x, $y, $color);
            } else {
                imagesetpixel($img, $x, $y, $black);
            }
        }
    }
    return $img;
}

// =====================
// END HELPER FUNCTIONS
// =====================

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
        if (!emailExists($email)) {
            $visually_impaired = (isset($_SESSION['visually_impaired']) && $_SESSION['visually_impaired']) ? 1 : 0;
            $role = 'user';

            $stmt = $conn->prepare("INSERT INTO users (email, role, visually_impaired) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $email, $role, $visually_impaired);

            if (!$stmt->execute()) {
                error_log("Failed to create a temporary user record for email: $email");
                echo json_encode(['status' => false, 'message' => 'Error creating user record.']);
                exit;
            }
            $newUserId = $conn->insert_id;
            recordAuditLog($newUserId, "Signup Initiated", "Temporary user record created for email: $email");
            $stmt->close();
        } else {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                recordAuditLog($row['user_id'], "Signup Initiated", "Existing user attempted signup with email: $email");
            }
            $stmt->close();
        }

        if (generateOTP($email)) {
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

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        if (verifyOTP($email, $otp)) {
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
    // Final signup step: Collect additional details and optionally process faceData.
    elseif (isset($data['first_name'], $data['last_name'], $data['email'])) {
        $email = trim($data['email']);
        $first_name = trim($data['first_name']);
        $last_name = trim($data['last_name']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        if (empty($first_name) || empty($last_name)) {
            echo json_encode(['status' => false, 'message' => 'First name and last name are required.']);
            exit;
        }

        global $conn;
        $visually_impaired = (isset($_SESSION['visually_impaired']) && $_SESSION['visually_impaired']) ? 1 : 0;

        // Update names and visually_impaired only (face image support removed)
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, visually_impaired = ? WHERE email = ?");
        $stmt->bind_param("ssis", $first_name, $last_name, $visually_impaired, $email);

        if ($stmt->execute()) {
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
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid request.']);
        exit;
    }
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
}

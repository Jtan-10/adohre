<?php
require_once '../db/db_connect.php'; // Adjust path if necessary
require_once '../s3config.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Set JSON header and allow only POST requests.
header('Content-Type: application/json');

// -------------------------
// Read input only once
// -------------------------
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (empty($data)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'No data received or invalid JSON.']);
    exit;
}

// -------------------------
// Define helper function if not already defined.
// -------------------------
if (!function_exists('embedDataInPng')) {
    /**
     * embedDataInPng:
     * Converts binary data into a valid PNG image by mapping every 3 bytes to a pixel (R, G, B).
     * Remaining pixels are padded with black.
     *
     * @param string $binaryData The binary data to embed.
     * @param int    $desiredWidth Desired width (not used in calculation below; width is computed to form a roughly square image)
     * @return GdImage A GD image resource.
     */
    function embedDataInPng($binaryData, $desiredWidth = 100): GdImage
    {
        $dataLen = strlen($binaryData);
        // Each pixel holds 3 bytes.
        $numPixels = ceil($dataLen / 3);
        // Create a roughly square image.
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
                    $r = ord($binaryData[$pos++]);
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
}

// -------------------------
// Enforce POST method.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
    exit;
}

// -------------------------
// Check session or allow email-based lookup
// -------------------------
if (!isset($_SESSION['user_id']) && !isset($_SESSION['temp_user'])) {
    if (!empty($data['email'])) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $data['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_id = $row['user_id'];
        } else {
            http_response_code(401);
            echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
            exit;
        }
        $stmt->close();
    } else {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
        exit;
    }
} else {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SESSION['temp_user']['user_id'];
}

// (Optional) Fetch the user's email from the database using user_id.
$stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$email = $row['email'] ?? '';
$stmt->close();

// -------------------------
// Validate required fields
// -------------------------
if (empty($data['first_name']) || empty($data['last_name'])) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Missing required fields: first_name and last_name.']);
    exit;
}

// Sanitize and validate input.
$first_name = htmlspecialchars(trim($data['first_name']), ENT_QUOTES, 'UTF-8');
$last_name  = htmlspecialchars(trim($data['last_name']), ENT_QUOTES, 'UTF-8');

if (strlen($first_name) > 50 || strlen($last_name) > 50) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Name fields are too long.']);
    exit;
}

$s3Key = '';
$relativeFileName = '';

// -------------------------
// Process face image if provided
// -------------------------
if (!empty($data['faceData'])) {
    $faceData = $data['faceData'];
    if (strpos($faceData, 'base64,') !== false) {
        $faceData = explode('base64,', $faceData)[1];
    }
    if (strlen($faceData) > (5 * 1024 * 1024)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Image size exceeds allowed limit.']);
        exit;
    }
    $decodedFaceData = base64_decode($faceData);
    if ($decodedFaceData === false) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Failed to decode face image.']);
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($decodedFaceData);
    if ($mimeType !== 'image/png') {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid image format. Only PNG allowed.']);
        exit;
    }
    // Write decoded image data to a temporary file.
    $tempFaceFile = tempnam(sys_get_temp_dir(), 'face_') . '.png';
    file_put_contents($tempFaceFile, $decodedFaceData);

    // ---- Encryption & Embedding Step ----
    $cipher = "AES-256-CBC";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $rawKey = getenv('ENCRYPTION_KEY');
    $encryptionKey = hash('sha256', $rawKey, true);
    $clearImageData = file_get_contents($tempFaceFile);
    $encryptedData = openssl_encrypt($clearImageData, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    $encryptedImageData = $iv . $encryptedData;

    // Embed the encrypted data into a valid PNG.
    $pngImage = embedDataInPng($encryptedImageData, 100);
    $finalEncryptedPngFile = tempnam(sys_get_temp_dir(), 'enc_png_') . '.png';
    imagepng($pngImage, $finalEncryptedPngFile);
    imagedestroy($pngImage);
    // ---- End Encryption & Embedding Step ----

    // Generate a unique S3 key.
    $s3Key = 'uploads/faces/' . uniqid() . '.png';
    try {
        $result = $s3->putObject([
            'Bucket'      => $bucketName,
            'Key'         => $s3Key,
            'Body'        => fopen($finalEncryptedPngFile, 'rb'),
            'ACL'         => 'public-read',
            'ContentType' => 'image/png'
        ]);
        $relativeFileName = str_replace(
            "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
            "/s3proxy/",
            $result['ObjectURL']
        );
    } catch (Aws\Exception\AwsException $e) {
        error_log('S3 Upload Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Failed to upload face image.']);
        exit;
    }
    @unlink($tempFaceFile);
    @unlink($finalEncryptedPngFile);
}

// -------------------------
// Prepare and execute update query
// -------------------------
if (!empty($relativeFileName)) {
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, face_image = ? WHERE user_id = ?");
    if ($stmt === false) {
        error_log('Database prepare error: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error.']);
        exit;
    }
    $stmt->bind_param("sssi", $first_name, $last_name, $relativeFileName, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE user_id = ?");
    if ($stmt === false) {
        error_log('Database prepare error: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error.']);
        exit;
    }
    $stmt->bind_param("ssi", $first_name, $last_name, $user_id);
}

if ($stmt->execute()) {
    recordAuditLog($user_id, 'Profile Update', 'User updated profile details' . (!empty($relativeFileName) ? ' (face image updated)' : ''));
    http_response_code(200);
    echo json_encode(['status' => true, 'message' => 'Profile updated successfully!']);
} else {
    error_log("Database execution error for user_id: $user_id - " . $stmt->error);
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to update profile details.']);
}

$stmt->close();
$conn->close();
exit;

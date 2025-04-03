<?php
session_start();
require_once '../db/db_connect.php'; // Adjust path if necessary
require_once '../s3config.php';

// Set JSON header and allow only POST requests.
header('Content-Type: application/json');

// Enforce POST method.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Instead of immediately returning 401 if no session, allow email-based lookup.
if (!isset($_SESSION['user_id']) && !isset($_SESSION['temp_user'])) {
    // If an email is provided in the payload, try to fetch the user_id.
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
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
    // Use the authenticated user's ID from session.
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

// Read and decode the JSON payload.
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (empty($data)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'No data received.']);
    exit;
}

// Ensure required fields are provided.
if (empty($data['first_name']) || empty($data['last_name'])) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Missing required fields: first_name and last_name.']);
    exit;
}

// Sanitize and validate the input.
$first_name = htmlspecialchars(trim($data['first_name']), ENT_QUOTES, 'UTF-8');
$last_name  = htmlspecialchars(trim($data['last_name']), ENT_QUOTES, 'UTF-8');

// Validate length constraints.
if (strlen($first_name) > 50 || strlen($last_name) > 50) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Name fields are too long.']);
    exit;
}

$s3Key = '';
$relativeFileName = '';

// Check if a face image (Base64) was submitted.
if (!empty($data['faceData'])) {
    $faceData = $data['faceData'];
    // Remove the prefix if it exists (e.g., "data:image/png;base64,")
    if (strpos($faceData, 'base64,') !== false) {
        $faceData = explode('base64,', $faceData)[1];
    }

    // Enforce a maximum size limit (adjust as needed).
    if (strlen($faceData) > (5 * 1024 * 1024)) { // roughly 5MB limit on base64 string size
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

    // Verify the MIME type of the image.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($decodedFaceData);
    if ($mimeType !== 'image/png') {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid image format. Only PNG allowed.']);
        exit;
    }

    // Write the decoded image data to a temporary file.
    $tempFaceFile = tempnam(sys_get_temp_dir(), 'face_') . '.png';
    file_put_contents($tempFaceFile, $decodedFaceData);

    // ---- Encryption & Embedding Step ----
    $cipher = "AES-256-CBC";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    // Retrieve the encryption key from your environment (.env file)
    $rawKey = getenv('ENCRYPTION_KEY');
    // Derive a 32-byte key using SHA-256.
    $encryptionKey = hash('sha256', $rawKey, true);
    $clearImageData = file_get_contents($tempFaceFile);
    $encryptedData = openssl_encrypt($clearImageData, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    // Prepend the IV to the encrypted data.
    $encryptedImageData = $iv . $encryptedData;

    // Embed the encrypted data into a valid PNG.
    // (Ensure that the embedDataInPng() function is defined in this file or included.)
    $pngImage = embedDataInPng($encryptedImageData, 100);
    $finalEncryptedPngFile = tempnam(sys_get_temp_dir(), 'enc_png_') . '.png';
    imagepng($pngImage, $finalEncryptedPngFile);
    imagedestroy($pngImage);
    // ---- End Encryption & Embedding Step ----

    // Generate a unique S3 key (using uniqid for consistency with signup.php).
    $s3Key = 'uploads/faces/' . uniqid() . '.png';

    try {
        // Upload the encrypted PNG file to S3.
        $result = $s3->putObject([
            'Bucket'      => $bucketName,
            'Key'         => $s3Key,
            'Body'        => fopen($finalEncryptedPngFile, 'rb'),
            'ACL'         => 'public-read', // Adjust ACL as needed
            'ContentType' => 'image/png'
        ]);
        // Map S3 URL to your internal relative path if necessary.
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

    // Clean up temporary files.
    @unlink($tempFaceFile);
    @unlink($finalEncryptedPngFile);
}

// Prepare the update query securely using prepared statements.
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
    // Audit log: record that the user updated their profile details.
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
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

// Check for valid session (ensure the user is authenticated).
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Validate CSRF token if your application uses one.
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

// Read and decode the JSON payload.
$input = file_get_contents("php://input");
$data = json_decode($input, true);
if (empty($data)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'No data received.']);
    exit;
}

// Ensure required fields are provided.
if (empty($data['email']) || empty($data['first_name']) || empty($data['last_name'])) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Sanitize and validate the input.
$email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
$first_name = htmlspecialchars(trim($data['first_name']), ENT_QUOTES, 'UTF-8');
$last_name = htmlspecialchars(trim($data['last_name']), ENT_QUOTES, 'UTF-8');

// Validate email format.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
    exit;
}

// Validate length constraints.
if (strlen($first_name) > 50 || strlen($last_name) > 50) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Name fields are too long.']);
    exit;
}

global $conn;
$s3Key = '';
$relativeFileName = '';

// Check if a face image (Base64) was submitted.
if (!empty($data['faceData'])) {
    $faceData = $data['faceData'];
    // Remove the prefix if it exists (e.g., "data:image/png;base64,")
    if (strpos($faceData, 'base64,') !== false) {
        $faceData = explode('base64,', $faceData)[1];
    }
    
    // Enforce a maximum size limit (adjust the limit as needed).
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
    
    // Generate a secure, unique file name for S3.
    $s3Key = 'uploads/faces/' . bin2hex(random_bytes(16)) . '.png';

    try {
        // Upload the image data directly to S3.
        $result = $s3->putObject([
            'Bucket'      => $bucketName,
            'Key'         => $s3Key,
            'Body'        => $decodedFaceData,
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
}

// Prepare the update query securely using prepared statements.
if (!empty($relativeFileName)) {
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, face_image = ? WHERE email = ?");
    if ($stmt === false) {
        error_log('Database prepare error: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error.']);
        exit;
    }
    $stmt->bind_param("ssss", $first_name, $last_name, $relativeFileName, $email);
} else {
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE email = ?");
    if ($stmt === false) {
        error_log('Database prepare error: ' . $conn->error);
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error.']);
        exit;
    }
    $stmt->bind_param("sss", $first_name, $last_name, $email);
}

if ($stmt->execute()) {
    // Audit log: record that the user updated their profile details.
    // We use the user_id from the session.
    recordAuditLog($_SESSION['user_id'], 'Profile Update', 'User updated profile details' . (!empty($relativeFileName) ? ' (face image updated)' : ''));

    http_response_code(200);
    echo json_encode(['status' => true, 'message' => 'Profile updated successfully!']);
} else {
    error_log("Database execution error for email: $email - " . $stmt->error);
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to update profile details.']);
}

$stmt->close();
$conn->close();
exit;
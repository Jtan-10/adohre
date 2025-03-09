<?php
session_start();
require_once '../db/db_connect.php'; // Adjust path if necessary

// Include the S3 configuration file (which sets up $s3 and $bucketName)
require_once '../s3config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read and decode the JSON payload
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data)) {
        echo json_encode(['status' => false, 'message' => 'No data received.']);
        exit;
    }

    // Check that required fields exist
    if (!isset($data['email']) || !isset($data['first_name']) || !isset($data['last_name'])) {
        echo json_encode(['status' => false, 'message' => 'Missing required fields.']);
        exit;
    }

    $email = trim($data['email']);
    $first_name = trim($data['first_name']);
    $last_name = trim($data['last_name']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    global $conn;

    // Check if a face image (Base64) was submitted
if (isset($data['faceData']) && !empty($data['faceData'])) {
    $faceData = $data['faceData'];
    // Remove the prefix if it exists (e.g., "data:image/png;base64,")
    if (strpos($faceData, 'base64,') !== false) {
        $faceData = explode('base64,', $faceData)[1];
    }
    $decodedFaceData = base64_decode($faceData);
    if ($decodedFaceData === false) {
        echo json_encode(['status' => false, 'message' => 'Failed to decode face image.']);
        exit;
    }

    // Generate a unique file name (used as S3 key)
    $s3Key = 'uploads/faces/' . uniqid() . '.png';

    try {
        // Upload the image data directly to S3
        $result = $s3->putObject([
            'Bucket'      => $bucketName,          // Your bucket name defined in s3config.php
            'Key'         => $s3Key,               // The key where the file will be stored
            'Body'        => $decodedFaceData,     // Raw image data
            'ACL'         => 'public-read',        // Change ACL as needed
            'ContentType' => 'image/png'
        ]);
    } catch (Aws\Exception\AwsException $e) {
        echo json_encode(['status' => false, 'message' => 'Failed to upload face image to S3: ' . $e->getMessage()]);
        exit;
    }

    // Use the S3 key as the stored path (you can also use $result['ObjectURL'] for the full URL)
    $relativeFileName = $result['ObjectURL'];

    // Prepare an UPDATE query that includes first_name, last_name, and face_image.
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, face_image = ? WHERE email = ?");
    $stmt->bind_param("ssss", $first_name, $last_name, $relativeFileName, $email);
} else {
        // If no face image is provided, update only the names.
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE email = ?");
        $stmt->bind_param("sss", $first_name, $last_name, $email);
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => true, 'message' => 'Profile updated successfully!']);
    } else {
        error_log("Failed to update user details for email: $email");
        echo json_encode(['status' => false, 'message' => 'Failed to update profile details.']);
    }
    $stmt->close();
    $conn->close();
    exit;
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
    exit;
}
?>
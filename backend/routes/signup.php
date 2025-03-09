<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../controllers/authController.php';

// Include the S3 configuration file (ensure it initializes $s3 and $bucketName)
require_once '../s3config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

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

        // Add a new record if the email does not exist
        if (!emailExists($email)) {
            global $conn;
            
            // Retrieve the visually impaired flag from the session (defaulting to 0 if not set)
            $visually_impaired = (isset($_SESSION['visually_impaired']) && $_SESSION['visually_impaired']) ? 1 : 0;
            $virtual_id = generateVirtualId(); // Generate virtual ID
            $role = 'user'; // Default role

            // Note: The INSERT query now includes the visually_impaired column.
            $stmt = $conn->prepare("INSERT INTO users (email, role, virtual_id, visually_impaired) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $email, $role, $virtual_id, $visually_impaired);

            if (!$stmt->execute()) {
                error_log("Failed to create a temporary user record for email: $email");
                echo json_encode(['status' => false, 'message' => 'Error creating user record.']);
                exit;
            }
        }

        // Generate OTP and send email
        if (generateOTP($email)) {
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

    // Retrieve visually impaired flag from session (default to 0 if not set)
    $visually_impaired = (isset($_SESSION['visually_impaired']) && $_SESSION['visually_impaired']) ? 1 : 0;

    // Check if a face image (Base64) was submitted.
if (isset($data['faceData']) && !empty($data['faceData'])) {
    $faceData = $data['faceData'];
    // Remove the prefix if it exists (e.g., "data:image/png;base64,")
    if (strpos($faceData, 'base64,') !== false) {
        $faceData = explode('base64,', $faceData)[1];
    }
    $decodedFaceData = base64_decode($faceData);

    // Generate a unique file name (S3 key)
    $s3Key = 'uploads/faces/' . uniqid() . '.png';

    try {
        $result = $s3->putObject([
            'Bucket'      => $bucketName,          // Your S3 bucket name
            'Key'         => $s3Key,               // The key under which the file is stored in S3
            'Body'        => $decodedFaceData,     // The raw image data
            'ACL'         => 'public-read',        // Adjust ACL as needed (e.g., 'private' for restricted access)
            'ContentType' => 'image/png'
        ]);
    } catch (Aws\Exception\AwsException $e) {
        echo json_encode(['status' => false, 'message' => 'Failed to upload face image to S3: ' . $e->getMessage()]);
        exit;
    }

    // Set the relative file name (or you could use $result['ObjectURL'] if you prefer the full URL)
    $relativeFileName = $result['ObjectURL'];

    // Update the user details including first name, last name, face image, and visually impaired flag.
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, face_image = ?, visually_impaired = ? WHERE email = ?");
    $stmt->bind_param("sssis", $first_name, $last_name, $relativeFileName, $visually_impaired, $email);
} else {
        // Update without face image, but still update visually impaired flag.
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, visually_impaired = ? WHERE email = ?");
        $stmt->bind_param("ssis", $first_name, $last_name, $visually_impaired, $email);
    }

    if ($stmt->execute()) {
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
<?php
require_once '../controllers/authController.php';
require_once '../db/db_connect.php'; // Ensure this is included for database connection

use Zxing\QrReader;

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check the request method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // **Virtual ID Login via Uploaded QR Code**
    if (isset($_FILES['virtualIdImage'])) {
        // Ensure file is uploaded
        $fileTmpPath = $_FILES['virtualIdImage']['tmp_name'];
        if (!$fileTmpPath || !file_exists($fileTmpPath)) {
            echo json_encode(['status' => false, 'message' => 'File upload failed.']);
            exit();
        }

        // Validate that the uploaded file is an image
        if (!getimagesize($fileTmpPath)) {
            echo json_encode(['status' => false, 'message' => 'Uploaded file is not a valid image.']);
            exit();
        }

        // Read QR code from the uploaded image
        try {
            $qrReader = new QrReader($fileTmpPath);
            $virtualId = $qrReader->text();
        } catch (Exception $e) {
            // Log the detailed error internally if needed
            error_log('QR code read error: ' . $e->getMessage());
            echo json_encode(['status' => false, 'message' => 'Error reading QR code.']);
            exit();
        }

        if (empty($virtualId)) {
            echo json_encode(['status' => false, 'message' => 'Invalid or unreadable QR code.']);
            exit();
        }

        // Validate the Virtual ID against the database
        $stmt = $conn->prepare('SELECT user_id, first_name, last_name, role, profile_image FROM users WHERE virtual_id = ?');
        $stmt->bind_param('s', $virtualId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Prevent session fixation
            session_regenerate_id(true);
            // Set user session data
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_image'] = !empty($user['profile_image']) ? $user['profile_image'] : './assets/default-profile.jpeg';

            // Record audit log for successful Virtual ID login
            recordAuditLog($user['user_id'], 'Login via Virtual ID', 'User logged in using Virtual ID (via QR code upload)');

            echo json_encode(['status' => true, 'message' => 'Login successful.', 'user' => $user]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Invalid Virtual ID.']);
        }

        exit();
    }

    // Get JSON data for other login methods
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid JSON input.']);
        exit();
    }

    // **Virtual ID Login via Direct Parameter**
    if (isset($data['virtual_id'])) {
        $virtualId = $data['virtual_id'];

        // Validate Virtual ID in the database
        $stmt = $conn->prepare('SELECT user_id, first_name, last_name, role, profile_image FROM users WHERE virtual_id = ?');
        $stmt->bind_param('s', $virtualId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Prevent session fixation
            session_regenerate_id(true);
            // Set user session data
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_image'] = !empty($user['profile_image']) ? $user['profile_image'] : './assets/default-profile.jpeg';

            // Record audit log for successful Virtual ID login
            recordAuditLog($user['user_id'], 'Login via Virtual ID', 'User logged in using Virtual ID (via direct parameter)');

            echo json_encode(['status' => true, 'message' => 'Login successful.', 'user' => $user]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Invalid Virtual ID.']);
        }

        exit();
    }

    // **Email Login with OTP**
    $email = $data['email'] ?? null;

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid or missing email address.']);
        exit();
    }

    if (emailExists($email)) {
        if (generateOTP($email)) {
            echo json_encode(['status' => true, 'message' => 'OTP sent to your email.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Failed to send OTP.']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['status' => false, 'message' => 'Email not registered.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method Not Allowed.']);
}
?>
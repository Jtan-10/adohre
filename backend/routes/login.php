<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../controllers/authController.php';
require_once '../db/db_connect.php'; // Ensure this is included for database connection

use Zxing\QrReader;

header('Content-Type: application/json');

// Allow OPTIONS method for preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    exit();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine action; default to 'finalize'
$action = $_POST['action'] ?? 'finalize';

// First, try to decode JSON input
$data = json_decode(file_get_contents("php://input"), true);
// If JSON decoding fails, fallback to $_POST (for multipart/form-data requests)
if (!$data) {
    $data = $_POST;
}

// Check the request method
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // -------------------------
        // Virtual ID Login via Uploaded QR Code (File Upload)
        // -------------------------
        if (isset($_FILES['virtualIdImage'])) {
            // Ensure file is uploaded
            $fileTmpPath = $_FILES['virtualIdImage']['tmp_name'];
            if (!$fileTmpPath || !file_exists($fileTmpPath)) {
                error_log("login.php backend: File upload failed for virtualIdImage");
                echo json_encode(['status' => false, 'message' => 'File upload failed.']);
                exit();
            }
            // Validate that the uploaded file is an image
            if (!getimagesize($fileTmpPath)) {
                error_log("login.php backend: Uploaded file is not a valid image");
                echo json_encode(['status' => false, 'message' => 'Uploaded file is not a valid image.']);
                exit();
            }
            // Read QR code from the uploaded image
            try {
                $qrReader = new QrReader($fileTmpPath);
                $virtualId = $qrReader->text();
            } catch (Exception $e) {
                error_log("login.php backend: QR code read error - " . $e->getMessage());
                echo json_encode(['status' => false, 'message' => 'Error reading QR code.']);
                exit();
            }
            if (empty($virtualId)) {
                error_log("login.php backend: QR code is empty or unreadable");
                echo json_encode(['status' => false, 'message' => 'Invalid or unreadable QR code.']);
                exit();
            }
            // Look up user by virtual ID using face_image as stored reference
            $stmt = $conn->prepare('SELECT user_id, first_name, last_name, role, profile_image, face_image, virtual_id FROM users WHERE virtual_id = ?');
            if (!$stmt) {
                error_log("login.php backend: Database prepare error - " . $conn->error);
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Internal server error.']);
                exit();
            }
            $stmt->bind_param('s', $virtualId);
            if (!$stmt->execute()) {
                error_log("login.php backend: Database execute error - " . $stmt->error);
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Internal server error.']);
                exit();
            }
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if ($action === 'fetch') {
                    // In fetch mode, store user data in session as temporary user.
                    session_regenerate_id(true);
                    $_SESSION['temp_user'] = $user;
                    echo json_encode(['status' => true, 'message' => 'User data retrieved.', 'user' => $user]);
                } else { // Finalize login (file upload branch)
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_image'] = !empty($user['profile_image']) ? $user['profile_image'] : './assets/default-profile.jpeg';
                    error_log("login.php backend: Finalizing login for user id " . $user['user_id'] . " (QR code upload)");
                    try {
                        $auditResult = recordAuditLog($user['user_id'], 'Login via Virtual ID', 'User logged in using Virtual ID (via QR code upload)');
                        if (!$auditResult) {
                            error_log("login.php backend: recordAuditLog returned false for user id " . $user['user_id']);
                        }
                    } catch (Exception $e) {
                        error_log("login.php backend: Exception in recordAuditLog (QR code upload) for user id " . $user['user_id'] . " - " . $e->getMessage());
                    }
                    echo json_encode(['status' => true, 'message' => 'Login successful.', 'user' => $user]);
                }
            } else {
                error_log("login.php backend: No user found matching Virtual ID: " . $virtualId);
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'Invalid Virtual ID.']);
            }
            exit();
        }

        // -------------------------
        // Virtual ID Login via Direct Parameter
        // -------------------------
        if (isset($data['virtual_id'])) {
            $virtualId = $data['virtual_id'];
            // Updated query: include virtual_id in the SELECT
            $stmt = $conn->prepare('SELECT user_id, first_name, last_name, role, profile_image, face_image, virtual_id FROM users WHERE virtual_id = ?');
            if (!$stmt) {
                error_log("login.php backend: Database prepare error - " . $conn->error);
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Internal server error.']);
                exit();
            }
            $stmt->bind_param('s', $virtualId);
            if (!$stmt->execute()) {
                error_log("login.php backend: Database execute error - " . $stmt->error);
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Internal server error.']);
                exit();
            }
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if ($action === 'finalize') {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_image'] = !empty($user['profile_image']) ? $user['profile_image'] : './assets/default-profile.jpeg';
                    error_log("login.php backend: Finalizing login for user id " . $user['user_id'] . " (Direct parameter)");
                    try {
                        $auditResult = recordAuditLog($user['user_id'], 'Login via Virtual ID', 'User logged in using Virtual ID (via direct parameter)');
                        if (!$auditResult) {
                            error_log("login.php backend: recordAuditLog returned false for user id " . $user['user_id']);
                        }
                    } catch (Exception $e) {
                        error_log("login.php backend: Exception in recordAuditLog (Direct parameter) for user id " . $user['user_id'] . " - " . $e->getMessage());
                    }
                    echo json_encode(['status' => true, 'message' => 'Login successful.', 'user' => $user]);
                } else {
                    // For non-finalize action, store as temporary user.
                    session_regenerate_id(true);
                    $_SESSION['temp_user'] = $user;
                    echo json_encode(['status' => true, 'message' => 'User data retrieved.', 'user' => $user]);
                }
            } else {
                error_log("login.php backend: No user found matching Virtual ID: " . $virtualId);
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'Invalid Virtual ID.']);
            }
            exit();
        }

        // -------------------------
        // Email Login with OTP
        // -------------------------
        $email = $data['email'] ?? null;
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("login.php backend: Invalid or missing email address");
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid or missing email address.']);
            exit();
        }
        if (emailExists($email)) {
            if (generateOTP($email)) {
                echo json_encode(['status' => true, 'message' => 'OTP sent to your email.']);
            } else {
                error_log("login.php backend: Failed to send OTP to email: " . $email);
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Failed to send OTP.']);
            }
        } else {
            error_log("login.php backend: Email not registered: " . $email);
            http_response_code(404);
            echo json_encode(['status' => false, 'message' => 'Email not registered.']);
        }
        exit();
    } catch (Exception $e) {
        error_log('Error in login.php: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Internal server error.']);
        exit();
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method Not Allowed.']);
}

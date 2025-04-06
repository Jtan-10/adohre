<?php
// Enable debugging for development (set to false in production)
define('DEBUG', true);

if (DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

ob_start();
if (ob_get_length()) {
    ob_end_clean(); // Fully clear and close the output buffer
}

require_once '../controllers/authController.php';
require_once '../db/db_connect.php';

// Make sure required extensions are available
if (!extension_loaded('imagick')) {
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'message' => 'Server configuration error: Imagick extension is not available.']);
    exit();
}

// Check for QR library before using it
if (!class_exists('Zxing\QrReader')) {
    try {
        // Try to load the QR reader library
        require_once '../vendor/autoload.php';
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => false, 'message' => 'Server configuration error: QR code reader library not available.']);
        exit();
    }
}

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------
// Improved Input Handling:
// -------------------------
// Determine how to process input based on the Content-Type header
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => false, 'message' => 'Malformed JSON input.']);
        exit();
    }
} else {
    $data = $_POST;
}

// Set the action parameter (defaults to 'finalize' if not provided)
$action = $data['action'] ?? $_POST['action'] ?? 'finalize';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // -------------------------
        // Virtual ID Login via Uploaded PDF
        // -------------------------
        if (isset($_FILES['virtualIdPdf'])) {
            // Ensure file is uploaded
            $fileTmpPath = $_FILES['virtualIdPdf']['tmp_name'];
            if (!$fileTmpPath || !file_exists($fileTmpPath)) {
                echo json_encode(['status' => false, 'message' => 'File upload failed.']);
                exit();
            }
            // Validate that the uploaded file is a PDF
            if ($_FILES['virtualIdPdf']['type'] !== 'application/pdf') {
                echo json_encode(['status' => false, 'message' => 'Uploaded file must be a PDF.']);
                exit();
            }
            // Get the provided password from the form
            $pdfPassword = $_POST['virtualIdPassword'] ?? '';
            if (empty($pdfPassword)) {
                echo json_encode(['status' => false, 'message' => 'PDF password is required.']);
                exit();
            }
            
            try {
                // Create a temporary file for the decrypted PDF
                $tempOutputFile = tempnam(sys_get_temp_dir(), 'pdf_');

                // Try decryption using pdftk first
                $cmd = sprintf(
                    'pdftk %s input_pw %s output %s 2>&1',
                    escapeshellarg($fileTmpPath),
                    escapeshellarg($pdfPassword),
                    escapeshellarg($tempOutputFile)
                );
                exec($cmd, $output, $returnCode);
                if (DEBUG) {
                    error_log("pdftk command: $cmd");
                    error_log("pdftk return code: $returnCode");
                    error_log("pdftk output: " . implode(", ", $output));
                }
                if ($returnCode !== 0 || !file_exists($tempOutputFile) || filesize($tempOutputFile) == 0) {
                    // If pdftk fails, try qpdf
                    $cmd = sprintf(
                        'qpdf --password=%s --decrypt %s %s 2>&1',
                        escapeshellarg($pdfPassword),
                        escapeshellarg($fileTmpPath),
                        escapeshellarg($tempOutputFile)
                    );
                    exec($cmd, $output, $returnCode);
                    if (DEBUG) {
                        error_log("qpdf command: $cmd");
                        error_log("qpdf return code: $returnCode");
                        error_log("qpdf output: " . implode(", ", $output));
                    }
                    if ($returnCode !== 0 || !file_exists($tempOutputFile) || filesize($tempOutputFile) == 0) {
                        throw new Exception("Failed to decrypt PDF with external tools. Please ensure the password is correct.");
                    }
                }
                
                // --- Convert decrypted PDF page to image and extract QR code ---
                // Check if Imagick is loaded again (redundant but safe)
                if (!extension_loaded('imagick')) {
                    throw new Exception("Imagick extension is required for QR code extraction.");
                }
                
                $imagick = new Imagick();
                // Optionally set a resolution for better image quality
                $imagick->setResolution(300, 300);
                
                // Read only the first page ([0]) of the decrypted PDF
                $imagick->readImage($tempOutputFile . "[0]");
                $imagick->setImageFormat("png");
                $tempImageFile = tempnam(sys_get_temp_dir(), 'img_') . ".png";
                $imagick->writeImage($tempImageFile);
                $imagick->clear();
                $imagick->destroy();

                // Use QrReader to decode the QR code from the image
                $qrReader = new \Zxing\QrReader($tempImageFile);
                $virtualId = $qrReader->text();
                
                // Clean up temporary image file
                if (file_exists($tempImageFile)) {
                    unlink($tempImageFile);
                }

                if (DEBUG) {
                    error_log("Extracted Virtual ID from QR code: " . $virtualId);
                }

                if (empty($virtualId)) {
                    throw new Exception("QR code not detected or virtual ID is empty.");
                }

                // Clean up temporary PDF file
                if (file_exists($tempOutputFile)) {
                    unlink($tempOutputFile);
                }
            } catch (Exception $e) {
                if (isset($tempOutputFile) && file_exists($tempOutputFile)) {
                    unlink($tempOutputFile);
                }
                error_log('PDF processing error: ' . $e->getMessage());
                $msg = DEBUG ? $e->getMessage() : "Error processing PDF.";
                echo json_encode(['status' => false, 'message' => $msg]);
                exit();
            }

            // Look up user by virtual ID (obtained from the QR code)
            $stmt = $conn->prepare('SELECT user_id, first_name, last_name, role, profile_image, face_image, virtual_id FROM users WHERE virtual_id = ?');
            $stmt->bind_param('s', $virtualId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if ($action === 'fetch') {
                    session_regenerate_id(true);
                    $_SESSION['temp_user'] = $user;
                    echo json_encode(['status' => true, 'message' => 'User data retrieved.', 'user' => $user]);
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_image'] = !empty($user['profile_image']) ? $user['profile_image'] : './assets/default-profile.jpeg';
                    recordAuditLog($user['user_id'], 'Login via Virtual ID', 'User logged in using Virtual ID (via PDF upload)');
                    echo json_encode(['status' => true, 'message' => 'Login successful.', 'user' => $user]);
                }
            } else {
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'Invalid Virtual ID.']);
            }
            exit();
        }

        // -------------------------
        // Virtual ID Login via Uploaded Image (QR Code)
        // -------------------------
        if (isset($_FILES['virtualIdImage'])) {
            $fileTmpPath = $_FILES['virtualIdImage']['tmp_name'];
            if (!$fileTmpPath || !file_exists($fileTmpPath)) {
                echo json_encode(['status' => false, 'message' => 'File upload failed.']);
                exit();
            }
            
            // Check if the file is actually an image
            $imgInfo = @getimagesize($fileTmpPath);
            if ($imgInfo === false) {
                echo json_encode(['status' => false, 'message' => 'Uploaded file is not a valid image.']);
                exit();
            }
            
            try {
                // Use QrReader with full namespace if needed
                $qrReader = new \Zxing\QrReader($fileTmpPath);
                $virtualId = $qrReader->text();
            } catch (Exception $e) {
                error_log('QR code read error: ' . $e->getMessage());
                echo json_encode(['status' => false, 'message' => 'Error reading QR code: ' . ($DEBUG ? $e->getMessage() : '')]);
                exit();
            }
            
            if (empty($virtualId)) {
                echo json_encode(['status' => false, 'message' => 'Invalid or unreadable QR code.']);
                exit();
            }
            
            $stmt = $conn->prepare('SELECT user_id, first_name, last_name, role, profile_image, face_image, virtual_id FROM users WHERE virtual_id = ?');
            $stmt->bind_param('s', $virtualId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if ($action === 'fetch') {
                    session_regenerate_id(true);
                    $_SESSION['temp_user'] = $user;
                    echo json_encode(['status' => true, 'message' => 'User data retrieved.', 'user' => $user]);
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_image'] = !empty($user['profile_image']) ? $user['profile_image'] : './assets/default-profile.jpeg';
                    recordAuditLog($user['user_id'], 'Login via Virtual ID', 'User logged in using Virtual ID (via QR code upload)');
                    echo json_encode(['status' => true, 'message' => 'Login successful.', 'user' => $user]);
                }
            } else {
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'Invalid Virtual ID.']);
            }
            exit();
        }

        // -------------------------
        // Finalize login using virtual_id (face validation step)
        // -------------------------
        if (!isset($data['email']) && isset($_POST['virtual_id'])) {
            $virtualId = $_POST['virtual_id'];
            $stmt = $conn->prepare('SELECT user_id, first_name, last_name, role, profile_image, face_image, virtual_id FROM users WHERE virtual_id = ?');
            $stmt->bind_param('s', $virtualId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_image'] = !empty($user['profile_image']) ? $user['profile_image'] : './assets/default-profile.jpeg';
                recordAuditLog($user['user_id'], 'Login via Virtual ID', 'User logged in using Virtual ID (finalized after face validation)');
                echo json_encode(['status' => true, 'message' => 'Login successful.', 'user' => $user]);
            } else {
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
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid or missing email address.']);
            exit();
        }
        
        if (function_exists('emailExists') && function_exists('generateOTP')) {
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
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Authentication controller functions not available.']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Method Not Allowed.']);
    }
} catch (Exception $e) {
    // Global exception handler
    error_log('Uncaught exception in login.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => false, 
        'message' => DEBUG ? 'Server error: ' . $e->getMessage() : 'An unexpected error occurred.'
    ]);
    exit();
}
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

use Zxing\QrReader;
use setasign\Fpdi\Tcpdf\Fpdi;

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? 'finalize';
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST;
}

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
            // Use FPDI to process the PDF (free version does not support decryption natively)
            $pdf = new \setasign\Fpdi\Fpdi();

            // Create temporary file for processing
            $tempOutputFile = tempnam(sys_get_temp_dir(), 'pdf_');

            // Check if the PDF is encrypted by reading its header.
            $fileHeader = file_get_contents($fileTmpPath, false, null, 0, 1024);
            $isEncrypted = (strpos($fileHeader, '/Encrypt') !== false);

            if (DEBUG) {
                error_log("File header: " . substr($fileHeader, 0, 200));
                error_log("Is Encrypted: " . ($isEncrypted ? 'Yes' : 'No'));
            }

            // If the file does not appear encrypted, try loading it directly.
            if (!$isEncrypted) {
                try {
                    $pageCount = $pdf->setSourceFile($fileTmpPath);
                    // Successfully processed unencrypted PDF.
                    $virtualId = pathinfo($_FILES['virtualIdPdf']['name'], PATHINFO_FILENAME);
                } catch (Exception $e) {
                    // If FPDI fails, assume encryption and force fallback.
                    $isEncrypted = true;
                    if (DEBUG) {
                        error_log("FPDI load failed, forcing decryption fallback: " . $e->getMessage());
                    }
                }
            }

            // If the file is encrypted or FPDI failed, use external tools.
            if ($isEncrypted) {
                if (function_exists('exec')) {
                    // First try pdftk
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
                    if ($returnCode === 0 && file_exists($tempOutputFile) && filesize($tempOutputFile) > 0) {
                        try {
                            $pageCount = $pdf->setSourceFile($tempOutputFile);
                            $virtualId = pathinfo($_FILES['virtualIdPdf']['name'], PATHINFO_FILENAME);
                        } catch (Exception $e2) {
                            throw new Exception("Failed to process decrypted PDF (pdftk): " . $e2->getMessage());
                        }
                    } else {
                        // Fallback to qpdf if pdftk did not succeed
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
                        if ($returnCode === 0 && file_exists($tempOutputFile) && filesize($tempOutputFile) > 0) {
                            try {
                                $pageCount = $pdf->setSourceFile($tempOutputFile);
                                $virtualId = pathinfo($_FILES['virtualIdPdf']['name'], PATHINFO_FILENAME);
                            } catch (Exception $e3) {
                                throw new Exception("Failed to process decrypted PDF (qpdf): " . $e3->getMessage());
                            }
                        } else {
                            throw new Exception("Failed to decrypt PDF with external tools. Please ensure the password is correct.");
                        }
                    }
                } else {
                    throw new Exception("Failed to process encrypted PDF. Server configuration doesn't support external tools.");
                }
            }

            // Clean up temporary file
            if (file_exists($tempOutputFile)) {
                unlink($tempOutputFile);
            }
        } catch (Exception $e) {
            if (isset($tempOutputFile) && file_exists($tempOutputFile)) {
                unlink($tempOutputFile);
            }
            error_log('PDF processing error: ' . $e->getMessage());
            // Optionally, include detailed error info if DEBUG is enabled.
            $msg = DEBUG ? $e->getMessage() : "Error processing PDF.";
            echo json_encode(['status' => false, 'message' => $msg]);
            exit();
        }

        // Look up user by virtual ID
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
        if (!getimagesize($fileTmpPath)) {
            echo json_encode(['status' => false, 'message' => 'Uploaded file is not a valid image.']);
            exit();
        }
        try {
            $qrReader = new QrReader($fileTmpPath);
            $virtualId = $qrReader->text();
        } catch (Exception $e) {
            error_log('QR code read error: ' . $e->getMessage());
            echo json_encode(['status' => false, 'message' => 'Error reading QR code.']);
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
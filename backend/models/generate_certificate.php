<?php
// backend/models/generate_certificate.php

// Start output buffering to prevent extra output (e.g. whitespace, warnings)
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set response type to JSON
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    // Include Composer's autoloader and other required files
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../db/db_connect.php';
    require_once __DIR__ . '/../s3config.php';

    /**
     * Create certificate_layouts table if it doesn't exist yet
     */
    function ensureCertificateLayoutsTableExists()
    {
        global $conn;
        $query = "CREATE TABLE IF NOT EXISTS certificate_layouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            training_id INT NOT NULL,
            user_id INT NOT NULL,
            layout_image VARCHAR(255) NULL,
            canvas_json MEDIUMTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_layout (training_id, user_id)
        )";
        return $conn->query($query);
    }
    ensureCertificateLayoutsTableExists();

    /**
     * Render a PDF using the final layout image from `certificate_layouts`.
     */
    function generateCertificate($participant, $training_id)
    {
        global $conn;

        // Try to find the final layout image from certificate_layouts.
        $stmt = $conn->prepare("
            SELECT layout_image 
            FROM certificate_layouts 
            WHERE training_id = ? AND user_id = ? 
            LIMIT 1
        ");
        $stmt->bind_param("ii", $training_id, $participant['user_id']);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        // Create new TCPDF instance in landscape A4.
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

        // 1) Disable auto page break and remove margins
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setMargins(0, 0, 0);

        // 2) Remove any headers/footers
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // 3) Add a page
        $pdf->AddPage();

        // 4) If a saved layout image exists, fill the entire A4 area (297 x 210 mm).
        if ($row && !empty($row['layout_image'])) {
            $layoutPath = $row['layout_image'];
            if (file_exists($layoutPath)) {
                // If local file
                $pdf->Image($layoutPath, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
            } else {
                // Possibly an S3-based or absolute URL
                $isLocal = ($_SERVER['SERVER_NAME'] === 'localhost');
                $fullPath = $isLocal
                    ? "http://localhost{$layoutPath}"
                    : "https://www.adohre.site{$layoutPath}";

                $pdf->Image($fullPath, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
            }
        } else {
            // Fallback if no layout image is found
            $pdf->SetFont('helvetica', 'B', 24);
            $pdf->Cell(0, 20, "Certificate of Completion", 0, 1, 'C');
            $pdf->Cell(0, 20, "Name: " . $participant['first_name'] . ' ' . $participant['last_name'], 0, 1, 'C');
        }

        // Save PDF to a temporary file and return its path.
        $tempFile = tempnam(sys_get_temp_dir(), 'cert_') . '.pdf';
        $pdf->Output($tempFile, 'F');

        // Audit log the certificate generation event.
        recordAuditLog($participant['user_id'], "Certificate Generated", "Certificate generated for training ID {$training_id}");

        return $tempFile;
    }

    /**
     * Email the certificate PDF to the participant.
     */
    function emailCertificate($participant, $pdfPath)
    {
        // Ensure the session is started.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Log the certificate email request.
        error_log("emailCertificate request for participant: " . $participant['email'] . " at " . date('Y-m-d H:i:s'));

        // Rate limiting: Allow a maximum of 3 certificate emails per email within a 1-hour window.
        $window = 3600;        // 1 hour in seconds.
        $maxRequests = 3;      // Maximum allowed requests.
        $now = time();

        if (!isset($_SESSION['certificate_email_requests'])) {
            $_SESSION['certificate_email_requests'] = [];
        }

        $email = $participant['email'];
        if (!isset($_SESSION['certificate_email_requests'][$email])) {
            $_SESSION['certificate_email_requests'][$email] = [
                'count' => 0,
                'first_request_time' => $now
            ];
        }

        // Reset count if the time window has expired.
        if ($now - $_SESSION['certificate_email_requests'][$email]['first_request_time'] > $window) {
            $_SESSION['certificate_email_requests'][$email]['count'] = 0;
            $_SESSION['certificate_email_requests'][$email]['first_request_time'] = $now;
        }

        // Check if the rate limit has been reached.
        if ($_SESSION['certificate_email_requests'][$email]['count'] >= $maxRequests) {
            error_log("Rate limit exceeded for certificate emails for: " . $email);
            return false;
        }

        // Increment the count.
        $_SESSION['certificate_email_requests'][$email]['count']++;

        // Validate participant's email.
        if (!isset($participant['email']) || !filter_var($participant['email'], FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid email address provided for participant.");
            return false;
        }

        // Validate participant's first name.
        if (!isset($participant['first_name']) || empty(trim($participant['first_name']))) {
            error_log("Participant first name is required.");
            return false;
        }

        // Validate that the PDF file exists and is of type application/pdf.
        if (!file_exists($pdfPath)) {
            error_log("PDF file does not exist: " . $pdfPath);
            return false;
        }
        if (mime_content_type($pdfPath) !== 'application/pdf') {
            error_log("Invalid file type for certificate, expected application/pdf: " . $pdfPath);
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            // SMTP setup.
            $mail->isSMTP();
            $mail->Host       = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $_ENV['SMTP_USER'];
            $mail->Password   = $_ENV['SMTP_PASS'];
            $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
            $mail->Port       = $_ENV['SMTP_PORT'];

            // Enforce secure SMTP connection.
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ];

            // Set sender and recipient.
            $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
            $mail->addAddress($participant['email']);
            $mail->Subject = "Your Course Completion Certificate";

            // Sanitize participant's first name.
            $firstName = htmlspecialchars($participant['first_name'], ENT_QUOTES, 'UTF-8');

            // Prepare email body.
            $body = "Dear {$firstName},\n\n" .
                "Congratulations on completing your course.\n\n" .
                "Best regards,\nYour Organization";
            $mail->Body = $body;

            // Attach the PDF certificate.
            $mail->addAttachment($pdfPath);

            // Attempt to send the email.
            $mail->send();

            global $conn;
            // Log the sent certificate email into the database.
            $stmtLog = $conn->prepare("INSERT INTO email_notifications (user_id, subject, body) VALUES (?, ?, ?)");
            $subjectLog = $mail->Subject;
            $bodyLog = $mail->Body;
            $stmtLog->bind_param("iss", $participant['user_id'], $subjectLog, $bodyLog);
            $stmtLog->execute();
            $stmtLog->close();

            // Audit log the certificate email event.
            recordAuditLog($participant['user_id'], "Certificate Email Sent", "Certificate email sent to " . $participant['email']);

            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Insert the generated PDF path into `certificates` and update `training_registrations`.
     */
    function finalizeCertificate($participant, $training_id, $pdfPath, $emailSent)
    {
        global $conn;

        // Insert certificate record.
        $insertStmt = $conn->prepare("
            INSERT INTO certificates (user_id, training_id, pdf_path, date_generated)
            VALUES (?, ?, ?, NOW())
        ");
        $insertStmt->bind_param("iis", $participant['user_id'], $training_id, $pdfPath);
        $insertStmt->execute();

        // If email was successful, mark the registration as certificate issued.
        if ($emailSent) {
            $updateStmt = $conn->prepare("
                UPDATE training_registrations
                SET certificate_issued = 1
                WHERE user_id = ? AND training_id = ?
            ");
            $updateStmt->bind_param("ii", $participant['user_id'], $training_id);
            $updateStmt->execute();
            $updateStmt->close();

            // Audit log certificate issuance.
            recordAuditLog($participant['user_id'], "Certificate Issued", "Certificate issued for training ID {$training_id}");
        }
    }

    /**
     * Save final layout for a single participant into `certificate_layouts`.
     * Saves both the final PNG image URL (for PDF generation) and the editable canvas JSON.
     */
    function saveCertificateLayoutSingle($training_id, $user_id, $localImagePath, $canvas_json)
    {
        global $conn, $s3, $bucketName;

        // Process the canvas JSON (if needed, e.g. to mark name placeholders)
        $json = json_decode($canvas_json, true);
        if ($json && isset($json['objects']) && is_array($json['objects'])) {
            foreach ($json['objects'] as &$obj) {
                if (isset($obj['type']) && $obj['type'] === 'i-text') {
                    if (isset($obj['text'])) {
                        if ($obj['text'] === '[Name]' || strpos($obj['text'], '[Name]') !== false) {
                            $obj['placeholderType'] = 'name';
                        }
                    }
                }
            }
            $canvas_json = json_encode($json);
        }

        // Upload image to S3
        $filename = time() . "_layout_single.png";
        $s3Key = 'uploads/certificate_layouts/' . $filename;
        try {
            $result = $s3->putObject([
                'Bucket'      => $bucketName,
                'Key'         => $s3Key,
                'Body'        => fopen($localImagePath, 'rb'),
                'ACL'         => 'public-read',
                'ContentType' => 'image/png'
            ]);
            $layoutImagePath = str_replace(
                "https://{$bucketName}.s3.ap-southeast-1.amazonaws.com/",
                "/s3proxy/",
                $result['ObjectURL']
            );
        } catch (Aws\Exception\AwsException $e) {
            throw new Exception("Failed to upload layout image to S3: " . $e->getMessage());
        }

        // If $user_id is -1 (common layout for all), do not insert into DB here.
        if ($user_id == -1) {
            return $layoutImagePath;
        }

        // Save to the database
        $stmt = $conn->prepare("
            REPLACE INTO certificate_layouts (training_id, user_id, layout_image, canvas_json)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiss", $training_id, $user_id, $layoutImagePath, $canvas_json);
        if (!$stmt->execute()) {
            error_log("Database error when saving layout: " . $conn->error);
            throw new Exception("DB error saving layout for user {$user_id}.");
        }

        error_log("Successfully saved certificate layout for training $training_id, user $user_id with canvas_json length " . strlen($canvas_json));
        return $layoutImagePath;
    }

    /**
     * Save final layout for ALL participants in a training.
     * Uses the same layout image and canvas JSON for every participant.
     */
    function saveCertificateLayoutAll($training_id, $localImagePath, $canvas_json)
    {
        global $conn;
        // First, check if there are participants for this training.
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM training_registrations WHERE training_id = ?");
        $stmt->bind_param("i", $training_id);
        $stmt->execute();
        $countResult = $stmt->get_result()->fetch_assoc();

        if ($countResult['count'] == 0) {
            return "No participants found for this training";
        }

        // Get list of participants.
        $stmt = $conn->prepare("SELECT user_id FROM training_registrations WHERE training_id = ?");
        $stmt->bind_param("i", $training_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $errors = [];
        $uploadedPath = "";

        try {
            // Upload once using user_id = -1 to get a single S3 URL.
            $uploadedPath = saveCertificateLayoutSingle($training_id, -1, $localImagePath, $canvas_json);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        if ($uploadedPath && empty($errors)) {
            while ($row = $res->fetch_assoc()) {
                $user_id = (int)$row['user_id'];
                try {
                    $stmt2 = $conn->prepare("
                        REPLACE INTO certificate_layouts (training_id, user_id, layout_image, canvas_json)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt2->bind_param("iiss", $training_id, $user_id, $uploadedPath, $canvas_json);
                    if (!$stmt2->execute()) {
                        $errors[] = "Failed for user {$user_id}: " . $conn->error;
                    }
                } catch (Exception $e) {
                    $errors[] = "Exception for user {$user_id}: " . $e->getMessage();
                }
            }
        }

        return empty($errors) ? true : implode("; ", $errors);
    }

    /**
     * Load saved layout (editable canvas JSON) for editing.
     * If a user_id is provided, load that user’s layout; otherwise, load a shared one.
     */
    if (isset($_GET['action']) && $_GET['action'] === 'load_certificate_layout') {
        $training_id = (int)($_GET['training_id'] ?? 0);
        $user_id = (int)($_GET['user_id'] ?? 0);

        if ($training_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid training id.']);
            exit;
        }

        error_log("Loading certificate layout for training_id: $training_id, user_id: $user_id");

        // First try user-specific layout
        if ($user_id > 0) {
            $stmt = $conn->prepare("SELECT canvas_json FROM certificate_layouts WHERE training_id = ? AND user_id = ? LIMIT 1");
            $stmt->bind_param("ii", $training_id, $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();

            if ($row && !empty($row['canvas_json'])) {
                error_log("Found specific canvas JSON for user $user_id");
                echo json_encode(['status' => true, 'data' => $row]);
                exit;
            }
        }

        // Fallback: load shared layout (e.g. for user_id = -1)
        $stmt = $conn->prepare("
            SELECT canvas_json FROM certificate_layouts 
            WHERE training_id = ? 
            ORDER BY CASE WHEN user_id = -1 THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $stmt->bind_param("i", $training_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && !empty($row['canvas_json'])) {
            error_log("Found generic canvas JSON for training $training_id");
            echo json_encode(['status' => true, 'data' => $row]);
        } else {
            error_log("No canvas JSON found for training $training_id");
            echo json_encode(['status' => false, 'message' => 'No saved layout found.']);
        }
        exit;
    }

    // -------------- MAIN SWITCH --------------
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {

        case 'save_certificate_layout_single':
            $training_id = (int)($_POST['training_id'] ?? 0);
            $user_id = (int)($_POST['preview_user_id'] ?? 0);
            $final_image = $_POST['final_image'] ?? '';
            $canvas_json = $_POST['canvas_json'] ?? '';

            if ($training_id <= 0 || $user_id <= 0 || !$final_image) {
                ob_end_clean();
                echo json_encode(['status' => false, 'message' => 'Missing training_id, user_id, or final_image.']);
                exit;
            }

            error_log("Saving certificate layout for training_id: $training_id, user_id: $user_id");

            list(, $encoded) = explode(',', $final_image);
            $decoded = base64_decode($encoded);
            $temp = tempnam(sys_get_temp_dir(), 'cert_layout_') . '.png';
            file_put_contents($temp, $decoded);

            try {
                $path = saveCertificateLayoutSingle($training_id, $user_id, $temp, $canvas_json);
                @unlink($temp);
                ob_end_clean();
                echo json_encode(['status' => true, 'layout_path' => $path]);
                // Audit log for saving single layout.
                recordAuditLog($user_id, "Certificate Layout Saved", "Certificate layout saved for training ID {$training_id} (single).");
            } catch (Exception $e) {
                error_log("Error saving certificate layout: " . $e->getMessage());
                ob_end_clean();
                echo json_encode(['status' => false, 'message' => 'An error occurred while saving the certificate layout.']);
            }
            break;

        case 'save_certificate_layout_all':
            $training_id = (int)($_POST['training_id'] ?? 0);
            $final_image = $_POST['final_image'] ?? '';
            $canvas_json = $_POST['canvas_json'] ?? '';
            if ($training_id <= 0 || !$final_image) {
                ob_end_clean();
                echo json_encode(['status' => false, 'message' => 'Missing training_id or final_image.']);
                exit;
            }

            list(, $encoded) = explode(',', $final_image);
            $decoded = base64_decode($encoded);
            $temp = tempnam(sys_get_temp_dir(), 'cert_layout_all_') . '.png';
            file_put_contents($temp, $decoded);

            try {
                $res = saveCertificateLayoutAll($training_id, $temp, $canvas_json);
                @unlink($temp);
                ob_end_clean();
                if ($res === true) {
                    echo json_encode(['status' => true, 'message' => 'Layout saved for all participants.']);
                    recordAuditLog($_SESSION['user_id'], "Certificate Layout Saved", "Certificate layout saved for training ID {$training_id} (all participants).");
                } else {
                    echo json_encode(['status' => false, 'message' => 'An error occurred while saving layout for all participants.']);
                }
            } catch (Exception $e) {
                error_log("Error in save_certificate_layout_all: " . $e->getMessage());
                @unlink($temp);
                ob_end_clean();
                echo json_encode(['status' => false, 'message' => 'An error occurred while processing your request.']);
            }
            break;

        case 'batch_release_certificates':
            $training_id = (int)($_POST['training_id'] ?? 0);
            if ($training_id <= 0) {
                ob_end_clean();
                echo json_encode(['status' => false, 'message' => 'Invalid training id.']);
                exit;
            }
            $stmt = $conn->prepare("
                SELECT u.user_id, u.first_name, u.last_name, u.email,
                       tr.assessment_completed,
                       t.title AS course,
                       tr.registered_at AS date
                FROM training_registrations tr
                JOIN users u ON tr.user_id = u.user_id
                JOIN trainings t ON tr.training_id = t.training_id
                WHERE tr.training_id = ?
                  AND tr.assessment_completed = 1
                  AND (tr.certificate_issued = 0 OR tr.certificate_issued IS NULL)
            ");
            $stmt->bind_param("i", $training_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $results = [];
            while ($p = $res->fetch_assoc()) {
                $pdfPath = generateCertificate($p, $training_id);
                $sent = emailCertificate($p, $pdfPath);
                finalizeCertificate($p, $training_id, $pdfPath, $sent);
                @unlink($pdfPath);
                $results[] = [
                    'user_id' => $p['user_id'],
                    'email'   => $p['email'],
                    'status'  => $sent ? 'sent' : 'failed'
                ];
                // Audit log certificate release for each participant.
                recordAuditLog($p['user_id'], "Certificate Released", "Certificate for training ID {$training_id} released. Email sent: " . ($sent ? "Yes" : "No"));
            }
            ob_end_clean();
            echo json_encode(['status' => true, 'results' => $results]);
            break;

        case 'release_certificate':
            session_start();
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
                ob_end_clean();
                echo json_encode(['status' => false, 'message' => 'Access denied.']);
                exit;
            }
            $user_id = (int)($_REQUEST['user_id'] ?? 0);
            $training_id = (int)($_REQUEST['training_id'] ?? 0);
            if ($user_id <= 0 || $training_id <= 0) {
                ob_end_clean();
                echo json_encode(['status' => false, 'message' => 'Invalid user/training id.']);
                exit;
            }
            $stmt = $conn->prepare("
                SELECT u.user_id, u.first_name, u.last_name, u.email,
                       t.title AS course, tr.registered_at AS date
                FROM users u
                JOIN training_registrations tr ON u.user_id = tr.user_id
                JOIN trainings t ON tr.training_id = t.training_id
                WHERE u.user_id = ? AND tr.training_id = ?
                  AND tr.assessment_completed = 1
                LIMIT 1
            ");
            $stmt->bind_param("ii", $user_id, $training_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) {
                ob_end_clean();
                echo json_encode(['status' => false, 'message' => 'Participant not found or incomplete assessment.']);
                exit;
            }
            $pdfPath = generateCertificate($row, $training_id);
            $sent = emailCertificate($row, $pdfPath);
            if (!$sent) {
                @unlink($pdfPath);
                ob_end_clean();
                echo json_encode(['status' => false, 'message' => 'Failed to email certificate.']);
                exit;
            }
            finalizeCertificate($row, $training_id, $pdfPath, true);
            @unlink($pdfPath);
            ob_end_clean();
            echo json_encode(['status' => true, 'message' => 'Certificate released successfully.']);
            // Audit log for releasing individual certificate.
            recordAuditLog($row['user_id'], "Certificate Released", "Individual certificate released for training ID {$training_id}");
            break;

        default:
            ob_end_clean();
            echo json_encode(['status' => false, 'message' => 'Invalid action.']);
            break;
    }
} catch (Exception $e) {
    error_log("Fatal error in certificate generator: " . $e->getMessage());
    ob_end_clean();
    echo json_encode([
        'status' => false,
        'message' => 'A server error occurred. Please contact support.'
    ]);
}

$conn->close();
?>
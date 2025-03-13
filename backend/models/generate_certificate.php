<?php
// backend/models/generate_certificate.php

// Start output buffering to prevent extra output (e.g. whitespace, warnings)
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response type to JSON
header('Content-Type: application/json');

// Include Composer's autoloader and other required files
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../db/db_connect.php';
require_once __DIR__ . '/../s3config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Create certificate_layouts table if it doesn't exist yet
 * This ensures we have the proper structure for storing layouts
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
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->setMargins(0, 0, 0);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    // If a saved layout image exists, use it as the background.
    if ($row && !empty($row['layout_image'])) {
        $layoutPath = $row['layout_image'];
        if (file_exists($layoutPath)) {
            $pdf->Image($layoutPath, 0, 0, 297, 210);
        } else {
            $isLocal = ($_SERVER['SERVER_NAME'] === 'localhost');
            // Replace with your actual production domain.
            $fullPath = $isLocal
                ? "http://localhost" . $layoutPath
                : "https://www.yourproductiondomain.com" . $layoutPath;
            $pdf->Image($fullPath, 0, 0, 297, 210);
        }
    } else {
        // Fallback if no layout image exists.
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->Cell(0, 20, "Certificate of Completion", 0, 1, 'C');
        $pdf->Cell(0, 20, "Name: " . $participant['first_name'] . ' ' . $participant['last_name'], 0, 1, 'C');
    }

    // Save PDF to a temporary file and return its path.
    $tempFile = tempnam(sys_get_temp_dir(), 'cert_') . '.pdf';
    $pdf->Output($tempFile, 'F');

    return $tempFile;
}

/**
 * Email the certificate PDF to the participant.
 */
function emailCertificate($participant, $pdfPath)
{
    $mail = new PHPMailer(true);
    try {
        // SMTP setup
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port       = $_ENV['SMTP_PORT'];

        $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($participant['email']);
        $mail->Subject = "Your Course Completion Certificate";

        $body = "Dear {$participant['first_name']},\n\n" .
            "Congratulations on completing your course.\n\n" .
            "Best regards,\nYour Organization";
        $mail->Body = $body;

        $mail->addAttachment($pdfPath);
        $mail->send();
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
    }
}

/**
 * Save final layout for a single participant into `certificate_layouts`.
 * Saves both the final PNG image URL (for PDF generation) and the editable canvas JSON.
 */
function saveCertificateLayoutSingle($training_id, $user_id, $localImagePath, $canvas_json)
{
    global $conn, $s3, $bucketName;

    // Process the canvas JSON before saving
    $json = json_decode($canvas_json, true);
    if ($json && isset($json['objects']) && is_array($json['objects'])) {
        // Identify name placeholders for better handling when the layout is loaded later
        foreach ($json['objects'] as &$obj) {
            if (isset($obj['type']) && $obj['type'] === 'i-text') {
                // Check for placeholder text
                if (isset($obj['text'])) {
                    // Look for name pattern indicators
                    if (
                        $obj['text'] === '[Name]' ||
                        strpos($obj['text'], '[Name]') !== false ||
                        (isset($obj['placeholderType']) && $obj['placeholderType'] === 'name')
                    ) {

                        // Ensure it's marked as a name placeholder
                        $obj['placeholderType'] = 'name';
                    }
                }
            }
        }
        // Re-encode the modified JSON
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
            $stmt2 = $conn->prepare("
                REPLACE INTO certificate_layouts (training_id, user_id, layout_image, canvas_json)
                VALUES (?, ?, ?, ?)
            ");
            $stmt2->bind_param("iiss", $training_id, $user_id, $uploadedPath, $canvas_json);
            if (!$stmt2->execute()) {
                $errors[] = "Failed for user {$user_id}";
            }
        }
    }
    return empty($errors) ? true : implode("; ", $errors);
}

/**
 * Load saved layout (editable canvas JSON) for editing.
 * If a user_id is provided, load that user’s layout; otherwise, load a shared one for the training.
 */
if (isset($_GET['action']) && $_GET['action'] === 'load_certificate_layout') {
    $training_id = (int)($_GET['training_id'] ?? 0);
    $user_id = (int)($_GET['user_id'] ?? 0);

    if ($training_id <= 0) {
        echo json_encode(['status' => false, 'message' => 'Invalid training id.']);
        exit;
    }

    error_log("Loading certificate layout for training_id: $training_id, user_id: $user_id");

    // First try user-specific layout, then fall back to shared layout
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT canvas_json FROM certificate_layouts WHERE training_id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param("ii", $training_id, $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && !empty($row['canvas_json'])) {
            error_log("Found specific canvas JSON for user $user_id");

            // Process the JSON to ensure name placeholders are marked correctly
            $json = json_decode($row['canvas_json'], true);
            if ($json && isset($json['objects']) && is_array($json['objects'])) {
                foreach ($json['objects'] as &$obj) {
                    if (isset($obj['type']) && $obj['type'] === 'i-text') {
                        // Check for actual names and mark them as placeholders
                        if (isset($obj['placeholderType']) && $obj['placeholderType'] === 'name') {
                            // Make sure we preserve this flag
                            continue;
                        }

                        // Look for name text patterns
                        if (isset($obj['text']) && (
                            $obj['text'] === '[Name]' ||
                            strpos($obj['text'], '[Name]') !== false ||
                            // Check if it looks like a real name (e.g. "John Smith")
                            preg_match('/^[A-Z][a-z]+ [A-Z][a-z]+(\s+[A-Z][a-z]+)?$/', $obj['text'])
                        )) {
                            // Mark as a name placeholder
                            $obj['placeholderType'] = 'name';
                        }
                    }
                }
                $row['canvas_json'] = json_encode($json);
            }

            echo json_encode(['status' => true, 'data' => $row]);
            exit;
        }
    }

    // Try to find any layout for this training (regular user_id or shared -1)
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

        // Same processing as above
        $json = json_decode($row['canvas_json'], true);
        if ($json && isset($json['objects']) && is_array($json['objects'])) {
            foreach ($json['objects'] as &$obj) {
                if (isset($obj['type']) && $obj['type'] === 'i-text') {
                    // Mark name placeholders
                    if (isset($obj['placeholderType']) && $obj['placeholderType'] === 'name') {
                        continue;
                    }

                    if (isset($obj['text']) && (
                        $obj['text'] === '[Name]' ||
                        strpos($obj['text'], '[Name]') !== false ||
                        preg_match('/^[A-Z][a-z]+ [A-Z][a-z]+(\s+[A-Z][a-z]+)?$/', $obj['text'])
                    )) {
                        $obj['placeholderType'] = 'name';
                    }
                }
            }
            $row['canvas_json'] = json_encode($json);
        }

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
        $canvas_json = $_POST['canvas_json'] ?? ''; // Editable canvas JSON

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
        } catch (Exception $e) {
            error_log("Error saving certificate layout: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['status' => false, 'message' => $e->getMessage()]);
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
            } else {
                echo json_encode(['status' => false, 'message' => $res]);
            }
        } catch (Exception $e) {
            ob_end_clean();
            echo json_encode(['status' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'preview_certificate':
        $final_image = $_POST['final_image'] ?? '';
        $preview_user_id = (int)($_POST['preview_user_id'] ?? 0);
        $layout_json = $_POST['layout_json'] ?? '';

        // Verify the participant exists
        $stmt = $conn->prepare("
            SELECT u.user_id, u.first_name, u.last_name, u.email, t.title AS course, tr.registered_at AS date
            FROM users u
            JOIN training_registrations tr ON u.user_id = tr.user_id
            JOIN trainings t ON tr.training_id = t.training_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $preview_user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            ob_end_clean();
            echo json_encode(['status' => false, 'message' => 'Participant not found.']);
            exit;
        }

        $participant = [
            'user_id'    => $row['user_id'],
            'first_name' => $row['first_name'],
            'last_name'  => $row['last_name'],
            'email'      => $row['email'],
            'course'     => $row['course'] ?? 'Sample Course',
            'date'       => date('Y-m-d', strtotime($row['date'])),
        ];

        // Generate PDF from the final image
        if (!$final_image) {
            ob_end_clean();
            echo json_encode(['status' => false, 'message' => 'No final_image provided.']);
            exit;
        }

        list(, $encoded) = explode(',', $final_image);
        $decoded = base64_decode($encoded);
        $tempLayout = tempnam(sys_get_temp_dir(), 'cert_preview_') . '.png';
        file_put_contents($tempLayout, $decoded);

        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setMargins(0, 0, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        // Insert the background image
        $pdf->Image($tempLayout, 0, 0, 297, 210);

        $tempPdf = tempnam(sys_get_temp_dir(), 'cert_pdf_') . '.pdf';
        $pdf->Output($tempPdf, 'F');
        $pdfData = file_get_contents($tempPdf);
        $pdfBase64 = base64_encode($pdfData);
        @unlink($tempLayout);
        @unlink($tempPdf);

        ob_end_clean();
        echo json_encode(['status' => true, 'pdf_base64' => $pdfBase64]);
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
        break;

    default:
        ob_end_clean();
        echo json_encode(['status' => false, 'message' => 'Invalid action.']);
        break;
}
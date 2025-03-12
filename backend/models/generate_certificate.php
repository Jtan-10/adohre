<?php
// backend/models/generate_certificate.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response type to JSON
header('Content-Type: application/json');

// Include Composer's autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Include database connection and S3 configuration
require_once __DIR__ . '/../db/db_connect.php';
require_once __DIR__ . '/../s3config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Update the saved layout JSON by replacing placeholder text with actual participant data.
 *
 * @param string $layoutJson  The raw JSON layout.
 * @param array  $participant Expects keys: course, first_name, last_name, date.
 * @return string             The updated layout JSON.
 */
function updateLayoutPlaceholders($layoutJson, $participant)
{
    $layout = json_decode($layoutJson, true);
    if (!$layout) {
        return $layoutJson; // If JSON is invalid, return as is.
    }

    if (isset($layout['objects']) && is_array($layout['objects'])) {
        foreach ($layout['objects'] as &$obj) {
            if (isset($obj['text'])) {
                // Replace placeholders in text.
                $obj['text'] = str_replace(
                    ['[Training Title]', '[Name]', '[Date]'],
                    [
                        isset($participant['course']) ? $participant['course'] : 'Training Title',
                        $participant['first_name'] . ' ' . $participant['last_name'],
                        isset($participant['date']) ? $participant['date'] : date("Y-m-d")
                    ],
                    $obj['text']
                );
            }
        }
    }
    return json_encode($layout);
}

/**
 * Render the certificate PDF.
 *
 * @param array $participant  Expects keys: first_name, last_name, email, course, date.
 * @param array $config       Expects keys: background_image, layout_json.
 * @return string             Path to the generated PDF file.
 */
function generateCertificate($participant, $config)
{
    try {
        // Create new TCPDF instance in landscape A4
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

        // Disable page breaks, remove margins, remove headers/footers
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setMargins(0, 0, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Add exactly one page
        $pdf->AddPage();

        // If background_image is set, convert to full path
        if (!empty($config['background_image'])) {
            $relativePath = $config['background_image'];
            $isLocal = ($_SERVER['SERVER_NAME'] === 'localhost');
            if ($isLocal) {
                $fullPath = "http://localhost" . $relativePath;
            } else {
                $fullPath = "https://www.adohre.site" . $relativePath;
            }
            // Place background on entire page (297mm x 210mm)
            $pdf->Image($fullPath, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
        }

        // If we have a saved layout JSON, parse it and place text
        if (!empty($config['layout_json'])) {
            $updatedLayoutJson = updateLayoutPlaceholders($config['layout_json'], $participant);
            $layout = json_decode($updatedLayoutJson, true);

            if (isset($layout['objects']) && is_array($layout['objects'])) {
                // 1) Separate scale factors for width and height
$scaleX = 297.0 / 1123.0;  // Canvas width (1123px) => PDF width (297mm)
$scaleY = 210.0 / 792.0;   // Canvas height (792px) => PDF height (210mm)

// 2) Use whichever is appropriate for left/top vs. font size
//    Usually, for font size, you take the smaller scale to avoid distortion:
$baseScale = min($scaleX, $scaleY);

foreach ($layout['objects'] as $obj) {
    if (isset($obj['type']) && $obj['type'] === 'i-text') {
        // Convert positions
        $left = isset($obj['left']) ? $obj['left'] * $scaleX : 0;
        $top  = isset($obj['top'])  ? $obj['top']  * $scaleY : 0;

        // Scale the font size with $baseScale. 
        // Optionally multiply by 1.2 or 1.3 if you want the text bigger.
        $fontSize = $obj['fontSize'] * min($scaleX, $scaleY) * 1.2;


        // Map font families
        $fontFamily = isset($obj['fontFamily']) ? strtolower($obj['fontFamily']) : 'helvetica';
        switch ($fontFamily) {
            case 'arial':
                $fontFamily = 'helvetica';
                break;
            case 'times new roman':
                $fontFamily = 'times';
                break;
            case 'courier new':
                $fontFamily = 'courier';
                break;
            default:
                if (!in_array($fontFamily, ['helvetica', 'times', 'courier', 'symbol', 'zapfdingbats'])) {
                    $fontFamily = 'helvetica';
                }
                break;
        }

        $pdf->SetFont($fontFamily, '', $fontSize);
        $pdf->SetXY($left, $top);

        $text = isset($obj['text']) ? $obj['text'] : '';
        $pdf->Cell(0, 0, $text, 0, 1, 'L', 0, '', 0, false, 'T', 'M');
    }
    elseif (isset($obj['type']) && $obj['type'] === 'image' && !empty($obj['src'])) {
        // Similar approach for images:
        $left = isset($obj['left']) ? $obj['left'] * $scaleX : 0;
        $top  = isset($obj['top']) ? $obj['top'] * $scaleY : 0;
        $imgWidth = isset($obj['width']) ? $obj['width'] * $scaleX : 50;
        $imgHeight = isset($obj['height']) ? $obj['height'] * $scaleY : 50;

        // decode base64, etc...
        // $pdf->Image('@'.$decodedData, $left, $top, $imgWidth, $imgHeight, ...);
    }
}

            }
        } else {
            // Fallback: static text
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 24);
            $pdf->Cell(0, 20, "Certificate of Completion", 0, 1, 'C');

            $fullName = $participant['first_name'] . ' ' . $participant['last_name'];
            $pdf->Cell(0, 20, "This certifies that " . $fullName, 0, 1, 'C');

            $courseTitle = $participant['course'] ?? 'Course Title';
            $pdf->Cell(0, 20, "has completed the course: " . $courseTitle, 0, 1, 'C');

            $completionDate = $participant['date'] ?? date("Y-m-d");
            $pdf->Cell(0, 20, "Date: " . $completionDate, 0, 1, 'C');
        }

        // Save PDF to temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'cert_') . '.pdf';
        $pdf->Output($tempFile, 'F');

        return $tempFile;
    } catch (Exception $e) {
        error_log("TCPDF Error: " . $e->getMessage());
        throw new Exception("Failed to generate certificate: " . $e->getMessage());
    }
}

/**
 * Email the generated certificate to the participant using PHPMailer.
 *
 * @param array  $participant     Expects keys: email, first_name.
 * @param string $certificatePath Path to the certificate PDF.
 * @return bool                   True on success, false on failure.
 */
function emailCertificate($participant, $certificatePath)
{
    $mail = new PHPMailer(true);
    try {
        // SMTP settings
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

        $body = "Dear " . $participant['first_name'] . ",\n\n" .
            "Congratulations on completing your course. Please find attached your certificate.\n\n" .
            "Best regards,\nInnovative Senior Citizen Engagement";
        $mail->Body = $body;

        // Attach the certificate
        $mail->addAttachment($certificatePath);

        $mail->send();
        error_log("Email sent successfully to: " . $participant['email']);
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Process batch certificate generation and release.
 *
 * Only processes participants whose assessments are complete and certificates not yet released.
 *
 * @param int $training_id
 * @return array
 */
function processBatchCertificates($training_id)
{
    global $conn;
    $results = [];

    // 1) Fetch certificate configuration
    $stmt = $conn->prepare("SELECT background_image, layout_json FROM certificate_configurations WHERE training_id = ?");
    $stmt->bind_param("i", $training_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $config = $result->fetch_assoc();
    if (!$config) {
        $config = ['background_image' => '', 'layout_json' => ''];
    }

    // 2) Find participants who need certificates
    $stmt = $conn->prepare("
        SELECT u.user_id, u.first_name, u.last_name, u.email, 
               t.title AS course, 
               tr.assessment_completed AS assessment_status, 
               tr.certificate_issued AS certificate_status, 
               tr.registered_at AS date
        FROM users u
        JOIN training_registrations tr ON u.user_id = tr.user_id
        JOIN trainings t ON tr.training_id = t.training_id
        WHERE tr.training_id = ? 
          AND tr.assessment_completed = 1 
          AND (tr.certificate_issued = 0 OR tr.certificate_issued IS NULL)
    ");
    $stmt->bind_param("i", $training_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $participants = [];
    while ($row = $result->fetch_assoc()) {
        $participants[] = $row;
    }

    // 3) Prepare DB statements for insertion and update
    $insertStmt = $conn->prepare("INSERT INTO certificates (user_id, training_id, pdf_path, date_generated) VALUES (?, ?, ?, NOW())");
    $updateStmt = $conn->prepare("UPDATE training_registrations SET certificate_issued = 1 WHERE user_id = ? AND training_id = ?");

    // 4) Generate, email, and update for each participant
    foreach ($participants as $participant) {
        $pdfPath = generateCertificate($participant, $config);
        $emailSent = emailCertificate($participant, $pdfPath);

        // Insert record
        $insertStmt->bind_param("iis", $participant['user_id'], $training_id, $pdfPath);
        $insertStmt->execute();

        // Update if email was successful
        if ($emailSent) {
            $updateStmt->bind_param("ii", $participant['user_id'], $training_id);
            $updateStmt->execute();
        }

        // Summarize result
        $results[] = [
            'user_id' => $participant['user_id'],
            'email'   => $participant['email'],
            'status'  => $emailSent ? 'sent' : 'failed'
        ];

        // Remove temp file
        unlink($pdfPath);
    }
    return $results;
}

// Main logic: parse $action from GET/POST/JSON
$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"), true);
$action = '';

if (is_array($input) && isset($input['action'])) {
    $action = $input['action'];
} else {
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
}

switch ($action) {
    case 'save_certificate_configuration':
        // (Legacy) Save only background image
        $training_id = isset($_POST['training_id']) ? intval($_POST['training_id']) : 0;
        if ($training_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid training id.']);
            exit();
        }
        if (isset($_FILES['certificate_background']) && $_FILES['certificate_background']['error'] == UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['certificate_background']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }
            $filename = basename($_FILES['certificate_background']['name']);
            $uniqueFileName = time() . "_" . $filename;
            $s3Key = 'uploads/certificates/' . $uniqueFileName;

            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => fopen($_FILES['certificate_background']['tmp_name'], 'rb'),
                    'ACL'         => 'public-read',
                    'ContentType' => $_FILES['certificate_background']['type']
                ]);
                $relativeImagePath = str_replace("https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/", "/s3proxy/", $result['ObjectURL']);

                $stmt = $conn->prepare("REPLACE INTO certificate_configurations (training_id, background_image) VALUES (?, ?)");
                $stmt->bind_param("is", $training_id, $relativeImagePath);
                if ($stmt->execute()) {
                    echo json_encode(['status' => true, 'message' => 'Configuration saved.']);
                } else {
                    echo json_encode(['status' => false, 'message' => 'Database error saving configuration.']);
                }
            } catch (Aws\Exception\AwsException $e) {
                echo json_encode(['status' => false, 'message' => 'Failed to upload background image to S3: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => false, 'message' => 'No background image uploaded.']);
        }
        break;

    case 'save_certificate_layout':
        // Save layout JSON + optional background
        $training_id = isset($_POST['training_id']) ? intval($_POST['training_id']) : 0;
        if ($training_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid training id.']);
            exit();
        }
        $layout_json = isset($_POST['layout_json']) ? $_POST['layout_json'] : '';
        $background_image_path = '';

        // 1) Check final_image data URL
        if (isset($_POST['final_image']) && !empty($_POST['final_image'])) {
            $dataUrl = $_POST['final_image'];
            list($type, $data) = explode(';', $dataUrl);
            list(, $data) = explode(',', $data);
            $data = base64_decode($data);
            $imageName = time() . "_final.png";
            $s3Key = 'uploads/certificates/' . $imageName;

            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => $data,
                    'ACL'         => 'public-read',
                    'ContentType' => 'image/png'
                ]);
                $background_image_path = str_replace("https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/", "/s3proxy/", $result['ObjectURL']);
            } catch (Aws\Exception\AwsException $e) {
                echo json_encode(['status' => false, 'message' => 'Failed to upload final image to S3: ' . $e->getMessage()]);
                exit();
            }
        }

        // 2) Check if file was uploaded
        if (isset($_FILES['background_image']) && $_FILES['background_image']['error'] == UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['background_image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type.']);
                exit();
            }
            $filename = basename($_FILES['background_image']['name']);
            $uniqueName = time() . "_" . $filename;
            $s3Key = 'uploads/certificates/' . $uniqueName;
            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => fopen($_FILES['background_image']['tmp_name'], 'rb'),
                    'ACL'         => 'public-read',
                    'ContentType' => $_FILES['background_image']['type']
                ]);
                $background_image_path = str_replace("https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/", "/s3proxy/", $result['ObjectURL']);
            } catch (Aws\Exception\AwsException $e) {
                echo json_encode(['status' => false, 'message' => 'Failed to upload background image to S3: ' . $e->getMessage()]);
                exit();
            }
        }

        // 3) Fallback: use selected_design if provided
        if (empty($background_image_path) && isset($_POST['selected_design']) && !empty($_POST['selected_design'])) {
            $background_image_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($_POST['selected_design'], '/');
        }

        // 4) Save or update
        $stmt = $conn->prepare("REPLACE INTO certificate_configurations (training_id, background_image, layout_json) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $training_id, $background_image_path, $layout_json);
        if ($stmt->execute()) {
            echo json_encode(['status' => true, 'message' => 'Certificate layout saved successfully.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Database error saving certificate layout.']);
        }
        break;

    case 'batch_release_certificates':
        // Batch release
        $input = json_decode(file_get_contents("php://input"), true);
        $training_id = isset($input['training_id']) ? intval($input['training_id']) : 0;
        if ($training_id > 0) {
            $results = processBatchCertificates($training_id);
            echo json_encode(['status' => true, 'results' => $results]);
        } else {
            echo json_encode(['status' => false, 'message' => 'Invalid training id.']);
        }
        break;

    case 'release_certificate':
        // Single participant release
        session_start();
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit();
        }

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $participantUserId = isset($input['user_id']) ? intval($input['user_id']) : 0;
            $trainingId = isset($input['training_id']) ? intval($input['training_id']) : 0;
        } else {
            $participantUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
            $trainingId = isset($_GET['training_id']) ? intval($_GET['training_id']) : 0;
        }

        if ($participantUserId <= 0 || $trainingId <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid parameters.']);
            exit();
        }

        // Check participant
        $stmt = $conn->prepare("
            SELECT u.user_id, u.first_name, u.last_name, u.email, 
                   t.title AS course, tr.registered_at AS date
            FROM users u
            JOIN training_registrations tr ON u.user_id = tr.user_id
            JOIN trainings t ON tr.training_id = t.training_id
            WHERE u.user_id = ? AND tr.training_id = ? AND tr.assessment_completed = 1
        ");
        $stmt->bind_param("ii", $participantUserId, $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$participant = $result->fetch_assoc()) {
            echo json_encode(['status' => false, 'message' => 'Participant not found or assessment not completed.']);
            exit();
        }

        // Fetch config
        $configStmt = $conn->prepare("SELECT background_image, layout_json FROM certificate_configurations WHERE training_id = ?");
        $configStmt->bind_param("i", $trainingId);
        $configStmt->execute();
        $configResult = $configStmt->get_result();
        $config = $configResult->fetch_assoc();
        if (!$config) {
            $config = ['background_image' => '', 'layout_json' => ''];
        }

        // Generate certificate
        try {
            $pdfPath = generateCertificate($participant, $config);
        } catch (Exception $e) {
            echo json_encode(['status' => false, 'message' => 'Error generating certificate: ' . $e->getMessage()]);
            exit();
        }

        // Email
        $emailSent = emailCertificate($participant, $pdfPath);
        if (!$emailSent) {
            echo json_encode(['status' => false, 'message' => 'Failed to email certificate.']);
            unlink($pdfPath);
            exit();
        }

        // Insert record
        $insertStmt = $conn->prepare("INSERT INTO certificates (user_id, training_id, pdf_path, date_generated) VALUES (?, ?, ?, NOW())");
        $insertStmt->bind_param("iis", $participantUserId, $trainingId, $pdfPath);
        $insertStmt->execute();

        // Update registration
        $updateStmt = $conn->prepare("UPDATE training_registrations SET certificate_issued = 1 WHERE user_id = ? AND training_id = ?");
        $updateStmt->bind_param("ii", $participantUserId, $trainingId);
        $updateStmt->execute();

        // Cleanup
        unlink($pdfPath);

        echo json_encode(['status' => true, 'message' => 'Certificate released successfully.']);
        break;

    case 'load_certificate_layout':
        // Return saved layout for a training
        $training_id = isset($_GET['training_id']) ? intval($_GET['training_id']) : 0;
        if ($training_id > 0) {
            $stmt = $conn->prepare("SELECT background_image, layout_json FROM certificate_configurations WHERE training_id = ?");
            $stmt->bind_param("i", $training_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Convert absolute path if needed
                $row['background_image'] = str_replace($_SERVER['DOCUMENT_ROOT'] . '/capstone-php/', '', $row['background_image']);
                echo json_encode(['status' => true, 'data' => $row]);
            } else {
                echo json_encode(['status' => false, 'message' => 'No saved layout found.']);
            }
        } else {
            echo json_encode(['status' => false, 'message' => 'Invalid training id.']);
        }
        break;

    default:
        echo json_encode(['status' => false, 'message' => 'Invalid action.']);
        break;
}
<?php
// backend/models/generate_certificate.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response type to JSON
header('Content-Type: application/json');

// Include Composer's autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Include database connection
require_once __DIR__ . '/../db/db_connect.php';

// Include the S3 configuration file.
require_once __DIR__ . '/../s3config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*** Update the saved layout JSON by replacing placeholder text with actual participant data.
*
* @param string $layoutJson  The raw JSON layout.
* @param array  $participant Expects keys: course, first_name, last_name, date.
* @return string             The updated layout JSON.
*/
function updateLayoutPlaceholders($layoutJson, $participant) {
   $layout = json_decode($layoutJson, true);
   if (!$layout) {
       return $layoutJson;
   }
   if (isset($layout['objects']) && is_array($layout['objects'])) {
       foreach ($layout['objects'] as &$obj) {
           if (isset($obj['text'])) {
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
* If a saved layout JSON exists in the configuration, this function will use it to render the certificate:
* - It updates the placeholders in the layout JSON with actual values.
* - Then, for each object (assumed to be text objects), it positions the text on the PDF.
*
* If no layout JSON is present, it falls back to the default static template.
*
* @param array $participant  Expects keys: first_name, last_name, email, course, date.
* @param array $config       Expects keys: background_image, layout_json.
* @return string             Path to the generated PDF file.
*/
function generateCertificate($participant, $config) {
   // Create new TCPDF instance in landscape A4.
   $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
   // Disable header and footer.
   $pdf->setPrintHeader(false);
   $pdf->setPrintFooter(false);
   $pdf->AddPage();

   // Set the background image if provided and exists.
   if (!empty($config['background_image']) && file_exists($config['background_image'])) {
       // A4 landscape dimensions: 297 x 210 mm.
       $pdf->Image($config['background_image'], 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
   }

   // If a saved layout JSON exists, use it to render text objects.
   if (!empty($config['layout_json'])) {
       // Update placeholders in the layout JSON.
       $updatedLayoutJson = updateLayoutPlaceholders($config['layout_json'], $participant);
       $layout = json_decode($updatedLayoutJson, true);
       if (isset($layout['objects']) && is_array($layout['objects'])) {
           // Define a conversion factor from Fabric.js canvas pixels to mm.
           // For example, if your canvas width is 1123px corresponding to 297mm:
           $scale = 297 / 1123; // Adjust as needed.
           foreach ($layout['objects'] as $obj) {
               if (isset($obj['text'])) {
                   // Extract position and styling info.
                   $left = isset($obj['left']) ? $obj['left'] * $scale : 0;
                   $top = isset($obj['top']) ? $obj['top'] * $scale : 0;
                   $fontSize = isset($obj['fontSize']) ? $obj['fontSize'] * $scale : 12;
                   $fontFamily = isset($obj['fontFamily']) ? strtolower($obj['fontFamily']) : 'helvetica';
                   
                   // Set the font (TCPDF supports a few standard fonts).
                   $pdf->SetFont($fontFamily, '', $fontSize);
                   // Position the cursor.
                   $pdf->SetXY($left, $top);
                   // Write the text. Using Cell with zero height so the text renders at the specified position.
                   $pdf->Cell(0, 0, $obj['text'], 0, 1, 'L', 0, '', 0, false, 'T', 'M');
               }
           }
       }
   } else {
       // Fallback: use a static template if no layout JSON is provided.
       // Set text color and add certificate content.
       $pdf->SetTextColor(0, 0, 0);
       $pdf->SetFont('helvetica', 'B', 24);
       $pdf->Cell(0, 20, "Certificate of Completion", 0, 1, 'C', 0, '', 0, false, 'T', 'M');
   
       $pdf->SetFont('helvetica', '', 20);
       $fullName = $participant['first_name'] . ' ' . $participant['last_name'];
       $pdf->Cell(0, 20, "This certifies that " . $fullName, 0, 1, 'C', 0, '', 0, false, 'T', 'M');
   
       $courseTitle = isset($participant['course']) ? $participant['course'] : 'Course Title';
       $pdf->Cell(0, 20, "has completed the course: " . $courseTitle, 0, 1, 'C', 0, '', 0, false, 'T', 'M');
   
       $completionDate = isset($participant['date']) ? $participant['date'] : date("Y-m-d");
       $pdf->Cell(0, 20, "Date: " . $completionDate, 0, 1, 'C', 0, '', 0, false, 'T', 'M');
   }

   // Save the PDF to a temporary file.
   $tempFile = tempnam(sys_get_temp_dir(), 'cert_') . '.pdf';
   $pdf->Output($tempFile, 'F');

   return $tempFile;
}

/**
* Email the generated certificate to the participant using PHPMailer.
*
* @param array  $participant      Expects keys: email, first_name.
* @param string $certificatePath  Path to the certificate PDF.
* @return bool                    True on success, false on failure.
*/
function emailCertificate($participant, $certificatePath) {
   $mail = new PHPMailer(true);

   try {
      // Load SMTP settings from .env
      $mail->isSMTP();
      $mail->Host       = $_ENV['SMTP_HOST'];
      $mail->SMTPAuth   = true;
      $mail->Username   = $_ENV['SMTP_USER'];
      $mail->Password   = $_ENV['SMTP_PASS'];
      $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
      $mail->Port       = $_ENV['SMTP_PORT'];

       // Email settings
       $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
       $mail->addAddress($participant['email']);
       $mail->Subject = "Your Course Completion Certificate";
       
       // Compose the email body.
       $body = "Dear " . $participant['first_name'] . ",\n\n" .
               "Congratulations on completing your course. Please find attached your certificate.\n\n" .
               "Best regards,\nInnovative Senior Citizen Engagement";
       $mail->Body = $body;

       // Attach the generated certificate PDF.
       $mail->addAttachment($certificatePath);
       
       $mail->send();
       return true;
   } catch (Exception $e) {
       error_log("PHPMailer Error: " . $mail->ErrorInfo);
       return false;
   }
}

/**
* Process batch certificate generation and release.
* Only processes participants whose assessments are complete and certificates not yet released.
*
* Additionally, after generating each certificate, the generated PDF file path is saved to the
* 'certificates' table in the database.
*
* @param int $training_id  The training id for which to process certificates.
* @return array            Array of results per participant.
*/
function processBatchCertificates($training_id) {
   global $conn;

   // Fetch certificate configuration for this training.
   $stmt = $conn->prepare("SELECT background_image, layout_json FROM certificate_configurations WHERE training_id = ?");
   $stmt->bind_param("i", $training_id);
   $stmt->execute();
   $result = $stmt->get_result();
   $config = $result->fetch_assoc();
   if (!$config) {
       $config = ['background_image' => '', 'layout_json' => ''];
   }

   // Fetch participants who have completed the assessment and haven't been issued a certificate.
   $stmt = $conn->prepare("SELECT p.user_id, p.first_name, p.last_name, p.email, t.title AS course, a.assessment_status, a.certificate_status, a.date
                             FROM participants p
                             JOIN assessments a ON p.user_id = a.user_id
                             JOIN trainings t ON a.training_id = t.training_id
                             WHERE a.training_id = ? 
                               AND a.assessment_status = 'completed'
                               AND (a.certificate_status IS NULL OR a.certificate_status = '')");
   $stmt->bind_param("i", $training_id);
   $stmt->execute();
   $result = $stmt->get_result();
   $participants = [];
   while ($row = $result->fetch_assoc()) {
       $participants[] = $row;
   }

   $results = [];
   foreach ($participants as $participant) {
       // Generate certificate PDF for this participant.
       $pdfPath = generateCertificate($participant, $config);
       // Email the certificate.
       $emailSent = emailCertificate($participant, $pdfPath);

       // Save the certificate info to the database.
       $insertStmt = $conn->prepare("INSERT INTO certificates (user_id, training_id, pdf_path, date_generated) VALUES (?, ?, ?, NOW())");
       $insertStmt->bind_param("iis", $participant['user_id'], $training_id, $pdfPath);
       $insertStmt->execute();

       // Update certificate status in assessments if email sent successfully.
       if ($emailSent) {
           $updateStmt = $conn->prepare("UPDATE assessments SET certificate_status = 'released' WHERE user_id = ? AND training_id = ?");
           $updateStmt->bind_param("ii", $participant['user_id'], $training_id);
           $updateStmt->execute();
       }

       $results[] = [
           'user_id' => $participant['user_id'],
           'email'   => $participant['email'],
           'status'  => $emailSent ? 'sent' : 'failed'
       ];
       // Optionally, delete the temporary file after processing.
       unlink($pdfPath);
   }
   return $results;
}

// Main processing based on incoming request.
$method = $_SERVER['REQUEST_METHOD'];
// Attempt to decode the JSON payload
$input = json_decode(file_get_contents("php://input"), true);

// Use the JSON payload if available, otherwise fallback to $_REQUEST
if (is_array($input) && isset($input['action'])) {
   $action = $input['action'];
} else {
   $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
}

if ($method == 'POST') {

    if ($action == 'save_certificate_configuration') {
        // Legacy branch: saves only a background image.
        $training_id = isset($_POST['training_id']) ? intval($_POST['training_id']) : 0;

        if (isset($_FILES['certificate_background']) && $_FILES['certificate_background']['error'] == UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['certificate_background']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }
            
            // Generate a unique file name and set the S3 object key.
            $filename = basename($_FILES['certificate_background']['name']);
            $uniqueFileName = time() . "_" . $filename;
            $s3Key = 'uploads/certificates/' . $uniqueFileName;
    
            try {
                // Upload the file to S3.
                $result = $s3->putObject([
                    'Bucket'      => $bucketName, // defined in s3config.php
                    'Key'         => $s3Key,
                    'Body'        => fopen($_FILES['certificate_background']['tmp_name'], 'rb'),
                    'ACL'         => 'public-read', // adjust if needed
                    'ContentType' => $_FILES['certificate_background']['type']
                ]);
    
                // Here, you can store either the S3 object URL or just the key.
                // We'll store the S3 key as the relative path.
                $relativeImagePath = $s3Key;
    
                // Save the configuration in the database.
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
    } else if ($action == 'save_certificate_layout') {
        // New branch to save the full certificate layout (JSON) along with an optional background image.
        $training_id = isset($_POST['training_id']) ? intval($_POST['training_id']) : 0;
        $layout_json = isset($_POST['layout_json']) ? $_POST['layout_json'] : '';

        // Initialize background image path (optional).
        $background_image_path = '';
        
        // Option 2: Use final_image if provided (data URL).
        if (isset($_POST['final_image']) && !empty($_POST['final_image'])) {
            $dataUrl = $_POST['final_image'];
            // Split off the base64 part.
            list($type, $data) = explode(';', $dataUrl);
            list(, $data) = explode(',', $data);
            $data = base64_decode($data);
    
            // Upload the final image to S3.
            $imageName = time() . "_final.png";
            $s3Key = 'uploads/certificates/' . $imageName;
            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => $data, // raw image data
                    'ACL'         => 'public-read',
                    'ContentType' => 'image/png'
                ]);
                // Store the S3 key (or use $result['ObjectURL'] if you want the full URL)
                $background_image_path = $s3Key;
            } catch (Aws\Exception\AwsException $e) {
                echo json_encode(['status' => false, 'message' => 'Failed to upload final image to S3: ' . $e->getMessage()]);
                exit();
            }
        }
        
        // Fallback: If no final image was provided, use selected_design if available.
        if (empty($background_image_path) && isset($_POST['selected_design']) && !empty($_POST['selected_design'])) {
            $background_image_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($_POST['selected_design'], '/');
        }
        
        // Also, check if a file was uploaded via the "background_image" file input (which overrides the design).
        if (isset($_FILES['background_image']) && $_FILES['background_image']['error'] == UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['background_image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
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
                $background_image_path = $s3Key;
            } catch (Aws\Exception\AwsException $e) {
                echo json_encode(['status' => false, 'message' => 'Failed to upload background image to S3: ' . $e->getMessage()]);
                exit();
            }
        }
        // Save or update the configuration with both background image and layout JSON.
        $stmt = $conn->prepare("REPLACE INTO certificate_configurations (training_id, background_image, layout_json) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $training_id, $background_image_path, $layout_json);
        if ($stmt->execute()) {
            echo json_encode(['status' => true, 'message' => 'Certificate layout saved successfully.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Database error saving certificate layout.']);
        }
    } else if ($action == 'batch_release_certificates') {
        // Accept JSON input for batch certificate release.
        $input = json_decode(file_get_contents("php://input"), true);
        $training_id = isset($input['training_id']) ? intval($input['training_id']) : 0;
        if ($training_id > 0) {
            $results = processBatchCertificates($training_id);
            echo json_encode(['status' => true, 'results' => $results]);
        } else {
            echo json_encode(['status' => false, 'message' => 'Invalid training id.']);
        }
    } else if ($action == 'release_certificate') {
        session_start();
        // Ensure the user is a trainer
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $participantUserId = intval($input['user_id']);
        $trainingId = intval($input['training_id']);
    
        // Check that the assessment is completed and no certificate has been issued yet.
        // You might need to adjust the query if you also want to generate a certificate PDF, send email, etc.
        $updateQuery = "UPDATE training_registrations 
                        SET certificate_issued = 1 
                        WHERE user_id = ? AND training_id = ? AND assessment_completed = 1";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('ii', $participantUserId, $trainingId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Optionally, add certificate generation and emailing code here.
            echo json_encode(['status' => true, 'message' => 'Certificate released successfully.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Certificate could not be released (perhaps the assessment is not completed).']);
        }
        exit;
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid action.']);
    }
} else if ($method == 'GET' && $action == 'load_certificate_layout') {
    $training_id = isset($_GET['training_id']) ? intval($_GET['training_id']) : 0;
    if ($training_id > 0) {
        $stmt = $conn->prepare("SELECT background_image, layout_json FROM certificate_configurations WHERE training_id = ?");
        $stmt->bind_param("i", $training_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            // Convert absolute path to a relative URL.
            if (!empty($row['background_image'])) {
                // Assuming your project folder is "capstone-php":
                $row['background_image'] = str_replace($_SERVER['DOCUMENT_ROOT'] . '/capstone-php/', '', $row['background_image']);
            }
            echo json_encode(['status' => true, 'data' => $row]);
        } else {
            echo json_encode(['status' => false, 'message' => 'No saved layout found.']);
        }
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid training id.']);
    }
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
}
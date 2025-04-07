<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Add secure session settings before starting the session
session_set_cookie_params([
    'lifetime' => 0,
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'You must be logged in to access this feature.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // For actions that require training_id validation
    if (isset($_GET['action']) && in_array($_GET['action'], ['join_training', 'initiate_payment', 'check_payment_status'])) {
        if (!isset($input['training_id']) || filter_var($input['training_id'], FILTER_VALIDATE_INT) === false) {
            echo json_encode(['status' => false, 'message' => 'Invalid training ID.']);
            exit;
        }
    }

    $action = isset($_GET['action']) ? $_GET['action'] : null;
    $userId = $_SESSION['user_id'];

    if ($action === 'join_training') {
        $trainingId = (int)$input['training_id'];

        // Check if the user is already registered for the training
        $checkQuery = "SELECT * FROM training_registrations WHERE user_id = ? AND training_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ii', $userId, $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['status' => false, 'message' => 'You have already joined this training.']);
        } else {
            // Register the user for the training
            $insertQuery = "INSERT INTO training_registrations (user_id, training_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param('ii', $userId, $trainingId);
            $stmt->execute();

            // Record audit log for joining the training
            recordAuditLog($userId, "Join Training", "User joined training ID: $trainingId");

            // Retrieve training details
            $trainingQuery = "SELECT title, schedule, image, description FROM trainings WHERE training_id = ?";
            $stmtTraining = $conn->prepare($trainingQuery);
            $stmtTraining->bind_param("i", $trainingId);
            $stmtTraining->execute();
            $resultTraining = $stmtTraining->get_result();
            $training = $resultTraining->fetch_assoc();
            $stmtTraining->close();

            // Retrieve user details
            $userQuery = "SELECT email, first_name FROM users WHERE user_id = ?";
            $stmtUser = $conn->prepare($userQuery);
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();
            $user = $resultUser->fetch_assoc();
            $stmtUser->close();

            // Rate limiting for email sending
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $window = 3600;  // 1 hour
            $maxEmails = 10; // Maximum emails per window
            $now = time();
            $userEmail = $user['email'];

            if (!isset($_SESSION['email_send_requests'])) {
                $_SESSION['email_send_requests'] = [];
            }
            if (!isset($_SESSION['email_send_requests'][$userEmail])) {
                $_SESSION['email_send_requests'][$userEmail] = [
                    'count' => 0,
                    'first_request_time' => $now
                ];
            }
            // Reset counter if the window has expired.
            if ($now - $_SESSION['email_send_requests'][$userEmail]['first_request_time'] > $window) {
                $_SESSION['email_send_requests'][$userEmail]['count'] = 0;
                $_SESSION['email_send_requests'][$userEmail]['first_request_time'] = $now;
            }

            if ($_SESSION['email_send_requests'][$userEmail]['count'] < $maxEmails) {
                $_SESSION['email_send_requests'][$userEmail]['count']++;

                // Send email notification using PHPMailer
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['SMTP_HOST'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['SMTP_USER'];
                    $mail->Password   = $_ENV['SMTP_PASS'];
                    $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
                    $mail->Port       = $_ENV['SMTP_PORT'];

                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer'      => true,
                            'verify_peer_name' => true,
                            'allow_self_signed' => false,
                        ],
                    ];

                    $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
                    $mail->addAddress($userEmail);

                    $mail->isHTML(true);
                    $mail->Subject = "Training Registration Confirmation";
                    $mail->Body    = "
                        <h1>Hello " . htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') . ",</h1>
                        <p>Thank you for joining our training!</p>
                        <p>You have successfully registered for the training: <strong>" . htmlspecialchars($training['title'], ENT_QUOTES, 'UTF-8') . "</strong>.</p>
                        <p><strong>Schedule:</strong> " . htmlspecialchars($training['schedule'], ENT_QUOTES, 'UTF-8') . "</p>
                        <p>" . htmlspecialchars($training['description'], ENT_QUOTES, 'UTF-8') . "</p>
                        <p>For more details, please log in to your account.</p>";
                    $mail->AltBody = strip_tags($mail->Body);

                    $mail->send();

                    // Log the sent email into the database
                    $stmtLog = $conn->prepare("INSERT INTO email_notifications (user_id, subject, body) VALUES (?, ?, ?)");
                    $subjectLog = "Training Registration Confirmation";
                    $bodyLog = $mail->Body;
                    $stmtLog->bind_param("iss", $userId, $subjectLog, $bodyLog);
                    $stmtLog->execute();
                    $stmtLog->close();
                } catch (Exception $e) {
                    // No error logging if email fails
                }
            }

            echo json_encode(['status' => true, 'message' => 'Successfully joined the training.']);
        }
    } elseif ($action === 'initiate_payment') {
        // Initiate a payment record for trainings that require a fee
        if (!isset($input['training_id']) || filter_var($input['training_id'], FILTER_VALIDATE_INT) === false) {
            echo json_encode(['status' => false, 'message' => 'Invalid training ID.']);
            exit;
        }
        $trainingId = (int)$input['training_id'];

        // Retrieve training fee, title and schedule
        $queryFee = "SELECT fee, title, schedule FROM trainings WHERE training_id = ?";
        $stmtFee = $conn->prepare($queryFee);
        $stmtFee->bind_param("i", $trainingId);
        $stmtFee->execute();
        $resultFee = $stmtFee->get_result();
        if ($resultFee->num_rows == 0) {
            echo json_encode(['status' => false, 'message' => 'Training not found.']);
            exit;
        }
        $trainingData = $resultFee->fetch_assoc();
        $fee = (float)$trainingData['fee'];
        $schedule = $trainingData['schedule'];
        $stmtFee->close();

        if ($fee <= 0) {
            echo json_encode(['status' => false, 'message' => 'No payment is required for this training.']);
            exit;
        }

        // Check if the user already has a payment record for this training
        $checkPaymentQuery = "SELECT * FROM payments WHERE user_id = ? AND training_id = ?";
        $stmtCheck = $conn->prepare($checkPaymentQuery);
        $stmtCheck->bind_param("ii", $userId, $trainingId);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows > 0) {
            echo json_encode(['status' => true, 'message' => 'Payment already initiated. Please complete your payment in the Profile & Payments section.']);
            $stmtCheck->close();
            exit;
        }
        $stmtCheck->close();

        // Calculate due_date as one day before the training schedule
        $due_date = date("Y-m-d", strtotime($schedule . " -1 day"));
        // Set payment type as "Training Registration"
        $payment_type = 'Training Registration';
        $status = 'New';

        // Insert a payment record with payment_type and due_date
        $insertPaymentQuery = "INSERT INTO payments (user_id, training_id, payment_type, amount, status, due_date) VALUES (?, ?, ?, ?, ?, ?)";
        $stmtPayment = $conn->prepare($insertPaymentQuery);
        if (!$stmtPayment) {
            echo json_encode(['status' => false, 'message' => 'Database prepare error: ' . $conn->error]);
            exit;
        }
        // Bind parameters: user_id (i), training_id (i), payment_type (s), fee (d), status (s), due_date (s)
        $stmtPayment->bind_param("iisdss", $userId, $trainingId, $payment_type, $fee, $status, $due_date);
        if (!$stmtPayment->execute()) {
            echo json_encode(['status' => false, 'message' => 'Failed to initiate payment: ' . $stmtPayment->error]);
            exit;
        }
        if ($stmtPayment->affected_rows > 0) {
            echo json_encode(['status' => true, 'message' => 'Payment initiated. Please complete your payment in the Profile & Payments section.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to initiate payment. No records were inserted.']);
        }
        $stmtPayment->close();
    } elseif ($action === 'check_payment_status') {
        if (!isset($input['training_id']) || filter_var($input['training_id'], FILTER_VALIDATE_INT) === false) {
            echo json_encode(['status' => false, 'message' => 'Invalid training ID.']);
            exit;
        }
        $trainingId = (int)$input['training_id'];

        // Retrieve the payment record with status "Completed" (if it exists) for this training
        $stmt = $conn->prepare("SELECT * FROM payments WHERE user_id = ? AND training_id = ? AND status = 'Completed'");
        $stmt->bind_param("ii", $userId, $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // If payment is completed, ensure the user is registered for the training.
            $checkReg = $conn->prepare("SELECT * FROM training_registrations WHERE user_id = ? AND training_id = ?");
            $checkReg->bind_param("ii", $userId, $trainingId);
            $checkReg->execute();
            $resReg = $checkReg->get_result();
            if ($resReg->num_rows === 0) {
                // Create training registration
                $insertReg = $conn->prepare("INSERT INTO training_registrations (user_id, training_id) VALUES (?, ?)");
                $insertReg->bind_param("ii", $userId, $trainingId);
                $insertReg->execute();
            }
            echo json_encode([
                'status' => true,
                'payment_completed' => true,
                'message' => 'Payment completed. You are now joined to the training.'
            ]);
        } else {
            echo json_encode([
                'status' => true,
                'payment_completed' => false,
                'message' => 'Payment not completed yet.'
            ]);
        }
    } elseif ($action === 'get_joined_trainings') {
        // Fetch the trainings the user has joined
        $query = "SELECT t.title, t.description, t.schedule, t.image 
                  FROM training_registrations tr
                  INNER JOIN trainings t ON tr.training_id = t.training_id
                  WHERE tr.user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $trainings = [];
        while ($row = $result->fetch_assoc()) {
            $trainings[] = $row;
        }

        echo json_encode(['status' => true, 'trainings' => $trainings]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid action specified.']);
    }
} catch (Exception $e) {
    error_log("Error in training_registration.php: " . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'An internal error occurred: ' . $e->getMessage()]);
}

function recordAuditLog($userId, $action, $details)
{
    // Assumes there is an audit_log table with columns: user_id, action, details, created_at
    global $conn;
    $query = "INSERT INTO audit_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $userId, $action, $details);
    $stmt->execute();
    $stmt->close();
}

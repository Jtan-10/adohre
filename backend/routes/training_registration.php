<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Add secure session settings before starting the session
session_set_cookie_params([
    'lifetime' => 0,
    'secure' => true,
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
    $action = isset($_GET['action']) ? $_GET['action'] : null;
    $userId = $_SESSION['user_id'];

    if ($action === 'join_training') {
        $input = json_decode(file_get_contents('php://input'), true);
        // Validate training_id is a valid integer
        $trainingId = filter_var($input['training_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$trainingId) {
            echo json_encode(['status' => false, 'message' => 'Invalid training identifier.']);
            exit;
        }

        // Check if the user is already registered
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

            // Log the email request with recipient and timestamp
            error_log("Sending training registration email to: " . $user['email'] . " at " . date('Y-m-d H:i:s'));

            // Rate limiting for email sending: allow a maximum of 5 emails per recipient within a 1-hour window.
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $window = 3600; // 1 hour in seconds
            $maxEmails = 10;
            $now = time();
            $recipient = $user['email'];

            if (!isset($_SESSION['email_send_requests'])) {
                $_SESSION['email_send_requests'] = [];
            }
            if (!isset($_SESSION['email_send_requests'][$recipient])) {
                $_SESSION['email_send_requests'][$recipient] = [
                    'count' => 0,
                    'first_request_time' => $now
                ];
            }
            // Reset the counter if the window has expired.
            if ($now - $_SESSION['email_send_requests'][$recipient]['first_request_time'] > $window) {
                $_SESSION['email_send_requests'][$recipient]['count'] = 0;
                $_SESSION['email_send_requests'][$recipient]['first_request_time'] = $now;
            }
            // Check if the rate limit has been reached.
            if ($_SESSION['email_send_requests'][$recipient]['count'] >= $maxEmails) {
                error_log("Rate limit exceeded for sending emails to: " . $recipient);
            } else {
                // Increment the counter and send the email.
                $_SESSION['email_send_requests'][$recipient]['count']++;

                $mail = new PHPMailer(true);
                try {
                    // Configure SMTP using environment variables.
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['SMTP_HOST']; // e.g., smtp.gmail.com
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['SMTP_USER'];
                    $mail->Password   = $_ENV['SMTP_PASS'];
                    $mail->SMTPSecure = $_ENV['SMTP_SECURE']; // e.g., TLS
                    $mail->Port       = $_ENV['SMTP_PORT'];   // e.g., 587

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
                    $mail->addAddress($user['email']);

                    // Email content.
                    $mail->isHTML(true);
                    $mail->Subject = "Training Registration Confirmation";
                    $mail->Body = "
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
                    error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                }
            }

            echo json_encode(['status' => true, 'message' => 'Successfully joined the training.']);
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
        echo json_encode(['status' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    error_log("Error in training_registration.php: " . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'An internal error occurred.']);
}

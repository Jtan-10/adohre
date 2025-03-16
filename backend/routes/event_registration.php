<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'You must be logged in to perform this action.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    // Validate event_id for join_event action
    if (isset($_GET['action']) && $_GET['action'] === 'join_event') {
        if (!isset($input['event_id']) || filter_var($input['event_id'], FILTER_VALIDATE_INT) === false) {
            echo json_encode(['status' => false, 'message' => 'Invalid event ID.']);
            exit;
        }
    }
    $action = isset($_GET['action']) ? $_GET['action'] : null;

    if ($action === 'join_event') {
        $userId = $_SESSION['user_id'];
        $eventId = (int)$input['event_id'];

        // Check if the user is already registered
        $checkQuery = "SELECT * FROM event_registrations WHERE user_id = ? AND event_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ii', $userId, $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['status' => false, 'message' => 'You have already joined this event.']);
        } else {
            // Register the user for the event
            $insertQuery = "INSERT INTO event_registrations (user_id, event_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param('ii', $userId, $eventId);
            $stmt->execute();

            // Record audit log for joining the event
            recordAuditLog($userId, "Join Event", "User joined event ID: $eventId");

            // Retrieve event details
            $eventQuery = "SELECT title, date, location FROM events WHERE event_id = ?";
            $stmtEvent = $conn->prepare($eventQuery);
            $stmtEvent->bind_param("i", $eventId);
            $stmtEvent->execute();
            $resultEvent = $stmtEvent->get_result();
            $event = $resultEvent->fetch_assoc();
            $stmtEvent->close();

            // Retrieve user details
            $userQuery = "SELECT email, first_name FROM users WHERE user_id = ?";
            $stmtUser = $conn->prepare($userQuery);
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();
            $user = $resultUser->fetch_assoc();
            $stmtUser->close();

            // Rate limiting for email sending:
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $window = 3600;        // 1 hour window in seconds.
            $maxEmails = 10;        // Maximum allowed emails per recipient within the window.
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

            // Reset counter if window expired.
            if ($now - $_SESSION['email_send_requests'][$userEmail]['first_request_time'] > $window) {
                $_SESSION['email_send_requests'][$userEmail]['count'] = 0;
                $_SESSION['email_send_requests'][$userEmail]['first_request_time'] = $now;
            }

            // Check if the rate limit has been reached.
            if ($_SESSION['email_send_requests'][$userEmail]['count'] >= $maxEmails) {
                error_log("Rate limit exceeded for sending emails to: " . $userEmail);
                // Optionally, you can notify the user or take other actions.
            } else {
                // Increment the count and proceed to send the email.
                $_SESSION['email_send_requests'][$userEmail]['count']++;

                // Send email notification using PHPMailer.
                $mail = new PHPMailer(true);
                try {
                    // SMTP configuration using environment variables.
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

                    $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
                    $mail->addAddress($userEmail);

                    $mail->isHTML(true);
                    $mail->Subject = "Event Registration Confirmation";
                    $mail->Body    = "
                        <h1>Hello " . htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') . ",</h1>
                        <p>Thank you for joining our event!</p>
                        <p>You have successfully registered for the event: <strong>" . htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') . "</strong>.</p>
                        <p><strong>Date:</strong> " . htmlspecialchars($event['date'], ENT_QUOTES, 'UTF-8') . "</p>
                        <p><strong>Location:</strong> " . htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') . "</p>
                        <p>For more details, please log in to your account.</p>";
                    $mail->AltBody = strip_tags($mail->Body);

                    $mail->send();
                    // Log the sent email into the database
                    $stmtLog = $conn->prepare("INSERT INTO email_notifications (user_id, subject, body) VALUES (?, ?, ?)");
                    $subjectLog = "Event Registration Confirmation";
                    $bodyLog = $mail->Body;
                    $stmtLog->bind_param("iss", $userId, $subjectLog, $bodyLog);
                    $stmtLog->execute();
                    $stmtLog->close();
                } catch (Exception $e) {
                    error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                    // Optionally handle email failure
                }
            }
            echo json_encode(['status' => true, 'message' => 'Successfully joined the event.']);
        }
    } elseif ($action === 'get_joined_events') {
        $userId = $_SESSION['user_id'];

        // Fetch joined events for the logged-in user
        $query = "SELECT e.event_id, e.title, e.description, e.date, e.location, e.image
                  FROM event_registrations er
                  JOIN events e ON er.event_id = e.event_id
                  WHERE er.user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }

        echo json_encode(['status' => true, 'events' => $events]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid action specified.']);
    }
} catch (Exception $e) {
    error_log("Error in event_registration: " . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'An error occurred.']);
}
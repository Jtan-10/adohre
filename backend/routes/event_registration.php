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
    $action = isset($_GET['action']) ? $_GET['action'] : null;

    if ($action === 'join_event') {
        $userId = $_SESSION['user_id'];
        $eventId = $input['event_id'];

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

            // Send email notification using PHPMailer

            $mail = new PHPMailer(true);
            try {
                // SMTP configuration using environment variables
                $mail->isSMTP();
                $mail->Host       = $_ENV['SMTP_HOST']; 
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['SMTP_USER'];
                $mail->Password   = $_ENV['SMTP_PASS'];
                $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
                $mail->Port       = $_ENV['SMTP_PORT'];

                $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
                $mail->addAddress($user['email']);

                $mail->isHTML(true);
                $mail->Subject = "Event Registration Confirmation";
                $mail->Body    = "
                    <h1>Hello " . htmlspecialchars($user['first_name']) . ",</h1>
                    <p>Thank you for joining our event!</p>
                    <p>You have successfully registered for the event: <strong>" . htmlspecialchars($event['title']) . "</strong>.</p>
                    <p><strong>Date:</strong> " . htmlspecialchars($event['date']) . "</p>
                    <p><strong>Location:</strong> " . htmlspecialchars($event['location']) . "</p>
                    <p>For more details, please log in to your account.</p>";
                $mail->AltBody = strip_tags($mail->Body);

                $mail->send();
            } catch (Exception $e) {
                error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
                // Optionally handle email failure
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
    echo json_encode(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
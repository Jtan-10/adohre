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
    
    // Validate event_id for join_event, initiate_payment, and check_payment_status actions
    if (isset($_GET['action']) && in_array($_GET['action'], ['join_event', 'initiate_payment', 'check_payment_status'])) {
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
                    // No error logging
                }
            }
            
            echo json_encode(['status' => true, 'message' => 'Successfully joined the event.']);
        }
    } elseif ($action === 'initiate_payment') {
        // Initiate a payment record for events that require a fee
        $userId = $_SESSION['user_id'];
        $eventId = (int)$input['event_id'];
        
        // Retrieve event fee, title, and date
        $queryFee = "SELECT fee, title, date FROM events WHERE event_id = ?";
        $stmtFee = $conn->prepare($queryFee);
        $stmtFee->bind_param("i", $eventId);
        $stmtFee->execute();
        $resultFee = $stmtFee->get_result();
        
        if ($resultFee->num_rows == 0) {
            echo json_encode(['status' => false, 'message' => 'Event not found.']);
            exit;
        }
        
        $eventData = $resultFee->fetch_assoc();
        $fee = (float)$eventData['fee'];
        $eventDate = $eventData['date']; // Expected to be in a valid date format
        $stmtFee->close();
        
        if ($fee <= 0) {
            echo json_encode(['status' => false, 'message' => 'No payment is required for this event.']);
            exit;
        }
        
        // Calculate due_date as one day before the event date
        $due_date = date("Y-m-d", strtotime($eventDate . " -1 day"));
        
        // Check if the user already has a payment record for this event
        $checkPaymentQuery = "SELECT * FROM payments WHERE user_id = ? AND event_id = ?";
        $stmtCheckPayment = $conn->prepare($checkPaymentQuery);
        if (!$stmtCheckPayment) {
            echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        
        $stmtCheckPayment->bind_param("ii", $userId, $eventId);
        if (!$stmtCheckPayment->execute()) {
            echo json_encode(['status' => false, 'message' => 'Database execute error: ' . $stmtCheckPayment->error]);
            exit;
        }
        
        $resultCheckPayment = $stmtCheckPayment->get_result();
        if ($resultCheckPayment->num_rows > 0) {
            echo json_encode(['status' => true, 'message' => 'Payment already initiated. Please complete your payment in the Profile & Payments section.']);
            $stmtCheckPayment->close();
            exit;
        }
        $stmtCheckPayment->close();
        
        // Insert a payment record with status "New" including due_date
        $insertPaymentQuery = "INSERT INTO payments (user_id, event_id, amount, status, due_date, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmtPayment = $conn->prepare($insertPaymentQuery);
        if (!$stmtPayment) {
            echo json_encode(['status' => false, 'message' => 'Database prepare error: ' . $conn->error]);
            exit;
        }
        
        $status = 'New';
        // Bind parameters: user_id (i), event_id (i), fee (d), status (s), due_date (s)
        $stmtPayment->bind_param("iids s", $userId, $eventId, $fee, $status, $due_date);
        // Note: If your PHP version does not support named spaces in bind_param,
        // you can remove the extra space. The proper call is:
        // $stmtPayment->bind_param("iids s", $userId, $eventId, $fee, $status, $due_date);
        // However, if you face issues, please adjust accordingly.
        // For example, you might need: $stmtPayment->bind_param("iids s", $userId, $eventId, $fee, $status, $due_date);
        
        // Using the correct syntax:
        $stmtPayment->bind_param("iids s", $userId, $eventId, $fee, $status, $due_date);
        
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
        $userId = $_SESSION['user_id'];
        $eventId = (int)$input['event_id'];
        
        // Check if the payments table exists
        $checkTableResult = $conn->query("SHOW TABLES LIKE 'payments'");
        if ($checkTableResult->num_rows === 0) {
            echo json_encode(['status' => false, 'message' => 'Payments system is not available.']);
            exit;
        }

        // Check if there's a payment record for this user and event
        $checkStmt = $conn->prepare("SELECT * FROM payments WHERE user_id = ? AND event_id = ?");
        if (!$checkStmt) {
            echo json_encode(['status' => false, 'message' => 'Database prepare error: ' . $conn->error]);
            exit;
        }

        $checkStmt->bind_param("ii", $userId, $eventId);
        if (!$checkStmt->execute()) {
            echo json_encode(['status' => false, 'message' => 'Database execute error: ' . $checkStmt->error]);
            exit;
        }

        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            // No payment record found
            echo json_encode([
                'status' => true,
                'payment_completed' => false,
                'message' => 'No payment record found for this event.'
            ]);
            $checkStmt->close();
            exit;
        }
        
        $payment = $result->fetch_assoc();
        $paymentStatus = $payment['status'];
        $checkStmt->close();
        
        if ($paymentStatus === 'Completed') {
            // Check if already registered
            $checkRegStmt = $conn->prepare("SELECT * FROM event_registrations WHERE user_id = ? AND event_id = ?");
            if (!$checkRegStmt) {
                echo json_encode(['status' => false, 'message' => 'Database prepare error: ' . $conn->error]);
                exit;
            }
            
            $checkRegStmt->bind_param("ii", $userId, $eventId);
            $checkRegStmt->execute();
            $regResult = $checkRegStmt->get_result();
            
            if ($regResult->num_rows === 0) {
                // Register the user since payment is completed
                $insertRegStmt = $conn->prepare("INSERT INTO event_registrations (user_id, event_id) VALUES (?, ?)");
                if (!$insertRegStmt) {
                    echo json_encode(['status' => false, 'message' => 'Registration error: ' . $conn->error]);
                    exit;
                }
                
                $insertRegStmt->bind_param("ii", $userId, $eventId);
                $insertRegStmt->execute();
                $insertRegStmt->close();
                
                // Add an audit log entry for the registration
                recordAuditLog($userId, "Join Event", "User joined event ID: $eventId after payment");
            }
            $checkRegStmt->close();
            
            echo json_encode([
                'status' => true,
                'payment_completed' => true,
                'message' => 'Payment completed. You are now joined to the event.'
            ]);
        } else {
            // Payment not completed yet
            echo json_encode([
                'status' => true,
                'payment_completed' => false,
                'message' => "Payment status: $paymentStatus. Please complete your payment to join the event."
            ]);
        }
    } elseif ($action === 'get_joined_events') {
        $userId = $_SESSION['user_id'];
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

function recordAuditLog($userId, $action, $details) {
    // Assumes there is an audit_log table with columns: user_id, action, details, created_at
    global $conn;
    $query = "INSERT INTO audit_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $userId, $action, $details);
    $stmt->execute();
    $stmt->close();
}
?>
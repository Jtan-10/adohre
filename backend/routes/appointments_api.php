<?php
require_once '../db/db_connect.php';

header("Content-Type: application/json");
// Turn off error display for production and log errors instead
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configure session security based on environment
configureSessionSecurity();
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

require_once '../db/db_connect.php';
// Include PHPMailer via Composer's autoloader
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    if (!isset($data['action'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing action parameter"]);
        exit;
    }
    $action = $data['action'];
    if ($action === 'schedule_appointment') {
        // Validate required field.
        if (empty($data['appointment_date'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing appointment_date"]);
            exit;
        }
        // Validate CSRF token.
        if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(["error" => "Invalid CSRF token"]);
            exit;
        }
        // Convert appointment date.
        $appointment_date = date('Y-m-d H:i:s', strtotime($data['appointment_date']));
        $description = trim($data['description'] ?? '');
        $stmt = $conn->prepare("INSERT INTO appointments (user_id, appointment_date, description) VALUES (?, ?, ?)");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
            exit;
        }
        $stmt->bind_param("iss", $userId, $appointment_date, $description);
        if ($stmt->execute()) {
            // Audit log the scheduling of an appointment.
            recordAuditLog($userId, "Schedule Appointment", "Appointment scheduled for $appointment_date. Description: $description");
            echo json_encode(["status" => "success", "message" => "Appointment scheduled successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
        }
        $stmt->close();
        exit;
    } elseif ($action === 'accept_appointment_with_details') {
        // Only admin allowed.
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }
        if (empty($data['appointment_id']) || !isset($data['csrf_token'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing parameters"]);
            exit;
        }
        if ($data['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(["error" => "Invalid CSRF token"]);
            exit;
        }
        $appointment_id = $data['appointment_id'];
        $accept_details = trim($data['accept_details'] ?? '');
        $stmt = $conn->prepare("UPDATE appointments SET accepted = 1, accept_details = ? WHERE appointment_id = ?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
            exit;
        }
        $stmt->bind_param("si", $accept_details, $appointment_id);
        if ($stmt->execute()) {
            $stmt->close();
            $stmt2 = $conn->prepare("SELECT u.email FROM appointments a JOIN users u ON a.user_id = u.user_id WHERE a.appointment_id = ?");
            if ($stmt2) {
                $stmt2->bind_param("i", $appointment_id);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $userEmail = '';
                if ($row = $result->fetch_assoc()) {
                    $userEmail = $row['email'];
                }
                $stmt2->close();
            }
            $subject = "Your Appointment Has Been Accepted";
            $message = "Hello,\n\nYour appointment has been accepted. Details: " . $accept_details;
            if (sendAppointmentEmail($userEmail, $subject, $message)) {
                // Audit log the acceptance action.
                recordAuditLog($userId, "Accept Appointment", "Appointment ID $appointment_id accepted with details: $accept_details");
                echo json_encode(["status" => "success", "message" => "Appointment accepted and details sent by email."]);
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Failed to send email."]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
        }
        exit;
    } elseif ($action === 'accept_appointment') {
        if (empty($data['appointment_id']) || !isset($data['csrf_token'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing appointment_id or CSRF token"]);
            exit;
        }
        if ($data['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(["error" => "Invalid CSRF token"]);
            exit;
        }
        $appointment_id = $data['appointment_id'];
        if ($role === 'admin') {
            $stmt = $conn->prepare("UPDATE appointments SET accepted = 1 WHERE appointment_id = ?");
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(["error" => "Internal Server Error"]);
                exit;
            }
            $stmt->bind_param("i", $appointment_id);
        } else {
            $stmt = $conn->prepare("UPDATE appointments SET accepted = 1 WHERE appointment_id = ? AND user_id = ?");
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(["error" => "Internal Server Error"]);
                exit;
            }
            $stmt->bind_param("ii", $appointment_id, $userId);
        }
        if ($stmt->execute()) {
            // Audit log the acceptance (without additional details)
            recordAuditLog($userId, "Accept Appointment", "Appointment ID $appointment_id accepted.");
            echo json_encode(["status" => "success", "message" => "Appointment accepted successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
        }
        $stmt->close();
        exit;
    } elseif ($action === 'mark_done') {
        if (empty($data['appointment_id']) || !isset($data['csrf_token'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing parameters"]);
            exit;
        }
        if ($data['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(["error" => "Invalid CSRF token"]);
            exit;
        }
        $appointment_id = $data['appointment_id'];
        $stmt = $conn->prepare("UPDATE appointments SET done = 1 WHERE appointment_id = ?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
            exit;
        }
        $stmt->bind_param("i", $appointment_id);
        if ($stmt->execute()) {
            // Audit log the "mark done" action.
            recordAuditLog($userId, "Mark Appointment Done", "Appointment ID $appointment_id marked as done.");
            echo json_encode(["status" => "success", "message" => "Appointment marked as done."]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
        }
        $stmt->close();
        exit;
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid action"]);
        exit;
    }
} elseif ($method === 'GET') {
    $stmt = $conn->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date ASC");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Internal Server Error"]);
        exit;
    }
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Internal Server Error"]);
        exit;
    }
    $result = $stmt->get_result();
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    echo json_encode(["status" => "success", "appointments" => $appointments]);
    $stmt->close();
    exit;
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

/**
 * Send an appointment notification email using PHPMailer.
 *
 * @param string $email Recipient email address.
 * @param string $subject Email subject.
 * @param string $body Email body.
 * @return bool True if sent successfully, false otherwise.
 */
function sendAppointmentEmail($email, $subject, $body)
{
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

        // Set sender and recipient.
        $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($email);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        // Log the sent email into the database
        global $conn;
        $userId = $_SESSION['user_id'];
        $stmtLog = $conn->prepare("INSERT INTO email_notifications (user_id, subject, body) VALUES (?, ?, ?)");
        $stmtLog->bind_param("iss", $userId, $subject, $body);
        $stmtLog->execute();
        $stmtLog->close();

        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

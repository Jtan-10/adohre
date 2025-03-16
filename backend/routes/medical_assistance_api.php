<?php
header("Content-Type: application/json");
// ...existing error reporting and security settings...

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

require_once '../db/db_connect.php';
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
    if ($action === 'schedule_medical_assistance') {
        if (empty($data['assistance_date'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing assistance_date"]);
            exit;
        }
        if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(["error" => "Invalid CSRF token"]);
            exit;
        }
        $assistance_date = date('Y-m-d H:i:s', strtotime($data['assistance_date']));
        $description = trim($data['description'] ?? '');
        $stmt = $conn->prepare("INSERT INTO medical_assistance (user_id, assistance_date, description) VALUES (?, ?, ?)");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
            exit;
        }
        $stmt->bind_param("iss", $userId, $assistance_date, $description);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Medical assistance request scheduled successfully."]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
        }
        $stmt->close();
        exit;
    } elseif ($action === 'accept_medical_assistance_with_details') {
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(["error" => "Unauthorized"]);
            exit;
        }
        if (empty($data['assistance_id']) || !isset($data['csrf_token'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing parameters"]);
            exit;
        }
        if ($data['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(["error" => "Invalid CSRF token"]);
            exit;
        }
        $assistance_id = $data['assistance_id'];
        $accept_details = trim($data['accept_details'] ?? '');
        $stmt = $conn->prepare("UPDATE medical_assistance SET accepted = 1, accept_details = ? WHERE assistance_id = ?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
            exit;
        }
        $stmt->bind_param("si", $accept_details, $assistance_id);
        if ($stmt->execute()) {
            $stmt->close();
            $stmt2 = $conn->prepare("SELECT u.email FROM medical_assistance m JOIN users u ON m.user_id = u.user_id WHERE m.assistance_id = ?");
            if ($stmt2) {
                $stmt2->bind_param("i", $assistance_id);
                $stmt2->execute();
                $result = $stmt2->get_result();
                $userEmail = '';
                if ($row = $result->fetch_assoc()) {
                    $userEmail = $row['email'];
                }
                $stmt2->close();
            }
            $subject = "Your Medical Assistance Request Has Been Accepted";
            $message = "Hello,\n\nYour medical assistance request has been accepted. Details: " . $accept_details;
            if (sendMedicalAssistanceEmail($userEmail, $subject, $message)) {
                echo json_encode(["status" => "success", "message" => "Request accepted and details sent by email."]);
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Failed to send email."]);
            }
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Internal Server Error"]);
        }
        exit;
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid action"]);
        exit;
    }
} elseif ($method === 'GET') {
    $stmt = $conn->prepare("SELECT * FROM medical_assistance WHERE user_id = ? ORDER BY assistance_date ASC");
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
    $assistance = [];
    while ($row = $result->fetch_assoc()) {
        $assistance[] = $row;
    }
    echo json_encode(["status" => "success", "assistance" => $assistance]);
    $stmt->close();
    exit;
} else {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

function sendMedicalAssistanceEmail($email, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port       = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
        $mail->addAddress($email);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>

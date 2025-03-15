<?php
header("Content-Type: application/json");
// Turn off error display for production and log errors instead
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start a secure session (ensure session cookie settings are configured in php.ini or here if needed)
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

require_once '../db/db_connect.php';
$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    if (isset($data['action'])) {
        if ($data['action'] === 'schedule_appointment') {
            // Validate required field.
            if (empty($data['appointment_date'])) {
                http_response_code(400);
                echo json_encode(["error" => "Missing appointment_date"]);
                exit;
            }
            // Convert the appointment date to MySQL datetime format.
            $appointment_date = date('Y-m-d H:i:s', strtotime($data['appointment_date']));
            $description = trim($data['description'] ?? '');
            
            $stmt = $conn->prepare("INSERT INTO appointments (user_id, appointment_date, description) VALUES (?, ?, ?)");
            if (!$stmt) {
                // Log error details and respond with a generic error message
                http_response_code(500);
                echo json_encode(["error" => "Internal Server Error"]);
                exit;
            }
            $stmt->bind_param("iss", $userId, $appointment_date, $description);
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Appointment scheduled successfully."]);
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Internal Server Error"]);
            }
            $stmt->close();
            exit;
        } elseif ($data['action'] === 'accept_appointment') {
            if (empty($data['appointment_id'])) {
                http_response_code(400);
                echo json_encode(["error" => "Missing appointment_id"]);
                exit;
            }
            $appointment_id = $data['appointment_id'];
            $stmt = $conn->prepare("UPDATE appointments SET accepted = 1 WHERE appointment_id = ? AND user_id = ?");
            if (!$stmt) {
                http_response_code(500);
                echo json_encode(["error" => "Internal Server Error"]);
                exit;
            }
            $stmt->bind_param("ii", $appointment_id, $userId);
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Appointment accepted successfully."]);
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
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Missing action parameter"]);
        exit;
    }
} elseif ($method === 'GET') {
    // Secure the GET request using a prepared statement to avoid potential SQL injection
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
?>
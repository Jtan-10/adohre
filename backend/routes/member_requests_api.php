<?php
require_once '../db/db_connect.php';

header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Configure session security based on environment
configureSessionSecurity();
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$allowedTypes = ['appointment', 'medical_assistance', 'death_assistance', 'other'];
$allowedStatuses = ['submitted', 'pending', 'approved'];

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
    $action = $data['action'] ?? null;
    if (!$action) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing action']);
        exit;
    }

    if ($action === 'create_request') {
        // CSRF check
        if (!isset($data['csrf_token']) || $data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
        $request_type = $data['request_type'] ?? '';
        $requested_at_raw = $data['requested_at'] ?? '';
        $description = trim($data['description'] ?? '');
        if (!in_array($request_type, $allowedTypes, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request type']);
            exit;
        }
        if (empty($requested_at_raw)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing requested_at']);
            exit;
        }
        $requested_at = date('Y-m-d H:i:s', strtotime($requested_at_raw));

        $stmt = $conn->prepare("INSERT INTO member_requests (user_id, request_type, requested_at, description, status) VALUES (?, ?, ?, ?, 'submitted')");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
            exit;
        }
        $stmt->bind_param('isss', $userId, $request_type, $requested_at, $description);
        if ($stmt->execute()) {
            $requestId = $stmt->insert_id;
            $stmt->close();

            // Log audit
            recordAuditLog($userId, 'Create Member Request', "Type=$request_type at $requested_at, ID=$requestId");

            // Notify all admins via email
            notifyAdminsNewRequest($requestId, $request_type, $requested_at, $description, $conn);

            echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        exit;
    }

    if ($action === 'update_status') {
        if ($role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        if (!isset($data['csrf_token']) || $data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
        $request_id = isset($data['request_id']) ? (int)$data['request_id'] : 0;
        $new_status = $data['status'] ?? '';
        if ($request_id <= 0 || !in_array($new_status, $allowedStatuses, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE member_requests SET status = ? WHERE request_id = ?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
            exit;
        }
        $stmt->bind_param('si', $new_status, $request_id);
        if ($stmt->execute()) {
            $stmt->close();
            recordAuditLog($userId, 'Update Member Request Status', "Request ID=$request_id -> $new_status");
            echo json_encode(['status' => 'success', 'message' => 'Status updated.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
} elseif ($method === 'GET') {
    // Return only the current user's requests
    $stmt = $conn->prepare('SELECT * FROM member_requests WHERE user_id = ? ORDER BY requested_at DESC');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error']);
        exit;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    echo json_encode(['status' => 'success', 'requests' => $rows]);
    exit;
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

function notifyAdminsNewRequest($requestId, $type, $when, $description, $conn)
{
    // Fetch requesting user details
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $user = ['email' => '', 'first_name' => '', 'last_name' => ''];
    $uStmt = $conn->prepare('SELECT first_name, last_name, email FROM users WHERE user_id = ?');
    if ($uStmt) {
        $uStmt->bind_param('i', $userId);
        $uStmt->execute();
        $res = $uStmt->get_result();
        if ($row = $res->fetch_assoc()) $user = $row;
        $uStmt->close();
    }

    // Collect admin emails
    $admins = [];
    $q = $conn->query("SELECT email, first_name FROM users WHERE role = 'admin'");
    if ($q) {
        while ($row = $q->fetch_assoc()) $admins[] = $row;
    }
    if (empty($admins)) return; // no admins to notify

    $subject = 'New Member Request Submitted';
    $safeDesc = htmlspecialchars($description ?? '', ENT_QUOTES, 'UTF-8');
    $safeType = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
    $safeWhen = htmlspecialchars($when, ENT_QUOTES, 'UTF-8');
    $safeUser = htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');
    $body = "<h3>New Member Request</h3>
             <p><strong>Request ID:</strong> {$requestId}</p>
             <p><strong>User:</strong> {$safeUser} ({$safeEmail})</p>
             <p><strong>Type:</strong> {$safeType}</p>
             <p><strong>Requested At:</strong> {$safeWhen}</p>
             <p><strong>Description:</strong><br> {$safeDesc}</p>";

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port       = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
        foreach ($admins as $a) {
            if (!empty($a['email'])) $mail->addAddress($a['email']);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        $mail->send();

        // Log email (one row per admin not needed; log once for requester)
        $stmtLog = $conn->prepare('INSERT INTO email_notifications (user_id, subject, body) VALUES (?, ?, ?)');
        if ($stmtLog) {
            $stmtLog->bind_param('iss', $userId, $subject, $body);
            $stmtLog->execute();
            $stmtLog->close();
        }
    } catch (Exception $e) {
        // Silent fail, but log server-side
        error_log('NotifyAdmins email error: ' . $e->getMessage());
    }
}

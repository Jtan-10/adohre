<?php
require_once '../db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed.']);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) {
    // Fallback to form-data
    $data = $_POST;
}

$email = trim($data['email'] ?? '');
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => false, 'message' => 'Invalid email.']);
    exit();
}

$stmt = $conn->prepare('SELECT user_id, password_hash FROM users WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['status' => true, 'exists' => false]);
    exit();
}

$hasPassword = !empty($row['password_hash']);
echo json_encode(['status' => true, 'exists' => true, 'hasPassword' => $hasPassword]);
exit();

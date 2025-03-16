<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once '../db/db_connect.php';
// Assuming a table "email_notifications" with columns: id, user_id, subject, body, sent_at
$userId = $_SESSION['user_id'];
$otpPattern = "%OTP%"; // Exclude OTP emails based on subject
$stmt = $conn->prepare("SELECT id, subject, body, sent_at FROM email_notifications WHERE user_id = ? AND subject NOT LIKE ? ORDER BY sent_at DESC");
$stmt->bind_param("is", $userId, $otpPattern);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
    // Simplify the notification by trimming a long message body
    $row['body'] = (strlen($row['body']) > 100) ? substr($row['body'], 0, 100) . "..." : $row['body'];
    $notifications[] = $row;
}
echo json_encode(['status' => true, 'notifications' => $notifications]);
exit;

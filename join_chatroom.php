<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'backend/db/db_connect.php';

// Validate room_id
if (!isset($_GET['room_id']) || empty($_GET['room_id'])) {
    die("Invalid or missing chat room ID.");
}

$roomId = intval($_GET['room_id']);
$userId = $_SESSION['user_id'];

// Check if the chat room exists
$query = "SELECT id FROM chat_rooms WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $roomId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    die("Chat room does not exist.");
}
$stmt->close();

// Check if the user is already a participant
$query = "SELECT 1 FROM chat_participants WHERE room_id = ? AND user_id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("ii", $roomId, $userId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    // Instead of auto-adding the user, deny access.
    die("Access Denied: You have not been added to this chatroom by the admin. Please contact your administrator for access.");
}
$stmt->close();

// Check for the "share" parameter: if set to 'admin', redirect to the admin chatroom.
if (isset($_GET['share']) && $_GET['share'] === 'admin') {
    header("Location: admin/chatroom.php?room_id=" . $roomId);
    exit;
}

// Otherwise, redirect to the regular (frontend) chatroom.
header("Location: chatroom.php?room_id=" . $roomId);
exit;
?>
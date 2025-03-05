<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'backend/db/db_connect.php';

if (!isset($_GET['room_id']) || empty($_GET['room_id'])) {
    echo "Invalid request.";
    exit;
}

$roomId = intval($_GET['room_id']);

// Determine if we're in admin mode (download requested by an admin)
$mode = isset($_GET['mode']) ? $_GET['mode'] : '';

$query = "SELECT cm.message, cm.sent_at, cm.is_admin, u.first_name, u.last_name
          FROM chat_messages cm
          JOIN users u ON cm.user_id = u.user_id
          WHERE cm.room_id = $roomId
          ORDER BY cm.sent_at ASC";
$result = $conn->query($query);

$chatContent = "Chat Transcript for Room #$roomId\n\n";
while ($row = $result->fetch_assoc()) {
    $timestamp = date("Y-m-d H:i:s", strtotime($row['sent_at']));
    if ($mode === 'admin' && $row['is_admin'] == 1) {
        // In admin mode, show the full name with "(Admin)" appended.
        $sender = "{$row['first_name']} {$row['last_name']} (Admin)";
    } else {
        $sender = ($row['is_admin'] == 1) ? "Admin" : "{$row['first_name']} {$row['last_name']}";
    }
    $chatContent .= "[{$timestamp}] {$sender}: {$row['message']}\n";
}

// Set the filename based on mode.
$filename = "chat_room_{$roomId}.txt";
if ($mode === 'admin') {
    $filename = "chat_room_{$roomId}_admin.txt";
}

header('Content-Type: text/plain');
header("Content-Disposition: attachment; filename=" . $filename);
echo $chatContent;
exit;
?>
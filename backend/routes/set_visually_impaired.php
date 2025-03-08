<?php
session_start();
header('Content-Type: application/json');

// Include DB connection if needed
require_once '../db/db_connect.php';

// Read the JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['visually_impaired'])) {
    // Missing required data
    echo json_encode([
        'status' => false,
        'message' => 'Missing visually_impaired field'
    ]);
    exit;
}

// Convert to 1 or 0
$visually_impaired = $data['visually_impaired'] ? 1 : 0;

// Example database logic (adjust to your schema)
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode([
        'status' => false,
        'message' => 'No user session found.'
    ]);
    exit;
}

// Suppose we want to update the users table:
$stmt = $conn->prepare("UPDATE users SET visually_impaired = ? WHERE user_id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $visually_impaired, $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode([
        'status' => true,
        'message' => 'Visually impaired preference updated.'
    ]);
} else {
    echo json_encode([
        'status' => false,
        'message' => 'Database error: Could not prepare statement.'
    ]);
}
?>
<?php
session_start();
header('Content-Type: application/json');

// Include your database connection file.
require_once '../db/db_connect.php';

// Get the JSON input.
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['visually_impaired'])) {
    echo json_encode(['status' => false, 'message' => 'No visually_impaired value provided.']);
    exit;
}

$visually_impaired = $data['visually_impaired'] ? 1 : 0;
$_SESSION['visually_impaired'] = $visually_impaired;

// Insert or update the temporary table.
$session_id = session_id();
$query = "INSERT INTO temp_visually_impaired (session_id, visually_impaired) VALUES (?, ?)
          ON DUPLICATE KEY UPDATE visually_impaired = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['status' => false, 'message' => 'Database error: failed to prepare statement.']);
    exit;
}
$stmt->bind_param("sii", $session_id, $visually_impaired, $visually_impaired);

if ($stmt->execute()) {
    echo json_encode(['status' => true, 'message' => 'Preference updated in database.']);
} else {
    echo json_encode(['status' => false, 'message' => 'Failed to update preference in database.']);
}

$stmt->close();
$conn->close();
?>
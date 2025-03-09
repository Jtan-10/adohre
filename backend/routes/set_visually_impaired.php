<?php
session_start();
header('Content-Type: application/json');

// Include DB connection if needed
require_once '../db/db_connect.php';

// Read the JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['visually_impaired'])) {
    // Missing required data
    error_log("visually_impaired key not set in input");

    echo json_encode([
        'status' => false,
        'message' => 'Missing visually_impaired field'
    ]);
    exit;
}

// Convert to 1 or 0
$_SESSION['visually_impaired'] = $data['visually_impaired'] ? 1 : 0;


error_log("Visually impaired flag in session: " . $_SESSION['visually_impaired']);

echo json_encode(['status' => true, 'message' => 'Visually impaired flag saved in session.']);
exit;
?>
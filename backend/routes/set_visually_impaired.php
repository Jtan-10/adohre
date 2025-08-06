<?php
require_once '../db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Regenerate session ID to prevent fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

header('Content-Type: application/json');

// Enforce POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

// Include DB connection if needed (and to get the audit logging helper function)
require_once '../db/db_connect.php';

// Read the JSON input
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
    error_log("Invalid JSON input");
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Invalid JSON input'
    ]);
    exit;
}

if (!array_key_exists('visually_impaired', $data)) {
    error_log("visually_impaired key not set in input");
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Missing visually_impaired field'
    ]);
    exit;
}

// Validate the value strictly (should be boolean or equivalent)
$visuallyImpaired = filter_var($data['visually_impaired'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($visuallyImpaired === null) {
    error_log("Invalid value for visually_impaired");
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Invalid value for visually_impaired'
    ]);
    exit;
}

// Convert to 1 or 0 for session storage
$_SESSION['visually_impaired'] = $visuallyImpaired ? 1 : 0;
error_log("Visually impaired flag in session: " . $_SESSION['visually_impaired']);

// If the user is logged in, record this action in the audit log
if (isset($_SESSION['user_id'])) {
    // recordAuditLog() is defined in db_connect.php
    recordAuditLog($_SESSION['user_id'], 'Set Visually Impaired', 'Flag set to ' . ($_SESSION['visually_impaired'] ? '1' : '0'));
}

echo json_encode(['status' => true, 'message' => 'Visually impaired flag saved in session.']);
exit;

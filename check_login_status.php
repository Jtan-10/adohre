<?php
require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

header('Content-Type: application/json');

// Check what's actually in the session
$debugInfo = [
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'is_logged_in' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'role' => $_SESSION['role'] ?? 'not set',
    'first_name' => $_SESSION['first_name'] ?? 'not set',
    'cookies_received' => $_COOKIE,
    'request_headers' => getallheaders()
];

echo json_encode($debugInfo, JSON_PRETTY_PRINT);

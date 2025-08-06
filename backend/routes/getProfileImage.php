<?php
require_once '../db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Set Content-Type header for JSON output
header('Content-Type: application/json; charset=utf-8');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit();
}

// Return the profile image path
echo json_encode([
    'status' => true,
    'profile_image' => $_SESSION['profile_image'] ?? './assets/default-profile.jpeg'
]);
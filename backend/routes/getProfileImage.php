<?php
// Set secure session cookie parameters
session_set_cookie_params([
    'secure' => true,       // Ensure cookie is sent over HTTPS only
    'httponly' => true,     // Inaccessible to JavaScript
    'samesite' => 'Strict'  // Prevent CSRF
]);
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
<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit();
}

// Return the profile image path
echo json_encode([
    'status' => true,
    'profile_image' => $_SESSION['profile_image'] ?? './assets/default-profile.jpeg'
]);
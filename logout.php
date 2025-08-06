<?php
require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Check if the user is logged in and get their ID
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Replace manual audit logging with a call to recordAuditLog
    recordAuditLog($userId, 'User Logout', 'User logged out successfully');

    // Optionally, close the database connection:
    $conn->close();
}

// Destroy the session and redirect to login.
session_destroy();
header("Location: login.php");
exit;

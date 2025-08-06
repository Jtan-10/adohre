<?php
require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

header('Content-Type: application/json');

// Test session functionality
$sessionInfo = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_cookie_params' => session_get_cookie_params(),
    'server_info' => [
        'https' => isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : 'not set',
        'server_port' => $_SERVER['SERVER_PORT'] ?? 'not set',
        'http_x_forwarded_proto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
        'force_secure_cookies_env' => $_ENV['FORCE_SECURE_COOKIES'] ?? 'not set',
    ],
    'session_data' => $_SESSION,
    'test_set' => false
];

// Test setting a session variable
$_SESSION['test_timestamp'] = time();
$sessionInfo['test_set'] = true;
$sessionInfo['session_data_after_set'] = $_SESSION;

echo json_encode($sessionInfo, JSON_PRETTY_PRINT);

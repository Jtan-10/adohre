<?php
header('Content-Type: application/json');

$data = file_get_contents('php://input');
if (!$data) {
    echo json_encode(['status' => false, 'message' => 'No data received']);
    exit;
}

$json = json_decode($data, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => false, 'message' => 'Invalid JSON']);
    exit;
}

$error = isset($json['error']) ? $json['error'] : 'Unknown error';
$url = isset($json['url']) ? $json['url'] : 'Unknown URL';

// Log error using PHP's error_log function
error_log("Error logged: $error | URL: $url");

// Optionally, to append errors to a custom log file uncomment below:
// $logFile = __DIR__ . '/error_log.txt';
// file_put_contents($logFile, "Error logged: $error | URL: $url" . PHP_EOL, FILE_APPEND);

echo json_encode(['status' => true, 'message' => 'Error logged successfully']);

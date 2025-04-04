<?php
// log_error.php

// Make sure the content type is JSON for the response.
header('Content-Type: application/json');

// Retrieve the JSON payload from the POST request.
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (isset($data['error'])) {
    // Log the error message along with the timestamp.
    error_log("CLIENT-DEBUG: " . $data['timestamp'] . " - " . $data['error']);
    echo json_encode(['status' => 'logged']);
} else {
    error_log("CLIENT-DEBUG: Received invalid log data: " . $input);
    echo json_encode(['status' => 'failed', 'message' => 'Invalid log data']);
}
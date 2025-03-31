<?php
// deploy.php - Webhook receiver for GitHub deployment

// Use Composer's autoload to load Dotenv
require_once __DIR__ . '/backend/db/db_connect.php';

// Get the secret from the environment
$secret = getenv('GITHUB_WEBHOOK_SECRET');
if (!$secret) {
    http_response_code(500);
    echo json_encode(['status' => 'Error', 'message' => 'Webhook secret is not configured.']);
    exit();
}

// Retrieve the signature sent by GitHub
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
// Get the raw POST payload
$payload = file_get_contents('php://input');
// Compute HMAC hash with SHA1 using the secret
$hash = 'sha1=' . hash_hmac('sha1', $payload, $secret);

// Validate the signature
if (hash_equals($hash, $signature)) {
    // Log the event for auditing purposes
    file_put_contents('/home/bitnami/deploy.log', "Webhook triggered at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    
    // Execute your deployment script
    // Ensure that update_and_restart.sh is executable and located at /home/bitnami/update_and_restart.sh
    shell_exec('/home/bitnami/update_and_restart.sh > /home/bitnami/deploy.log 2>&1');
    
    echo json_encode(['status' => 'Deployment triggered']);
} else {
    http_response_code(403);
    echo json_encode(['status' => 'Error', 'message' => 'Invalid signature']);
}
?>
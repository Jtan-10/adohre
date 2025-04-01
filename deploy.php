<?php
// deploy.php - Webhook receiver for GitHub deployment with debugging

// Use Composer's autoload to load Dotenv
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from .env file located in the current directory
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get the secret from the environment
$secret = getenv('GITHUB_WEBHOOK_SECRET');
if (!$secret) {
    error_log("deploy.php error: Webhook secret is not configured.");
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

// Debugging: log payload, received signature, and computed hash
error_log("deploy.php debug: Payload: " . $payload);
error_log("deploy.php debug: Received Signature: " . $signature);
error_log("deploy.php debug: Computed Hash: " . $hash);

if (hash_equals($hash, $signature)) {
    error_log("deploy.php: Valid webhook received at " . date('Y-m-d H:i:s'));
    // Execute your deployment script
    shell_exec('/home/bitnami/update_and_restart.sh > /home/bitnami/deploy.log 2>&1');
    echo json_encode(['status' => 'Deployment triggered']);
} else {
    error_log("deploy.php error: Invalid signature at " . date('Y-m-d H:i:s'));
    http_response_code(403);
    echo json_encode(['status' => 'Error', 'message' => 'Invalid signature']);
}
?>
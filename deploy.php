<?php
// deploy.php - Webhook receiver for GitHub deployment with extra debugging

error_log("deploy.php debug: __DIR__ is " . __DIR__);
$envPath = __DIR__ . '/.env';
error_log("deploy.php debug: Checking for .env at " . $envPath);
if (!file_exists($envPath)) {
    error_log("deploy.php error: .env file does not exist at " . $envPath);
} else {
    error_log("deploy.php debug: .env file found");
}

// Use Composer's autoload to load Dotenv
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Debug: check if variable is loaded
$secret = getenv('GITHUB_WEBHOOK_SECRET');
error_log("deploy.php debug: GITHUB_WEBHOOK_SECRET is: " . var_export($secret, true));

if (!$secret) {
    http_response_code(500);
    echo json_encode(['status' => 'Error', 'message' => 'Webhook secret is not configured.']);
    exit();
}

// (Rest of your code follows...)

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
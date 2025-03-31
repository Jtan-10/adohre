<?php
// deploy.php - Webhook receiver for GitHub deployment with debugging

// Use Composer's autoload to load Dotenv
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables from the .env file located in this directory
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Define a log file for debugging
$logFile = '/home/bitnami/deploy.log';

// Simple debug logging function
function debugLog($message) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n", FILE_APPEND);
}

debugLog("deploy.php triggered.");

// Get the secret from the environment
$secret = getenv('GITHUB_WEBHOOK_SECRET');
if (!$secret) {
    debugLog("ERROR: GITHUB_WEBHOOK_SECRET is not configured.");
    http_response_code(500);
    echo json_encode(['status' => 'Error', 'message' => 'Webhook secret is not configured.']);
    exit();
} else {
    debugLog("GITHUB_WEBHOOK_SECRET loaded (value redacted).");
}

// Retrieve the signature sent by GitHub
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
debugLog("Received signature: " . ($signature ? $signature : "none"));

// Get the raw POST payload
$payload = file_get_contents('php://input');
debugLog("Payload received (first 100 chars): " . substr($payload, 0, 100));

// Compute HMAC hash with SHA1 using the secret
$hash = 'sha1=' . hash_hmac('sha1', $payload, $secret);
debugLog("Computed hash: " . $hash);

// Validate the signature
if (hash_equals($hash, $signature)) {
    debugLog("Signature valid. Triggering deployment.");
    // Execute your deployment script (make sure the script is executable)
    shell_exec('/home/bitnami/update_and_restart.sh > /home/bitnami/deploy.log 2>&1');
    echo json_encode(['status' => 'Deployment triggered']);
} else {
    debugLog("Invalid signature. Computed: $hash, Received: $signature");
    http_response_code(403);
    echo json_encode(['status' => 'Error', 'message' => 'Invalid signature']);
}
?>
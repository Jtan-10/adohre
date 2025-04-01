<?php
// deploy.php - Webhook receiver for GitHub deployment with extra debugging and hardcoded secret

error_log("deploy.php debug: __DIR__ is " . __DIR__);

// Hard-code the webhook secret
$hardCodedSecret = 'j8U2ufG7d9bnCG6UIIdp0ryYu';
error_log("deploy.php debug: Hardcoded secret is: " . $hardCodedSecret);

if (!$hardCodedSecret) {
    http_response_code(500);
    echo json_encode(['status' => 'Error', 'message' => 'Webhook secret is not configured.']);
    exit();
}

// Retrieve the signature sent by GitHub
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

// Get the raw POST payload
$payload = file_get_contents('php://input');

// Compute HMAC hash with SHA1 using the hard-coded secret
$hash = 'sha1=' . hash_hmac('sha1', $payload, $hardCodedSecret);

// Debugging: log payload, received signature, and computed hash
error_log("deploy.php debug: Payload: " . $payload);
error_log("deploy.php debug: Received Signature: " . $signature);
error_log("deploy.php debug: Computed Hash: " . $hash);

if (hash_equals($hash, $signature)) {
    error_log("deploy.php: Valid webhook received at " . date('Y-m-d H:i:s'));
    // Execute your deployment script (ensure update_and_restart.sh is executable)
    shell_exec('/home/bitnami/update_and_restart.sh > /home/bitnami/deploy.log 2>&1');
    echo json_encode(['status' => 'Deployment triggered']);
} else {
    error_log("deploy.php error: Invalid signature at " . date('Y-m-d H:i:s'));
    http_response_code(403);
    echo json_encode(['status' => 'Error', 'message' => 'Invalid signature']);
}
?>
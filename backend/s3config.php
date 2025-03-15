<?php
// s3config.php

// Require Composer's autoloader.
require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Dotenv\Dotenv;

// Path to your .env file (ensure it is outside of your public webroot).
$envFile = __DIR__ . '/../.env';

if (!file_exists($envFile)) {
    error_log('Environment file not found: ' . $envFile);
    throw new Exception('Environment file not found.');
}

// Load environment variables from the .env file using immutable settings.
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Validate required environment variables.
$requiredVars = ['AWS_REGION', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_BUCKET_NAME'];
foreach ($requiredVars as $var) {
    if (empty($_ENV[$var])) {
        error_log("Missing required environment variable: $var");
        throw new Exception("Missing required environment variable: $var");
    }
}

try {
    // Create an S3Client instance with secure settings.
    $s3 = new S3Client([
        'region'      => $_ENV['AWS_REGION'],
        'version'     => 'latest',
        'credentials' => [
            'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        ],
        // Secure HTTP options: enable SSL certificate verification and set appropriate timeouts.
        'http' => [
            'verify'          => true,
            'timeout'         => 30,
            'connect_timeout' => 5,
        ],
    ]);
} catch (Exception $e) {
    error_log('Error creating S3 client: ' . $e->getMessage());
    throw new Exception('Error creating S3 client.');
}

// Define the bucket name as a global variable.
$bucketName = $_ENV['AWS_BUCKET_NAME'];
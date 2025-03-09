<?php
// s3config.php

// Require Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Create and return an S3Client instance
$s3 = new S3Client([
    'region'      => $_ENV['AWS_REGION'],
    'version'     => 'latest',
    'credentials' => [
        'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
    ],
]);

// Optionally, you can define your bucket name as a global variable:
$bucketName = $_ENV['AWS_BUCKET_NAME'];

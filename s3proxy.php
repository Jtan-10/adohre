<?php
// s3proxy.php
require_once __DIR__ . '/backend/s3config.php';

// Get the requested path
$requestPath = $_SERVER['REQUEST_URI'];
$s3proxyPrefix = '/s3proxy/';

if (strpos($requestPath, $s3proxyPrefix) === 0) {
    // Extract the S3 key from the request path
    $s3Key = urldecode(substr($requestPath, strlen($s3proxyPrefix)));

    try {
        // Get the object from S3
        $result = $s3->getObject([
            'Bucket' => $bucketName,
            'Key'    => $s3Key
        ]);

        // Set appropriate headers
        if (isset($result['ContentType'])) {
            header('Content-Type: ' . $result['ContentType']);
        }

        // Output the object body
        echo $result['Body'];
        exit;
    } catch (Exception $e) {
        // Log the error
        error_log("S3 proxy error: " . $e->getMessage());

        // Return a 404 error
        header('HTTP/1.1 404 Not Found');
        echo 'File not found';
        exit;
    }
} else {
    // If the request doesn't start with /s3proxy/, return a 404 error
    header('HTTP/1.1 404 Not Found');
    echo 'Invalid S3 proxy request';
    exit;
}

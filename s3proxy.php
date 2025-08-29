<?php
// s3proxy.php
require_once __DIR__ . '/backend/s3config.php';

// Get the requested path and fix double slashes
$requestPath = $_SERVER['REQUEST_URI'];
$requestPath = preg_replace('#/{2,}#', '/', $requestPath);
error_log("S3 proxy request path: " . $requestPath);

// Check for all possible s3proxy path formats
if (strpos($requestPath, '/s3proxy/') !== false) {
    // Extract everything after /s3proxy/
    preg_match('#/s3proxy/(.+)#', $requestPath, $matches);
    if (!empty($matches[1])) {
        $s3Key = urldecode($matches[1]);
        error_log("Extracted S3 key: " . $s3Key);

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
}
} else {
    // No match for /s3proxy/ in the path
    header('HTTP/1.1 404 Not Found');
    echo 'Invalid S3 proxy request';
    error_log("No /s3proxy/ pattern found in URL: " . $requestPath);
    exit;
}
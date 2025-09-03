<?php
// Streams a PDF stored in S3 via an internal /s3proxy/<key> reference.
require_once __DIR__ . '/vendor/autoload.php';

header('X-Content-Type-Options: nosniff');

$url = $_GET['url'] ?? null;
if (!$url) {
    http_response_code(400);
    echo 'Missing url';
    exit;
}

// Normalize double slashes but keep protocol
$url = preg_replace('#(?<!:)//+#', '/', $url);

// Map /s3proxy/<key> to a real S3 URL using env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$bucket = $_ENV['AWS_BUCKET_NAME'] ?? '';
$region = $_ENV['AWS_REGION'] ?? '';
$customBase = $_ENV['AWS_S3_BASE_URL'] ?? '';

if (strpos($url, '/s3proxy/') !== false) {
    $parts = explode('/s3proxy/', $url, 2);
    $s3Key = isset($parts[1]) ? ltrim($parts[1], '/') : '';
    $baseUrl = !empty($customBase) ? rtrim($customBase, '/') . '/' : "https://{$bucket}.s3.{$region}.amazonaws.com/";
    $url = $baseUrl . $s3Key;
}

// Stream with cURL to avoid allow_url_fopen issues
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
if (stripos($url, 'https://') === 0) {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
}
$data = curl_exec($ch);
if ($data === false) {
    error_log('pdf_proxy curl error: ' . curl_error($ch));
    http_response_code(502);
    echo 'Failed to fetch PDF';
    curl_close($ch);
    exit;
}
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status >= 400) {
    http_response_code($status);
    echo 'Error fetching PDF';
    exit;
}

header('Content-Type: application/pdf');
header('Cache-Control: public, max-age=3600');
echo $data;
exit;

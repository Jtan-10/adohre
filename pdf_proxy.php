<?php
// Securely stream a PDF stored in S3 using app credentials (works with private buckets).
require_once __DIR__ . '/vendor/autoload.php';

header('X-Content-Type-Options: nosniff');

// Load env and S3 client
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
require_once __DIR__ . '/backend/s3config.php'; // $s3, $bucketName

// Input
$url = isset($_GET['url']) ? (string)$_GET['url'] : null; // may be /s3proxy/<key> or absolute S3/CF URL
$keyParam = isset($_GET['key']) ? (string)$_GET['key'] : null; // optional explicit key
$disposition = isset($_GET['disposition']) ? strtolower((string)$_GET['disposition']) : 'inline'; // inline | attachment
$filename = isset($_GET['filename']) ? (string)$_GET['filename'] : null;

if (!$url && !$keyParam) {
    http_response_code(400);
    echo 'Missing url or key';
    exit;
}

// Normalize double slashes but keep protocol
if ($url) {
    $url = preg_replace('#(?<!:)//+#', '/', $url);
}

// Derive S3 key
$bucket = $_ENV['AWS_BUCKET_NAME'] ?? '';
$region = $_ENV['AWS_REGION'] ?? '';
$customBase = $_ENV['AWS_S3_BASE_URL'] ?? '';

$s3Key = '';
if ($keyParam) {
    $s3Key = ltrim($keyParam, '/');
} elseif ($url) {
    if (strpos($url, '/s3proxy/') !== false) {
        $parts = explode('/s3proxy/', $url, 2);
        $s3Key = isset($parts[1]) ? ltrim($parts[1], '/') : '';
    } elseif (preg_match('/^https?:\/\//i', $url)) {
        $u = @parse_url($url);
        $host = $u['host'] ?? '';
        $path = isset($u['path']) ? ltrim($u['path'], '/') : '';
        $expected = $bucket && $region ? strtolower($bucket . '.s3.' . $region . '.amazonaws.com') : '';
        if ($expected && strtolower($host) === $expected) {
            $s3Key = $path;
        } elseif (preg_match('/^s3[.-][a-z0-9-]+\.amazonaws\.com$/i', $host) || strcasecmp($host, 's3.amazonaws.com') === 0) {
            $parts = explode('/', $path, 2);
            if (!empty($parts[0]) && strcasecmp($parts[0], $bucket) === 0) {
                $s3Key = $parts[1] ?? '';
            }
        } elseif (!empty($customBase)) {
            $cb = @parse_url(rtrim($customBase, '/') . '/');
            $cbHost = $cb['host'] ?? '';
            $cbPath = isset($cb['path']) ? trim($cb['path'], '/') : '';
            if ($cbHost && strcasecmp($host, $cbHost) === 0) {
                if ($cbPath && stripos($path, $cbPath . '/') === 0) {
                    $s3Key = substr($path, strlen($cbPath) + 1);
                } else {
                    $s3Key = $path;
                }
            }
        }
    } else {
        $s3Key = ltrim($url, '/');
    }
}

if ($s3Key === '') {
    http_response_code(400);
    echo 'Could not determine S3 key';
    exit;
}

// HEAD and Range support
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isHead = (strcasecmp($method, 'HEAD') === 0);
$rangeHeader = null;
if (!empty($_SERVER['HTTP_RANGE'])) {
    $h = trim($_SERVER['HTTP_RANGE']);
    if (preg_match('/^bytes=\d*-\d*(,\d*-\d*)*$/', $h)) {
        $rangeHeader = $h;
    }
}

try {
    if ($isHead) {
        $head = $s3->headObject(['Bucket' => $bucketName, 'Key' => $s3Key]);
        $len = $head['ContentLength'] ?? null;
        $etag = $head['ETag'] ?? null;
        $lm = isset($head['LastModified']) ? gmdate('D, d M Y H:i:s \G\M\T', strtotime((string)$head['LastModified'])) : null;
        header('Content-Type: application/pdf');
        if ($len !== null) header('Content-Length: ' . $len);
        if ($etag) header('ETag: ' . $etag);
        if ($lm) header('Last-Modified: ' . $lm);
        header('Accept-Ranges: bytes');
        if ($disposition === 'attachment') {
            $name = $filename ?: basename($s3Key);
            header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
        } else {
            header('Content-Disposition: inline');
        }
        http_response_code(200);
        exit;
    }

    $params = ['Bucket' => $bucketName, 'Key' => $s3Key];
    if ($rangeHeader) $params['Range'] = $rangeHeader;
    $res = $s3->getObject($params);

    $status = isset($res['ContentRange']) ? 206 : 200;
    http_response_code($status);

    header('Content-Type: application/pdf');
    if (isset($res['ContentLength'])) header('Content-Length: ' . $res['ContentLength']);
    if (isset($res['ContentRange'])) header('Content-Range: ' . $res['ContentRange']);
    header('Accept-Ranges: bytes');
    if (isset($res['ETag'])) header('ETag: ' . $res['ETag']);
    if (isset($res['LastModified'])) header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', strtotime((string)$res['LastModified'])));
    header('Cache-Control: public, max-age=3600');
    if ($disposition === 'attachment') {
        $name = $filename ?: basename($s3Key);
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
    } else {
        header('Content-Disposition: inline');
    }

    $body = $res['Body'];
    if (is_object($body) && method_exists($body, 'isSeekable')) {
        if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
        if (function_exists('ini_set')) @ini_set('zlib.output_compression', '0');
        while (!$body->eof()) {
            echo $body->read(8192);
            @ob_flush();
            flush();
        }
    } else {
        echo (string)$body;
    }
    exit;
} catch (\Aws\S3\Exception\S3Exception $e) {
    $code = $e->getAwsErrorCode();
    if ($code === 'NoSuchKey' || $code === 'NotFound') {
        http_response_code(404);
        echo 'Object not found';
        exit;
    }
    if ($code === 'AccessDenied') {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
    error_log('pdf_proxy S3 error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error retrieving PDF';
    exit;
} catch (\Throwable $e) {
    error_log('pdf_proxy error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Unexpected server error';
    exit;
}

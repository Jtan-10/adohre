<?php
// Secure S3 proxy: fetches objects from a private bucket using IAM creds and streams to client.

require_once __DIR__ . '/../../vendor/autoload.php';

// Load env for AWS_*
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Build S3 client using central config
require_once __DIR__ . '/../s3config.php'; // provides $s3 and $bucketName

// Helpers ------------------------------------------------------------------
function respond_error(int $code, string $message)
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function derive_s3_key_from_input(string $keyParam = null, string $urlParam = null, string $bucket = '', string $region = '', string $customBase = '')
{
    // 1) Explicit key wins
    if ($keyParam !== null && $keyParam !== '') {
        return ltrim($keyParam, '/');
    }

    // 2) If /s3proxy/ present in provided URL, extract trailing path
    if ($urlParam && strpos($urlParam, '/s3proxy/') !== false) {
        $parts = explode('/s3proxy/', $urlParam, 2);
        return isset($parts[1]) ? ltrim($parts[1], '/') : '';
    }

    // 3) Parse as absolute URL and try to infer
    if ($urlParam && preg_match('/^https?:\/\//i', $urlParam)) {
        $u = @parse_url($urlParam);
        $host = $u['host'] ?? '';
        $path = isset($u['path']) ? ltrim($u['path'], '/') : '';

        // Virtual-hosted-style: bucket.s3.region.amazonaws.com/key
        if ($bucket && $region) {
            $expected = strtolower($bucket . '.s3.' . $region . '.amazonaws.com');
            if (strtolower($host) === $expected) {
                return $path; // entire path is the key
            }
        }

        // Path-style: s3.region.amazonaws.com/bucket/key or s3.amazonaws.com/bucket/key
        if (preg_match('/^s3[.-][a-z0-9-]+\.amazonaws\.com$/i', $host) || strcasecmp($host, 's3.amazonaws.com') === 0) {
            $parts = explode('/', $path, 2);
            if (!empty($parts[0]) && strcasecmp($parts[0], $bucket) === 0) {
                return $parts[1] ?? '';
            }
        }

        // Custom base (e.g., CloudFront or alternate domain)
        if ($customBase) {
            $cb = @parse_url(rtrim($customBase, '/') . '/');
            $cbHost = $cb['host'] ?? '';
            $cbPath = isset($cb['path']) ? trim($cb['path'], '/') : '';
            if ($cbHost && strcasecmp($host, $cbHost) === 0) {
                if ($cbPath && stripos($path, $cbPath . '/') === 0) {
                    return substr($path, strlen($cbPath) + 1);
                }
                return $path;
            }
        }
    }

    // 4) As a last resort, treat urlParam as a bare path
    if ($urlParam && !preg_match('/^https?:\/\//i', $urlParam)) {
        return ltrim($urlParam, '/');
    }

    return '';
}

// Input --------------------------------------------------------------------
$keyParam = isset($_GET['key']) ? (string)$_GET['key'] : null;
$urlParam = isset($_GET['url']) ? (string)$_GET['url'] : null;
$disposition = isset($_GET['disposition']) ? strtolower((string)$_GET['disposition']) : 'inline'; // inline | attachment
$filename = isset($_GET['filename']) ? (string)$_GET['filename'] : null;

$bucket = $_ENV['AWS_BUCKET_NAME'] ?? getenv('AWS_BUCKET_NAME') ?? '';
$region = $_ENV['AWS_REGION'] ?? getenv('AWS_REGION') ?? '';
$customBase = $_ENV['AWS_S3_BASE_URL'] ?? getenv('AWS_S3_BASE_URL') ?? '';

$s3Key = derive_s3_key_from_input($keyParam, $urlParam, $bucket, $region, $customBase);
if ($s3Key === '') {
    respond_error(400, 'Missing or invalid S3 key/url');
}

// HEAD support: return headers only
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isHead = (strcasecmp($method, 'HEAD') === 0);

// Range support (bytes=...)
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
        // Reflect key headers
        $ct = $head['ContentType'] ?? 'application/octet-stream';
        $len = $head['ContentLength'] ?? null;
        $etag = $head['ETag'] ?? null;
        $lm = isset($head['LastModified']) ? gmdate('D, d M Y H:i:s \G\M\T', strtotime((string)$head['LastModified'])) : null;
        header('Content-Type: ' . $ct);
        if ($len !== null) header('Content-Length: ' . $len);
        if ($etag) header('ETag: ' . $etag);
        if ($lm) header('Last-Modified: ' . $lm);
        header('Accept-Ranges: bytes');
        if ($disposition === 'attachment') {
            $name = $filename ?: basename($s3Key);
            header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
        }
        // No body on HEAD
        http_response_code(200);
        exit;
    }

    $params = [
        'Bucket' => $bucketName,
        'Key'    => $s3Key,
    ];
    if ($rangeHeader) {
        $params['Range'] = $rangeHeader;
    }
    $res = $s3->getObject($params);

    // Decide status for ranged vs full
    $status = isset($res['ContentRange']) ? 206 : 200;
    http_response_code($status);

    // Headers
    $ct = $res['ContentType'] ?? 'application/octet-stream';
    header('Content-Type: ' . $ct);
    if (isset($res['ContentLength'])) header('Content-Length: ' . $res['ContentLength']);
    if (isset($res['ContentRange'])) header('Content-Range: ' . $res['ContentRange']);
    header('Accept-Ranges: bytes');
    if (isset($res['ETag'])) header('ETag: ' . $res['ETag']);
    if (isset($res['LastModified'])) header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', strtotime((string)$res['LastModified'])));
    if (isset($res['CacheControl'])) header('Cache-Control: ' . $res['CacheControl']);

    if ($disposition === 'attachment') {
        $name = $filename ?: basename($s3Key);
        header('Content-Disposition: attachment; filename="' . addslashes($name) . '"');
    } else {
        header('Content-Disposition: inline');
    }

    // Stream body
    $body = $res['Body']; // StreamInterface
    if (is_object($body) && method_exists($body, 'isSeekable')) {
        // Turn off output buffering for large files
        if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
        if (function_exists('ini_set')) @ini_set('zlib.output_compression', '0');
        while (!$body->eof()) {
            echo $body->read(8192);
            if (function_exists('fastcgi_finish_request')) {
                // don't call here; we need to finish the stream first
            }
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
        respond_error(404, 'Object not found');
    }
    if ($code === 'AccessDenied') {
        respond_error(403, 'Access denied');
    }
    error_log('s3_proxy error: ' . $e->getMessage());
    respond_error(500, 'Error retrieving object');
} catch (\Throwable $e) {
    error_log('s3_proxy throwable: ' . $e->getMessage());
    respond_error(500, 'Unexpected server error');
}

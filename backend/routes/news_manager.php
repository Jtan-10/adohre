<?php
// news_manager.php
// Add secure session cookie settings and production-level error reporting
session_set_cookie_params([
    'secure'    => true,
    'httponly'  => true,
    'samesite'  => 'Strict'
]);
if (getenv('ENVIRONMENT') !== 'development') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}
session_start();

require_once '../db/db_connect.php';
require_once '../s3config.php';

use Aws\Exception\AwsException;

header('Content-Type: application/json');

/**
 * embedDataInPng:
 * Converts binary data into a valid PNG image by mapping every 3 bytes to a pixel (R, G, B).
 * Remaining pixels are padded with black.
 *
 * @param string $binaryData The binary data to embed.
 * @param int    $desiredWidth Desired width (used to compute a roughly square image)
 * @return GdImage A GD image resource.
 */
if (!function_exists('embedDataInPng')) {
    function embedDataInPng($binaryData, $desiredWidth = 100) {
        $dataLen = strlen($binaryData);
        // Each pixel holds 3 bytes.
        $numPixels = ceil($dataLen / 3);
        // Create a roughly square image.
        $width = (int) floor(sqrt($numPixels));
        if ($width < 1) {
            $width = 1;
        }
        $height = (int) ceil($numPixels / $width);
        $img = imagecreatetruecolor($width, $height);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $black);
        $pos = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($pos < $dataLen) {
                    $r = ord($binaryData[$pos++]);
                    $g = ($pos < $dataLen) ? ord($binaryData[$pos++]) : 0;
                    $b = ($pos < $dataLen) ? ord($binaryData[$pos++]) : 0;
                    $color = imagecolorallocate($img, $r, $g, $b);
                    imagesetpixel($img, $x, $y, $color);
                } else {
                    imagesetpixel($img, $x, $y, $black);
                }
            }
        }
        return $img;
    }
}

/**
 * handleImageUpload:
 * Handles image file uploads for news. This version encrypts the image before uploading.
 *
 * @return string|null The S3 image URL (with /s3proxy/ prefix) or null if no file was uploaded.
 */
function handleImageUpload() {
    global $s3, $bucketName; // Ensure these are available from s3config.php

    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_file_size = 2 * 1024 * 1024; // 2 MB

    if (!in_array($_FILES['image']['type'], $allowed_types)) {
        echo json_encode(['status' => false, 'message' => 'Invalid file type for news image.']);
        exit();
    }

    if ($_FILES['image']['size'] > $max_file_size) {
        echo json_encode(['status' => false, 'message' => 'News image exceeds 2 MB limit.']);
        exit();
    }

    // Read the clear image data
    $clearImageData = file_get_contents($_FILES['image']['tmp_name']);

    // Set up encryption parameters
    $cipher = "AES-256-CBC";
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $rawKey = getenv('ENCRYPTION_KEY');
    $encryptionKey = hash('sha256', $rawKey, true);

    // Encrypt the image data (raw output) and prepend the IV
    $encryptedData = openssl_encrypt($clearImageData, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    $encryptedImageData = $iv . $encryptedData;

    // Embed the encrypted data into a PNG image
    $pngImage = embedDataInPng($encryptedImageData, 100);
    $finalEncryptedPngFile = tempnam(sys_get_temp_dir(), 'enc_png_') . '.png';
    imagepng($pngImage, $finalEncryptedPngFile);
    imagedestroy($pngImage);

    // Generate a unique file name for S3
    $file_name = uniqid() . '_' . time() . '_' . basename($_FILES['image']['name']);
    $s3_key = 'uploads/news_images/' . $file_name;

    try {
        $result = $s3->putObject([
            'Bucket'      => $bucketName,
            'Key'         => $s3_key,
            'Body'        => fopen($finalEncryptedPngFile, 'rb'),
            'ACL'         => 'public-read',
            'ContentType' => 'image/png'
        ]);

        @unlink($finalEncryptedPngFile);

        return str_replace(
            "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
            "/s3proxy/",
            $result['ObjectURL']
        );
    } catch (AwsException $e) {
        echo json_encode(['status' => false, 'message' => 'Error uploading news image: ' . $e->getMessage()]);
        exit();
    }
}

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING)
    ?: filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);

switch ($action) {
    case 'fetch':
        fetchNews();
        break;
    case 'add':
        addNews();
        break;
    case 'update':
        updateNews();
        break;
    case 'delete':
        deleteNews();
        break;
    case 'like':
        likeNews();
        break;
    case 'increment_view': // New action for incrementing view count
        incrementView();
        break;
    default:
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid action: ' . $action]);
        break;
}

function fetchNews() {
    global $conn;
    $newsQuery = "SELECT 
        n.news_id, 
        n.title, 
        n.excerpt, 
        n.content, 
        n.category, 
        n.image, 
        n.author_first, 
        n.author_last, 
        CONCAT(n.author_first, ' ', n.author_last) AS author,
        n.published_date, 
        n.created_by, 
        n.views,
        CONCAT(u.first_name, ' ', u.last_name) AS creator,
        (SELECT COUNT(*) FROM news_likes WHERE news_id = n.news_id) AS likes_count
      FROM news n 
      LEFT JOIN users u ON n.created_by = u.user_id";

    if (isset($_GET['id'])) {
        $newsQuery .= " WHERE n.news_id = ?";
    }
    $newsQuery .= " ORDER BY n.published_date DESC";

    $stmt = $conn->prepare($newsQuery);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt->bind_param("i", $id);
    }
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $stmt->error]);
        return;
    }
    $result = $stmt->get_result();
    $news = array();
    while ($row = $result->fetch_assoc()) {
        $news[] = $row;
    }
    $stmt->close();
    echo json_encode([
        'status' => true,
        'news'   => $news
    ]);
}

function addNews() {
    global $conn;
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Permission denied']);
        return;
    }
    $image_path = handleImageUpload();
    $author_first = trim($_POST['author_first']);
    $author_last = trim($_POST['author_last']);

    $stmt = $conn->prepare("INSERT INTO news (title, excerpt, content, category, image, author_first, author_last, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    $title      = $_POST['title'];
    $excerpt    = $_POST['excerpt'];
    $content    = $_POST['content'];
    $category   = $_POST['category'];
    $created_by = $_SESSION['user_id'];
    $stmt->bind_param("sssssssi", $title, $excerpt, $content, $category, $image_path, $author_first, $author_last, $created_by);
    if ($stmt->execute()) {
        recordAuditLog($created_by, 'Add News', "News titled '$title' added.");
        echo json_encode(['status' => true, 'message' => 'News article added successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}

function updateNews() {
    global $conn, $s3, $bucketName;
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Permission denied']);
        return;
    }
    $image_path = handleImageUpload();
    $author_first = trim($_POST['author_first']);
    $author_last = trim($_POST['author_last']);
    $news_id = $_POST['id'];

    // If a new image is uploaded, delete the existing image from S3.
    if ($image_path !== null) {
        $stmtCheck = $conn->prepare("SELECT image FROM news WHERE news_id = ?");
        $stmtCheck->bind_param("i", $news_id);
        $stmtCheck->execute();
        $result = $stmtCheck->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (!empty($row['image'])) {
                // Remove the proxy prefix and decode the URL to get the S3 key.
                $existingS3Key = urldecode(str_replace('/s3proxy/', '', $row['image']));
                try {
                    $s3->deleteObject([
                        'Bucket' => $bucketName,
                        'Key'    => $existingS3Key
                    ]);
                } catch (Aws\Exception\AwsException $e) {
                    error_log("S3 deletion error: " . $e->getMessage());
                }
            }
        }
        $stmtCheck->close();
    }

    // Prepare the SQL query depending on whether a new image was uploaded.
    if ($image_path !== null) {
        $sql = "UPDATE news SET title = ?, excerpt = ?, content = ?, category = ?, image = ?, author_first = ?, author_last = ? WHERE news_id = ?";
    } else {
        $sql = "UPDATE news SET title = ?, excerpt = ?, content = ?, category = ?, author_first = ?, author_last = ? WHERE news_id = ?";
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    $title    = $_POST['title'];
    $excerpt  = $_POST['excerpt'];
    $content  = $_POST['content'];
    $category = $_POST['category'];
    if ($image_path !== null) {
        $stmt->bind_param("sssssssi", $title, $excerpt, $content, $category, $image_path, $author_first, $author_last, $news_id);
    } else {
        $stmt->bind_param("ssssssi", $title, $excerpt, $content, $category, $author_first, $author_last, $news_id);
    }
    if ($stmt->execute()) {
        recordAuditLog($_SESSION['user_id'], 'Update News', "News ID $news_id updated.");
        echo json_encode(['status' => true, 'message' => 'News article updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}

function deleteNews() {
    global $conn, $s3, $bucketName;
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Permission denied']);
        return;
    }
    
    // Retrieve the news record to check for an existing image.
    $stmt = $conn->prepare("SELECT image FROM news WHERE news_id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    $news_id = $_POST['id'];
    $stmt->bind_param("i", $news_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $news = $result->fetch_assoc();
        if (!empty($news['image'])) {
            // Remove the proxy prefix and decode URL to obtain the original S3 key.
            $existingS3Key = urldecode(str_replace('/s3proxy/', '', $news['image']));
            try {
                $s3->deleteObject([
                    'Bucket' => $bucketName,
                    'Key'    => $existingS3Key
                ]);
            } catch (Aws\Exception\AwsException $e) {
                // Log error and continue deletion of the news record.
                error_log("S3 deletion error: " . $e->getMessage());
            }
        }
    }
    $stmt->close();

    // Now delete the news record from the database.
    $stmt = $conn->prepare("DELETE FROM news WHERE news_id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    $stmt->bind_param("i", $news_id);
    if ($stmt->execute()) {
        recordAuditLog($_SESSION['user_id'], 'Delete News', "News ID $news_id deleted.");
        echo json_encode(['status' => true, 'message' => 'News article deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}

function likeNews() {
    global $conn;
    $checkStmt = $conn->prepare("SELECT like_id FROM news_likes WHERE news_id=? AND user_id=?");
    if (!$checkStmt) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    $news_id = $_POST['news_id'];
    $user_id = $_SESSION['user_id'];
    $checkStmt->bind_param("ii", $news_id, $user_id);
    if (!$checkStmt->execute()) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $checkStmt->error]);
        return;
    }
    $result = $checkStmt->get_result();
    $exists = $result->fetch_assoc();
    $checkStmt->close();
    if ($exists) {
        $stmt = $conn->prepare("DELETE FROM news_likes WHERE news_id=? AND user_id=?");
        $actionMsg = 'Article unliked successfully';
    } else {
        $stmt = $conn->prepare("INSERT INTO news_likes (news_id, user_id) VALUES (?, ?)");
        $actionMsg = 'Article liked successfully';
    }
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    $stmt->bind_param("ii", $news_id, $user_id);
    if ($stmt->execute()) {
        recordAuditLog($user_id, 'Like News', "News ID $news_id " . ($exists ? "unliked" : "liked") . ".");
        $result = $conn->query("SELECT COUNT(*) AS like_count FROM news_likes WHERE news_id = $news_id");
        $data = $result->fetch_assoc();
        echo json_encode(['status' => true, 'message' => $actionMsg, 'like_count' => intval($data['like_count'])]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}

function incrementView() {
    global $conn;
    // Validate news_id from POST data
    $news_id = filter_input(INPUT_POST, 'news_id', FILTER_VALIDATE_INT);
    if (!$news_id) {
        echo json_encode(['status' => false, 'message' => 'Invalid news id']);
        return;
    }
    $stmt = $conn->prepare("UPDATE news SET views = views + 1 WHERE news_id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    $stmt->bind_param("i", $news_id);
    if ($stmt->execute()) {
        $result = $conn->query("SELECT views FROM news WHERE news_id = $news_id");
        $row = $result->fetch_assoc();
        echo json_encode(['status' => true, 'views' => intval($row['views'])]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}
?>
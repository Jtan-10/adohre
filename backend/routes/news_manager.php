<?php
// Add secure session cookie settings and production-level error reporting
session_set_cookie_params([
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
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


// Replace action assignment with sanitized input
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
    default:
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid action: ' . $action]);
        break;
}

function handleImageUpload()
{
    global $s3, $bucketName; // Ensure these are available from s3config.php

    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    // Allowed file types and max file size (2 MB)
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

    // Generate a unique file name
    $file_name = uniqid() . '_' . time() . '_' . basename($_FILES['image']['name']);
    $s3_key = 'uploads/news_images/' . $file_name;

    try {
        $result = $s3->putObject([
            'Bucket' => $bucketName, // from s3config.php
            'Key'    => $s3_key,
            'Body'   => fopen($_FILES['image']['tmp_name'], 'rb'),
            'ACL'    => 'public-read' // Adjust permissions as needed
        ]);

        // Return the S3 key or full URL if needed:
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

function fetchNews()
{
    global $conn;
    // Join with users table to get creator's full name
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
        'news' => $news
    ]);
}

function addNews()
{
    global $conn;
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Permission denied']);
        return;
    }
    $image_path = handleImageUpload();
    // Get the manual author inputs separately.
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
        // Audit log: record news addition.
        recordAuditLog($created_by, 'Add News', "News titled '$title' added.");
        echo json_encode(['status' => true, 'message' => 'News article added successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}

function updateNews()
{
    global $conn;
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Permission denied']);
        return;
    }
    $image_path = handleImageUpload();
    // Get updated author inputs.
    $author_first = trim($_POST['author_first']);
    $author_last = trim($_POST['author_last']);

    if ($image_path !== null) {
        $sql = "UPDATE news SET title=?, excerpt=?, content=?, category=?, image=?, author_first=?, author_last=? WHERE news_id=?";
    } else {
        $sql = "UPDATE news SET title=?, excerpt=?, content=?, category=?, author_first=?, author_last=? WHERE news_id=?";
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
    $news_id  = $_POST['id'];
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

function deleteNews()
{
    global $conn;
    if ($_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Permission denied']);
        return;
    }
    $stmt = $conn->prepare("DELETE FROM news WHERE news_id=?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    $news_id = $_POST['id'];
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

function likeNews()
{
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
        echo json_encode(['status' => true, 'message' => $actionMsg]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
    $stmt->close();
}
?>
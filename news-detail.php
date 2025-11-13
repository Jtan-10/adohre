<?php
// news-detail.php
require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Generate a nonce for inline styles.
$style_nonce = base64_encode(random_bytes(16));

// Validate session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Use filter_input to validate news id.
$news_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$news_id) {
    header("Location: news.php");
    exit;
}
$news_id = intval($news_id);

// Connect to the database and update the view count
$updateStmt = $conn->prepare("UPDATE news SET views = views + 1 WHERE news_id = ?");
if ($updateStmt) {
    $updateStmt->bind_param("i", $news_id);
    $updateStmt->execute();
    $updateStmt->close();
} else {
    error_log("Database error updating views: " . $conn->error);
}

// Helper function to format view count
function formatViews($views)
{
    if ($views >= 1000000000) {
        return number_format($views / 1000000000, 1) . 'B';
    } elseif ($views >= 1000000) {
        return number_format($views / 1000000, 1) . 'M';
    } elseif ($views >= 1000) {
        return number_format($views / 1000, 1) . 'K';
    } else {
        return $views;
    }
}

// Fetch the news article directly from the database (no internal HTTP dependency)
$sql = "SELECT 
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
      LEFT JOIN users u ON n.created_by = u.user_id
      WHERE n.news_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $news_id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $news = $row;
        }
    }
    $stmt->close();
}
if (!isset($news)) {
    echo "<p class='text-center mt-5'>News article not found.</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- Updated CSP including nonce for inline style -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($news['title']); ?> - ADOHRE News</title>
    <link rel="icon" href="assets/logo.png" type="image/jpg" />
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style nonce="<?php echo $style_nonce; ?>">
        .news-detail-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 1rem;
        }

        .news-detail-img {
            width: 100%;
            height: auto;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }

        .news-meta {
            color: #28a745;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .news-content {
            font-size: 1.1rem;
            line-height: 1.6;
        }
    </style>
</head>

<body>
    <?php include('header.php'); ?>
    <div class="container news-detail-container">
        <h1 class="mb-3"><?php echo htmlspecialchars($news['title']); ?></h1>
        <div class="news-meta mb-3">
            <span><i class="fas fa-clock"></i> <?php echo date("F j, Y", strtotime($news['published_date'])); ?></span>
            <span class="ms-3"><i class="fas fa-user"></i> <?php echo htmlspecialchars($news['author']); ?></span>
            <span class="ms-3"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($news['category']); ?></span>
            <span class="ms-3"><i class="fas fa-eye"></i> <?php echo formatViews(intval($news['views'])); ?>
                views</span>
        </div>
        <?php
        $imageSrc = !empty($news['image'])
            ? 'backend/routes/decrypt_image.php?image_url=' . urlencode($news['image'])
            : 'assets/default-image.jpg';
        ?>
        <img src="<?php echo $imageSrc; ?>" alt="<?php echo htmlspecialchars($news['title']); ?>"
            class="news-detail-img">
        <div class="news-content">
            <?php echo nl2br(htmlspecialchars($news['content'])); ?>
        </div>
        <!-- Like and Back Buttons in one row -->
        <div class="d-flex justify-content-between align-items-center mt-3">
            <a href="news.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to News
            </a>
            <button id="like-button" class="btn btn-outline-success btn-sm">
                <i class="fas fa-thumbs-up"></i> Like (<span
                    id="like-count"><?php echo intval($news['likes_count']); ?></span>)
            </button>
        </div>
    </div>
    <?php include('footer.php'); ?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script nonce="<?php echo $style_nonce; ?>">
        // Like button functionality
        document.getElementById('like-button').addEventListener('click', function() {
            this.disabled = true;
            fetch('backend/routes/news_manager.php?action=like', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'news_id=<?php echo $news_id; ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        document.getElementById('like-count').textContent = data.like_count;
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An unexpected error occurred. Please try again.');
                })
                .finally(() => {
                    document.getElementById('like-button').disabled = false;
                });
        });
    </script>
</body>

</html>
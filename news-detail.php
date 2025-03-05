<?php
// news-detail.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Ensure a valid news ID is provided.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: news.php");
    exit;
}

$news_id = intval($_GET['id']);

// Build the URL for the backend route.
$base_url = "http://localhost/capstone-php/backend/routes/news_manager.php";
$url = $base_url . "?action=fetch&id=" . $news_id;

// Release the session lock before making the cURL call.
session_write_close();

// Use cURL to fetch the news detail.
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
$response = curl_exec($ch);
curl_close($ch);



$newsData = json_decode($response, true);
if (!$newsData || !$newsData['status'] || count($newsData['news']) < 1) {
    echo "<p class='text-center mt-5'>News article not found.</p>";
    exit;
}

$news = $newsData['news'][0];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($news['title']); ?> - ADOHRE News</title>
    <link rel="icon" href="assets/logo.png" type="image/jpg" />
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            <span class="ms-3"><i class="fas fa-eye"></i> <?php echo intval($news['views']); ?> views</span>
        </div>
        <?php if (!empty($news['image'])): ?>
        <img src="<?php echo htmlspecialchars($news['image']); ?>" alt="<?php echo htmlspecialchars($news['title']); ?>"
            class="news-detail-img">
        <?php endif; ?>
        <div class="news-content">
            <?php echo nl2br(htmlspecialchars($news['content'])); ?>
        </div>
    </div>
    <?php include('footer.php'); ?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>

</html>
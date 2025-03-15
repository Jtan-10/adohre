<?php
define('APP_INIT', true);
require_once 'admin_header.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        .form-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #f9f9f9;
        }

        .content-item {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
        }

        .news-image {
            max-height: 150px;
            object-fit: cover;
            margin-bottom: 10px;
        }

        .category-badge {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-bottom: 10px;
            display: inline-block;
        }

        .meta-info {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <main class="container">
            <h1 class="text-center mt-4">News Management</h1>

            <!-- News Form for Creating/Editing -->
            <div class="form-section">
                <h3>Create / Edit News Article</h3>
                <form id="newsForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="newsTitle" class="form-label">Title</label>
                        <input type="text" id="newsTitle" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="newsExcerpt" class="form-label">Excerpt (Short Description)</label>
                        <textarea id="newsExcerpt" name="excerpt" class="form-control" rows="2" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="newsContent" class="form-label">Content</label>
                        <textarea id="newsContent" name="content" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="newsCategory" class="form-label">Category</label>
                        <input type="text" id="newsCategory" name="category" class="form-control" required>
                    </div>
                    <!-- New fields for manual author input -->
                    <div class="mb-3">
                        <label for="newsAuthorFirst" class="form-label">Author First Name</label>
                        <input type="text" id="newsAuthorFirst" name="author_first" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="newsAuthorLast" class="form-label">Author Last Name</label>
                        <input type="text" id="newsAuthorLast" name="author_last" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="newsImage" class="form-label">Image</label>
                        <input type="file" id="newsImage" name="image" class="form-control" accept="image/*">
                    </div>
                    <input type="hidden" id="newsId" name="id">
                    <!-- Add CSRF token -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" class="btn btn-success">Save Article</button>
                </form>
            </div>

            <!-- List of News Articles -->
            <div class="mt-4">
                <h3>Published Articles</h3>
                <div id="newsList" class="mt-3"></div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Updated inline script with nonce attribute -->
    <script nonce="<?php echo $cspNonce; ?>">
        // escapeHTML function to sanitize output
        function escapeHTML(text) {
            return text.replace(/[&<>"']/g, m => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[m]);
        }

        document.addEventListener('DOMContentLoaded', function() {
            fetchNews();

            document.getElementById('newsForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const newsId = document.getElementById('newsId').value;
                const action = newsId ? 'update' : 'add';
                formData.append('action', action);
                // Append CSRF token if not already included
                if (!formData.get('csrf_token')) {
                    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                }

                fetch('../backend/routes/news_manager.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(text => {
                        try {
                            if (text.trim() === '') {
                                throw new Error('Empty response from server');
                            }
                            const data = JSON.parse(text);
                            if (data.status) {
                                alert(data.message);
                                this.reset();
                                document.getElementById('newsId').value = '';
                                fetchNews();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        } catch (err) {
                            console.error("Parse error:", err);
                            console.error("Raw text:", text);
                            alert('Server error. Check console for details.');
                        }
                    })
                    .catch(err => {
                        console.error("Fetch error:", err);
                        alert('Failed to save the article. Check console for details.');
                    });
            });

            function fetchNews() {
                fetch('../backend/routes/news_manager.php?action=fetch')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            let html = '';
                            data.news.forEach(article => {
                                html += `
                      <div class="content-item">
                        <div class="row">
                          <div class="col-md-3">
                            <img src="${ article.image ? escapeHTML(article.image) : 'assets/default-news.jpg' }" 
                                 alt="${ escapeHTML(article.title) }" 
                                 class="img-fluid news-image">
                          </div>
                          <div class="col-md-9">
                            <span class="category-badge">${ escapeHTML(article.category) }</span>
                            <h4>${ escapeHTML(article.title) }</h4>
                            <div class="meta-info">
                              <i class="bi bi-person"></i> Author: ${ escapeHTML(article.author) } <br>
                              <i class="bi bi-info-circle"></i> Created by: ${ article.creator ? escapeHTML(article.creator) : 'Unknown' } <br>
                              <i class="bi bi-calendar"></i> ${ new Date(article.published_date).toLocaleDateString() } |
                              <i class="bi bi-hand-thumbs-up"></i> ${ escapeHTML(String(article.likes_count || 0)) } likes
                            </div>
                            <p>${ escapeHTML(article.excerpt) }</p>
                            <div class="btn-group">
                              <button class="btn btn-sm btn-primary me-2 rounded edit-news" data-id="${ article.news_id }">Edit</button>
                              <button class="btn btn-sm btn-danger rounded delete-news" data-id="${ article.news_id }">Delete</button>
                            </div>
                          </div>
                        </div>
                      </div>`;
                            });
                            document.getElementById('newsList').innerHTML = html || '<p>No articles found.</p>';

                            // Attach event listeners for edit and delete buttons
                            document.querySelectorAll('.edit-news').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    editNews(this.getAttribute('data-id'));
                                });
                            });
                            document.querySelectorAll('.delete-news').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    deleteNews(this.getAttribute('data-id'));
                                });
                            });
                        }
                    })
                    .catch(err => console.error(err));
            }

            function editNews(id) {
                fetch(`../backend/routes/news_manager.php?action=fetch&id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status && data.news.length > 0) {
                            const article = data.news[0];
                            document.getElementById('newsId').value = article.news_id;
                            document.getElementById('newsTitle').value = article.title;
                            document.getElementById('newsExcerpt').value = article.excerpt;
                            document.getElementById('newsContent').value = article.content;
                            document.getElementById('newsCategory').value = article.category;
                            const names = article.author.split(' ');
                            document.getElementById('newsAuthorFirst').value = names[0] || '';
                            document.getElementById('newsAuthorLast').value = names.slice(1).join(' ') || '';
                        } else {
                            alert('Error fetching article details.');
                        }
                    })
                    .catch(err => console.error(err));
            }

            function deleteNews(id) {
                if (confirm('Are you sure you want to delete this article?')) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('id', id);
                    // Append CSRF token for deletion
                    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

                    fetch('../backend/routes/news_manager.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status) {
                                alert(data.message);
                                fetchNews();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Failed to delete the article.');
                        });
                }
            }
        });
    </script>
</body>

</html>
<div class="form-section">
    <div class="d-flex justify-content-between align-items-center">
        <h3 class="m-0">Manage News</h3>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newsModal" data-mode="create">
            <i class="bi bi-plus-lg"></i> Add Article
        </button>
    </div>
    <hr class="mt-3 mb-2" />

    <div id="newsList"></div>
</div>

<!-- News Modal -->
<div class="modal fade" id="newsModal" tabindex="-1" aria-labelledby="newsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newsModalLabel">Add Article</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
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
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="newsAuthorFirst" class="form-label">Author First Name</label>
                            <input type="text" id="newsAuthorFirst" name="author_first" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="newsAuthorLast" class="form-label">Author Last Name</label>
                            <input type="text" id="newsAuthorLast" name="author_last" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label for="newsImage" class="form-label">Image</label>
                        <input type="file" id="newsImage" name="image" class="form-control" accept="image/*">
                    </div>
                    <input type="hidden" id="newsId" name="id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveNewsBtn" class="btn btn-success">Save</button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= $cspNonce ?>">
    // Escape HTML for safe rendering
    function escapeHTML(text) {
        if (text === null || text === undefined) return '';
        return String(text).replace(/[&<>"']/g, (m) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[m]);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const newsList = document.getElementById('newsList');
        const newsModal = document.getElementById('newsModal');
        const newsForm = document.getElementById('newsForm');
        const saveBtn = document.getElementById('saveNewsBtn');
        const modalTitle = document.getElementById('newsModalLabel');

        // Reset form when opening for create
        newsModal.addEventListener('show.bs.modal', (e) => {
            const trigger = e.relatedTarget;
            if (trigger && trigger.getAttribute('data-mode') === 'create') {
                modalTitle.textContent = 'Add Article';
                newsForm.reset();
                document.getElementById('newsId').value = '';
            }
        });

        // Save handler (add/update)
        saveBtn.addEventListener('click', () => {
            const formData = new FormData(newsForm);
            const id = document.getElementById('newsId').value;
            formData.append('action', id ? 'update' : 'add');
            if (!formData.get('csrf_token')) {
                const csrf = newsForm.querySelector('input[name="csrf_token"]').value;
                if (csrf) formData.append('csrf_token', csrf);
            }

            fetch('../backend/routes/news_manager.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.text())
                .then(text => {
                    const data = JSON.parse(text);
                    if (data.status) {
                        bootstrap.Modal.getInstance(newsModal)?.hide();
                        fetchNews();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error('Save error', err);
                    alert('Failed to save article.');
                });
        });

        function fetchNews() {
            fetch('../backend/routes/news_manager.php?action=fetch')
                .then(r => r.json())
                .then(data => {
                    if (!data.status) return;
                    if (!Array.isArray(data.news) || data.news.length === 0) {
                        newsList.innerHTML = '<p class="text-muted m-0">No articles found.</p>';
                        return;
                    }
                    const html = data.news.map(article => `
          <div class="content-item">
            <div class="row">
              <div class="col-md-3">
                <img src="${ article.image ? '../backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(article.image) : '../assets/default-image.jpg' }" alt="${ escapeHTML(article.title) }" class="img-fluid news-image" style="max-height:150px; object-fit:cover;">
              </div>
              <div class="col-md-9">
                <span class="badge bg-success mb-2">${ escapeHTML(article.category) }</span>
                <h5 class="mb-1">${ escapeHTML(article.title) }</h5>
                <div class="text-muted mb-2" style="font-size:.9rem">
                  <i class="bi bi-person"></i> Author: ${ escapeHTML(article.author) } &nbsp;|
                  <i class="bi bi-calendar"></i> ${ new Date(article.published_date).toLocaleDateString() } &nbsp;|
                  <i class="bi bi-hand-thumbs-up"></i> ${ escapeHTML(String(article.likes_count || 0)) } likes
                </div>
                <p class="mb-2">${ escapeHTML(article.excerpt) }</p>
                <div class="btn-group">
                  <button class="btn btn-sm btn-primary edit-news" data-id="${article.news_id}"><i class="bi bi-pencil"></i> Edit</button>
                  <button class="btn btn-sm btn-danger delete-news ms-2" data-id="${article.news_id}"><i class="bi bi-trash"></i> Delete</button>
                </div>
              </div>
            </div>
          </div>`).join('');
                    newsList.innerHTML = html;

                    // Wire actions
                    newsList.querySelectorAll('.edit-news').forEach(btn => {
                        btn.addEventListener('click', () => editNews(btn.getAttribute('data-id')));
                    });
                    newsList.querySelectorAll('.delete-news').forEach(btn => {
                        btn.addEventListener('click', () => deleteNews(btn.getAttribute('data-id')));
                    });
                })
                .catch(err => console.error('Fetch news error', err));
        }

        function editNews(id) {
            fetch(`../backend/routes/news_manager.php?action=fetch&id=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.status || !Array.isArray(data.news) || data.news.length === 0) {
                        alert('Failed to load article');
                        return;
                    }
                    const a = data.news[0];
                    document.getElementById('newsId').value = a.news_id;
                    document.getElementById('newsTitle').value = a.title || '';
                    document.getElementById('newsExcerpt').value = a.excerpt || '';
                    document.getElementById('newsContent').value = a.content || '';
                    document.getElementById('newsCategory').value = a.category || '';
                    const names = (a.author || '').split(' ');
                    document.getElementById('newsAuthorFirst').value = names[0] || '';
                    document.getElementById('newsAuthorLast').value = names.slice(1).join(' ') || '';
                    modalTitle.textContent = 'Edit Article';
                    new bootstrap.Modal(newsModal).show();
                })
                .catch(err => console.error('Edit load error', err));
        }

        function deleteNews(id) {
            if (!confirm('Are you sure you want to delete this article?')) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            const csrf = newsForm.querySelector('input[name="csrf_token"]').value;
            if (csrf) fd.append('csrf_token', csrf);
            fetch('../backend/routes/news_manager.php', {
                    method: 'POST',
                    body: fd
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status) fetchNews();
                    else alert('Error: ' + data.message);
                })
                .catch(err => {
                    console.error('Delete error', err);
                    alert('Failed to delete the article.');
                });
        }

        // Initial load
        fetchNews();
    });
</script>
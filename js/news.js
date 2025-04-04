document.addEventListener("DOMContentLoaded", function() {
  const newsList = document.getElementById('newsList');

  // Fetch and render news
  fetch('backend/routes/news_manager.php?action=fetch')
      .then(response => response.json())
      .then(data => {
          if (data.status) {
              renderNews(data.news);
          } else {
              showError('Failed to load news');
          }
      })
      .catch(err => {
          console.error('Fetch error:', err);
          showError('Failed to load news');
      });

  function renderNews(news) {
      const html = news.map(article => `
        <div class="news-card">
          <img src="${ article.image ? '../backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(article.image) : 'assets/default-news.jpg' }" class="card-img-top" alt="${article.title}">
          <div class="news-card-body">
            <span class="category-badge">${article.category}</span>
            <div class="news-meta">
              <i class="fas fa-clock me-2"></i>
              ${new Date(article.published_date).toLocaleDateString()}
              <i class="fas fa-user ms-3 me-2"></i>
              ${article.author}
            </div>
            <h3 class="h4 fw-bold mb-3">${article.title}</h3>
            <p class="text-muted mb-3">${article.excerpt}</p>
            <a href="news-detail.php?id=${article.news_id}" class="read-more" data-id="${article.news_id}" rel="noreferrer">
              Read More 
              <i class="fas fa-arrow-right"></i>
            </a>
          </div>
        </div>
      `).join('');
      newsList.innerHTML = html || '<p class="text-center text-muted">No news articles available</p>';
  }

  function showError(message) {
      newsList.innerHTML = `
        <div class="col-12 text-center text-danger">
          <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
          <p>${message}</p>
        </div>
      `;
  }
});

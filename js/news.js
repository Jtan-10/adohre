document.addEventListener("DOMContentLoaded", function() {
  const newsList = document.getElementById('newsList');
  const baseUrl = window.location.origin + '/capstone-php';
  console.log("Base URL:", baseUrl);

  // Fetch and render news
  fetch(baseUrl + '/backend/routes/news_manager.php?action=fetch')
      .then(response => {
          console.log("Fetch response status:", response.status);
          return response.json();
      })
      .then(data => {
          console.log("Fetched news data:", data);
          if (data.status) {
              renderNews(data.news);
          } else {
              console.error("Data status is false. Message:", data.message);
              showError('Failed to load news');
          }
      })
      .catch(err => {
          console.error('Fetch error:', err);
          showError('Failed to load news');
      });

  function renderNews(news) {
      console.log("Rendering news, total articles:", news.length);
      const html = news.map(article => {
          console.log("Article ID:", article.news_id, "Original image URL:", article.image);
          let imageUrl;
          if (article.image) {
              // Build the decrypted image URL
              imageUrl = baseUrl + '/backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(article.image);
              console.log("Generated decrypted image URL for article ID", article.news_id, ":", imageUrl);
          } else {
              imageUrl = 'assets/default-news.jpg';
              console.log("No image for article ID", article.news_id, "- using default image:", imageUrl);
          }
          return `
            <div class="news-card">
              <img src="${imageUrl}" class="card-img-top" alt="${article.title}">
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
          `;
      }).join('');
      console.log("Generated HTML for news articles:", html);
      newsList.innerHTML = html || '<p class="text-center text-muted">No news articles available</p>';
  }

  function showError(message) {
      console.error("Error:", message);
      newsList.innerHTML = `
        <div class="col-12 text-center text-danger">
          <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
          <p>${message}</p>
        </div>
      `;
  }
});

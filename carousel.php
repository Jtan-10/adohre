<?php
// Expect $conn and possibly $editMode from index.php
$slides = [
  [
    'image_url' => 'assets/pexels-pixabay-164572.jpg',
    'title' => 'Welcome to ADOHRE',
    'caption' => 'Discover ADOHRE: Your Best Chapter is Here!',
    'active' => true
  ],
  [
    'image_url' => 'assets/pexels-pixabay-269077.jpg',
    'title' => 'Empowering Retired Employees',
    'caption' => 'Join our community to grow together.',
    'active' => false
  ],
  [
    'image_url' => 'assets/pexels-sevenstormphotography-443383.jpg',
    'title' => 'Make a Difference',
    'caption' => 'Participate in our events and trainings.',
    'active' => false
  ]
];
if (isset($conn)) {
  $stmt = $conn->prepare("SELECT value FROM settings WHERE `key`='home_carousel_json' LIMIT 1");
  if ($stmt) {
    $stmt->execute();
    $stmt->bind_result($val);
    if ($stmt->fetch()) {
      $arr = json_decode($val, true);
      if (is_array($arr) && count($arr) > 0) {
        $slides = $arr;
      }
    }
    $stmt->close();
  }
}
// helper to display encrypted S3 image via proxy
function display_url($u)
{
  if (!$u) return '';
  if (strpos($u, '/s3proxy/') !== false) {
    return 'backend/routes/decrypt_image.php?image_url=' . urlencode($u);
  }
  return $u;
}
?>
<style>
  /* Set the carousel to fill the full viewport height */
  #carouselExampleIndicators .carousel-inner {
    height: 100vh;
  }

  /* Force images to fill the entire carousel area while ensuring the bottom is visible */
  #carouselExampleIndicators .carousel-item img {
    width: 100%;
    height: 100vh;
    /* Force image height to be full viewport height */
    object-fit: cover;
    /* Cover the container uniformly */
    object-position: bottom;
    /* Always show the bottom of the image */
  }

  /* Ensure each carousel item is positioned relatively */
  #carouselExampleIndicators .carousel-item {
    position: relative;
  }

  /* Always display captions with consistent styling */
  #carouselExampleIndicators .carousel-caption {
    z-index: 10;
    display: block !important;
    position: absolute;
    bottom: 10%;
    /* Adjust if needed */
    left: 15%;
    right: 15%;
    text-align: center;
    background: rgba(0, 0, 0, 0.5);
    padding: 10px 20px;
    border-radius: 5px;
  }

  /* Explicit caption text styling */
  #carouselExampleIndicators .carousel-caption h5,
  #carouselExampleIndicators .carousel-caption p {
    color: #fff;
    margin: 0;
  }

  /* Ensure carousel arrows are above captions */
  #carouselExampleIndicators .carousel-control-prev,
  #carouselExampleIndicators .carousel-control-next {
    z-index: 20;
  }
</style>

<div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
  <?php if (!empty($editMode)): ?>
    <div class="position-absolute" style="z-index:25; top:10px; right:10px; display:flex; gap:6px;">
      <button class="btn btn-light btn-sm" id="carouselAddBtn"><i class="fa fa-plus me-1"></i>Add slide</button>
      <button class="btn btn-light btn-sm" id="carouselRemoveBtn"><i class="fa fa-trash me-1"></i>Remove slide</button>
      <button class="btn btn-light btn-sm" id="carouselChangeImgBtn"><i class="fa fa-image me-1"></i>Change image</button>
      <input type="file" id="carouselImgFile" accept="image/*" class="d-none" />
    </div>
  <?php endif; ?>
  <div class="carousel-indicators">
    <?php foreach ($slides as $i => $s): ?>
      <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>"
        <?php if ($i === 0): ?>aria-current="true" <?php endif; ?> aria-label="Slide <?= $i + 1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <div class="carousel-inner">
    <?php foreach ($slides as $i => $s): $img = display_url($s['image_url'] ?? ''); ?>
      <div class="carousel-item <?= ($s['active'] ?? false) || ($i === 0) ? 'active' : '' ?>">
        <img src="<?= htmlspecialchars($img, ENT_QUOTES) ?>" class="d-block w-100" alt="Slide <?= $i + 1 ?>">
        <div class="carousel-caption">
          <h5 <?php if (!empty($editMode)): ?>contenteditable="true" class="edit-outline" data-slide-index="<?= $i ?>" data-field="title" <?php endif; ?>><?= htmlspecialchars($s['title'] ?? '', ENT_QUOTES) ?></h5>
          <p <?php if (!empty($editMode)): ?>contenteditable="true" class="edit-outline" data-slide-index="<?= $i ?>" data-field="caption" <?php endif; ?>><?= htmlspecialchars($s['caption'] ?? '', ENT_QUOTES) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Previous</span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
    <span class="carousel-control-next-icon" aria-hidden="true"></span>
    <span class="visually-hidden">Next</span>
  </button>
</div>

<?php if (!empty($editMode)): ?>
  <script>
    (function() {
      // Initial slides data from PHP
      let slides = <?php echo json_encode($slides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
      const carousel = document.getElementById('carouselExampleIndicators');
      const indicators = carousel.querySelector('.carousel-indicators');
      const inner = carousel.querySelector('.carousel-inner');
      let activeIndex = [...inner.children].findIndex(c => c.classList.contains('active'));
      if (activeIndex < 0) activeIndex = 0;

      const displayUrl = (u) => u && u.includes('/s3proxy/') ? ('backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(u)) : (u || '');
      const notifyDirty = () => {
        try {
          parent.postMessage({
            type: 'pageEditChange',
            page: 'home'
          }, '*');
        } catch (e) {}
      };

      function rebuild() {
        // rebuild indicators
        indicators.innerHTML = slides.map((s, i) => `<button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="${i}" class="${i===activeIndex?'active':''}" ${i===activeIndex?'aria-current="true"':''} aria-label="Slide ${i+1}"></button>`).join('');
        // rebuild items
        inner.innerHTML = slides.map((s, i) => `
        <div class="carousel-item ${i===activeIndex?'active':''}">
          <img src="${displayUrl(s.image_url||'')}" class="d-block w-100" alt="Slide ${i+1}">
          <div class="carousel-caption">
            <h5 contenteditable="true" class="edit-outline" data-slide-index="${i}" data-field="title">${(s.title||'')}</h5>
            <p contenteditable="true" class="edit-outline" data-slide-index="${i}" data-field="caption">${(s.caption||'')}</p>
          </div>
        </div>`).join('');
        bindEditors();
      }

      function bindEditors() {
        inner.querySelectorAll('[data-slide-index][data-field]').forEach(el => {
          el.addEventListener('input', (e) => {
            const idx = parseInt(el.getAttribute('data-slide-index'));
            const field = el.getAttribute('data-field');
            const val = el.innerText.trim();
            if (!isNaN(idx) && slides[idx]) {
              slides[idx][field] = val;
              notifyDirty();
            }
          });
        });
      }

      // Bootstrap slide event to track active index
      carousel.addEventListener('slid.bs.carousel', () => {
        const newIdx = [...inner.children].findIndex(c => c.classList.contains('active'));
        activeIndex = newIdx < 0 ? 0 : newIdx;
      });

      // Add / Remove
      document.getElementById('carouselAddBtn')?.addEventListener('click', () => {
        slides.push({
          image_url: '',
          title: 'New Slide',
          caption: ''
        });
        activeIndex = slides.length - 1;
        rebuild();
        notifyDirty();
      });
      document.getElementById('carouselRemoveBtn')?.addEventListener('click', () => {
        if (slides.length <= 1) return;
        slides.splice(activeIndex, 1);
        activeIndex = Math.max(0, Math.min(activeIndex, slides.length - 1));
        rebuild();
        notifyDirty();
      });

      // Change image
      const imgBtn = document.getElementById('carouselChangeImgBtn');
      const fileInput = document.getElementById('carouselImgFile');
      imgBtn?.addEventListener('click', () => fileInput && fileInput.click());
      fileInput?.addEventListener('change', async (e) => {
        const f = e.target.files && e.target.files[0];
        if (!f) return;
        const fd = new FormData();
        fd.append('page', 'home_carousel');
        fd.append('field', 'slide_' + activeIndex + '_image_url');
        fd.append('image', f);
        try {
          const res = await fetch('backend/routes/settings_api.php?action=upload_page_image', {
            method: 'POST',
            body: fd
          });
          const j = await res.json();
          if (j.status) {
            slides[activeIndex].image_url = j.url;
            // update current img
            const active = inner.children[activeIndex];
            if (active) {
              const img = active.querySelector('img');
              if (img) img.src = displayUrl(j.url);
            }
            notifyDirty();
            alert('Image uploaded');
          } else {
            alert(j.message || 'Upload failed');
          }
        } catch (err) {
          alert('Upload error');
        } finally {
          e.target.value = '';
        }
      });

      // Expose data provider for parent page save
      window.carouselEditor = {
        getData: () => JSON.stringify(slides)
      };

      // prevent anchor clicks during edit
      document.addEventListener('click', (e) => {
        const a = e.target.closest('a');
        if (a) {
          e.preventDefault();
          e.stopPropagation();
        }
      }, true);

      // initial binding
      bindEditors();
    })();
  </script>
<?php endif; ?>
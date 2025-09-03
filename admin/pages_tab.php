<?php if (!defined('APP_INIT')) {
    http_response_code(403);
    exit('Forbidden');
} ?>
<div class="container mt-3">
    <h3>Pages</h3>
    <ul class="nav nav-pills mb-3" id="pagesTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="home-tab" data-bs-toggle="pill" data-bs-target="#homePage" type="button" role="tab">Home</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="about-tab" data-bs-toggle="pill" data-bs-target="#aboutPage" type="button" role="tab">About</button>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="homePage" role="tabpanel">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Hero Image</label>
                    <div class="d-flex align-items-center gap-3">
                        <img id="homeHeroPreview" src="" alt="Home Hero" style="max-width:220px; max-height:120px; object-fit:cover; border:1px solid #ddd; border-radius:6px; background:#f8f9fa;">
                        <div>
                            <input type="file" id="homeHeroFile" accept="image/*" class="form-control form-control-sm" style="max-width:280px;">
                            <small class="text-muted">JPG/PNG/WebP. Upload replaces current image.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hero Title</label>
                    <input id="home_hero_title" class="form-control" placeholder="Homepage big title">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hero Subtitle</label>
                    <input id="home_hero_subtitle" class="form-control" placeholder="Short subtitle">
                </div>
                <div class="col-12">
                    <label class="form-label">About Section (HTML allowed)</label>
                    <textarea id="home_about_html" class="form-control" rows="6" placeholder="HTML supported"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Contact Address</label>
                    <input id="home_contact_address" class="form-control" placeholder="Address text">
                </div>
            </div>
            <div class="mt-4">
                <div class="form-title h5">Live Preview (Home)</div>
                <div id="homePreview" class="p-4 rounded" style="background:#fafafa; border:1px solid #eee;">
                    <div class="mb-3" style="position:relative; min-height:140px; display:flex; align-items:center; justify-content:center; color:#fff; text-align:center; border-radius:8px; overflow:hidden;">
                        <div id="homeHeroBG" style="position:absolute; inset:0; background:#333 center/cover no-repeat; opacity:0.9;"></div>
                        <div style="position:relative; z-index:2; padding:16px;">
                            <h4 id="homePrevTitle" class="m-0"></h4>
                            <div id="homePrevSub" class="small"></div>
                        </div>
                    </div>
                    <div>
                        <div class="fw-semibold mb-1">About Section</div>
                        <div id="homePrevAbout" class="small"></div>
                        <div class="mt-2 text-muted small">Address: <span id="homePrevAddr"></span></div>
                    </div>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" onclick="savePage('home')">Save</button>
                <button class="btn btn-outline-secondary" onclick="loadPage('home')">Reload</button>
                <a class="btn btn-outline-primary" href="../index.php" target="_blank">Open Home</a>
            </div>
        </div>
        <div class="tab-pane fade" id="aboutPage" role="tabpanel">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Hero Image</label>
                    <div class="d-flex align-items-center gap-3">
                        <img id="aboutHeroPreview" src="" alt="About Hero" style="max-width:220px; max-height:120px; object-fit:cover; border:1px solid #ddd; border-radius:6px; background:#f8f9fa;">
                        <div>
                            <input type="file" id="aboutHeroFile" accept="image/*" class="form-control form-control-sm" style="max-width:280px;">
                            <small class="text-muted">JPG/PNG/WebP. Upload replaces current image.</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hero Title</label>
                    <input id="about_hero_title" class="form-control" placeholder="About page title">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hero Subtitle</label>
                    <input id="about_hero_subtitle" class="form-control" placeholder="Subtitle">
                </div>
                <div class="col-12">
                    <label class="form-label">Purpose Text</label>
                    <textarea id="about_purpose_text" class="form-control" rows="4"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Mission Text</label>
                    <textarea id="about_mission_text" class="form-control" rows="4"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Vision Text</label>
                    <textarea id="about_vision_text" class="form-control" rows="4"></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Objectives (HTML list)</label>
                    <textarea id="about_objectives_html" class="form-control" rows="6" placeholder="<ul>...</ul>"></textarea>
                </div>
            </div>
            <div class="mt-4">
                <div class="form-title h5">Live Preview (About)</div>
                <div id="aboutPreview" class="p-4 rounded" style="background:#fafafa; border:1px solid #eee;">
                    <div class="mb-3" style="position:relative; min-height:140px; display:flex; align-items:center; justify-content:center; color:#fff; text-align:center; border-radius:8px; overflow:hidden;">
                        <div id="aboutHeroBG" style="position:absolute; inset:0; background:#333 center/cover no-repeat; opacity:0.9;"></div>
                        <div style="position:relative; z-index:2; padding:16px;">
                            <h4 id="aboutPrevTitle" class="m-0"></h4>
                            <div id="aboutPrevSub" class="small"></div>
                        </div>
                    </div>
                    <div class="small">
                        <div class="fw-semibold">Purpose</div>
                        <div id="aboutPrevPurpose" class="mb-2"></div>
                        <div class="fw-semibold">Mission</div>
                        <div id="aboutPrevMission" class="mb-2"></div>
                        <div class="fw-semibold">Vision</div>
                        <div id="aboutPrevVision" class="mb-2"></div>
                        <div class="fw-semibold">Objectives</div>
                        <div id="aboutPrevObjectives"></div>
                    </div>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" onclick="savePage('about')">Save</button>
                <button class="btn btn-outline-secondary" onclick="loadPage('about')">Reload</button>
                <a class="btn btn-outline-primary" href="../about.php" target="_blank">Open About</a>
            </div>
        </div>
    </div>

    <script>
        function imgDisplay(url) {
            if (!url) return '';
            return url.includes('/s3proxy/') ? ('../backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(url)) : url;
        }
        async function loadPage(page) {
            const res = await fetch('../backend/routes/settings_api.php?action=get_page_content&page=' + encodeURIComponent(page));
            const j = await res.json();
            if (!j.status) {
                alert(j.message || 'Failed');
                return;
            }
            const d = j.data || {};
            for (const k in d) {
                const el = document.getElementById(k);
                if (el) {
                    el.value = d[k] || '';
                }
            }
            // Set previews
            if (page === 'home') {
                if (d.home_hero_image_url) {
                    const u = imgDisplay(d.home_hero_image_url);
                    document.getElementById('homeHeroPreview').src = u;
                    document.getElementById('homeHeroBG').style.backgroundImage = `url('${u}')`;
                }
                document.getElementById('homePrevTitle').textContent = d.home_hero_title || '';
                document.getElementById('homePrevSub').textContent = d.home_hero_subtitle || '';
                document.getElementById('homePrevAbout').innerHTML = d.home_about_html || '';
                document.getElementById('homePrevAddr').textContent = d.home_contact_address || '';
            } else if (page === 'about') {
                if (d.about_hero_image_url) {
                    const u = imgDisplay(d.about_hero_image_url);
                    document.getElementById('aboutHeroPreview').src = u;
                    document.getElementById('aboutHeroBG').style.backgroundImage = `url('${u}')`;
                }
                document.getElementById('aboutPrevTitle').textContent = d.about_hero_title || '';
                document.getElementById('aboutPrevSub').textContent = d.about_hero_subtitle || '';
                document.getElementById('aboutPrevPurpose').textContent = d.about_purpose_text || '';
                document.getElementById('aboutPrevMission').textContent = d.about_mission_text || '';
                document.getElementById('aboutPrevVision').textContent = d.about_vision_text || '';
                document.getElementById('aboutPrevObjectives').innerHTML = d.about_objectives_html || '';
            }
        }
        async function savePage(page) {
            const data = {};
            document.querySelectorAll('#' + (page === 'home' ? 'homePage' : 'aboutPage') + ' input, #' + (page === 'home' ? 'homePage' : 'aboutPage') + ' textarea').forEach(el => {
                data[el.id] = el.value;
            });
            const fd = new FormData();
            fd.append('action', 'update_page_content');
            fd.append('page', page);
            fd.append('data', JSON.stringify(data));
            const res = await fetch('../backend/routes/settings_api.php?action=update_page_content', {
                method: 'POST',
                body: fd
            });
            const j = await res.json();
            if (j.status) {
                alert('Saved');
            } else {
                alert(j.message || 'Failed');
            }
        }

        function hookLivePreview() {
            const map = {
                home_hero_title: 'homePrevTitle',
                home_hero_subtitle: 'homePrevSub',
                home_about_html: 'homePrevAbout',
                home_contact_address: 'homePrevAddr',
                about_hero_title: 'aboutPrevTitle',
                about_hero_subtitle: 'aboutPrevSub',
                about_purpose_text: 'aboutPrevPurpose',
                about_mission_text: 'aboutPrevMission',
                about_vision_text: 'aboutPrevVision',
                about_objectives_html: 'aboutPrevObjectives'
            };
            document.querySelectorAll('#homePage input, #homePage textarea, #aboutPage input, #aboutPage textarea').forEach(el => {
                el.addEventListener('input', () => {
                    const targetId = map[el.id];
                    if (!targetId) return;
                    if (el.tagName === 'TEXTAREA' && el.id.endsWith('_html')) {
                        document.getElementById(targetId).innerHTML = el.value;
                    } else {
                        document.getElementById(targetId).textContent = el.value;
                    }
                });
            });
            // Image upload handlers
            document.getElementById('homeHeroFile').addEventListener('change', async (e) => {
                const f = e.target.files[0];
                if (!f) return;
                const fd = new FormData();
                fd.append('page', 'home');
                fd.append('field', 'hero_image_url');
                fd.append('image', f);
                const res = await fetch('../backend/routes/settings_api.php?action=upload_page_image', {
                    method: 'POST',
                    body: fd
                });
                const j = await res.json();
                if (j.status) {
                    const u = imgDisplay(j.url);
                    document.getElementById('homeHeroPreview').src = u;
                    document.getElementById('homeHeroBG').style.backgroundImage = `url('${u}')`;
                    // persist into the form state so Save includes it too
                    const hidden = document.getElementById('home_hero_image_url') || (function() {
                        const i = document.createElement('input');
                        i.type = 'hidden';
                        i.id = 'home_hero_image_url';
                        document.getElementById('homePage').appendChild(i);
                        return i;
                    })();
                    hidden.value = j.url;
                    alert('Image uploaded');
                } else {
                    alert(j.message || 'Upload failed');
                }
            });
            document.getElementById('aboutHeroFile').addEventListener('change', async (e) => {
                const f = e.target.files[0];
                if (!f) return;
                const fd = new FormData();
                fd.append('page', 'about');
                fd.append('field', 'hero_image_url');
                fd.append('image', f);
                const res = await fetch('../backend/routes/settings_api.php?action=upload_page_image', {
                    method: 'POST',
                    body: fd
                });
                const j = await res.json();
                if (j.status) {
                    const u = imgDisplay(j.url);
                    document.getElementById('aboutHeroPreview').src = u;
                    document.getElementById('aboutHeroBG').style.backgroundImage = `url('${u}')`;
                    const hidden = document.getElementById('about_hero_image_url') || (function() {
                        const i = document.createElement('input');
                        i.type = 'hidden';
                        i.id = 'about_hero_image_url';
                        document.getElementById('aboutPage').appendChild(i);
                        return i;
                    })();
                    hidden.value = j.url;
                    alert('Image uploaded');
                } else {
                    alert(j.message || 'Upload failed');
                }
            });
        }
        document.addEventListener('DOMContentLoaded', () => {
            loadPage('home');
            hookLivePreview();
        });
    </script>
</div>
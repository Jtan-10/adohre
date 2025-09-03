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
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" onclick="savePage('home')">Save</button>
                <button class="btn btn-outline-secondary" onclick="loadPage('home')">Reload</button>
            </div>
        </div>
        <div class="tab-pane fade" id="aboutPage" role="tabpanel">
            <div class="row g-3">
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
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" onclick="savePage('about')">Save</button>
                <button class="btn btn-outline-secondary" onclick="loadPage('about')">Reload</button>
            </div>
        </div>
    </div>

    <script>
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
        document.addEventListener('DOMContentLoaded', () => {
            loadPage('home');
        });
    </script>
</div>
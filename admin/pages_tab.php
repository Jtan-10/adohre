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
        <!-- Home Visual Editor -->
        <div class="tab-pane fade show active" id="homePage" role="tabpanel">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill text-bg-secondary" id="homeDirty" style="display:none;">Unsaved changes</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm" onclick="requestSave('home')"><i class="fa fa-floppy-disk me-1"></i>Save</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="reloadFrame('home')"><i class="fa fa-rotate me-1"></i>Reload</button>
                    <a class="btn btn-outline-primary btn-sm" href="../index.php" target="_blank">Open Home</a>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Home Sections</strong>
                    <button class="btn btn-success btn-sm" onclick="addSection('home')"><i class="bi bi-plus-lg"></i> Add Section</button>
                </div>
                <div class="card-body">
                    <div id="homeSections"></div>
                </div>
            </div>
            <div class="ratio ratio-16x9 border rounded overflow-hidden bg-light">
                <iframe id="homeFrame" src="../index.php?edit=1" title="Home Editor" style="width:100%; height:100%; border:0;"></iframe>
            </div>
        </div>

        <!-- About Visual Editor -->
        <div class="tab-pane fade" id="aboutPage" role="tabpanel">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill text-bg-secondary" id="aboutDirty" style="display:none;">Unsaved changes</span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm" onclick="requestSave('about')"><i class="fa fa-floppy-disk me-1"></i>Save</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="reloadFrame('about')"><i class="fa fa-rotate me-1"></i>Reload</button>
                    <a class="btn btn-outline-primary btn-sm" href="../about.php" target="_blank">Open About</a>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>About Sections</strong>
                    <button class="btn btn-success btn-sm" onclick="addSection('about')"><i class="bi bi-plus-lg"></i> Add Section</button>
                </div>
                <div class="card-body">
                    <div id="aboutSections"></div>
                </div>
            </div>
            <div class="ratio ratio-16x9 border rounded overflow-hidden bg-light">
                <iframe id="aboutFrame" src="../about.php?edit=1" title="About Editor" style="width:100%; height:100%; border:0;"></iframe>
            </div>
        </div>
    </div>

    <script>
        // Track dirty state per page when iframe reports changes
        const state = {
            home: {
                dirty: false,
                sections: []
            },
            about: {
                dirty: false,
                sections: []
            }
        };

        function setDirty(page, dirty) {
            state[page].dirty = !!dirty;
            const badge = document.getElementById(page + 'Dirty');
            if (badge) badge.style.display = dirty ? '' : 'none';
        }

        function reloadFrame(page) {
            const frame = document.getElementById(page + 'Frame');
            if (frame) {
                frame.src = frame.src; // simple reload
                setDirty(page, false);
            }
        }

        function requestSave(page) {
            const frame = document.getElementById(page + 'Frame');
            if (frame && frame.contentWindow) {
                frame.contentWindow.postMessage({
                    type: 'pageSave'
                }, '*');
            }
            // Also persist sections JSON
            saveSections(page).catch(() => alert('Failed to save sections'));
        }

        window.addEventListener('message', (event) => {
            const data = event.data || {};
            if (!data || !data.type) return;
            switch (data.type) {
                case 'pageEditChange':
                    if (data.page === 'home' || data.page === 'about') {
                        setDirty(data.page, true);
                    }
                    break;
                case 'pageEditSaved':
                    if (data.page === 'home' || data.page === 'about') {
                        setDirty(data.page, false);
                        alert('Saved');
                    }
                    break;
                case 'pageEditError':
                    alert(data.message || 'Save failed');
                    break;
            }
        });

        function sectionCardTpl(page, idx, s) {
            const img = s.image_url ? ('../backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(s.image_url)) : '../assets/default-image.jpg';
            return `
            <div class="card mb-2" data-index="${idx}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Section #${idx + 1}</strong>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary me-1" data-move="up">↑</button>
                            <button class="btn btn-sm btn-outline-secondary me-1" data-move="down">↓</button>
                            <button class="btn btn-sm btn-outline-danger" data-remove>Remove</button>
                        </div>
                    </div>
                    <div class="row g-3 align-items-start">
                        <div class="col-md-4">
                            <img src="${img}" class="img-fluid rounded border mb-2" alt="Section image">
                            <input type="file" accept="image/*" data-file>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-2">
                                <label class="form-label">Title</label>
                                <input type="text" class="form-control" data-field="title" value="${(s.title||'').replace(/"/g,'&quot;')}">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Text</label>
                                <textarea rows="4" class="form-control" data-field="text">${(s.text||'').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
        }

        function renderSections(page) {
            const container = document.getElementById(page + 'Sections');
            if (!container) return;
            const arr = state[page].sections || [];
            container.innerHTML = arr.map((s, i) => sectionCardTpl(page, i, s)).join('');
            // Wire controls
            container.querySelectorAll('[data-remove]').forEach(btn => btn.addEventListener('click', (e) => {
                const card = e.target.closest('.card');
                const idx = parseInt(card.getAttribute('data-index'));
                state[page].sections.splice(idx, 1);
                setDirty(page, true);
                renderSections(page);
            }));
            container.querySelectorAll('[data-move]').forEach(btn => btn.addEventListener('click', (e) => {
                const dir = e.target.getAttribute('data-move');
                const card = e.target.closest('.card');
                const idx = parseInt(card.getAttribute('data-index'));
                const arr = state[page].sections;
                const swap = (i, j) => {
                    const t = arr[i];
                    arr[i] = arr[j];
                    arr[j] = t;
                };
                if (dir === 'up' && idx > 0) swap(idx, idx - 1);
                if (dir === 'down' && idx < arr.length - 1) swap(idx, idx + 1);
                setDirty(page, true);
                renderSections(page);
            }));
            container.querySelectorAll('[data-field]').forEach(inp => inp.addEventListener('input', (e) => {
                const card = e.target.closest('.card');
                const idx = parseInt(card.getAttribute('data-index'));
                const field = e.target.getAttribute('data-field');
                state[page].sections[idx][field] = e.target.value;
                setDirty(page, true);
            }));
            container.querySelectorAll('input[type=file][data-file]').forEach(file => file.addEventListener('change', async (e) => {
                const f = e.target.files && e.target.files[0];
                if (!f) return;
                const card = e.target.closest('.card');
                const idx = parseInt(card.getAttribute('data-index'));
                try {
                    const fd = new FormData();
                    fd.append('page', page);
                    fd.append('image', f);
                    const res = await fetch('../backend/routes/settings_api.php?action=upload_page_asset', {
                        method: 'POST',
                        body: fd
                    });
                    const j = await res.json();
                    if (j.status) {
                        state[page].sections[idx].image_url = j.url;
                        setDirty(page, true);
                        renderSections(page);
                    } else {
                        alert(j.message || 'Upload failed');
                    }
                } catch (err) {
                    alert('Upload error');
                }
            }));
        }

        function addSection(page) {
            state[page].sections.push({
                title: '',
                text: '',
                image_url: ''
            });
            setDirty(page, true);
            renderSections(page);
        }

        async function loadSections(page) {
            try {
                const res = await fetch('../backend/routes/settings_api.php?action=get_page_content&page=' + encodeURIComponent(page));
                const j = await res.json();
                if (!j.status) return;
                const key = page + '_sections_json';
                const raw = j.data && j.data[key];
                state[page].sections = Array.isArray(raw) ? raw : (raw ? JSON.parse(raw) : []);
                renderSections(page);
            } catch (e) {
                // ignore
            }
        }

        async function saveSections(page) {
            const key = page + '_sections_json';
            const payload = {};
            payload[key] = JSON.stringify(state[page].sections || []);
            const fd = new FormData();
            fd.append('page', page);
            fd.append('data', JSON.stringify(payload));
            const res = await fetch('../backend/routes/settings_api.php?action=update_page_content', {
                method: 'POST',
                body: fd
            });
            const j = await res.json();
            if (!j.status) throw new Error('Save failed');
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadSections('home');
            loadSections('about');
        });
    </script>
</div>
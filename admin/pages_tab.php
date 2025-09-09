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
            <div class="mb-3 d-flex justify-content-end">
                <button class="btn btn-outline-success btn-sm" id="btnOpenLeadershipModal"><i class="bi bi-people-fill me-1"></i>Edit Leadership & Committees</button>
            </div>
            <div class="ratio ratio-16x9 border rounded overflow-hidden bg-light">
                <iframe id="aboutFrame" src="../about.php?edit=1" title="About Editor" style="width:100%; height:100%; border:0;"></iframe>
            </div>
        </div>
    </div>

    <!-- Leadership & Committees Modal -->
    <div class="modal fade" id="leadershipModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-people-fill me-1"></i>Leadership & Committees</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="leadTabs" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#leadTab" type="button" role="tab">Leadership Groups</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#commTab" type="button" role="tab">Committees</button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="leadTab" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Leadership Groups</strong>
                                <div>
                                    <button class="btn btn-sm btn-success" id="btnAddLeadershipGroupModal"><i class="bi bi-plus-lg"></i> Add Group</button>
                                </div>
                            </div>
                            <div id="aboutLeadership" class="mb-4"></div>
                        </div>
                        <div class="tab-pane fade" id="commTab" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Committees</strong>
                                <div>
                                    <button class="btn btn-sm btn-success" id="btnAddCommitteeGroupModal"><i class="bi bi-plus-lg"></i> Add Committee</button>
                                </div>
                            </div>
                            <div id="aboutCommittees"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="me-auto small text-muted" id="leadershipDirtyHint" style="display:none;">Unsaved changes</div>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnSaveLeadershipModal"><i class="bi bi-save me-1"></i>Save</button>
                </div>
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
                sections: [],
                leadership: [], // [{title, period, members:[{name,role}]}]
                committees: [] // same structure
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

        // Leadership group card template
        function leadershipGroupTpl(idx, g) {
            const members = Array.isArray(g.members) ? g.members : [];
            const esc = (v) => (v || '').replace(/"/g, '&quot;');
            return `
                        <div class="card mb-2 leadership-card" data-index="${idx}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Group #${idx+1}</strong>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary me-1" data-lg-move="up">↑</button>
                                        <button class="btn btn-sm btn-outline-secondary me-1" data-lg-move="down">↓</button>
                                        <button class="btn btn-sm btn-outline-secondary me-1" data-lg-dup>Duplicate</button>
                                        <button class="btn btn-sm btn-outline-danger" data-lg-remove>Remove</button>
                                    </div>
                                </div>
                                <div class="row g-3 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label mb-1">Title</label>
                                        <input type="text" class="form-control form-control-sm" data-lg-field="title" value="${esc(g.title)}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label mb-1">Period (optional)</label>
                                        <input type="text" class="form-control form-control-sm" data-lg-field="period" value="${esc(g.period)}" placeholder="e.g. 2025–2026">
                                    </div>
                                </div>
                                <div class="table-responsive mb-2">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light"><tr><th style="width:40%">Name</th><th style="width:40%">Role</th><th style="width:20%"></th></tr></thead>
                                        <tbody>
                                            ${members.map((m,i)=>`<tr data-m-index="${i}">
                                                <td><input type="text" class="form-control form-control-sm" data-lg-m-field="name" value="${esc(m.name)}"></td>
                                                <td><input type="text" class="form-control form-control-sm" data-lg-m-field="role" value="${esc(m.role)}"></td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-secondary" data-lg-m-dup title="Duplicate">⧉</button>
                                                        <button class="btn btn-outline-danger" data-lg-m-remove title="Remove">✕</button>
                                                    </div>
                                                </td>
                                            </tr>`).join('')}
                                        </tbody>
                                    </table>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" data-lg-add-member><i class="bi bi-plus"></i> Add Member</button>
                            </div>
                        </div>`;
        }

        function renderLeadership() {
            const container = document.getElementById('aboutLeadership');
            if (!container) return;
            const arr = state.about.leadership || [];
            container.innerHTML = arr.map((g, i) => leadershipGroupTpl(i, g)).join('');
            // Wire group actions
            container.querySelectorAll('.leadership-card').forEach(card => {
                const idx = parseInt(card.getAttribute('data-index'));
                card.querySelectorAll('[data-lg-field]').forEach(inp => inp.addEventListener('input', e => {
                    const field = e.target.getAttribute('data-lg-field');
                    state.about.leadership[idx][field] = e.target.value;
                    setDirty('about', true);
                }));
                card.querySelectorAll('[data-lg-m-field]').forEach(inp => inp.addEventListener('input', e => {
                    const tr = e.target.closest('tr');
                    const mi = parseInt(tr.getAttribute('data-m-index'));
                    const field = e.target.getAttribute('data-lg-m-field');
                    state.about.leadership[idx].members[mi][field] = e.target.value;
                    setDirty('about', true);
                }));
                const up = card.querySelector('[data-lg-move="up"]');
                const down = card.querySelector('[data-lg-move="down"]');
                up && up.addEventListener('click', () => {
                    if (idx > 0) {
                        const arr = state.about.leadership;
                        [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
                        renderLeadership();
                        setDirty('about', true);
                    }
                });
                down && down.addEventListener('click', () => {
                    const arr = state.about.leadership;
                    if (idx < arr.length - 1) {
                        [arr[idx + 1], arr[idx]] = [arr[idx], arr[idx + 1]];
                        renderLeadership();
                        setDirty('about', true);
                    }
                });
                const dup = card.querySelector('[data-lg-dup]');
                dup && dup.addEventListener('click', () => {
                    const arr = state.about.leadership;
                    const clone = JSON.parse(JSON.stringify(arr[idx]));
                    arr.splice(idx + 1, 0, clone);
                    renderLeadership();
                    setDirty('about', true);
                });
                const rem = card.querySelector('[data-lg-remove]');
                rem && rem.addEventListener('click', () => {
                    if (confirm('Remove this group?')) {
                        state.about.leadership.splice(idx, 1);
                        renderLeadership();
                        setDirty('about', true);
                    }
                });
                const addMemberBtn = card.querySelector('[data-lg-add-member]');
                addMemberBtn && addMemberBtn.addEventListener('click', () => {
                    state.about.leadership[idx].members.push({
                        name: 'New Member',
                        role: ''
                    });
                    renderLeadership();
                    setDirty('about', true);
                });
                card.querySelectorAll('[data-lg-m-dup]').forEach(b => b.addEventListener('click', e => {
                    const tr = e.target.closest('tr');
                    const mi = parseInt(tr.getAttribute('data-m-index'));
                    const mems = state.about.leadership[idx].members;
                    const clone = {
                        ...mems[mi]
                    };
                    mems.splice(mi + 1, 0, clone);
                    renderLeadership();
                    setDirty('about', true);
                }));
                card.querySelectorAll('[data-lg-m-remove]').forEach(b => b.addEventListener('click', e => {
                    const tr = e.target.closest('tr');
                    const mi = parseInt(tr.getAttribute('data-m-index'));
                    const mems = state.about.leadership[idx].members;
                    mems.splice(mi, 1);
                    renderLeadership();
                    setDirty('about', true);
                }));
            });
        }

        function addLeadershipGroup() {
            state.about.leadership.push({
                title: '',
                period: '',
                members: []
            });
            dirtyModal(true);
            renderLeadership();
        }

        // Committees rendering
        function committeeGroupTpl(idx, g) {
            const members = Array.isArray(g.members) ? g.members : [];
            const esc = v => (v || '').replace(/"/g, '&quot;');
            return `<div class="card mb-2 committee-card" data-index="${idx}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Committee #${idx+1}</strong>
                                    <div>
                                        <button class="btn btn-sm btn-outline-secondary me-1" data-cm-move="up">↑</button>
                                        <button class="btn btn-sm btn-outline-secondary me-1" data-cm-move="down">↓</button>
                                        <button class="btn btn-sm btn-outline-secondary me-1" data-cm-dup>Duplicate</button>
                                        <button class="btn btn-sm btn-outline-danger" data-cm-remove>Remove</button>
                                    </div>
                                </div>
                                <div class="row g-3 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label mb-1">Title</label>
                                        <input type="text" class="form-control form-control-sm" data-cm-field="title" value="${esc(g.title)}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label mb-1">Period (optional)</label>
                                        <input type="text" class="form-control form-control-sm" data-cm-field="period" value="${esc(g.period)}" placeholder="e.g. 2025–2026">
                                    </div>
                                </div>
                                <div class="table-responsive mb-2">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light"><tr><th style="width:40%">Name</th><th style="width:40%">Role</th><th style="width:20%"></th></tr></thead>
                                        <tbody>
                                            ${members.map((m,i)=>`<tr data-m-index="${i}">
                                                <td><input type="text" class="form-control form-control-sm" data-cm-m-field="name" value="${esc(m.name)}"></td>
                                                <td><input type="text" class="form-control form-control-sm" data-cm-m-field="role" value="${esc(m.role)}"></td>
                                                <td class="text-end">
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-secondary" data-cm-m-dup title="Duplicate">⧉</button>
                                                        <button class="btn btn-outline-danger" data-cm-m-remove title="Remove">✕</button>
                                                    </div>
                                                </td>
                                            </tr>`).join('')}
                                        </tbody>
                                    </table>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" data-cm-add-member><i class="bi bi-plus"></i> Add Member</button>
                            </div>
                        </div>`;
        }

        function renderCommittees() {
            const container = document.getElementById('aboutCommittees');
            if (!container) return;
            const arr = state.about.committees || [];
            container.innerHTML = arr.map((g, i) => committeeGroupTpl(i, g)).join('');
            container.querySelectorAll('.committee-card').forEach(card => {
                const idx = parseInt(card.getAttribute('data-index'));
                // field edits
                card.querySelectorAll('[data-cm-field]').forEach(inp => inp.addEventListener('input', e => {
                    const f = e.target.getAttribute('data-cm-field');
                    state.about.committees[idx][f] = e.target.value;
                    dirtyModal(true);
                }));
                card.querySelectorAll('[data-cm-m-field]').forEach(inp => inp.addEventListener('input', e => {
                    const tr = e.target.closest('tr');
                    const mi = parseInt(tr.getAttribute('data-m-index'));
                    const f = e.target.getAttribute('data-cm-m-field');
                    state.about.committees[idx].members[mi][f] = e.target.value;
                    dirtyModal(true);
                }));
                const up = card.querySelector('[data-cm-move="up"]');
                const down = card.querySelector('[data-cm-move="down"]');
                up && up.addEventListener('click', () => {
                    if (idx > 0) {
                        const arr = state.about.committees;
                        [arr[idx - 1], arr[idx]] = [arr[idx], arr[idx - 1]];
                        renderCommittees();
                        dirtyModal(true);
                    }
                });
                down && down.addEventListener('click', () => {
                    const arr = state.about.committees;
                    if (idx < arr.length - 1) {
                        [arr[idx + 1], arr[idx]] = [arr[idx], arr[idx + 1]];
                        renderCommittees();
                        dirtyModal(true);
                    }
                });
                const dup = card.querySelector('[data-cm-dup]');
                dup && dup.addEventListener('click', () => {
                    const arr = state.about.committees;
                    const clone = JSON.parse(JSON.stringify(arr[idx]));
                    arr.splice(idx + 1, 0, clone);
                    renderCommittees();
                    dirtyModal(true);
                });
                const rem = card.querySelector('[data-cm-remove]');
                rem && rem.addEventListener('click', () => {
                    if (confirm('Remove this committee?')) {
                        state.about.committees.splice(idx, 1);
                        renderCommittees();
                        dirtyModal(true);
                    }
                });
                const addMemberBtn = card.querySelector('[data-cm-add-member]');
                addMemberBtn && addMemberBtn.addEventListener('click', () => {
                    state.about.committees[idx].members.push({
                        name: 'New Member',
                        role: ''
                    });
                    renderCommittees();
                    dirtyModal(true);
                });
                card.querySelectorAll('[data-cm-m-dup]').forEach(b => b.addEventListener('click', e => {
                    const tr = e.target.closest('tr');
                    const mi = parseInt(tr.getAttribute('data-m-index'));
                    const mems = state.about.committees[idx].members;
                    const clone = {
                        ...mems[mi]
                    };
                    mems.splice(mi + 1, 0, clone);
                    renderCommittees();
                    dirtyModal(true);
                }));
                card.querySelectorAll('[data-cm-m-remove]').forEach(b => b.addEventListener('click', e => {
                    const tr = e.target.closest('tr');
                    const mi = parseInt(tr.getAttribute('data-m-index'));
                    const mems = state.about.committees[idx].members;
                    mems.splice(mi, 1);
                    renderCommittees();
                    dirtyModal(true);
                }));
            });
        }

        function addCommitteeGroup() {
            state.about.committees.push({
                title: '',
                period: '',
                members: []
            });
            dirtyModal(true);
            renderCommittees();
        }

        function dirtyModal(d) {
            const hint = document.getElementById('leadershipDirtyHint');
            if (hint) hint.style.display = d ? '' : 'none';
            setDirty('about', d || state.about.dirty);
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
                if (page === 'about') {
                    const lraw = j.data && j.data['about_leadership_json'];
                    state.about.leadership = Array.isArray(lraw) ? lraw : (lraw ? JSON.parse(lraw) : []);
                    const craw = j.data && j.data['about_committees_json'];
                    state.about.committees = Array.isArray(craw) ? craw : (craw ? JSON.parse(craw) : []);
                }
            } catch (e) {
                // ignore
            }
        }

        async function saveSections(page) {
            const key = page + '_sections_json';
            const payload = {};
            payload[key] = JSON.stringify(state[page].sections || []);
            if (page === 'about') {
                payload['about_leadership_json'] = JSON.stringify(state.about.leadership || []);
                payload['about_committees_json'] = JSON.stringify(state.about.committees || []);
            }
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
            const modalEl = document.getElementById('leadershipModal');
            const openBtn = document.getElementById('btnOpenLeadershipModal');
            let modalInstance = null;
            if (modalEl) {
                modalInstance = new bootstrap.Modal(modalEl);
            }
            openBtn && openBtn.addEventListener('click', () => {
                renderLeadership();
                renderCommittees();
                dirtyModal(false);
                modalInstance && modalInstance.show();
            });
            document.getElementById('btnAddLeadershipGroupModal')?.addEventListener('click', addLeadershipGroup);
            document.getElementById('btnAddCommitteeGroupModal')?.addEventListener('click', addCommitteeGroup);
            document.getElementById('btnSaveLeadershipModal')?.addEventListener('click', async () => {
                try {
                    const fd = new FormData();
                    fd.append('page', 'about');
                    const payload = {
                        about_leadership_json: JSON.stringify(state.about.leadership || []),
                        about_committees_json: JSON.stringify(state.about.committees || [])
                    };
                    fd.append('data', JSON.stringify(payload));
                    const res = await fetch('../backend/routes/settings_api.php?action=update_page_content', {
                        method: 'POST',
                        body: fd
                    });
                    const j = await res.json();
                    if (!j.status) {
                        alert(j.message || 'Save failed');
                        return;
                    }
                    dirtyModal(false);
                    alert('Saved');
                } catch (e) {
                    alert('Save error');
                }
            });
        });
    </script>
</div>
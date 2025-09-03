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
            <div class="ratio ratio-16x9 border rounded overflow-hidden bg-light">
                <iframe id="aboutFrame" src="../about.php?edit=1" title="About Editor" style="width:100%; height:100%; border:0;"></iframe>
            </div>
        </div>
    </div>

    <script>
        // Track dirty state per page when iframe reports changes
        const state = {
            home: {
                dirty: false
            },
            about: {
                dirty: false
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

        document.addEventListener('DOMContentLoaded', () => {
            // nothing extra for now
        });
    </script>
</div>
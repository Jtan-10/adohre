<div class="form-section" id="manageEventsSection">
    <div class="d-flex justify-content-between align-items-center">
        <h3 class="m-0">Manage Events</h3>
        <button type="button" id="openAddEventBtn" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#eventModal" data-mode="create">
            <i class="bi bi-plus-lg"></i> Add Event
        </button>
    </div>
    <hr>

    <!-- Tab Navigation for Events List -->
    <ul class="nav nav-tabs" id="eventsTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming"
                type="button" role="tab">
                Upcoming Events
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab">
                Past Events
            </button>
        </li>
    </ul>
    <div class="tab-content mt-3">
        <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
            <div id="currentEventsList"></div>
        </div>
        <div class="tab-pane fade" id="past" role="tabpanel">
            <div id="pastEventsList"></div>
        </div>
    </div>
</div>

<!-- Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalLabel">Add Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="eventForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="eventTitle" class="form-label">Event Title</label>
                        <input type="text" id="eventTitle" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="eventDescription" class="form-label">Event Description</label>
                        <textarea id="eventDescription" name="description" class="form-control" required></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="eventDate" class="form-label">Event Month</label>
                            <input type="month" id="eventDate" name="date" class="form-control" required>
                            <div class="form-text">Stores as the first day of the month; shown as Month and Year only.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="eventLocation" class="form-label">Event Location</label>
                            <input type="text" id="eventLocation" name="location" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label for="eventFee" class="form-label">Event Fee</label>
                            <input type="number" id="eventFee" name="fee" class="form-control" placeholder="Enter event fee (0 for free)" step="0.01">
                        </div>
                        <div class="col-md-6">
                            <label for="eventImages" class="form-label">Event Images</label>
                            <input type="file" id="eventImages" name="images[]" class="form-control" accept="image/*" multiple>
                            <div class="form-text">Select multiple images to create a stacked gallery.</div>
                            <div id="eventImagesChips" class="mt-2"></div>
                        </div>
                    </div>
                    <input type="hidden" id="eventId" name="id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveEventBtn" class="btn btn-success">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Updated inline script with matching nonce -->
<script nonce="<?= $cspNonce ?>">
    document.addEventListener('DOMContentLoaded', function() {
        // Image state for edit mode
        let existingImages = [];
        // Fetch and display existing events
        fetchContent();

        const eventModal = document.getElementById('eventModal');
        const modalTitle = document.getElementById('eventModalLabel');
        const saveEventBtn = document.getElementById('saveEventBtn');
        const eventForm = document.getElementById('eventForm');

        // When modal opens in create mode, reset form
        eventModal.addEventListener('show.bs.modal', (e) => {
            const trigger = e.relatedTarget;
            if (trigger && trigger.getAttribute('data-mode') === 'create') {
                modalTitle.textContent = 'Add Event';
                eventForm.reset();
                document.getElementById('eventId').value = '';
                existingImages = [];
                renderImageChips();
            }
        });

        // Save (Add/Update)
        saveEventBtn.addEventListener('click', () => {
            const formData = new FormData(eventForm);
            // Normalize month-only input to first day of month (YYYY-MM-01)
            const monthVal = formData.get('date');
            if (monthVal && /^\d{4}-\d{2}$/.test(monthVal)) {
                formData.set('date', monthVal + '-01');
            }
            const id = document.getElementById('eventId').value;
            const action = id ? 'update_event' : 'add_event';
            formData.append('action', action);
            // include csrf if present
            const csrf = eventForm.querySelector('input[name="csrf_token"]').value;
            if (csrf && !formData.get('csrf_token')) formData.append('csrf_token', csrf);
            // Include list of images to keep (for update)
            formData.append('keep_images', JSON.stringify(existingImages));
            manageContent(formData, id ? 'Event updated successfully.' : 'Event added successfully.', () => {
                bootstrap.Modal.getInstance(eventModal)?.hide();
                eventForm.reset();
                document.getElementById('eventId').value = '';
                existingImages = [];
                renderImageChips();
            });
        });

        // Fetch and display events
        function fetchContent() {
            fetch('../backend/routes/content_manager.php?action=fetch')
                .then((response) => response.json())
                .then((data) => {
                    if (data.status) {
                        // Separate events into current/upcoming and past based on date
                        const now = new Date();
                        const currentEvents = data.events.filter(event => new Date(event.date) >= now);
                        const pastEvents = data.events.filter(event => new Date(event.date) < now);

                        // Map current events
                        const currentHtml = currentEvents.map(event => `
                        <div class="card mb-3">
                            <div class="row g-0">
                                <div class="col-md-4 p-2">
                                    ${renderEventCollage(event)}
                                </div>
                                <div class="col-md-8">
                                    <div class="card-body">
                                        <h5 class="card-title">${event.title}</h5>
                                        <p class="card-text">${event.description}</p>
                                        <p><strong>Date:</strong> ${new Date(event.date).toLocaleString('en-US', { month: 'long', year: 'numeric' })}</p>
                                        <p><strong>Location:</strong> ${event.location}</p>
                                        <p><strong>Fee:</strong> ${event.fee && parseFloat(event.fee) > 0 ? '₱' + event.fee : 'Free'}</p>
                                        <div>
                                            <button class="btn btn-primary btn-sm edit-event" data-id="${event.event_id}">Edit</button>
                                            <button class="btn btn-danger btn-sm delete-event" data-id="${event.event_id}">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('');

                        // Map past events
                        const pastHtml = pastEvents.map(event => `
                        <div class="card mb-3">
                            <div class="row g-0">
                                <div class="col-md-4 p-2">
                                    ${renderEventCollage(event)}
                                </div>
                                <div class="col-md-8">
                                    <div class="card-body">
                                        <h5 class="card-title">${event.title}</h5>
                                        <p class="card-text">${event.description}</p>
                                        <p><strong>Date:</strong> ${new Date(event.date).toLocaleString('en-US', { month: 'long', year: 'numeric' })}</p>
                                        <p><strong>Location:</strong> ${event.location}</p>
                                        <p><strong>Fee:</strong> ${event.fee && parseFloat(event.fee) > 0 ? '₱' + event.fee : 'Free'}</p>
                                        <div>
                                            <button class="btn btn-primary btn-sm edit-event" data-id="${event.event_id}">Edit</button>
                                            <button class="btn btn-danger btn-sm delete-event" data-id="${event.event_id}">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('');

                        document.getElementById('currentEventsList').innerHTML = currentHtml ||
                            '<p class="text-center text-muted">No upcoming events</p>';
                        document.getElementById('pastEventsList').innerHTML = pastHtml ||
                            '<p class="text-center text-muted">No past events</p>';

                        // Attach Edit and Delete Event Handlers for both sections
                        document.querySelectorAll('.edit-event').forEach((button) =>
                            button.addEventListener('click', function() {
                                editEvent(this.getAttribute('data-id'));
                            })
                        );
                        document.querySelectorAll('.delete-event').forEach((button) =>
                            button.addEventListener('click', function() {
                                deleteEvent(this.getAttribute('data-id'));
                            })
                        );
                    }
                })
                .catch((err) => console.error(err));
        }

        // Edit Event
        function editEvent(id) {
            fetch(`../backend/routes/content_manager.php?action=get_event&id=${id}`)
                .then((response) => response.json())
                .then((data) => {
                    if (data.status) {
                        const event = data.event;
                        document.getElementById('eventId').value = event.event_id;
                        document.getElementById('eventTitle').value = event.title;
                        document.getElementById('eventDescription').value = event.description;
                        // If stored as full date, set the month input to yyyy-MM
                        try {
                            const d = new Date(event.date);
                            const mm = String(d.getMonth() + 1).padStart(2, '0');
                            const yyyy = d.getFullYear();
                            document.getElementById('eventDate').value = `${yyyy}-${mm}`;
                        } catch (e) {
                            document.getElementById('eventDate').value = '';
                        }
                        document.getElementById('eventLocation').value = event.location;
                        document.getElementById('eventFee').value = event.fee || '';
                        // Init existing images from event (images_json preferred)
                        try {
                            if (event.images_json) {
                                const arr = JSON.parse(event.images_json);
                                existingImages = Array.isArray(arr) ? arr : [];
                            } else if (Array.isArray(event.images)) {
                                existingImages = event.images.slice(0);
                            } else if (event.image) {
                                existingImages = [event.image];
                            } else {
                                existingImages = [];
                            }
                        } catch (_) {
                            existingImages = [];
                        }
                        renderImageChips();

                        // Open modal in edit mode
                        modalTitle.textContent = 'Edit Event';
                        new bootstrap.Modal(eventModal).show();
                    }
                })
                .catch((err) => console.error(err));
        }

        // Delete Event
        function deleteEvent(id) {
            if (confirm('Are you sure you want to delete this event?')) {
                const formData = new FormData();
                formData.append('action', 'delete_event');
                formData.append('id', id);

                manageContent(formData, 'Event deleted successfully.');
            }
        }

        // Manage Content (Add/Update/Delete)
        function manageContent(formData, successMessage, onSuccess) {
            fetch('../backend/routes/content_manager.php', {
                    method: 'POST',
                    body: formData,
                })
                .then((response) => response.json())
                .then((data) => {
                    if (data.status) {
                        alert(successMessage);
                        fetchContent();
                        if (typeof onSuccess === 'function') onSuccess();
                    } else {
                        alert(`Error: ${data.message}`);
                    }
                })
                .catch((err) => alert('Failed to connect to the server. Please try again.'));
        }
        // Collage + lightbox styles (no black borders)
        const style = document.createElement('style');
        style.textContent = `
                        .evt-collage{position:relative;width:100%;height:180px;display:grid;gap:6px}
                        .evt-collage.one{grid-template-columns:1fr;grid-template-rows:1fr}
                        .evt-collage.two{grid-template-columns:1fr 1fr;grid-template-rows:1fr}
                        .evt-collage.three{grid-template-columns:2fr 1fr;grid-template-rows:1fr 1fr}
                        .evt-collage.three .cell:nth-child(1){grid-row:1 / span 2;grid-column:1}
                        .evt-collage.fourplus{grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr}
                        .evt-collage .cell{position:relative;width:100%;height:100%;background-size:cover;background-position:center;border-radius:8px;overflow:hidden;border:0;box-shadow:none}
                        .evt-collage .more{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.35);color:#fff;font-weight:700;font-size:1.1rem}
                        .evt-lightbox .modal-dialog{max-width:100%}
                        .evt-lightbox .modal-content{background:#000;border:0}
                        .evt-lightbox .modal-header{border:0}
                        .evt-lightbox .modal-body{position:relative;padding:0}
                        .evt-lightbox .main-wrap{position:relative;display:flex;align-items:center;justify-content:center;min-height:60vh}
                        .evt-lightbox img#evtGalleryImg{max-height:80vh;width:auto;height:auto;object-fit:contain;display:block;margin:0 auto;border:0;box-shadow:none}
                        .evt-nav{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:50%;border:0;background:rgba(255,255,255,.9);color:#111;display:flex;align-items:center;justify-content:center;font-size:20px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.35)}
                        .evt-prev{left:10px}
                        .evt-next{right:10px}
                        .evt-thumbs{display:flex;gap:8px;overflow-x:auto;padding:10px;background:#111}
                        .evt-thumbs img{width:72px;height:54px;object-fit:cover;border-radius:4px;opacity:.8;cursor:pointer;border:0;outline:2px solid transparent}
                        .evt-thumbs img.active{opacity:1;outline-color:#28a745}
                        #eventImagesChips .img-chip{display:inline-flex;align-items:center;gap:6px;background:#f8f9fa;border:1px solid #dee2e6;border-radius:16px;padding:3px 8px;margin:2px}
                        #eventImagesChips .img-chip button{border:none;background:transparent;color:#dc3545;cursor:pointer}
                `;
        document.head.appendChild(style);

        // Collage renderer + lightbox for admin list
        function renderEventCollage(event) {
            const imgs = Array.isArray(event.images) ? event.images : (event.image ? [event.image] : []);
            const proxied = imgs.map(u => `../backend/routes/decrypt_image.php?image_url=${encodeURIComponent(u)}`);
            if (proxied.length === 0) return `<img src="../assets/default-image.jpg" style="width:100%;height:180px;object-fit:cover;border-radius:8px;border:0;box-shadow:none;">`;
            const id = 'ag_' + Math.random().toString(36).slice(2);
            window.__evtGalleriesAdmin = window.__evtGalleriesAdmin || {};
            window.__evtGalleriesAdmin[id] = proxied;
            const firstFour = proxied.slice(0, 4);
            const extra = Math.max(0, proxied.length - 4);
            const variant = proxied.length === 1 ? 'one' : (proxied.length === 2 ? 'two' : (proxied.length === 3 ? 'three' : 'fourplus'));
            const cells = firstFour.map((u, i) => {
                const more = (i === 3 && extra > 0) ? `<div class="more" data-index="${i}">+${extra}</div>` : '';
                return `<button type="button" class="cell" data-gid="${id}" data-index="${i}" style="background-image:url('${u}')" aria-label="Open image ${i+1} of ${proxied.length}">${more}</button>`;
            }).join('');
            setTimeout(() => wireAdminGalleryTriggers(id), 0);
            return `<div class="evt-collage ${variant}" data-gid="${id}" style="height:180px;">${cells}</div>`;
        }

        function wireAdminGalleryTriggers(gid) {
            document.querySelectorAll(`[data-gid="${gid}"] .cell, [data-gid="${gid}"] .more`).forEach(el => {
                el.addEventListener('click', (e) => {
                    const idxAttr = e.currentTarget.getAttribute('data-index');
                    const idx = idxAttr ? parseInt(idxAttr) : 0;
                    openAdminGallery(gid, idx);
                });
            });
        }

        function openAdminGallery(gid, index) {
            const images = (window.__evtGalleriesAdmin && window.__evtGalleriesAdmin[gid]) || [];
            if (!images.length) return;
            const modalId = 'evtGalleryModalAdmin';
            let modalEl = document.getElementById(modalId);
            if (!modalEl) {
                const tpl = `
                                <div class="modal fade evt-lightbox" id="${modalId}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-fullscreen-sm-down modal-xl modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header border-0" style="background:#000;color:#fff;">
                                                <h5 class="modal-title">Event Photos</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="main-wrap">
                                                    <button class="evt-nav evt-prev" aria-label="Previous">&#8249;</button>
                                                    <img id="evtGalleryImgAdmin" src="" alt="Event photo" />
                                                    <button class="evt-nav evt-next" aria-label="Next">&#8250;</button>
                                                </div>
                                                <div class="evt-thumbs" id="evtThumbsAdmin"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>`;
                const wrap = document.createElement('div');
                wrap.innerHTML = tpl;
                document.body.appendChild(wrap.firstElementChild);
                modalEl = document.getElementById(modalId);
            }
            const imgEl = modalEl.querySelector('#evtGalleryImgAdmin');
            const thumbsWrap = modalEl.querySelector('#evtThumbsAdmin');
            thumbsWrap.innerHTML = images.map((u, i) => `<img src="${u}" data-index="${i}" alt="thumb ${i+1}">`).join('');
            let idx = Math.max(0, Math.min(index || 0, images.length - 1));
            const setActive = () => {
                thumbsWrap.querySelectorAll('img').forEach((t, i) => t.classList.toggle('active', i === idx));
                const active = thumbsWrap.querySelector('img.active');
                if (active) active.scrollIntoView({
                    inline: 'center',
                    behavior: 'smooth',
                    block: 'nearest'
                });
            };
            const update = () => {
                imgEl.src = images[idx];
                setActive();
            };
            const prev = () => {
                idx = (idx - 1 + images.length) % images.length;
                update();
            };
            const next = () => {
                idx = (idx + 1) % images.length;
                update();
            };
            modalEl.querySelector('.evt-prev').onclick = prev;
            modalEl.querySelector('.evt-next').onclick = next;
            imgEl.onclick = next;
            thumbsWrap.querySelectorAll('img').forEach(t => t.addEventListener('click', (e) => {
                idx = parseInt(e.currentTarget.getAttribute('data-index'));
                update();
            }));
            const onKey = (e) => {
                if (!modalEl.classList.contains('show')) return;
                if (e.key === 'ArrowLeft') prev();
                if (e.key === 'ArrowRight') next();
                if (e.key === 'Escape') bootstrap.Modal.getInstance(modalEl)?.hide();
            };
            document.addEventListener('keydown', onKey);
            modalEl.addEventListener('hidden.bs.modal', () => {
                document.removeEventListener('keydown', onKey);
            }, {
                once: true
            });
            let touchStartX = null;
            imgEl.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            }, {
                passive: true
            });
            imgEl.addEventListener('touchend', (e) => {
                const dx = e.changedTouches[0].screenX - (touchStartX ?? 0);
                if (Math.abs(dx) > 40) {
                    if (dx > 0) prev();
                    else next();
                }
            });
            update();
            const bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
        }

        function renderImageChips() {
            const container = document.getElementById('eventImagesChips');
            if (!container) return;
            const chips = existingImages.map((u, idx) => `
                <span class=\"img-chip\" data-index=\"${idx}\">
            <img src=\"../backend/routes/decrypt_image.php?image_url=${encodeURIComponent(u)}\" style=\"width:24px;height:24px;border-radius:50%;object-fit:cover;border:0;box-shadow:none;\"> Existing ${idx+1}
                    <button type=\"button\" title=\"Remove\">×</button>
                </span>`).join('');
            container.innerHTML = chips || '<small class="text-muted">No images yet.</small>';
            container.querySelectorAll('.img-chip button').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const chip = e.target.closest('.img-chip');
                    const idx = parseInt(chip.getAttribute('data-index'));
                    existingImages.splice(idx, 1);
                    renderImageChips();
                });
            });
        }
    });
</script>
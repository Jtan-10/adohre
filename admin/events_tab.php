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
                                <div class="col-md-4">
                                    ${renderStackedThumbs(event)}
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
                                <div class="col-md-4">
                                    ${renderStackedThumbs(event)}
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
        // Simple stacked preview styles
        const style = document.createElement('style');
        style.textContent = `
            .stacked-preview { position: relative; height: 72px; }
            .stacked-preview .thumb { position: absolute; top: 0; width: 72px; height: 72px; object-fit: cover; border-radius: 6px; border: 2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,.2); }
            .stacked-preview .thumb:nth-child(1) { left: 0; z-index: 3; }
            .stacked-preview .thumb:nth-child(2) { left: 16px; z-index: 2; }
            .stacked-preview .thumb:nth-child(3) { left: 32px; z-index: 1; }
            .stacked-preview .more { position: absolute; left: 48px; top: 0; width: 72px; height: 72px; display:flex;align-items:center;justify-content:center; background:#f1f3f5; border-radius:6px; border:2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,.2); font-weight:600; color:#555; }
            #eventImagesChips .img-chip { display:inline-flex; align-items:center; gap:6px; background:#f8f9fa; border:1px solid #dee2e6; border-radius:16px; padding:3px 8px; margin:2px; }
            #eventImagesChips .img-chip button { border:none; background:transparent; color:#dc3545; cursor:pointer; }
        `;
        document.head.appendChild(style);

        function renderStackedThumbs(event) {
            const imgs = Array.isArray(event.images) ? event.images : (event.image ? [event.image] : []);
            const urls = imgs.slice(0, 3).map(u => `../backend/routes/decrypt_image.php?image_url=${encodeURIComponent(u)}`);
            const extra = Math.max(0, imgs.length - 3);
            if (urls.length === 0) return `<img src="../assets/default-image.jpg" class="card-img-top" alt="Event image">`;
            return `
                <div class="stacked-preview">
                    ${urls.map(u=>`<img class=\"thumb\" src=\"${u}\" alt=\"\">`).join('')}
                    ${extra ? `<div class=\"more\">+${extra}</div>` : ''}
                </div>`;
        }

        function renderImageChips() {
            const container = document.getElementById('eventImagesChips');
            if (!container) return;
            const chips = existingImages.map((u, idx) => `
                <span class=\"img-chip\" data-index=\"${idx}\">
                    <img src=\"../backend/routes/decrypt_image.php?image_url=${encodeURIComponent(u)}\" style=\"width:24px;height:24px;border-radius:50%;object-fit:cover;\"> Existing ${idx+1}
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
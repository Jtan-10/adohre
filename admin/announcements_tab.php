<div class="form-section">
    <div class="d-flex justify-content-between align-items-center">
        <h3 class="m-0">Manage Announcements</h3>
        <button type="button" id="openAddAnnouncementBtn" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#announcementModal" data-mode="create">
            <i class="bi bi-plus-lg"></i> Add Announcement
        </button>
    </div>
    <hr>

    <!-- Announcements List -->
    <h4>Existing Announcements</h4>
    <div id="announcementsList"></div>
</div>

<!-- Announcement Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="announcementModalLabel">Add Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="announcementForm">
                    <div class="mb-3">
                        <label for="announcementTitle" class="form-label">Title</label>
                        <input type="text" id="announcementTitle" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="announcementContent" class="form-label">Content</label>
                        <textarea id="announcementContent" name="text" class="form-control" required></textarea>
                    </div>
                    <input type="hidden" id="announcementId" name="id">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveAnnouncementBtn" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Updated inline script with matching nonce -->
<script nonce="<?= $cspNonce ?>">
    document.addEventListener('DOMContentLoaded', function() {
        // Utility function to decode HTML entities.
        function decodeHtmlEntities(str) {
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        }

        // Ensure the required elements exist
        const announcementForm = document.getElementById('announcementForm');
        const announcementsListElement = document.getElementById('announcementsList');

        if (!announcementForm) {
            console.error("announcementForm element not found.");
            return;
        }
        if (!announcementsListElement) {
            console.error("announcementsList element not found.");
            return;
        }

        // Fetch and display existing announcements
        fetchContent();

        const announcementModal = document.getElementById('announcementModal');
        const modalTitle = document.getElementById('announcementModalLabel');
        const saveAnnouncementBtn = document.getElementById('saveAnnouncementBtn');

        // When modal opens in create mode, reset form
        announcementModal.addEventListener('show.bs.modal', (e) => {
            const trigger = e.relatedTarget;
            if (trigger && trigger.getAttribute('data-mode') === 'create') {
                modalTitle.textContent = 'Add Announcement';
                announcementForm.reset();
                const idField = document.getElementById('announcementId');
                if (idField) idField.value = '';
            }
        });

        // Save (Add/Update)
        saveAnnouncementBtn.addEventListener('click', () => {
            const formData = new FormData(announcementForm);
            const idField = document.getElementById('announcementId');
            const id = idField ? idField.value : '';
            const action = id ? 'update_announcement' : 'add_announcement';
            formData.append('action', action);
            const csrf = announcementForm.querySelector('input[name="csrf_token"]').value;
            if (csrf && !formData.get('csrf_token')) formData.append('csrf_token', csrf);
            manageContent(formData, id ? 'Announcement updated successfully.' : 'Announcement added successfully.', () => {
                bootstrap.Modal.getInstance(announcementModal)?.hide();
                announcementForm.reset();
                if (idField) idField.value = '';
            });
        });

        // Fetch and display announcements
        function fetchContent() {
            fetch('../backend/routes/content_manager.php?action=fetch')
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        const announcementsHtml = data.announcements
                            .map(announcement => {
                                const formattedDate = new Date(announcement.created_at).toLocaleString(
                                    'en-US', {
                                        month: 'long',
                                        day: 'numeric',
                                        year: 'numeric',
                                        hour: 'numeric',
                                        minute: 'numeric',
                                        hour12: true
                                    });
                                return `
<div class="card mb-3">
  <div class="card-body">
    <p class="h5 mb-2">${decodeHtmlEntities(announcement.title)}</p>
    <div style="white-space: pre-wrap;">${decodeHtmlEntities(announcement.text)}</div>
    <small class="text-muted">Posted on: ${formattedDate}</small>
    <div class="mt-2">
      <button class="btn btn-primary btn-sm edit-announcement" data-id="${announcement.announcement_id}">Edit</button>
      <button class="btn btn-danger btn-sm delete-announcement" data-id="${announcement.announcement_id}">Delete</button>
    </div>
  </div>
</div>`;
                            })
                            .join('');
                        announcementsListElement.innerHTML = announcementsHtml ||
                            '<p class="text-center text-muted">No announcements</p>';

                        // Attach event listeners for Edit/Delete after rendering
                        document.querySelectorAll('.edit-announcement').forEach(btn => {
                            btn.addEventListener('click', () => editAnnouncement(btn.getAttribute(
                                'data-id')));
                        });
                        document.querySelectorAll('.delete-announcement').forEach(btn => {
                            btn.addEventListener('click', () => deleteAnnouncement(btn.getAttribute(
                                'data-id')));
                        });
                    }
                })
                .catch(err => console.error('Fetch error:', err));
        }

        // Edit Announcement
        function editAnnouncement(id) {
            fetch(`../backend/routes/content_manager.php?action=get_announcement&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        const announcement = data.announcement;
                        const announcementIdField = document.getElementById('announcementId');
                        const announcementTitleField = document.getElementById('announcementTitle');
                        const announcementContentField = document.getElementById('announcementContent');
                        if (announcementIdField && announcementTitleField && announcementContentField) {
                            announcementIdField.value = announcement.announcement_id;
                            announcementTitleField.value = decodeHtmlEntities(announcement.title);
                            announcementContentField.value = decodeHtmlEntities(announcement.text);
                            modalTitle.textContent = 'Edit Announcement';
                            new bootstrap.Modal(announcementModal).show();
                        }
                    }
                })
                .catch(err => console.error('Edit announcement error:', err));
        }

        // Delete Announcement
        function deleteAnnouncement(id) {
            if (confirm('Are you sure you want to delete this announcement?')) {
                const formData = new FormData();
                formData.append('action', 'delete_announcement');
                formData.append('id', id);
                manageContent(formData, 'Announcement deleted successfully.');
            }
        }

        // Manage Content (Add/Update/Delete)
        function manageContent(formData, successMessage, onSuccess) {
            fetch('../backend/routes/content_manager.php', {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        alert(successMessage);
                        fetchContent();
                        if (typeof onSuccess === 'function') onSuccess();
                    } else {
                        alert(`Error: ${data.message}`);
                    }
                })
                .catch(err => alert('Failed to connect to the server. Please try again.'));
        }
    });
</script>
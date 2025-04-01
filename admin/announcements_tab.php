<div class="form-section">
    <h3>Manage Announcements</h3>

    <!-- Announcement Form -->
    <form id="announcementForm">
        <div class="mb-3">
            <label for="announcementTitle" class="form-label">Title</label>
            <input type="text" id="announcementTitle" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="announcementContent" class="form-label">Content</label>
            <textarea id="announcementContent" name="text" class="form-control" required></textarea>
        </div>
        <input type="hidden" id="announcementId" name="id"> <!-- Hidden field for updating announcements -->
        <button type="submit" class="btn btn-primary">Save Announcement</button>
    </form>
    <hr>

    <!-- Announcements List -->
    <h4>Existing Announcements</h4>
    <div id="announcementsList"></div>
</div>

<!-- Updated inline script with matching nonce -->
<script nonce="<?= $cspNonce ?>">
document.addEventListener('DOMContentLoaded', function() {
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

    // Handle form submission (Add/Update Announcement)
    announcementForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const idField = document.getElementById('announcementId');
        const id = idField ? idField.value : '';

        // Determine if it's Add or Update
        const action = id ? 'update_announcement' : 'add_announcement';
        formData.append('action', action);

        manageContent(formData, id ? 'Announcement updated successfully.' :
            'Announcement added successfully.');
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
    <p class="h5 mb-2">${announcement.title}</p>
    <div style="white-space: pre-wrap;">${announcement.text}</div>
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
                        announcementTitleField.value = announcement.title;
                        announcementContentField.value = announcement.text;
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
    function manageContent(formData, successMessage) {
        fetch('../backend/routes/content_manager.php', {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    alert(successMessage);
                    fetchContent();
                    announcementForm.reset();
                    const idField = document.getElementById('announcementId');
                    if (idField) idField.value = '';
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(err => alert('Failed to connect to the server. Please try again.'));
    }
});
</script>
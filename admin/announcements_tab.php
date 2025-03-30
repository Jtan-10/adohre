<div class="form-section">
    <h3>Manage Announcements</h3>

    <!-- Announcement Form -->
    <form id="announcementForm">
        <div class="mb-3">
            <label for="announcementText" class="form-label">Announcement</label>
            <textarea id="announcementText" name="text" class="form-control" required></textarea>
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
    // Fetch and display existing announcements
    fetchContent();

    // Handle form submission (Add/Update Announcement)
    document.getElementById('announcementForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const id = document.getElementById('announcementId').value;

        // Determine if it's Add or Update
        const action = id ? 'update_announcement' : 'add_announcement';
        formData.append('action', action);

        manageContent(formData, id ? 'Announcement updated successfully.' :
            'Announcement added successfully.');
    });

    // Fetch and display announcements
    function fetchContent() {
        fetch('../backend/routes/content_manager.php?action=fetch')
            .then((response) => response.json())
            .then((data) => {
                if (data.status) {
                    // Populate Announcements List
                    const announcementsList = data.announcements
                        .map((announcement) => {
                            // Format created_at date and time
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
                                <p class="card-text" style="white-space: pre-wrap;">
                                    ${announcement.text}
                                </p>
                                <small class="text-muted">Posted on: ${formattedDate}</small>
                                <div class="mt-2">
                                    <button class="btn btn-primary btn-sm edit-announcement" data-id="${announcement.announcement_id}">Edit</button>
                                    <button class="btn btn-danger btn-sm delete-announcement" data-id="${announcement.announcement_id}">Delete</button>
                                </div>
                            </div>
                        </div>
                    `;
                        })
                        .join('');
                    document.getElementById('announcementsList').innerHTML = announcementsList;
                    // Attach event listeners for Edit and Delete buttons...
                    document.querySelectorAll('.edit-announcement').forEach((button) =>
                        button.addEventListener('click', function() {
                            editAnnouncement(this.getAttribute('data-id'));
                        })
                    );
                    document.querySelectorAll('.delete-announcement').forEach((button) =>
                        button.addEventListener('click', function() {
                            deleteAnnouncement(this.getAttribute('data-id'));
                        })
                    );
                }
            })
            .catch((err) => console.error(err));
    }



    // Edit Announcement
    function editAnnouncement(id) {
        fetch(`../backend/routes/content_manager.php?action=get_announcement&id=${id}`)
            .then((response) => response.json())
            .then((data) => {
                if (data.status) {
                    const announcement = data.announcement;
                    document.getElementById('announcementId').value = announcement.announcement_id;
                    document.getElementById('announcementText').value = announcement.text;
                }
            })
            .catch((err) => console.error(err));
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
            .then((response) => response.json())
            .then((data) => {
                if (data.status) {
                    alert(successMessage);
                    fetchContent();
                    document.getElementById('announcementForm').reset();
                    document.getElementById('announcementId').value = '';
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch((err) => alert('Failed to connect to the server. Please try again.'));
    }
});
</script>
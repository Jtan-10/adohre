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
                            <label for="eventDate" class="form-label">Event Date &amp; Time</label>
                            <input type="datetime-local" id="eventDate" name="date" class="form-control" required>
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
                            <label for="eventImage" class="form-label">Event Image</label>
                            <input type="file" id="eventImage" name="image" class="form-control" accept="image/*">
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
            }
        });

        // Save (Add/Update)
        saveEventBtn.addEventListener('click', () => {
            const formData = new FormData(eventForm);
            const id = document.getElementById('eventId').value;
            const action = id ? 'update_event' : 'add_event';
            formData.append('action', action);
            // include csrf if present
            const csrf = eventForm.querySelector('input[name="csrf_token"]').value;
            if (csrf && !formData.get('csrf_token')) formData.append('csrf_token', csrf);
            manageContent(formData, id ? 'Event updated successfully.' : 'Event added successfully.', () => {
                bootstrap.Modal.getInstance(eventModal)?.hide();
                eventForm.reset();
                document.getElementById('eventId').value = '';
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
                                    <img src="../backend/routes/decrypt_image.php?image_url=${ encodeURIComponent(event.image || '/capstone-php/assets/default-image.jpg') }" class="card-img-top" alt="Event image">
                                </div>
                                <div class="col-md-8">
                                    <div class="card-body">
                                        <h5 class="card-title">${event.title}</h5>
                                        <p class="card-text">${event.description}</p>
                                        <p><strong>Date:</strong> ${event.date}</p>
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
                                    <img src="../backend/routes/decrypt_image.php?image_url=${ encodeURIComponent(event.image || '/capstone-php/assets/default-image.jpg') }" class="card-img-top" alt="Event image">
                                </div>
                                <div class="col-md-8">
                                    <div class="card-body">
                                        <h5 class="card-title">${event.title}</h5>
                                        <p class="card-text">${event.description}</p>
                                        <p><strong>Date:</strong> ${event.date}</p>
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
                        document.getElementById('eventDate').value = event.date;
                        document.getElementById('eventLocation').value = event.location;
                        document.getElementById('eventFee').value = event.fee || '';

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
    });
</script>
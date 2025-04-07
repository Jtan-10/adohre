<div class="form-section" id="manageEventsSection">
    <h3>Manage Events</h3>

    <!-- Event Form -->
    <form id="eventForm" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="eventTitle" class="form-label">Event Title</label>
            <input type="text" id="eventTitle" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="eventDescription" class="form-label">Event Description</label>
            <textarea id="eventDescription" name="description" class="form-control" required></textarea>
        </div>
        <div class="mb-3">
            <label for="eventDate" class="form-label">Event Date &amp; Time</label>
            <input type="datetime-local" id="eventDate" name="date" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="eventLocation" class="form-label">Event Location</label>
            <input type="text" id="eventLocation" name="location" class="form-control" required>
        </div>
        <!-- New Event Fee Field -->
        <div class="mb-3">
            <label for="eventFee" class="form-label">Event Fee</label>
            <input type="number" id="eventFee" name="fee" class="form-control"
                placeholder="Enter event fee (0 for free)" step="0.01">
        </div>
        <div class="mb-3">
            <label for="eventImage" class="form-label">Event Image</label>
            <input type="file" id="eventImage" name="image" class="form-control" accept="image/*">
        </div>
        <input type="hidden" id="eventId" name="id"> <!-- Hidden field for updating events -->
        <button type="submit" class="btn btn-success">Save Event</button>
    </form>
    <hr>

    <!-- Events List -->
    <h4>Upcoming / Current Events</h4>
    <div id="currentEventsList"></div>

    <h4>Past Events</h4>
    <div id="pastEventsList"></div>
</div>

<!-- Updated inline script with matching nonce -->
<script nonce="<?= $cspNonce ?>">
document.addEventListener('DOMContentLoaded', function() {
    // Fetch and display existing events
    fetchContent();

    // Handle form submission (Add/Update Event)
    document.getElementById('eventForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const id = document.getElementById('eventId').value;

        // Determine if it's Add or Update
        const action = id ? 'update_event' : 'add_event';
        formData.append('action', action);

        manageContent(formData, id ? 'Event updated successfully.' : 'Event added successfully.');
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
                                        <p><strong>Fee:</strong> ${event.fee && parseFloat(event.fee) > 0 ? '$' + event.fee : 'Free'}</p>
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
                                        <p><strong>Fee:</strong> ${event.fee && parseFloat(event.fee) > 0 ? '$' + event.fee : 'Free'}</p>
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

                    // Scroll smoothly to the manage events section (the event form)
                    document.getElementById('manageEventsSection').scrollIntoView({
                        behavior: 'smooth'
                    });
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
                    document.getElementById('eventForm').reset();
                    document.getElementById('eventId').value = '';
                } else {
                    alert(`Error: ${data.message}`);
                }
            })
            .catch((err) => alert('Failed to connect to the server. Please try again.'));
    }
});
</script>
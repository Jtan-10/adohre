<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - ADOHRE</title>
    <link rel="icon" href="assets/logo.png" type="image/jpg" />
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Styles -->
    <style>
    :root {
        --primary-color: #28a745;
        --secondary-color: #2c3e50;
        --accent-color: #f8f9fa;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #ffffff;
    }

    .events-container {
        background: var(--accent-color);
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .announcements-container {
        background: #fff;
        border-radius: 15px;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        border-left: 4px solid var(--primary-color);
    }

    .event-card {
        border: none;
        border-radius: 12px;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background: linear-gradient(145deg, #ffffff, #f8f9fa);
        overflow: hidden;
    }

    .event-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(40, 167, 69, 0.15);
    }

    .event-card img {
        height: 220px;
        object-fit: cover;
        border-radius: 12px 12px 0 0;
    }

    .event-card-body {
        padding: 1.5rem;
    }

    .event-date {
        background: var(--primary-color);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        display: inline-block;
        margin-bottom: 1rem;
    }

    .announcement-card {
        background: #fff;
        border-left: 3px solid var(--primary-color);
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
    }

    .announcement-card:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .scrollable-section {
        max-height: 80vh;
        overflow-y: auto;
        padding-right: 1rem;
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: #218838;
    }

    .section-title {
        font-size: 2rem;
        font-weight: 700;
        color: var(--secondary-color);
        margin-bottom: 2rem;
        position: relative;
        padding-left: 1.5rem;
    }

    .section-title::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        height: 70%;
        width: 4px;
        background: var(--primary-color);
        border-radius: 2px;
    }
    </style>
</head>

<body>
    <header>
        <?php include('header.php'); ?>
        <?php
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
        ?>
    </header>

    <?php include('sidebar.php'); ?>

    <main class="container mt-4 mb-4">
        <div class="row g-4">
            <!-- Events Section -->
            <div class="col-lg-8">
                <div class="events-container">
                    <h2 class="section-title">Upcoming Events</h2>
                    <div class="scrollable-section">
                        <div class="row g-4" id="eventsList">
                            <!-- Events will be populated here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Announcements Section -->
            <div class="col-lg-4">
                <div class="announcements-container">
                    <h2 class="section-title">Announcements</h2>
                    <div class="scrollable-section" id="announcementsList">
                        <!-- Announcements will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include('footer.php'); ?>

    <!-- Bootstrap JS -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const userId = "<?php echo $_SESSION['user_id']; ?>";
        const eventsList = document.getElementById('eventsList');
        let eventsData = [];

        // Event delegation for dynamic buttons
        eventsList.addEventListener('click', async (e) => {
            const joinBtn = e.target.closest('.join-event-btn');
            if (!joinBtn) return;

            const eventId = joinBtn.dataset.eventId;
            const card = joinBtn.closest('.event-card');

            try {
                joinBtn.innerHTML =
                    `<span class="spinner-border spinner-border-sm" role="status"></span> Joining...`;
                joinBtn.disabled = true;

                const response = await fetch(
                    'backend/routes/event_registration.php?action=join_event', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            event_id: eventId
                        })
                    });

                const data = await response.json();

                if (data.status) {
                    // Update button state
                    joinBtn.innerHTML = `Joined <i class="fas fa-check ms-2"></i>`;
                    joinBtn.classList.remove('btn-success');
                    joinBtn.classList.add('btn-secondary');
                    joinBtn.disabled = true;

                    // Update local data
                    const eventIndex = eventsData.findIndex(e => e.event_id == eventId);
                    if (eventIndex > -1) {
                        eventsData[eventIndex].joined = true;
                    }
                } else {
                    alert(data.message || 'Failed to join event');
                    joinBtn.innerHTML = `Join Now <i class="fas fa-arrow-right ms-2"></i>`;
                    joinBtn.disabled = false;
                }
            } catch (err) {
                console.error('Join event error:', err);
                alert('Error joining event');
                joinBtn.innerHTML = `Join Now <i class="fas fa-arrow-right ms-2"></i>`;
                joinBtn.disabled = false;
            }
        });

        // Fetch and render events
        fetch('backend/routes/content_manager.php?action=fetch')
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    eventsData = data.events;
                    renderEvents(data.events);
                    renderAnnouncements(data.announcements);
                } else {
                    showError('Failed to load events');
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                showError('Failed to load events');
            });

        function renderEvents(events) {
            const html = events.map(event => `
            <div class="col-12">
                <div class="event-card">
                    <img src="${event.image || 'assets/default-event.jpg'}" 
                         class="card-img-top" 
                         alt="${event.title}">
                    <div class="event-card-body">
                        <div class="event-date">
                            <i class="fas fa-calendar-alt me-2"></i>
                            ${new Date(event.date).toLocaleDateString()}
                        </div>
                        <h3 class="h5 fw-bold mb-3">${event.title}</h3>
                        <p class="text-muted mb-3">${event.description}</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="location">
                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                <span class="text-muted">${event.location}</span>
                            </div>
                            <button class="btn ${event.joined ? 'btn-secondary' : 'btn-success'} btn-sm join-event-btn" 
                                data-event-id="${event.event_id}"
                                ${event.joined ? 'disabled' : ''}>
                                ${event.joined ? 'Joined <i class="fas fa-check ms-2"></i>' : 'Join Now <i class="fas fa-arrow-right ms-2"></i>'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
            eventsList.innerHTML = html || '<p class="text-center text-muted">No upcoming events</p>';
        }

        function renderAnnouncements(announcements) {
            const announcementsList = document.getElementById('announcementsList');
            const html = announcements.map(announcement => `
            <div class="announcement-card">
                <div class="d-flex align-items-start mb-2">
                    <i class="fas fa-bullhorn text-primary me-3 mt-1"></i>
                    <div>
                        <p class="mb-1 fw-semibold">${announcement.title || 'Important Update'}</p>
                        <p class="text-muted mb-0 small">${announcement.text}</p>
                        <div class="text-end mt-2">
                            <small class="text-muted">
                                ${new Date(announcement.date).toLocaleDateString()}
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
            announcementsList.innerHTML = html || '<p class="text-center text-muted">No announcements</p>';
        }

        function showError(message) {
            eventsList.innerHTML = `
            <div class="col-12 text-center text-danger">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <p>${message}</p>
            </div>
        `;
        }
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
</body>

</html>
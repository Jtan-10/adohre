<?php
// Start session and disable error reporting for production
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Updated Content Security Policy for production -->
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
                    <h2 class="section-title">Events</h2>
                    <div class="scrollable-section">
                        <div id="eventsList">
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

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">Payment Required</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>This event requires a fee of <span id="eventFee"></span>.</p>
                    <p>Please proceed to your <strong>Profile &amp; Payments</strong> tab to complete your payment.</p>
                    <p>After reviewing the fee details, click <strong>Ok, Got It</strong> to proceed.</p>
                </div>
                <div class="modal-footer">
                    <!-- "Ok, Got It" button used to push the payment record -->
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="okGotItBtn">Ok, Got
                        It</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const userId = <?php echo json_encode($_SESSION['user_id']); ?>;
        const eventsList = document.getElementById('eventsList');
        let eventsData = [];
        const paymentModalInstance = new bootstrap.Modal(document.getElementById('paymentModal'));
        const eventFeeSpan = document.getElementById('eventFee');

        // Global variables to store the current event and button for payment initiation.
        let currentEventForPayment = null;
        let currentButtonForPayment = null;

        // Event delegation for dynamic buttons
        eventsList.addEventListener('click', async (e) => {
            const registerBtn = e.target.closest('.join-event-btn');
            if (!registerBtn) return;

            const eventId = registerBtn.dataset.eventId;
            const fee = parseFloat(registerBtn.dataset.fee);

            // If fee > 0, show the payment modal (but do not push payment yet)
            if (fee > 0) {
                eventFeeSpan.textContent = "PHP " + fee.toFixed(2);
                // Store current event and button for later use
                currentEventForPayment = eventId;
                currentButtonForPayment = registerBtn;
                paymentModalInstance.show();
                return;
            }

            // For free events (fee == 0), proceed to register immediately.
            try {
                registerBtn.innerHTML =
                    `<span class="spinner-border spinner-border-sm" role="status"></span> Joining...`;
                registerBtn.disabled = true;
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
                    registerBtn.innerHTML = `Joined <i class="fas fa-check ms-2"></i>`;
                    registerBtn.classList.remove('btn-success');
                    registerBtn.classList.add('btn-secondary');
                    registerBtn.disabled = true;
                    // Update local data
                    const eventIndex = eventsData.findIndex(e => e.event_id == eventId);
                    if (eventIndex > -1) {
                        eventsData[eventIndex].joined = true;
                    }
                } else {
                    alert(data.message || 'Failed to join event');
                    registerBtn.innerHTML = fee > 0 ?
                        `Register Now <i class="fas fa-arrow-right ms-2"></i>` :
                        `Join Now <i class="fas fa-arrow-right ms-2"></i>`;
                    registerBtn.disabled = false;
                }
            } catch (err) {
                console.error('Join event error:', err);
                alert('Error joining event');
                registerBtn.innerHTML = fee > 0 ?
                    `Register Now <i class="fas fa-arrow-right ms-2"></i>` :
                    `Join Now <i class="fas fa-arrow-right ms-2"></i>`;
                registerBtn.disabled = false;
            }
        });

        // Attach event listener to the "Ok, Got It" button in the payment modal
        document.getElementById('okGotItBtn').addEventListener('click', async function() {
            if (!currentEventForPayment) return;
            try {
                const response = await fetch(
                    'backend/routes/event_registration.php?action=initiate_payment', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            event_id: currentEventForPayment
                        })
                    });
                const data = await response.json();
                if (data.status) {
                    // Update stored button to "Pending Payment"
                    currentButtonForPayment.innerHTML = `Pending Payment`;
                    currentButtonForPayment.classList.remove('btn-success');
                    currentButtonForPayment.classList.add('btn-warning');
                    currentButtonForPayment.disabled = true;
                    // Start polling for payment status every 5 seconds.
                    const pollInterval = setInterval(async () => {
                        const pollResponse = await fetch(
                            'backend/routes/event_registration.php?action=check_payment_status', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    event_id: currentEventForPayment
                                })
                            });
                        const pollData = await pollResponse.json();
                        if (pollData.payment_completed) {
                            clearInterval(pollInterval);
                            // Instead of "Joined", you can show "View Event" if desired.
                            currentButtonForPayment.innerHTML =
                                `View Event <i class="fas fa-eye ms-2"></i>`;
                            currentButtonForPayment.classList.remove('btn-warning');
                            currentButtonForPayment.classList.add('btn-primary',
                                'view-event-btn');
                            currentButtonForPayment.disabled = false;
                            alert(pollData.message);
                            currentEventForPayment = null;
                            currentButtonForPayment = null;
                        }
                    }, 5000);
                } else {
                    alert(data.message || 'Failed to initiate payment');
                    currentButtonForPayment.innerHTML =
                        `Register Now <i class="fas fa-arrow-right ms-2"></i>`;
                    currentButtonForPayment.disabled = false;
                    currentEventForPayment = null;
                    currentButtonForPayment = null;
                }
            } catch (err) {
                console.error('initiate_payment error:', err);
                alert('Error initiating payment');
                currentButtonForPayment.innerHTML =
                    `Register Now <i class="fas fa-arrow-right ms-2"></i>`;
                currentButtonForPayment.disabled = false;
                currentEventForPayment = null;
                currentButtonForPayment = null;
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

        // --- Updated renderEvents() ---
        function renderEvents(events) {
            const now = new Date();
            const upcomingEvents = events.filter(event => new Date(event.date) >= now);
            const pastEvents = events.filter(event => new Date(event.date) < now);
            const upcomingHtml = upcomingEvents.map(event => {
                const eventDate = new Date(event.date);
                const dateStr = eventDate.toLocaleString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                    hour12: true
                });
                let joinBtn;
                if (event.joined) {
                    joinBtn = `
                        <button class="btn btn-secondary btn-sm join-event-btn" data-event-id="${event.event_id}" disabled>
                        Joined <i class="fas fa-check ms-2"></i>
                        </button>
                    `;
                } else if (event.pending_payment) {
                    joinBtn = `
                        <button class="btn btn-warning btn-sm join-event-btn" data-event-id="${event.event_id}" disabled>
                        Pending Payment
                        </button>
                    `;
                } else {
                    if (parseFloat(event.fee) > 0) {
                        joinBtn = `
                        <button class="btn btn-success btn-sm join-event-btn" data-event-id="${event.event_id}" data-fee="${event.fee}">
                            Register Now <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                        `;
                    } else {
                        joinBtn = `
                        <button class="btn btn-success btn-sm join-event-btn" data-event-id="${event.event_id}" data-fee="0">
                            Join Now <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                        `;
                    }
                }
                return `
            <div class="col-12">
              <div class="event-card">
                <img src="${ event.image ? '/backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(event.image) : 'assets/default-event.jpg' }" 
                     class="card-img-top" 
                     alt="${event.title}">
                <div class="event-card-body">
                  <div class="event-date">
                    <i class="fas fa-calendar-alt me-2"></i>
                    ${dateStr}
                  </div>
                  <h3 class="h5 fw-bold mb-3">${event.title}</h3>
                  <p class="text-muted mb-3">${event.description}</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <div class="location">
                      <i class="fas fa-map-marker-alt text-primary me-2"></i>
                      <span class="text-muted">${event.location}</span>
                    </div>
                    ${joinBtn}
                  </div>
                </div>
              </div>
            </div>`;
            }).join('');
            const pastHtml = pastEvents.map(event => {
                const eventDate = new Date(event.date);
                const dateStr = eventDate.toLocaleString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                    hour12: true
                });
                const pastBtn = `<button class="btn btn-secondary btn-sm" disabled>Past Event</button>`;
                return `
            <div class="col-12">
              <div class="event-card">
                <img src="${ event.image ? '/backend/routes/decrypt_image.php?image_url=' + encodeURIComponent(event.image) : 'assets/default-event.jpg' }" 
                     class="card-img-top" 
                     alt="${event.title}">
                <div class="event-card-body">
                  <div class="event-date">
                    <i class="fas fa-calendar-alt me-2"></i>
                    ${dateStr}
                  </div>
                  <h3 class="h5 fw-bold mb-3">${event.title}</h3>
                  <p class="text-muted mb-3">${event.description}</p>
                  <div class="d-flex justify-content-between align-items-center">
                    <div class="location">
                      <i class="fas fa-map-marker-alt text-primary me-2"></i>
                      <span class="text-muted">${event.location}</span>
                    </div>
                    ${pastBtn}
                  </div>
                </div>
              </div>
            </div>`;
            }).join('');
            eventsList.innerHTML = `
          <ul class="nav nav-tabs" id="eventsTab" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">
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
              <div class="row g-4">
                ${upcomingHtml || '<p class="text-center text-muted">No upcoming events</p>'}
              </div>
            </div>
            <div class="tab-pane fade" id="past" role="tabpanel">
              <div class="row g-4">
                ${pastHtml || '<p class="text-center text-muted">No past events</p>'}
              </div>
            </div>
          </div>
        `;
        }
        // --- End updated renderEvents() ---

        function renderAnnouncements(announcements) {
            const announcementsList = document.getElementById('announcementsList');
            const html = announcements.map(announcement => {
                const formattedDate = new Date(announcement.created_at).toLocaleString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                    hour12: true
                });
                return `
            <div class="announcement-card">
              <div class="d-flex align-items-start mb-2">
                <i class="fas fa-bullhorn text-primary me-3 mt-1"></i>
                <div>
                  <p class="h5 mb-2">${announcement.title}</p>
                  <div style="white-space: pre-wrap;">${announcement.text}</div>
                  <div class="text-end mt-2">
                    <small class="text-muted">Posted on: ${formattedDate}</small>
                  </div>
                </div>
              </div>
            </div>
          `;
            }).join('');
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
</body>

</html>
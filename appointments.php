<?php
// Add security headers.
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Generate CSRF token.
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'backend/db/db_connect.php';

$userId = (int)$_SESSION['user_id'];
$message = "";

// Handle new appointment scheduling if form is submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_appointment'])) {
    // Validate CSRF token.
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    $appointment_date_input = $_POST['appointment_date'] ?? '';
    $appointment_date = date('Y-m-d H:i:s', strtotime($appointment_date_input));
    $description = trim($_POST['description'] ?? '');

    $stmt = $conn->prepare("INSERT INTO appointments (user_id, appointment_date, description) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iss", $userId, $appointment_date, $description);
        if ($stmt->execute()) {
            $message = "Appointment scheduled successfully.";
        } else {
            $message = "Error scheduling appointment: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
    }
}

// Fetch all appointments for this user using a prepared statement.
$allAppointments = [];
$stmt = $conn->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date ASC");
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $allAppointments[] = $row;
    }
    $stmt->close();
} else {
    // ...existing error handling...
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - ADOHRE</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">

    <style>
        .strikethrough {
            text-decoration: line-through !important;
            color: gray !important;
        }

        .page-bottom-padding {
            padding-bottom: 50px;
        }
    </style>
</head>

<body>
    <header>
        <?php include('header.php'); ?>
    </header>
    <!-- Include the Sidebar -->
    <?php include('sidebar.php'); ?>
    <div class="container mt-5 page-bottom-padding">
        <h1 class="mb-4">Appointments</h1>
        <?php if ($message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <!-- Added API message container -->
        <div id="api-message"></div>

        <!-- Appointment Scheduling Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3>Schedule a New Appointment</h3>
            </div>
            <div class="card-body">
                <form id="appointment-form" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="mb-3">
                        <label for="appointment_date" class="form-label">Appointment Date &amp; Time</label>
                        <input type="datetime-local" class="form-control" id="appointment_date" name="appointment_date"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <button type="submit" name="schedule_appointment" class="btn btn-primary">Schedule
                        Appointment</button>
                </form>
            </div>
        </div>

        <!-- Tabs for Current/Past, Upcoming, and Completed Appointments -->
        <ul class="nav nav-tabs" id="appointmentTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button"
                    role="tab" aria-controls="past" aria-selected="true">Current Appointments</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button"
                    role="tab" aria-controls="upcoming" aria-selected="false">Upcoming Appointments</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed"
                    type="button" role="tab" aria-controls="completed" aria-selected="false">Completed
                    Appointments</button>
            </li>
        </ul>
        <div class="tab-content" id="appointmentTabsContent">
            <!-- Current/Past Appointments Tab -->
            <div class="tab-pane fade show active" id="past" role="tabpanel" aria-labelledby="past-tab">
                <div class="card mt-3">
                    <div class="card-body" id="past-appointments-container">
                        <p>Loading current appointments...</p>
                    </div>
                </div>
            </div>
            <!-- Upcoming Appointments Tab -->
            <div class="tab-pane fade" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                <div class="card mt-3">
                    <div class="card-body" id="upcoming-appointments-container">
                        <p>Loading upcoming appointments...</p>
                    </div>
                </div>
            </div>
            <!-- Completed Appointments Tab -->
            <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                <div class="card mt-3">
                    <div class="card-body" id="completed-appointments-container">
                        <p>Loading completed appointments...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Calendar View (displayed below the tabs) -->
        <div class="card mt-4">
            <div class="card-header bg-dark text-white">
                <h3>Calendar View</h3>
            </div>
            <div class="card-body">
                <div id="calendar-view"></div>
            </div>
        </div>
    </div>
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script>
        const apiUrl = 'backend/routes/appointments_api.php';
        // Added currentUser role and csrf_token for later use.
        const currentUser = {
            id: <?= json_encode($userId) ?>,
            role: <?= json_encode($_SESSION['role'] ?? 'user') ?>,
            csrf_token: <?= json_encode($_SESSION['csrf_token']) ?>
        };

        // Helper function to display a message.
        function showMessage(message, type = 'info') {
            const msgDiv = document.getElementById('api-message');
            msgDiv.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
            setTimeout(() => {
                msgDiv.innerHTML = '';
            }, 5000);
        }

        // Load appointments from the API and separate into past and upcoming.
        function loadAppointments() {
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const appointments = data.appointments;
                        // Get current date/time in MySQL format (YYYY-MM-DD HH:MM:SS).
                        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
                        // Filter: past appointments are those whose appointment_date is less than now OR accepted.
                        const past = appointments.filter(appt => ((appt.appointment_date < now) || (appt.accepted ==
                            1)) && appt.done != 1);
                        // Upcoming appointments: those with a future appointment_date and not accepted.
                        const upcoming = appointments.filter(appt => (appt.appointment_date >= now) && (appt.accepted ==
                            0) && appt.done != 1);
                        // Completed appointments: those marked as done.
                        const completed = appointments.filter(appt => appt.done == 1);

                        // Render past appointments with "Done" button if accepted.
                        let pastHtml = "";
                        if (past.length === 0) {
                            pastHtml = "<p>No current (past) appointments.</p>";
                        } else {
                            pastHtml = `<table class="table table-striped">
                <thead>
                  <tr>
                    <th>Appointment ID</th>
                    <th>Date &amp; Time</th>
                    <th>Description</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>`;
                            past.forEach(appt => {
                                pastHtml += `<tr id="appt-row-${appt.appointment_id}">
                  <td>${appt.appointment_id}</td>
                  <td>${appt.appointment_date}</td>
                  <td id="desc-${appt.appointment_id}">
                      ${appt.description || ""}
                      ${appt.accepted == 1 ? '<span class="badge bg-success">Accepted</span>' : ""}
                      ${appt.accept_details ? '<br><small>' + appt.accept_details + '</small>' : ""}
                  </td>
                  <td>${(appt.accepted == 1) ? '<button class="btn btn-sm btn-success" onclick="markDone(' + appt.appointment_id + ')">Done</button>' : ''}</td>
                </tr>`;
                            });
                            pastHtml += `</tbody></table>`;
                        }
                        document.getElementById('past-appointments-container').innerHTML = pastHtml;

                        // Render upcoming appointments.
                        let upcomingHtml = "";
                        if (upcoming.length === 0) {
                            upcomingHtml = "<p>No upcoming appointments.</p>";
                        } else {
                            upcomingHtml = `<table class="table table-striped">
                <thead>
                  <tr>
                    <th>Appointment ID</th>
                    <th>Date &amp; Time</th>
                    <th>Description</th>
                  </tr>
                </thead>
                <tbody>`;
                            upcoming.forEach(appt => {
                                upcomingHtml += `<tr>
                  <td>${appt.appointment_id}</td>
                  <td>${appt.appointment_date}</td>
                  <td>${appt.description || ""}</td>
                </tr>`;
                            });
                            upcomingHtml += `</tbody></table>`;
                        }
                        document.getElementById('upcoming-appointments-container').innerHTML = upcomingHtml;

                        // Render completed appointments.
                        let completedHtml = "";
                        if (completed.length === 0) {
                            completedHtml = "<p>No completed appointments.</p>";
                        } else {
                            completedHtml = `<table class="table table-striped">
                <thead>
                  <tr>
                    <th>Appointment ID</th>
                    <th>Date &amp; Time</th>
                    <th>Description</th>
                  </tr>
                </thead>
                <tbody>`;
                            completed.forEach(appt => {
                                completedHtml += `<tr>
                  <td>${appt.appointment_id}</td>
                  <td>${appt.appointment_date}</td>
                  <td id="desc-${appt.appointment_id}">
                      ${appt.description || ""}
                      ${appt.accepted == 1 ? '<span class="badge bg-success">Accepted</span>' : ""}
                      ${appt.accept_details ? '<br><small>' + appt.accept_details + '</small>' : ""}
                  </td>
                </tr>`;
                            });
                            completedHtml += `</tbody></table>`;
                        }
                        document.getElementById('completed-appointments-container').innerHTML = completedHtml;

                        // Update calendar events.
                        loadCalendarEvents(appointments);
                    } else {
                        showMessage(data.error || "Failed to load appointments", "danger");
                    }
                })
                .catch(err => {
                    console.error("Error loading appointments:", err);
                    showMessage("Error loading appointments", "danger");
                });
        }

        // Removed the acceptAppointment() function since admin acceptance is handled separately.

        // Calendar integration using FullCalendar.
        let calendar;

        function loadCalendarEvents(appointments) {
            const events = [];
            appointments.forEach(appt => {
                // Append "Accepted:" to title if appointment has been accepted.
                let eventTitle = (appt.accepted == 1 ? "Accepted: " : "") + (appt.description || "No description");
                events.push({
                    id: appt.appointment_id,
                    title: eventTitle,
                    start: appt.appointment_date,
                    className: (appt.done == 1) ? 'strikethrough' : '',
                    description: appt.description || "No description",
                    accept_details: appt.accept_details || ""
                });
            });
            if (calendar) {
                calendar.removeAllEventSources();
                calendar.addEventSource(events);
            }
        }

        function markDone(appointmentId) {
            fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'mark_done',
                        appointment_id: appointmentId,
                        csrf_token: currentUser.csrf_token
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showMessage(data.message, 'success');
                        loadAppointments();
                    } else {
                        showMessage(data.error || "Failed to mark as done", "danger");
                    }
                })
                .catch(err => {
                    console.error("Error marking appointment as done:", err);
                    showMessage("Error marking as done", "danger");
                });
        }

        // Initialize FullCalendar.
        document.addEventListener('DOMContentLoaded', function() {
            let calendarEl = document.getElementById('calendar-view');
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [], // Will be loaded via AJAX.
                eventDidMount: function(info) {
                    // Build tooltip text with description and accepted details if available.
                    let tooltipText = info.event.extendedProps.description || info.event.title ||
                        "No description";
                    if (info.event.extendedProps.accept_details) {
                        tooltipText += "\nDetails: " + info.event.extendedProps.accept_details;
                    }
                    let start = info.event.start ? info.event.start.toLocaleString() : "";
                    info.el.setAttribute('title', tooltipText + " (" + start + ")");
                }
            });
            calendar.render();
            loadAppointments();

            // Intercept scheduling form submission.
            document.getElementById('appointment-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const appointment_date = document.getElementById('appointment_date').value;
                const description = document.getElementById('description').value;
                const csrf_token = document.querySelector('input[name="csrf_token"]').value;

                fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'schedule_appointment',
                            appointment_date: appointment_date,
                            description: description,
                            csrf_token: csrf_token
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            showMessage(data.message, "success");
                            document.getElementById('appointment-form').reset();
                            loadAppointments();
                        } else {
                            showMessage(data.error || "Failed to schedule appointment", "danger");
                        }
                    })
                    .catch(err => {
                        console.error("Error scheduling appointment:", err);
                        showMessage("Error scheduling appointment", "danger");
                    });
            });
        });
    </script>
    <?php include('footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
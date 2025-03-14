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

        <!-- Appointment Scheduling Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3>Schedule a New Appointment</h3>
            </div>
            <div class="card-body">
                <form id="appointment-form">
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

        <!-- Tabs for Past (Current) and Upcoming Appointments -->
        <ul class="nav nav-tabs" id="appointmentTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button"
                    role="tab" aria-controls="past" aria-selected="true">Current/Past Appointments</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button"
                    role="tab" aria-controls="upcoming" aria-selected="false">Upcoming Appointments</button>
            </li>
        </ul>
        <div class="tab-content" id="appointmentTabsContent">
            <!-- Past (Current) Appointments Tab -->
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
        const currentUser = {
            id: <?= json_encode($userId) ?>
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
                        const past = appointments.filter(appt => (appt.appointment_date < now) || (appt.accepted == 1));
                        // Upcoming appointments: those with a future appointment_date and not accepted.
                        const upcoming = appointments.filter(appt => (appt.appointment_date >= now) && (appt.accepted ==
                            0));

                        // Render past appointments.
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
                  </td>
                  <td>`;
                                if (appt.accepted != 1) {
                                    pastHtml +=
                                        `<button class="btn btn-success btn-sm" onclick="acceptAppointment(${appt.appointment_id})">Accept</button>`;
                                } else {
                                    pastHtml += `<em>Accepted</em>`;
                                }
                                pastHtml += `</td></tr>`;
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

        // Function to accept an appointment.
        function acceptAppointment(appointmentId) {
            if (!confirm(
                    "Do you want to accept this appointment? It will be removed from the current appointments list.")) {
                return;
            }
            fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'accept_appointment',
                        appointment_id: appointmentId,
                        user_id: currentUser.id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Remove the accepted appointment row.
                        document.getElementById('appt-row-' + appointmentId).remove();
                        loadAppointments();
                        alert("Appointment accepted.");
                    } else {
                        alert(data.error || "Failed to accept appointment.");
                    }
                })
                .catch(err => {
                    console.error("Error accepting appointment:", err);
                    alert("Error accepting appointment.");
                });
        }

        // Calendar integration using FullCalendar.
        let calendar;

        function loadCalendarEvents(appointments) {
            const events = [];
            appointments.forEach(appt => {
                events.push({
                    id: appt.appointment_id,
                    title: appt.description || "No description",
                    start: appt.appointment_date,
                    className: (appt.accepted == 1) ? 'strikethrough' : '',
                    description: appt.description || "No description"
                });
            });
            if (calendar) {
                calendar.removeAllEventSources();
                calendar.addEventSource(events);
            }
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
                    // Set tooltip with description and start time.
                    let desc = info.event.extendedProps.description || info.event.title ||
                        "No description";
                    let start = info.event.start ? info.event.start.toLocaleString() : "";
                    info.el.setAttribute('title', desc + " (" + start + ")");
                }
            });
            calendar.render();
            loadAppointments();
        });
    </script>
    <?php include('footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
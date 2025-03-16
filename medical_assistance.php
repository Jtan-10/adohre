<?php
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

// Handle new medical assistance scheduling if form is submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_medical_assistance'])) {
    // Validate CSRF token.
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    $assistance_date_input = $_POST['assistance_date'] ?? '';
    $assistance_date = date('Y-m-d H:i:s', strtotime($assistance_date_input));
    $description = trim($_POST['description'] ?? '');
    
    // Assume table "medical_assistance" exists.
    $stmt = $conn->prepare("INSERT INTO medical_assistance (user_id, assistance_date, description) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iss", $userId, $assistance_date, $description);
        if ($stmt->execute()) {
            $message = "Medical assistance request scheduled successfully.";
        } else {
            $message = "Error scheduling request: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Database error: " . $conn->error;
    }
}

// Fetch all medical assistance requests for this user.
$allAssistance = [];
$stmt = $conn->prepare("SELECT * FROM medical_assistance WHERE user_id = ? ORDER BY assistance_date ASC");
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $allAssistance[] = $row;
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
    <title>Medical Assistance - Capstone</title>
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
        <h1 class="mb-4">Medical Assistance Requests</h1>
        <?php if ($message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <!-- Added API message container -->
        <div id="api-message"></div>
        <!-- Medical Assistance Scheduling Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3>Schedule a New Medical Assistance Request</h3>
            </div>
            <div class="card-body">
                <form id="medical-assistance-form" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="mb-3">
                        <label for="assistance_date" class="form-label">Request Date &amp; Time</label>
                        <input type="datetime-local" class="form-control" id="assistance_date" name="assistance_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <button type="submit" name="schedule_medical_assistance" class="btn btn-primary">Schedule Request</button>
                </form>
            </div>
        </div>
        <!-- Tabs for Current/Past and Upcoming Requests -->
        <ul class="nav nav-tabs" id="assistanceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab" aria-controls="past" aria-selected="true">Current/Past Requests</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab" aria-controls="upcoming" aria-selected="false">Upcoming Requests</button>
            </li>
        </ul>
        <div class="tab-content" id="assistanceTabsContent">
            <!-- Current/Past Requests Tab -->
            <div class="tab-pane fade show active" id="past" role="tabpanel" aria-labelledby="past-tab">
                <div class="card mt-3">
                    <div class="card-body" id="past-assistance-container">
                        <p>Loading current assistance requests...</p>
                    </div>
                </div>
            </div>
            <!-- Upcoming Requests Tab -->
            <div class="tab-pane fade" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                <div class="card mt-3">
                    <div class="card-body" id="upcoming-assistance-container">
                        <p>Loading upcoming assistance requests...</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Calendar View -->
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
        const apiUrl = 'backend/routes/medical_assistance_api.php'; // update endpoint if needed
        const currentUser = {
            id: <?= json_encode($userId) ?>,
            role: <?= json_encode($_SESSION['role'] ?? 'user') ?>,
            csrf_token: <?= json_encode($_SESSION['csrf_token']) ?>
        };

        function showMessage(message, type = 'info') {
            const msgDiv = document.getElementById('api-message');
            msgDiv.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
            setTimeout(() => { msgDiv.innerHTML = ''; }, 5000);
        }

        function loadAssistance() {
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'success'){
                        const assistance = data.assistance;
                        const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
                        const past = assistance.filter(req => (req.assistance_date < now) || (req.accepted == 1));
                        const upcoming = assistance.filter(req => (req.assistance_date >= now) && (req.accepted == 0));

                        let pastHtml = "";
                        if (past.length === 0) {
                            pastHtml = "<p>No current (past) assistance requests.</p>";
                        } else {
                            pastHtml = `<table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Date &amp; Time</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                            past.forEach(req => {
                                pastHtml += `<tr id="req-row-${req.assistance_id}">
                                    <td>${req.assistance_id}</td>
                                    <td>${req.assistance_date}</td>
                                    <td>
                                        ${req.description || ""}
                                        ${req.accepted == 1 ? '<span class="badge bg-success">Accepted</span>' : ""}
                                        ${req.accept_details ? '<br><small>' + req.accept_details + '</small>' : ""}
                                    </td>
                                </tr>`;
                            });
                            pastHtml += `</tbody></table>`;
                        }
                        document.getElementById('past-assistance-container').innerHTML = pastHtml;

                        let upcomingHtml = "";
                        if (upcoming.length === 0) {
                            upcomingHtml = "<p>No upcoming assistance requests.</p>";
                        } else {
                            upcomingHtml = `<table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Request ID</th>
                                        <th>Date &amp; Time</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                            upcoming.forEach(req => {
                                upcomingHtml += `<tr>
                                    <td>${req.assistance_id}</td>
                                    <td>${req.assistance_date}</td>
                                    <td>${req.description || ""}</td>
                                </tr>`;
                            });
                            upcomingHtml += `</tbody></table>`;
                        }
                        document.getElementById('upcoming-assistance-container').innerHTML = upcomingHtml;
                        loadCalendarEvents(assistance);
                    } else {
                        showMessage(data.error || "Failed to load assistance requests", "danger");
                    }
                })
                .catch(err => {
                    console.error("Error loading medical assistance:", err);
                    showMessage("Error loading assistance requests", "danger");
                });
        }

        let calendar;
        function loadCalendarEvents(assistance) {
            const events = [];
            assistance.forEach(req => {
                let eventTitle = (req.accepted == 1 ? "Accepted: " : "") + (req.description || "No description");
                events.push({
                    id: req.assistance_id,
                    title: eventTitle,
                    start: req.assistance_date,
                    className: (req.accepted == 1) ? 'strikethrough' : '',
                    description: req.description || "No description",
                    accept_details: req.accept_details || ""
                });
            });
            if(calendar){
                calendar.removeAllEventSources();
                calendar.addEventSource(events);
            }
        }

        document.addEventListener('DOMContentLoaded', function(){
            let calendarEl = document.getElementById('calendar-view');
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [],
                eventDidMount: function(info) {
                    let tooltipText = info.event.extendedProps.description || info.event.title || "No description";
                    if(info.event.extendedProps.accept_details){
                        tooltipText += "\nDetails: " + info.event.extendedProps.accept_details;
                    }
                    let start = info.event.start ? info.event.start.toLocaleString() : "";
                    info.el.setAttribute('title', tooltipText + " (" + start + ")");
                }
            });
            calendar.render();
            loadAssistance();

            document.getElementById('medical-assistance-form').addEventListener('submit', function(e){
                e.preventDefault();
                const assistance_date = document.getElementById('assistance_date').value;
                const description = document.getElementById('description').value;
                const csrf_token = document.querySelector('input[name="csrf_token"]').value;
                fetch(apiUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'schedule_medical_assistance',
                        assistance_date: assistance_date,
                        description: description,
                        csrf_token: csrf_token
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'success'){
                        showMessage(data.message, "success");
                        document.getElementById('medical-assistance-form').reset();
                        loadAssistance();
                    } else {
                        showMessage(data.error || "Failed to schedule request", "danger");
                    }
                })
                .catch(err => {
                    console.error("Error scheduling request:", err);
                    showMessage("Error scheduling request", "danger");
                });
            });
        });
    </script>
    <?php include('footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
include('admin_header.php');

// Ensure the user is logged in and is an admin.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require_once '../backend/db/db_connect.php';

$userId = $_SESSION['user_id'];

// Retrieve all appointments along with user details.
$appointments = [];
$query = "SELECT a.*, u.first_name, u.last_name, u.email 
          FROM appointments a 
          JOIN users u ON a.user_id = u.user_id 
          ORDER BY a.appointment_date ASC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
    /* Strikethrough style for accepted appointments */
    .strikethrough {
        text-decoration: line-through !important;
        color: gray !important;
    }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <div class="container mt-5">
            <h1 class="mb-4">Appointment Management</h1>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Appointment ID</th>
                        <th>User</th>
                        <th>Date & Time</th>
                        <th>Description</th>
                        <th>Accepted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="appointments-table">
                    <?php foreach ($appointments as $appt): ?>
                    <tr id="appt-<?= htmlspecialchars($appt['appointment_id']) ?>"
                        class="<?= $appt['accepted'] ? 'strikethrough' : '' ?>">
                        <td><?= htmlspecialchars($appt['appointment_id']) ?></td>
                        <td><?= htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name'] . ' (' . $appt['email'] . ')') ?>
                        </td>
                        <td><?= htmlspecialchars($appt['appointment_date']) ?></td>
                        <td><?= htmlspecialchars($appt['description']) ?></td>
                        <td><?= $appt['accepted'] ? 'Yes' : 'No' ?></td>
                        <td>
                            <?php if (!$appt['accepted']): ?>
                            <button class="btn btn-success btn-sm"
                                onclick="acceptAppointmentAdmin(<?= $appt['appointment_id'] ?>)">Mark as
                                Accepted</button>
                            <?php else: ?>
                            <em>Accepted</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Function to mark an appointment as accepted via AJAX.
    function acceptAppointmentAdmin(appointmentId) {
        if (!confirm("Mark this appointment as accepted?")) return;

        // Send a POST request to your admin API endpoint.
        fetch('../backend/routes/admin_appointments_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'accept_appointment',
                    appointment_id: appointmentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update the row visually.
                    const row = document.getElementById('appt-' + appointmentId);
                    if (row) {
                        row.classList.add('strikethrough');
                        // Update the Accepted column (5th cell, index 4)
                        row.cells[4].innerText = "Yes";
                        // Replace the action cell with a text indicator.
                        row.cells[5].innerHTML = "<em>Accepted</em>";
                    }
                    alert("Appointment marked as accepted.");
                } else {
                    alert(data.error || "Failed to mark appointment as accepted.");
                }
            })
            .catch(err => {
                console.error("Error:", err);
                alert("Error marking appointment as accepted.");
            });
    }
    </script>
</body>

</html>
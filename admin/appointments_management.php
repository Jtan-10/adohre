<?php
define('APP_INIT', true); // Added to enable proper access.
if (session_status() === PHP_SESSION_NONE) session_start();
// Generate CSRF token for production use.
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
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
    <script nonce="<?= $cspNonce ?>">
        // Expose CSRF token to JavaScript.
        const csrfToken = "<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>";
    </script>
</head>

<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <div class="container mt-5">
            <h1 class="mb-4">Appointment Management</h1>
            <!-- Added API message container -->
            <div id="api-message"></div>
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
                        <tr id="appt-<?= htmlspecialchars($appt['appointment_id']) ?>" class="">
                            <td><?= htmlspecialchars($appt['appointment_id']) ?></td>
                            <td><?= htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name'] . ' (' . $appt['email'] . ')') ?>
                            </td>
                            <td><?= htmlspecialchars($appt['appointment_date']) ?></td>
                            <td><?= htmlspecialchars($appt['description']) ?></td>
                            <td><?= $appt['accepted'] ? 'Yes' : 'No' ?></td>
                            <td>
                                <?php if (!$appt['accepted']): ?>
                                    <!-- New accept button with data attribute -->
                                    <button class="btn btn-success btn-sm accept-btn" data-appointment-id="<?= $appt['appointment_id'] ?>">Mark as Accepted</button>
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

    <!-- Modal for accepting appointment -->
    <div class="modal fade" id="acceptModal" tabindex="-1" aria-labelledby="acceptModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <!-- ...existing modal header... -->
          <div class="modal-header">
            <h5 class="modal-title" id="acceptModalLabel">Accept Appointment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="accept-form">
              <div class="mb-3">
                <label for="accept-details" class="form-label">Additional Details</label>
                <textarea class="form-control" id="accept-details" name="accept_details" rows="3" placeholder="Enter details to send via email"></textarea>
              </div>
              <input type="hidden" id="modal-appointment-id" name="appointment_id" value="">
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" id="modal-submit-btn" class="btn btn-primary">Send & Accept</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= $cspNonce ?>">
        // Function to display a message in the API message container.
        function showMessage(message, type = 'info') {
            const msgDiv = document.getElementById('api-message');
            msgDiv.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
            setTimeout(() => { msgDiv.innerHTML = ''; }, 5000);
        }
        
        // Open modal on accept button click.
        document.addEventListener('DOMContentLoaded', function() {
            const acceptButtons = document.querySelectorAll('.accept-btn');
            acceptButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const appointmentId = this.getAttribute('data-appointment-id');
                    document.getElementById('modal-appointment-id').value = appointmentId;
                    // Open modal using Bootstrap.
                    const modalEl = document.getElementById('acceptModal');
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                });
            });
            
            // On modal submit, send details via AJAX.
            document.getElementById('modal-submit-btn').addEventListener('click', function() {
                const appointmentId = document.getElementById('modal-appointment-id').value;
                const details = document.getElementById('accept-details').value;
                if(!confirm("Send details via email and mark appointment as accepted?")) return;
                fetch('../backend/routes/appointments_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'accept_appointment_with_details',
                        appointment_id: appointmentId,
                        accept_details: details,
                        csrf_token: csrfToken
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'success'){
                        // Update row without crossing it out.
                        const row = document.getElementById('appt-' + appointmentId);
                        if(row){
                            const cells = row.querySelectorAll('td');
                            if(cells.length >= 6){
                                cells[4].innerText = "Yes";
                                cells[5].innerHTML = "<em>Accepted</em>";
                            }
                        }
                        showMessage(data.message, "success");
                        // Close modal and reset form.
                        const modalEl = document.getElementById('acceptModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        modal.hide();
                        document.getElementById('accept-form').reset();
                    } else {
                        showMessage(data.error || "Failed to accept appointment.", "danger");
                    }
                })
                .catch(err => {
                    console.error("Error:", err);
                    showMessage("Error processing request.", "danger");
                });
            });
        });
    </script>
</body>

</html>
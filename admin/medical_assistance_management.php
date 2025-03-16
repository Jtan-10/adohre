<?php
define('APP_INIT', true);
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
include('admin_header.php');
require_once '../backend/db/db_connect.php';

$medicalRequests = [];
$query = "SELECT m.*, u.first_name, u.last_name, u.email 
          FROM medical_assistance m 
          JOIN users u ON m.user_id = u.user_id 
          ORDER BY m.assistance_date ASC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $medicalRequests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Assistance Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        .strikethrough { text-decoration: line-through !important; color: gray !important; }
    </style>
    <script nonce="<?= $cspNonce ?>">
        const csrfToken = "<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>";
    </script>
</head>
<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <div class="container mt-5">
            <h1 class="mb-4">Medical Assistance Management</h1>
            <div id="api-message"></div>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>User</th>
                        <th>Date &amp; Time</th>
                        <th>Description</th>
                        <th>Accepted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="medical-assistance-table">
                    <?php foreach ($medicalRequests as $req): ?>
                        <tr id="req-<?= htmlspecialchars($req['assistance_id']) ?>">
                            <td><?= htmlspecialchars($req['assistance_id']) ?></td>
                            <td><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name'] . ' (' . $req['email'] . ')') ?></td>
                            <td><?= htmlspecialchars($req['assistance_date']) ?></td>
                            <td><?= htmlspecialchars($req['description']) ?></td>
                            <td><?= $req['accepted'] ? 'Yes' : 'No' ?></td>
                            <td>
                                <?php if (!$req['accepted']): ?>
                                    <button class="btn btn-success btn-sm accept-btn" data-assistance-id="<?= $req['assistance_id'] ?>">Mark as Accepted</button>
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
    <!-- Modal for accepting request -->
    <div class="modal fade" id="acceptModal" tabindex="-1" aria-labelledby="acceptModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <!-- ...existing modal header... -->
          <div class="modal-header">
            <h5 class="modal-title" id="acceptModalLabel">Accept Medical Assistance Request</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="accept-form">
              <div class="mb-3">
                <label for="accept-details" class="form-label">Additional Details</label>
                <textarea class="form-control" id="accept-details" name="accept_details" rows="3" placeholder="Enter details to send via email"></textarea>
              </div>
              <input type="hidden" id="modal-assistance-id" name="assistance_id" value="">
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
    function showMessage(message, type = 'info') {
        const msgDiv = document.getElementById('api-message');
        msgDiv.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
        setTimeout(() => { msgDiv.innerHTML = ''; }, 5000);
    }
    document.addEventListener('DOMContentLoaded', function() {
        const acceptButtons = document.querySelectorAll('.accept-btn');
        acceptButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const assistanceId = this.getAttribute('data-assistance-id');
                document.getElementById('modal-assistance-id').value = assistanceId;
                const modalEl = document.getElementById('acceptModal');
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            });
        });
        document.getElementById('modal-submit-btn').addEventListener('click', function() {
            const assistanceId = document.getElementById('modal-assistance-id').value;
            const details = document.getElementById('accept-details').value;
            if(!confirm("Send details via email and mark request as accepted?")) return;
            fetch('../backend/routes/medical_assistance_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'accept_medical_assistance_with_details',
                    assistance_id: assistanceId,
                    accept_details: details,
                    csrf_token: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success'){
                    const row = document.getElementById('req-' + assistanceId);
                    if(row){
                        const cells = row.querySelectorAll('td');
                        if(cells.length >= 6){
                            cells[4].innerText = "Yes";
                            cells[5].innerHTML = "<em>Accepted</em>";
                        }
                    }
                    showMessage(data.message, "success");
                    const modalEl = document.getElementById('acceptModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();
                    document.getElementById('accept-form').reset();
                } else {
                    showMessage(data.error || "Failed to accept request.", "danger");
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

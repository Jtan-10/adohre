<?php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Sessions
if (session_status() === PHP_SESSION_NONE) {
    require_once 'backend/db/db_connect.php';
    configureSessionSecurity();
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        .page-bottom-padding {
            padding-bottom: 50px;
        }
    </style>
    <script>
        const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
    </script>
</head>

<body>
    <header>
        <?php include('header.php'); ?>
    </header>
    <?php include('sidebar.php'); ?>
    <div class="container mt-5 page-bottom-padding">
        <h1 class="mb-4">Member Requests</h1>
        <div id="api-message"></div>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3>Create a Request</h3>
            </div>
            <div class="card-body">
                <form id="request-form">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="request_type" class="form-label">Request Type</label>
                            <select id="request_type" name="request_type" class="form-select" required>
                                <option value="appointment">Appointment</option>
                                <option value="medical_assistance">Medical Assistance</option>
                                <option value="death_assistance">Death Assistance</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="requested_at" class="form-label">Date & Time</label>
                            <input type="datetime-local" id="requested_at" name="requested_at" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea id="description" name="description" rows="3" class="form-control"></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="mt-3">
                        <button class="btn btn-primary" type="submit">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-dark text-white">
                <h3>Your Requests</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Requested At</th>
                                <th>Description</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="requests-table"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include('footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const apiUrl = 'backend/routes/member_requests_api.php';

        function showMessage(message, type = 'info') {
            const msgDiv = document.getElementById('api-message');
            msgDiv.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
            setTimeout(() => msgDiv.innerHTML = '', 5000);
        }

        function loadRequests() {
            fetch(apiUrl).then(r => r.json()).then(d => {
                if (d.status !== 'success') {
                    showMessage(d.error || 'Failed to load', 'danger');
                    return;
                }
                const tb = document.getElementById('requests-table');
                tb.innerHTML = d.requests.map(r => `
                    <tr>
                        <td>${r.request_id}</td>
                        <td>${r.request_type.replace('_',' ')}</td>
                        <td>${r.requested_at}</td>
                        <td>${r.description ? r.description : ''}</td>
                        <td><span class="badge bg-${r.status==='approved'?'success':(r.status==='pending'?'warning text-dark':'secondary')}">${r.status}</span></td>
                    </tr>`).join('');
            }).catch(() => showMessage('Error loading requests', 'danger'));
        }
        document.addEventListener('DOMContentLoaded', () => {
            loadRequests();
            document.getElementById('request-form').addEventListener('submit', (e) => {
                e.preventDefault();
                const payload = {
                    action: 'create_request',
                    request_type: document.getElementById('request_type').value,
                    requested_at: document.getElementById('requested_at').value,
                    description: document.getElementById('description').value,
                    csrf_token: csrfToken
                };
                fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(r => r.json()).then(d => {
                        if (d.status === 'success') {
                            showMessage(d.message, 'success');
                            (e.target).reset();
                            loadRequests();
                        } else {
                            showMessage(d.error || 'Failed to create request', 'danger');
                        }
                    }).catch(() => showMessage('Error creating request', 'danger'));
            });
        });
    </script>
</body>

</html>
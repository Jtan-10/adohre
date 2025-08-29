<?php
define('APP_INIT', true);
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
include('admin_header.php');
require_once '../backend/db/db_connect.php';

// Fetch all member requests with user info
$rows = [];
$sql = "SELECT mr.*, u.first_name, u.last_name, u.email FROM member_requests mr JOIN users u ON mr.user_id = u.user_id ORDER BY mr.requested_at DESC";
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Requests - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script nonce="<?= $cspNonce ?>">
        const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
    </script>
</head>

<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <div class="container mt-5">
            <h1 class="mb-4">Member Requests</h1>
            <div id="api-message"></div>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Requested At</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Change Status</th>
                        </tr>
                    </thead>
                    <tbody id="requests-table">
                        <?php foreach ($rows as $r): ?>
                            <tr id="req-<?= htmlspecialchars($r['request_id']) ?>">
                                <td><?= htmlspecialchars($r['request_id']) ?></td>
                                <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name'] . ' (' . $r['email'] . ')') ?></td>
                                <td><?= htmlspecialchars($r['request_type']) ?></td>
                                <td><?= htmlspecialchars($r['requested_at']) ?></td>
                                <td><?= htmlspecialchars($r['description']) ?></td>
                                <td><span class="badge bg-<?= $r['status'] === 'approved' ? 'success' : ($r['status'] === 'pending' ? 'warning text-dark' : 'secondary') ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-secondary" data-status="submitted" data-id="<?= $r['request_id'] ?>">Submitted</button>
                                        <button class="btn btn-outline-warning" data-status="pending" data-id="<?= $r['request_id'] ?>">Pending</button>
                                        <button class="btn btn-outline-success" data-status="approved" data-id="<?= $r['request_id'] ?>">Approved</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= $cspNonce ?>">
        const apiUrl = '../backend/routes/member_requests_api.php';

        function showMessage(message, type = 'info') {
            const el = document.getElementById('api-message');
            el.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
            setTimeout(() => el.innerHTML = '', 4000);
        }
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('button[data-status]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const requestId = btn.getAttribute('data-id');
                    const status = btn.getAttribute('data-status');
                    fetch(apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'update_status',
                                request_id: parseInt(requestId),
                                status,
                                csrf_token: csrfToken
                            })
                        })
                        .then(r => r.json()).then(d => {
                            if (d.status === 'success') {
                                // update badge
                                const row = document.getElementById('req-' + requestId);
                                if (row) {
                                    const badgeCell = row.children[5].querySelector('span');
                                    badgeCell.className = 'badge ' + (status === 'approved' ? 'bg-success' : (status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary'));
                                    badgeCell.textContent = status;
                                }
                                showMessage('Status updated', 'success');
                            } else {
                                showMessage(d.error || 'Failed to update', 'danger');
                            }
                        }).catch(() => showMessage('Error updating status', 'danger'));
                });
            });
        });
    </script>
</body>

</html>
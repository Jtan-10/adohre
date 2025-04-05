<?php
// settings.php

// Define APP_INIT to allow inclusion of admin_header.php.
define('APP_INIT', true);

// Include the secure admin header (this starts session, connects to DB, sets CSP nonce, etc.)
require_once 'admin_header.php';

// Only allow admin access.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// The local upload directory is no longer needed since header logos are stored on S3.
// Removed: 
// $uploadDir = __DIR__ . '/uploads/settings/';
// if (!file_exists($uploadDir)) {
//     mkdir($uploadDir, 0755, true);
// }

// Helper function to get a setting value.
// Assumes a table "settings" with columns `key` (PRIMARY or UNIQUE) and `value`
function getSetting($key)
{
    global $conn;
    $stmt = $conn->prepare("SELECT value FROM settings WHERE `key` = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $stmt->bind_result($value);
    $stmt->fetch();
    $stmt->close();
    return $value;
}

$currentHeaderName = getSetting('header_name');
$currentHeaderLogo = getSetting('header_logo');

// Fetch recent audit logs (assumes an audit_logs table with columns: id, user_id, action, details, created_at).
$auditLogs = [];
$result = $conn->query("SELECT al.*, u.first_name, u.last_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.user_id ORDER BY al.created_at DESC LIMIT 100");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $auditLogs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Settings</title>
    <link rel="icon" href="../assets/logo.png" type="image/jpg" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style nonce="<?= $cspNonce ?>">
    .audit-log-table {
        max-height: 400px;
        overflow-y: auto;
    }
    </style>
</head>

<body>
    <div class="d-flex">

        <!-- Sidebar -->
        <?php require_once 'admin_sidebar.php'; ?>
        <div class="container mt-5">
            <h1>Admin Settings</h1>
            <!-- This div will display messages returned by API calls -->
            <div id="apiMessage"></div>

            <h2>Header Settings</h2>
            <form id="headerSettingsForm" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="header_name" class="form-label">Header Name</label>
                    <input type="text" class="form-control" id="header_name" name="header_name"
                        value="<?= htmlspecialchars($currentHeaderName) ?>">
                </div>
                <div class="mb-3">
                    <label for="header_logo" class="form-label">Header Logo</label>
                    <input type="file" class="form-control" id="header_logo" name="header_logo" accept="image/*">
                    <?php if ($currentHeaderLogo): ?>
                    <p>Current Logo: <img src="<?= htmlspecialchars($currentHeaderLogo) ?>" alt="Header Logo"
                            style="max-height: 50px;"></p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary">Update Header Settings</button>
            </form>

            <hr>

            <h2>Audit Logs</h2>
            <div class="audit-log-table">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody id="auditLogsTable">
                        <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                            <td><?= htmlspecialchars(trim($log['first_name'] . ' ' . $log['last_name'])) ?></td>
                            <td><?= htmlspecialchars($log['action']) ?></td>
                            <td><?= htmlspecialchars($log['details']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <hr>

            <h2>Database Backup &amp; Restore</h2>
            <!-- The backup form now submits normally to the backup endpoint so that the SQL file is downloaded -->
            <form id="backupForm" method="POST" action="../backend/routes/settings_api.php?action=backup_database">
                <button type="submit" class="btn btn-success">Backup Database</button>
            </form>
            <br>
            <form id="restoreForm" method="POST" enctype="multipart/form-data"
                action="../backend/routes/settings_api.php?action=restore_database">
                <div class="mb-3">
                    <label for="restore_file" class="form-label">Restore Database (Upload SQL file)</label>
                    <input type="file" class="form-control" id="restore_file" name="restore_file" accept=".sql">
                </div>
                <button type="submit" class="btn btn-warning">Restore Database</button>
            </form>
        </div>
    </div>
    <!-- JavaScript to handle API calls -->
    <script nonce="<?= $cspNonce ?>">
    // Utility function to display API messages.
    function showApiMessage(message, type = 'info') {
        const msgDiv = document.getElementById('apiMessage');
        msgDiv.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
        setTimeout(() => {
            msgDiv.innerHTML = '';
        }, 5000);
    }

    // Header Settings form submission.
    document.getElementById('headerSettingsForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const form = this;
        const headerNameInput = form.querySelector('#header_name');
        const headerLogoInput = form.querySelector('#header_logo');

        // Store original values loaded from PHP.
        const originalHeaderName = "<?= htmlspecialchars($currentHeaderName, ENT_QUOTES) ?>";

        // Check if header name changed and if a new file is selected.
        if (headerNameInput.value.trim() === originalHeaderName && headerLogoInput.files.length === 0) {
            showApiMessage("No changes detected.", "info");
            return;
        }

        const formData = new FormData(form);
        fetch('../backend/routes/settings_api.php?action=update_header_settings', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    showApiMessage(data.message, 'success');
                    // Optionally update the page (e.g. display new header name/logo in the UI)
                } else {
                    showApiMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error("Error updating header settings:", error);
                showApiMessage("An error occurred while updating header settings.", "danger");
            });
    });

    // Remove any JavaScript that intercepts the backup form submission.
    // The backupForm now submits normally so that the browser can handle the file download.
    // Similarly, the restoreForm submission remains handled via fetch if desired.

    // Restore Database form submission.
    document.getElementById('restoreForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        fetch('../backend/routes/settings_api.php?action=restore_database', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    showApiMessage(data.message, 'success');
                } else {
                    showApiMessage(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error("Error during restore:", error);
                showApiMessage("An error occurred during database restore.", "danger");
            });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
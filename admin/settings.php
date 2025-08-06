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

// Helper function to get a setting value.
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

// Get face validation setting (default to enabled if not set)
$currentFaceValidation = getSetting('face_validation_enabled') ?? 'true';

// Initialize face validation setting in database if it doesn't exist
if (getSetting('face_validation_enabled') === null) {
    $defaultValue = $_ENV['FACE_VALIDATION_ENABLED'] ?? 'true';
    $stmt = $conn->prepare("INSERT INTO settings (`key`, value) VALUES ('face_validation_enabled', ?)");
    if ($stmt) {
        $stmt->bind_param("s", $defaultValue);
        $stmt->execute();
        $stmt->close();
        $currentFaceValidation = $defaultValue;
    }
}

// Fetch recent audit logs.
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
                        <p>Current Logo:
                            <img src="../backend/routes/decrypt_image.php?image_url=<?= urlencode($currentHeaderLogo) ?>"
                                alt="Header Logo" style="max-height: 50px;">
                        </p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary">Update Header Settings</button>
            </form>

            <hr>

            <h2>Security Settings</h2>
            <form id="securitySettingsForm">
                <div class="mb-3">
                    <label class="form-label">Face Validation</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="face_validation_enabled"
                            name="face_validation_enabled" <?= $currentFaceValidation === 'true' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="face_validation_enabled">
                            Enable Face Validation for Login and Registration
                        </label>
                    </div>
                    <div class="form-text">
                        When enabled, users will be required to capture and validate their face during login and
                        registration.
                        When disabled, users can login and register without face validation.
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Update Security Settings</button>
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
            <form id="backupForm" method="POST" action="../backend/routes/settings_api.php?action=backup_database">
                <button type="submit" class="btn btn-success">Backup Database</button>
            </form>
            <br>
            <form id="restoreForm" method="POST" enctype="multipart/form-data"
                action="../backend/routes/settings_api.php?action=restore_database">
                <div class="mb-3">
                    <label for="restore_file" class="form-label">Restore Database (Upload Encrypted SQL file)</label>
                    <input type="file" class="form-control" id="restore_file" name="restore_file" accept=".sql.enc">
                </div>
                <div class="mb-3">
                    <label for="encryption_password" class="form-label">Encryption Password</label>
                    <input type="password" class="form-control" id="encryption_password" name="encryption_password"
                        required>
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
            const originalHeaderName = "<?= htmlspecialchars($currentHeaderName, ENT_QUOTES) ?>";
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
                    } else {
                        showApiMessage(data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error("Error updating header settings:", error);
                    showApiMessage("An error occurred while updating header settings.", "danger");
                });
        });

        // Security Settings form submission.
        document.getElementById('securitySettingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const faceValidationEnabled = form.querySelector('#face_validation_enabled').checked;

            const formData = new FormData();
            formData.append('face_validation_enabled', faceValidationEnabled ? 'true' : 'false');

            fetch('../backend/routes/settings_api.php?action=update_security_settings', {
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
                    console.error("Error updating security settings:", error);
                    showApiMessage("An error occurred while updating security settings.", "danger");
                });
        });

        // Wait for the DOM to fully load for modal-related operations.
        document.addEventListener('DOMContentLoaded', function() {
            // Backup Database form submission with password retrieval.
            document.getElementById('backupForm').addEventListener('submit', async function(e) {
                e.preventDefault();

                try {
                    // 1) Request the backup file as a Blob with credentials included.
                    const backupResponse = await fetch(
                        '../backend/routes/settings_api.php?action=backup_database', {
                            method: 'POST',
                            credentials: 'include'
                        }
                    );
                    console.log('Backup response status:', backupResponse.status);
                    if (!backupResponse.ok) {
                        throw new Error('Backup request failed with status ' + backupResponse.status);
                    }

                    // 2) Convert the response to a Blob (the .sql.enc file)
                    const backupBlob = await backupResponse.blob();
                    console.log('Received backup blob, size:', backupBlob.size);

                    // 3) Create a temporary link to trigger the file download
                    const downloadUrl = window.URL.createObjectURL(backupBlob);
                    const link = document.createElement('a');
                    link.href = downloadUrl;
                    link.download = 'database_backup_' + new Date().toISOString().replace(/[:.]/g,
                        '-') + '.sql.enc';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(downloadUrl);

                    // 4) Now fetch the backup password with credentials included.
                    const passwordResponse = await fetch(
                        '../backend/routes/settings_api.php?action=get_backup_password', {
                            credentials: 'include'
                        }
                    );
                    console.log('Password response status:', passwordResponse.status);
                    if (!passwordResponse.ok) {
                        throw new Error('Password request failed with status ' + passwordResponse
                            .status);
                    }
                    const passwordData = await passwordResponse.json();
                    console.log('Password data:', passwordData);

                    if (passwordData.status && passwordData.encryption_password) {
                        // Create the modal markup
                        const modalHtml = `
                        <div class="modal fade" id="backupPasswordModal" tabindex="-1" aria-labelledby="backupPasswordModalLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="backupPasswordModalLabel">Database Backup Encryption Password</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Your database backup has been downloaded. Please save this encryption password securely:</p>
                                        <div class="alert alert-warning">
                                            <strong>Encryption Password:</strong> 
                                            <code id="backupPasswordText">${passwordData.encryption_password}</code>
                                        </div>
                                        <button type="button" class="btn btn-primary" id="copyPasswordBtn">Copy Password</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                        // Remove existing modal if any.
                        document.getElementById('backupPasswordModal')?.remove();

                        // Insert the new modal.
                        const modalContainer = document.createElement('div');
                        modalContainer.innerHTML = modalHtml;
                        document.body.appendChild(modalContainer
                            .firstElementChild); // changed from firstChild

                        // Initialize & show the modal.
                        const backupPasswordModalEl = document.getElementById('backupPasswordModal');
                        if (backupPasswordModalEl) {
                            const backupPasswordModal = new bootstrap.Modal(backupPasswordModalEl);
                            backupPasswordModal.show();

                            // Copy-to-clipboard functionality.
                            const copyBtn = document.getElementById('copyPasswordBtn');
                            if (copyBtn) {
                                copyBtn.addEventListener('click', () => {
                                    const passwordText = document.getElementById(
                                        'backupPasswordText').textContent;
                                    navigator.clipboard.writeText(passwordText).then(() => {
                                        showApiMessage('Password copied to clipboard!',
                                            'success');
                                    });
                                });
                            } else {
                                console.error("Copy password button not found");
                            }
                        } else {
                            console.error("Backup modal element not found");
                        }

                        showApiMessage('Database backup successful', 'success');
                    } else {
                        showApiMessage('Backup succeeded, but no password returned.', 'warning');
                    }

                } catch (error) {
                    console.error("Backup error:", error);
                    showApiMessage('An error occurred during database backup: ' + error.message,
                        'danger');
                }
            });

            // Restore Database form submission.
            document.getElementById('restoreForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const form = this;
                const formData = new FormData(form);
                fetch('../backend/routes/settings_api.php?action=restore_database', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include'
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
        });
    </script>
</body>

</html>
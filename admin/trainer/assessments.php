<?php
define('APP_INIT', true); // Added to enable proper access.

// admin/trainer/assessments.php
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../admin_header.php';
require_once '../../backend/db/db_connect.php';

// Update security check to use APP_INIT instead of BASEPATH
if (!defined('APP_INIT')) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Direct access to this file is not allowed.';
    exit();
}

// Check if user is a trainer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'trainer' || !isset($_SESSION['user_id'])) {
    header('Location: /capstone-php/index.php');
    exit();
}

// Security: Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Generate CSP nonce for inline scripts
if (!isset($cspNonce)) {
    $cspNonce = base64_encode(random_bytes(16));
}

$trainerId = $_SESSION['user_id'];

// Fetch trainings created by this trainer with prepared statement
$stmt = $conn->prepare("SELECT training_id, title FROM trainings WHERE created_by = ?");
$stmt->bind_param("i", $trainerId);
$stmt->execute();
$result = $stmt->get_result();
$trainings = [];
while ($row = $result->fetch_assoc()) {
    $trainings[] = $row;
}
$stmt->close();

// Set security headers with nonce for inline scripts
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net https://static.cloudflareinsights.com 'nonce-{$cspNonce}'; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; frame-src https://docs.google.com;");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Trainer Assessments - ADOHRE</title>
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
    <style>
    .form-section {
        margin: 20px 0;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 10px;
        background: #f9f9f9;
    }

    table {
        margin-top: 15px;
    }

    .nav-btn {
        margin-bottom: 20px;
    }
    </style>
</head>

<body>
    <main class="container mt-4 mb-4">
        <h1 class="text-center">Assessments Management</h1>
        <div class="text-center nav-btn">
            <a href="dashboard.php" class="btn btn-info">Back to Trainings</a>
        </div>
        <!-- Assessment Form: Trainer inputs the URL of the assessment form -->
        <div class="form-section">
            <h3>Release Assessment to Participants</h3>
            <form id="assessmentForm" method="POST">
                <!-- CSRF Protection -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="mb-3">
                    <label for="assessmentTraining" class="form-label">Select Training</label>
                    <select id="assessmentTraining" name="training_id" class="form-control" required>
                        <option value="">-- Select Training --</option>
                        <?php
                        if (!empty($trainings)) {
                            foreach ($trainings as $t) {
                                echo '<option value="' . htmlspecialchars($t['training_id'], ENT_QUOTES, 'UTF-8') . '">' .
                                    htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="assessmentFormLink" class="form-label">Assessment Form Link</label>
                    <input type="url" id="assessmentFormLink" name="form_link" class="form-control"
                        placeholder="Enter the URL of your assessment form" pattern="https?://.+"
                        title="Please enter a valid URL starting with http:// or https://" required>
                </div>
                <!-- Preview Section: shows the embedded form -->
                <div id="formPreviewContainer" class="form-section" style="display: none;">
                    <h5>Form Preview</h5>
                    <iframe id="formPreview" src="" width="100%" height="500" frameborder="0"></iframe>
                </div>
                <!-- Hidden field for assessment id (if editing an existing one) -->
                <input type="hidden" id="assessmentId" name="assessment_id">
                <button type="submit" class="btn btn-success">Release Assessment</button>
                <!-- New Buttons -->
                <button type="button" class="btn btn-warning" id="configureCertificateBtn">Configure
                    Certificate</button>
                <button type="button" class="btn btn-primary" id="batchReleaseBtn">Batch Release Certificates</button>
            </form>
        </div>
        <!-- Participants Section: Automatically displays when a training is selected -->
        <div class="form-section">
            <h3>Participants</h3>
            <div id="participantsList"></div>
        </div>
    </main>

    <!-- Batch Release Certificates Modal -->
    <div class="modal fade" id="batchReleaseModal" tabindex="-1" aria-labelledby="batchReleaseModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="batchReleaseModalLabel">Batch Release Certificates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Batch release certificates for participants who have completed the assessments only. Certificates
                        will be sent to their registered emails.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmBatchRelease">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS with Subresource Integrity -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script nonce="<?php echo $cspNonce; ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const linkInput = document.getElementById('assessmentFormLink');
        const previewContainer = document.getElementById('formPreviewContainer');
        const previewFrame = document.getElementById('formPreview');
        const trainingSelect = document.getElementById('assessmentTraining');
        const participantsList = document.getElementById('participantsList');
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;

        // Security: Validate and sanitize URL input
        linkInput.addEventListener('input', function() {
            let link = linkInput.value.trim();
            if (link) {
                // Only allow specific domains for embedding
                if (!link.match(
                        /^https:\/\/(docs\.google\.com|forms\.microsoft\.com|forms\.office\.com)/i)) {
                    alert('Only Google Forms and Microsoft Forms are supported for security reasons.');
                    linkInput.value = '';
                    previewContainer.style.display = 'none';
                    return;
                }

                if (link.indexOf('docs.google.com/forms') !== -1 && link.indexOf('hl=') === -1) {
                    link += (link.indexOf('?') === -1) ? '?hl=en' : '&hl=en';
                }
                previewFrame.src = link;
                previewContainer.style.display = 'block';
            } else {
                previewContainer.style.display = 'none';
            }
        });

        // When a training is selected, automatically fetch assessment form link and participants.
        trainingSelect.addEventListener('change', function() {
            const trainingId = trainingSelect.value;
            if (trainingId) {
                if (!isNumeric(trainingId)) {
                    alert('Invalid training selection.');
                    return;
                }
                fetchAssessmentForm(trainingId);
                fetchParticipants(trainingId);
            } else {
                participantsList.innerHTML = '';
                linkInput.value = '';
                previewContainer.style.display = 'none';
            }
        });

        // Security: Input validation helper
        function isNumeric(value) {
            return /^\d+$/.test(value);
        }

        // Fetch the assessment form link for the selected training.
        function fetchAssessmentForm(trainingId) {
            fetch(
                    `../../backend/routes/assessment_manager.php?action=get_assessment_form&training_id=${encodeURIComponent(trainingId)}&csrf_token=${encodeURIComponent(csrfToken)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                )
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status && data.form_link) {
                        linkInput.value = data.form_link;
                        let link = data.form_link;
                        if (link.indexOf('docs.google.com/forms') !== -1 && link.indexOf('hl=') === -1) {
                            link += (link.indexOf('?') === -1) ? '?hl=en' : '&hl=en';
                        }
                        previewFrame.src = link;
                        previewContainer.style.display = 'block';
                    } else {
                        linkInput.value = '';
                        previewContainer.style.display = 'none';
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    alert('Failed to fetch assessment information. Please try again later.');
                    linkInput.value = '';
                    previewContainer.style.display = 'none';
                });
        }

        // Handle Assessment Form submission (release assessment)
        document.getElementById('assessmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'save_assessment_form');

            fetch('../../backend/routes/assessment_manager.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status) {
                        alert('Assessment released successfully!');
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error occurred'));
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    alert('Failed to connect to the server. Please try again later.');
                });
        });

        // Function to fetch participants for the selected training.
        function fetchParticipants(trainingId) {
            fetch(
                    `../../backend/routes/assessment_manager.php?action=fetch_participants&training_id=${encodeURIComponent(trainingId)}&csrf_token=${encodeURIComponent(csrfToken)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                )
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status) {
                        let html = `<table class="table">
                                  <thead>
                                    <tr>
                                      <th>Name</th>
                                      <th>Assessment Status</th>
                                      <th>Certificate Status</th>
                                      <th>Actions</th>
                                    </tr>
                                  </thead>
                                  <tbody>`;

                        if (data.participants && data.participants.length > 0) {
                            data.participants.forEach(participant => {
                                // Security: HTML escape all output
                                const name = escapeHTML(participant.first_name + ' ' + participant
                                    .last_name);
                                const assessmentStatus = escapeHTML(participant.assessment_status);
                                const certStatus = escapeHTML(participant.certificate_status ||
                                    'Not Released');
                                const userId = escapeHTML(participant.user_id);
                                const trainingId = escapeHTML(participant.training_id);

                                html += `<tr>
                                      <td>${name}</td>
                                      <td>${assessmentStatus}</td>
                                      <td>${certStatus}</td>
                                      <td>
                                        <button class="btn btn-sm btn-primary release-certificate" data-userid="${userId}" data-trainingid="${trainingId}">
                                          Release Certificate
                                        </button>
                                      </td>
                                      </tr>`;
                            });
                        } else {
                            html +=
                                `<tr><td colspan="4" class="text-center">No participants found</td></tr>`;
                        }

                        html += `</tbody></table>`;
                        participantsList.innerHTML = html;

                        // Security: HTML escape helper function - FIX TYPE ERROR
                        function escapeHTML(str) {
                            // Handle null, undefined, or non-string values
                            if (str === null || str === undefined) {
                                return '';
                            }
                            // Convert to string explicitly before using string methods
                            str = String(str);
                            return str
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#039;');
                        }

                        // Attach event listeners for individual "Release Certificate" buttons.
                        document.querySelectorAll('.release-certificate').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const userId = this.getAttribute('data-userid');
                                const trainingId = this.getAttribute('data-trainingid');

                                if (!isNumeric(userId) || !isNumeric(trainingId)) {
                                    alert('Invalid participant or training data');
                                    return;
                                }

                                if (confirm('Release certificate for this participant?')) {
                                    fetch(`../../backend/models/generate_certificate.php?action=release_certificate&user_id=${encodeURIComponent(userId)}&training_id=${encodeURIComponent(trainingId)}&csrf_token=${encodeURIComponent(csrfToken)}`, {
                                            headers: {
                                                'X-Requested-With': 'XMLHttpRequest'
                                            }
                                        })
                                        .then(response => {
                                            if (!response.ok) {
                                                throw new Error(
                                                    'Network response was not ok');
                                            }
                                            return response.text().then(text => {
                                                try {
                                                    return JSON.parse(text);
                                                } catch (e) {
                                                    console.error(
                                                        'Invalid JSON response:',
                                                        text);
                                                    throw new Error(
                                                        'Server returned invalid response'
                                                    );
                                                }
                                            });
                                        })
                                        .then(result => {
                                            if (result.status) {
                                                alert(
                                                    'Certificate released successfully.'
                                                );
                                                fetchParticipants(trainingId);
                                            } else {
                                                alert('Error: ' + (result.message ||
                                                    'Unknown error occurred'));
                                            }
                                        })
                                        .catch(err => {
                                            console.error('Fetch error:', err);
                                            alert(
                                                'Failed to release certificate. Please try again later.'
                                            );
                                        });
                                }
                            });
                        });
                    } else {
                        participantsList.innerHTML =
                            '<div class="alert alert-danger">Error fetching participants: ' +
                            (data.message || 'Unknown error') + '</div>';
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    participantsList.innerHTML =
                        '<div class="alert alert-danger">Failed to fetch participants. Please try again later.</div>';
                });
        }

        // Redirect to the certificate editor when "Configure Certificate" is clicked.
        document.getElementById('configureCertificateBtn').addEventListener('click', function() {
            const trainingId = trainingSelect.value;
            if (!trainingId) {
                alert('Please select a training first.');
                return;
            }

            if (!isNumeric(trainingId)) {
                alert('Invalid training selection.');
                return;
            }

            window.location.href =
                `certificate_editor.php?training_id=${encodeURIComponent(trainingId)}&csrf_token=${encodeURIComponent(csrfToken)}`;
        });

        // Batch Release Certificates: Show confirmation modal.
        document.getElementById('batchReleaseBtn').addEventListener('click', function() {
            const trainingId = trainingSelect.value;
            if (!trainingId) {
                alert('Please select a training first.');
                return;
            }

            if (!isNumeric(trainingId)) {
                alert('Invalid training selection.');
                return;
            }

            const batchModal = new bootstrap.Modal(document.getElementById('batchReleaseModal'));
            batchModal.show();
        });

        // When the user confirms batch release, fetch all participants, then release certificates in batch.
        document.getElementById('confirmBatchRelease').addEventListener('click', function() {
            const trainingId = trainingSelect.value;
            if (!trainingId) {
                alert('Please select a training first.');
                return;
            }

            if (!isNumeric(trainingId)) {
                alert('Invalid training selection.');
                return;
            }

            fetch(`../../backend/routes/assessment_manager.php?action=fetch_participants&training_id=${encodeURIComponent(trainingId)}&csrf_token=${encodeURIComponent(csrfToken)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status) {
                        // Collect all user_ids with certificate_status "Not Released" (or empty)
                        const userIds = data.participants
                            .filter(p => !p.certificate_status || p.certificate_status ===
                                'Not Released')
                            .map(p => p.user_id);

                        if (userIds.length === 0) {
                            alert('No certificates to release.');
                            return;
                        }

                        fetch('../../backend/models/generate_certificate.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({
                                    action: 'batch_release_certificates',
                                    training_id: trainingId,
                                    user_ids: userIds,
                                    csrf_token: csrfToken
                                })
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Network response was not ok');
                                }
                                return response.json();
                            })
                            .then(result => {
                                if (result.status) {
                                    alert('Certificates released successfully.');
                                    bootstrap.Modal.getInstance(document.getElementById(
                                        'batchReleaseModal')).hide();
                                    fetchParticipants(trainingId);
                                } else {
                                    alert('Error: ' + (result.message ||
                                        'Unknown error occurred'));
                                }
                            })
                            .catch(err => {
                                console.error('Fetch error:', err);
                                alert(
                                    'Failed to release certificates. Please try again later.'
                                );
                            });
                    } else {
                        alert('Error fetching participants: ' + (data.message ||
                            'Unknown error occurred'));
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    alert('Failed to fetch participants. Please try again later.');
                });
        });
    });
    </script>
</body>

</html>
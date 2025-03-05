<?php
// admin/trainer/assessments.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../admin_header.php';
require_once '../../backend/db/db_connect.php'; // ensure DB connection is available

// Check if user is a trainer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
    header('Location: /capstone-php/index.php');
    exit();
}

$trainerId = $_SESSION['user_id'];

// Fetch trainings created by this trainer
$stmt = $conn->prepare("SELECT training_id, title FROM trainings WHERE created_by = ?");
$stmt->bind_param("i", $trainerId);
$stmt->execute();
$result = $stmt->get_result();
$trainings = [];
while ($row = $result->fetch_assoc()) {
    $trainings[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Trainer Assessments - ADOHRE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
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
            <form id="assessmentForm">
                <div class="mb-3">
                    <label for="assessmentTraining" class="form-label">Select Training</label>
                    <select id="assessmentTraining" name="training_id" class="form-control" required>
                        <option value="">-- Select Training --</option>
                        <?php
                        if (!empty($trainings)) {
                            foreach ($trainings as $t) {
                                echo '<option value="' . htmlspecialchars($t['training_id']) . '">' . htmlspecialchars($t['title']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="assessmentFormLink" class="form-label">Assessment Form Link</label>
                    <input type="url" id="assessmentFormLink" name="form_link" class="form-control"
                        placeholder="Enter the URL of your assessment form" required>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const linkInput = document.getElementById('assessmentFormLink');
        const previewContainer = document.getElementById('formPreviewContainer');
        const previewFrame = document.getElementById('formPreview');
        const trainingSelect = document.getElementById('assessmentTraining');
        const participantsList = document.getElementById('participantsList');

        // Update the preview iframe when the assessment form link input changes
        linkInput.addEventListener('input', function() {
            let link = linkInput.value.trim();
            if (link) {
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
                fetchAssessmentForm(trainingId);
                fetchParticipants(trainingId);
            } else {
                participantsList.innerHTML = '';
                linkInput.value = '';
                previewContainer.style.display = 'none';
            }
        });

        // Fetch the assessment form link for the selected training.
        function fetchAssessmentForm(trainingId) {
            fetch(
                    `../../backend/routes/assessment_manager.php?action=get_assessment_form&training_id=${trainingId}`
                )
                .then(response => response.json())
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
                    console.error(err);
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
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        alert('Assessment released successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to connect to the server.');
                });
        });

        // Function to fetch participants for the selected training.
        function fetchParticipants(trainingId) {
            fetch(
                    `../../backend/routes/assessment_manager.php?action=fetch_participants&training_id=${trainingId}`
                )
                .then(response => response.json())
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
                        data.participants.forEach(participant => {
                            html += `<tr>
                                      <td>${participant.first_name} ${participant.last_name}</td>
                                      <td>${participant.assessment_status}</td>
                                      <td>${participant.certificate_status || 'Not Released'}</td>
                                      <td>
                                        <button class="btn btn-sm btn-primary release-certificate" data-userid="${participant.user_id}" data-trainingid="${participant.training_id}">
                                          Release Certificate
                                        </button>
                                      </td>
                                      </tr>`;
                        });
                        html += `</tbody></table>`;
                        participantsList.innerHTML = html;

                        // Attach event listeners for individual "Release Certificate" buttons.
                        document.querySelectorAll('.release-certificate').forEach(btn => {
                            btn.addEventListener('click', function() {
                                const userId = this.getAttribute('data-userid');
                                const trainingId = this.getAttribute('data-trainingid');
                                if (confirm('Release certificate for this participant?')) {
                                    fetch('../../backend/models/generate_certificate.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                action: 'release_certificate',
                                                user_id: userId,
                                                training_id: trainingId
                                            })
                                        })

                                        .then(response => response.json())
                                        .then(result => {
                                            if (result.status) {
                                                alert('Certificate released.');
                                                fetchParticipants(trainingId);
                                            } else {
                                                alert('Error: ' + result.message);
                                            }
                                        })
                                        .catch(err => {
                                            console.error(err);
                                            alert('Failed to release certificate.');
                                        });
                                }
                            });
                        });
                    } else {
                        alert('Error fetching participants: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to fetch participants.');
                });
        }

        // Redirect to the certificate editor (new file) when "Configure Certificate" is clicked.
        document.getElementById('configureCertificateBtn').addEventListener('click', function() {
            const trainingId = trainingSelect.value;
            if (!trainingId) {
                alert('Please select a training first.');
                return;
            }
            // Redirect to certificate_editor.php with the training id as a query parameter.
            window.location.href = `certificate_editor.php?training_id=${trainingId}`;
        });

        // Handle Batch Release Certificates button click to show confirmation modal.
        document.getElementById('batchReleaseBtn').addEventListener('click', function() {
            const trainingId = trainingSelect.value;
            if (!trainingId) {
                alert('Please select a training first.');
                return;
            }
            const batchModal = new bootstrap.Modal(document.getElementById('batchReleaseModal'));
            batchModal.show();
        });

        // When the user confirms batch release, trigger the certificate generation process.
        document.getElementById('confirmBatchRelease').addEventListener('click', function() {
            const trainingId = trainingSelect.value;
            // Call the certificate generator endpoint to process batch release.
            fetch('../../backend/models/generate_certificate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'release_certificate',
                        user_id: userId,
                        training_id: trainingId
                    })
                })

                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        alert('Certificates released successfully.');
                        bootstrap.Modal.getInstance(document.getElementById('batchReleaseModal'))
                            .hide();
                        fetchParticipants(trainingId);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to release certificates.');
                });
        });

    });
    </script>
</body>

</html>
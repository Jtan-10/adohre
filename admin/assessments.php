<?php
// admin/assessments.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'admin_header.php';
require_once '../backend/db/db_connect.php'; // ensure DB connection is available

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Fetch all trainings (admins can manage assessments for any training)
$stmt = $conn->prepare("SELECT training_id, title FROM trainings ORDER BY title ASC");
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
    <title>Admin Assessments Management - ADOHRE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
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
    </style>
</head>

<body>
    <div class="d-flex">

        <?php require_once 'admin_sidebar.php'; ?>

        <main class="container mt-4 mb-4">
            <h1 class="text-center">Assessments Management</h1>

            <!-- Assessment Form Section -->
            <div class="form-section">
                <h3>Release Assessment Form</h3>
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
                    <button type="submit" class="btn btn-success">Release Assessment Form</button>
                </form>
            </div>

            <!-- Participants Section -->
            <div class="form-section">
                <h3>Participants</h3>
                <div id="participantsList"></div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const linkInput = document.getElementById('assessmentFormLink');
        const previewContainer = document.getElementById('formPreviewContainer');
        const previewFrame = document.getElementById('formPreview');
        const trainingSelect = document.getElementById('assessmentTraining');
        const participantsList = document.getElementById('participantsList');

        // Update preview when the assessment form link changes.
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

        // When a training is selected, fetch the assessment form link and participants.
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
                    `/capstone-php/backend/routes/assessment_manager.php?action=get_assessment_form&training_id=${trainingId}`
                )
                .then(response => response.json())
                .then(data => {
                    if (data.status && data.form_link) {
                        linkInput.value = data.form_link;
                        let link = data.form_link;
                        if (link.indexOf('docs.google.com/forms') !== -1 && link.indexOf('hl=') === -
                            1) {
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

        // Handle Assessment Form submission (release assessment form).
        document.getElementById('assessmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'save_assessment_form');
            fetch('/capstone-php/backend/routes/assessment_manager.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        alert('Assessment form released successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to connect to the server.');
                });
        });

        // Fetch participants for the selected training.
        function fetchParticipants(trainingId) {
            fetch(
                    `/capstone-php/backend/routes/assessment_manager.php?action=fetch_participants&training_id=${trainingId}`
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
                                    fetch('../../backend/routes/generate_certificate.php', {
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
                        participantsList.innerHTML = '<p>No participants found for this training.</p>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to fetch participants.');
                });
        }
    });
    </script>
</body>

</html>
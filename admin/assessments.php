<?php
define('APP_INIT', true); // Added to enable proper access.
// admin/assessments.php

// Disable error reporting for production
// error_reporting(E_ALL);
// ini_set('display_errors', 1');
error_reporting(0);
ini_set('display_errors', 0);

// Ensure session is started and generate CSRF token if not already set
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'admin_header.php';
require_once '../backend/db/db_connect.php'; // ensure DB connection is available

// Check if user is admin
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'trainer')) {
    header('Location: ../index.php');
    exit();
}

// Fetch all trainings (admins can manage assessments for any training)
if ($_SESSION['role'] === 'admin') {
    $stmt = $conn->prepare("SELECT training_id, title FROM trainings ORDER BY title ASC");
    $stmt->execute();
} else { // trainers can only see their own trainings
    $stmt = $conn->prepare("SELECT training_id, title FROM trainings WHERE created_by = ? ORDER BY title ASC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
}
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

    /* Styling for the Google Sheet Analytics card */
    .sheet-card {
        margin-top: 20px;
        border: 1px solid #ddd;
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .sheet-card-header {
        background: #f1f1f1;
        padding: 10px 15px;
        border-bottom: 1px solid #ddd;
    }

    .sheet-card-body {
        padding: 15px;
    }

    .sheet-iframe {
        width: 100%;
        height: 500px;
        border: 0;
    }

    /* Question builder styles */
    .question-card {
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 15px;
        background-color: #fff;
        position: relative;
    }

    .question-actions {
        position: absolute;
        top: 10px;
        right: 10px;
    }

    .options-container {
        margin-top: 10px;
    }

    .option-item {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
    }

    .option-item button {
        margin-left: 10px;
    }

    .add-option-btn {
        margin-top: 5px;
    }

    .drag-handle {
        cursor: move;
        padding: 5px;
        margin-right: 5px;
        color: #aaa;
    }

    .question-type-selector {
        margin-bottom: 15px;
    }

    /* Assessment preview styles */
    .assessment-preview {
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 5px;
        background-color: #fff;
        margin-top: 20px;
    }

    .preview-question {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .question-required {
        color: red;
        margin-left: 5px;
    }

    /* Form type switcher */
    .form-type-switcher {
        margin-bottom: 20px;
    }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <main class="container mt-4 mb-4">
            <h1 class="text-center">Assessments and Evaluation Management</h1>

            <!-- Assessment Form Type Selector -->
            <div class="form-type-switcher">
                <ul class="nav nav-tabs" id="formTypeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="google-form-tab" data-bs-toggle="tab"
                            data-bs-target="#google-form" type="button" role="tab" aria-controls="google-form"
                            aria-selected="true">
                            Google Form
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="custom-form-tab" data-bs-toggle="tab" data-bs-target="#custom-form"
                            type="button" role="tab" aria-controls="custom-form" aria-selected="false">
                            Custom Assessment Form
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content" id="formTypeContent">
                <!-- Google Form Tab -->
                <div class="tab-pane fade show active" id="google-form" role="tabpanel"
                    aria-labelledby="google-form-tab">
                    <!-- Original Google Form content -->
                    <div class="form-section">
                        <h3>Release Assessment and Evaluation Form (Google Form)</h3>
                        <form id="assessmentForm">
                            <!-- CSRF Token Field -->
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

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
                                <label for="assessmentFormLink" class="form-label">Assessment and Evaluation Form
                                    Link</label>
                                <input type="url" id="assessmentFormLink" name="form_link" class="form-control"
                                    placeholder="Enter the URL of your assessment form" required>
                            </div>

                            <!-- Preview Section: shows the embedded form -->
                            <div id="formPreviewContainer" class="form-section" style="display: none;">
                                <h5>Form Preview</h5>
                                <iframe id="formPreview" src="" width="100%" height="500" frameborder="0"></iframe>
                            </div>

                            <!-- Google Sheet Analytics Section -->
                            <div class="sheet-card">
                                <div class="sheet-card-header">
                                    <h5 class="mb-0">Google Sheet Analytics</h5>
                                </div>
                                <div class="sheet-card-body">
                                    <!-- Replace the iframe src with your public Google Sheet URL as needed -->
                                    <iframe class="sheet-iframe"
                                        src="https://docs.google.com/spreadsheets/d/e/2PACX-1vS4QUctqUgDxzc4Ni1WtOkta8KEfWLQVdAiOYyMFmXflvPaOfdxLPBgdEtT88bzfTqwfi5U7Xv72Hk0/pubhtml?widget=true&amp;headers=false"></iframe>
                                </div>
                            </div>

                            <!-- Hidden field for assessment id (if editing an existing one) -->
                            <input type="hidden" id="assessmentId" name="assessment_id">

                            <button type="submit" class="btn btn-success">Release Assessment and Evaluation
                                Form</button>
                            <button type="button" class="btn btn-warning" id="configureCertificateBtn">Configure
                                Certificate</button>
                            <button type="button" class="btn btn-primary" id="batchReleaseBtn">Batch Release
                                Certificates</button>
                        </form>
                    </div>
                </div>

                <!-- Custom Assessment Form Tab -->
                <div class="tab-pane fade" id="custom-form" role="tabpanel" aria-labelledby="custom-form-tab">
                    <div class="form-section">
                        <h3>Create Custom Assessment Form</h3>

                        <div class="mb-3">
                            <label for="customFormTraining" class="form-label">Select Training</label>
                            <select id="customFormTraining" class="form-control" required>
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

                        <!-- Question Builder Section -->
                        <div id="questionBuilderSection" style="display: none;">
                            <h4>Assessment Questions</h4>
                            <p>Create questions for your assessment form below. Drag questions to reorder.</p>

                            <!-- Add New Question Button -->
                            <button id="addQuestionBtn" class="btn btn-primary mb-3">
                                <i class="bi bi-plus-circle"></i> Add New Question
                            </button>

                            <!-- Questions Container -->
                            <div id="questionsContainer"></div>

                            <!-- Create Assessment Button -->
                            <button id="saveQuestionsBtn" class="btn btn-success mt-3">
                                <i class="bi bi-check-circle"></i> Save Assessment
                            </button>

                            <!-- Preview Button -->
                            <button id="previewAssessmentBtn" class="btn btn-info mt-3 ms-2">
                                <i class="bi bi-eye"></i> Preview Assessment
                            </button>
                        </div>

                        <!-- Question Builder Modal -->
                        <div class="modal fade" id="questionBuilderModal" tabindex="-1"
                            aria-labelledby="questionBuilderModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="questionBuilderModalLabel">Add Question</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="questionForm">
                                            <input type="hidden" id="questionId" name="question_id" value="">

                                            <div class="mb-3">
                                                <label for="questionType" class="form-label">Question Type</label>
                                                <select id="questionType" name="question_type" class="form-control"
                                                    required>
                                                    <option value="text">Short Answer</option>
                                                    <option value="textarea">Paragraph</option>
                                                    <option value="multiple_choice">Multiple Choice</option>
                                                    <option value="checkbox">Checkboxes</option>
                                                    <option value="rating">Rating Scale</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label for="questionText" class="form-label">Question Text</label>
                                                <input type="text" id="questionText" name="question_text"
                                                    class="form-control" required>
                                            </div>

                                            <div class="mb-3 form-check">
                                                <input type="checkbox" id="questionRequired" name="required"
                                                    class="form-check-input" checked>
                                                <label for="questionRequired" class="form-check-label">Required</label>
                                            </div>

                                            <!-- Options container (for multiple choice, checkbox, rating) -->
                                            <div id="optionsContainer" style="display: none;">
                                                <div class="mb-3">
                                                    <label class="form-label">Options</label>
                                                    <div id="optionsList"></div>
                                                    <button type="button" id="addOptionBtn"
                                                        class="btn btn-sm btn-secondary mt-2">
                                                        <i class="bi bi-plus"></i> Add Option
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Rating Scale Options (for rating type) -->
                                            <div id="ratingOptions" style="display: none;">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <label for="minRating" class="form-label">Min Rating</label>
                                                        <input type="number" id="minRating" class="form-control"
                                                            value="1" min="0" max="10">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="maxRating" class="form-label">Max Rating</label>
                                                        <input type="number" id="maxRating" class="form-control"
                                                            value="5" min="1" max="10">
                                                    </div>
                                                </div>
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <label for="minLabel" class="form-label">Min Label</label>
                                                        <input type="text" id="minLabel" class="form-control"
                                                            placeholder="e.g., Poor">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="maxLabel" class="form-label">Max Label</label>
                                                        <input type="text" id="maxLabel" class="form-control"
                                                            placeholder="e.g., Excellent">
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" id="saveQuestionBtn" class="btn btn-primary">Save
                                            Question</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Assessment Preview Modal -->
                        <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel"
                            aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="previewModalLabel">Assessment Preview</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div id="assessmentPreview" class="assessment-preview"></div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Participants Section -->
            <div class="form-section">
                <h3>Participants</h3>
                <div id="participantsList"></div>
            </div>

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
                            <p>Batch release certificates for participants who have completed the assessments and
                                evaluation.
                                Certificates will be sent to their registered emails.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmBatchRelease">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script nonce="<?= $cspNonce ?>">
    document.addEventListener('DOMContentLoaded', function() {
        // Original Google Form functionality
        const linkInput = document.getElementById('assessmentFormLink');
        const previewContainer = document.getElementById('formPreviewContainer');
        const previewFrame = document.getElementById('formPreview');
        const trainingSelect = document.getElementById('assessmentTraining');
        const participantsList = document.getElementById('participantsList');

        // When the form link changes, update the preview.
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

        // When a training is selected in Google Form tab, fetch the assessment form link and participants.
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
                                    fetch('../backend/routes/generate_certificate.php', {
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

        // Configure Certificate button event.
        document.getElementById('configureCertificateBtn').addEventListener('click', function() {
            const trainingId = document.getElementById('assessmentTraining').value;
            if (!trainingId) {
                alert('Please select a training first.');
                return;
            }
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            window.location.href =
                `trainer/certificate_editor.php?training_id=${encodeURIComponent(trainingId)}&csrf_token=${encodeURIComponent(csrfToken)}`;
        });

        // Batch Release Certificates button event.
        document.getElementById('batchReleaseBtn').addEventListener('click', function() {
            const trainingId = document.getElementById('assessmentTraining').value;
            if (!trainingId) {
                alert('Please select a training first.');
                return;
            }
            const batchModal = new bootstrap.Modal(document.getElementById('batchReleaseModal'));
            batchModal.show();
        });

        // Confirm Batch Release event.
        document.getElementById('confirmBatchRelease').addEventListener('click', function() {
            const trainingId = document.getElementById('assessmentTraining').value;
            if (!trainingId) {
                alert('Please select a training first.');
                return;
            }
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            fetch(
                    `../backend/routes/assessment_manager.php?action=fetch_participants&training_id=${encodeURIComponent(trainingId)}&csrf_token=${encodeURIComponent(csrfToken)}`
                )
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        const userIds = data.participants.filter(p => !p.certificate_status || p
                                .certificate_status === 'Not Released')
                            .map(p => p.user_id);
                        if (userIds.length === 0) {
                            alert('No certificates to release.');
                            return;
                        }
                        return fetch('../backend/models/generate_certificate.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'batch_release_certificates',
                                training_id: trainingId,
                                user_ids: userIds,
                                csrf_token: csrfToken
                            })
                        });
                    } else {
                        throw new Error(data.message || 'Unknown error fetching participants.');
                    }
                })
                .then(response => response.json())
                .then(result => {
                    if (result.status) {
                        alert('Certificates released successfully.');
                        bootstrap.Modal.getInstance(document.getElementById('batchReleaseModal'))
                            .hide();
                    } else {
                        alert('Error: ' + (result.message || 'Unknown error occurred'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to release certificates. Please try again later.');
                });
        });

        // ===== CUSTOM ASSESSMENT FORM FUNCTIONALITY =====

        // Variables for the custom assessment form
        const customFormTraining = document.getElementById('customFormTraining');
        const questionBuilderSection = document.getElementById('questionBuilderSection');
        const questionsContainer = document.getElementById('questionsContainer');
        const addQuestionBtn = document.getElementById('addQuestionBtn');
        const saveQuestionsBtn = document.getElementById('saveQuestionsBtn');
        const previewAssessmentBtn = document.getElementById('previewAssessmentBtn');

        // Question form elements
        const questionForm = document.getElementById('questionForm');
        const questionId = document.getElementById('questionId');
        const questionType = document.getElementById('questionType');
        const questionText = document.getElementById('questionText');
        const questionRequired = document.getElementById('questionRequired');
        const optionsContainer = document.getElementById('optionsContainer');
        const optionsList = document.getElementById('optionsList');
        const addOptionBtn = document.getElementById('addOptionBtn');
        const ratingOptions = document.getElementById('ratingOptions');
        const minRating = document.getElementById('minRating');
        const maxRating = document.getElementById('maxRating');
        const minLabel = document.getElementById('minLabel');
        const maxLabel = document.getElementById('maxLabel');

        // Modals
        const questionBuilderModal = new bootstrap.Modal(document.getElementById('questionBuilderModal'));
        const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        const saveQuestionBtn = document.getElementById('saveQuestionBtn');
        const assessmentPreview = document.getElementById('assessmentPreview');

        // When training is selected in custom form tab, load existing questions
        customFormTraining.addEventListener('change', function() {
            const trainingId = customFormTraining.value;
            if (trainingId) {
                questionBuilderSection.style.display = 'block';
                loadExistingQuestions(trainingId);
                // Also load participants for the selected training
                fetchParticipants(trainingId);
            } else {
                questionBuilderSection.style.display = 'none';
                questionsContainer.innerHTML = '';
            }
        });

        // Load existing questions for the selected training
        function loadExistingQuestions(trainingId) {
            fetch(
                    `/capstone-php/backend/routes/assessment_manager.php?action=get_questions&training_id=${trainingId}`
                )
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        questionsContainer.innerHTML = ''; // Clear existing questions
                        if (data.questions.length > 0) {
                            data.questions.forEach(question => {
                                renderQuestionCard(question);
                            });
                            // Initialize sortable after rendering questions
                            initSortable();
                        } else {
                            questionsContainer.innerHTML =
                                '<p class="text-muted">No questions added yet. Click "Add New Question" to get started.</p>';
                        }
                    } else {
                        alert('Error loading questions: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to load questions. Please try again.');
                });
        }

        // Add new question button click
        addQuestionBtn.addEventListener('click', function() {
            // Reset form
            questionForm.reset();
            questionId.value = '';
            questionType.value = 'text';
            optionsList.innerHTML = '';
            optionsContainer.style.display = 'none';
            ratingOptions.style.display = 'none';

            // Show modal
            questionBuilderModal.show();
        });

        // Question type change event
        questionType.addEventListener('change', function() {
            const type = questionType.value;

            // Show/hide options container based on question type
            if (type === 'multiple_choice' || type === 'checkbox') {
                optionsContainer.style.display = 'block';
                ratingOptions.style.display = 'none';

                // Add default options if none exist
                if (optionsList.children.length === 0) {
                    addOption('Option 1');
                    addOption('Option 2');
                }
            } else if (type === 'rating') {
                optionsContainer.style.display = 'none';
                ratingOptions.style.display = 'block';
            } else {
                optionsContainer.style.display = 'none';
                ratingOptions.style.display = 'none';
            }
        });

        // Add option button click
        addOptionBtn.addEventListener('click', function() {
            addOption('');
        });

        // Add option function
        function addOption(value = '') {
            const optionIndex = optionsList.children.length + 1;
            const optionDiv = document.createElement('div');
            optionDiv.className = 'input-group mb-2 option-item';
            optionDiv.innerHTML = `
                <input type="text" class="form-control option-input" value="${value}" placeholder="Option ${optionIndex}">
                <button type="button" class="btn btn-outline-danger remove-option">
                    <i class="bi bi-trash"></i>
                </button>
            `;

            // Add remove event listener
            optionDiv.querySelector('.remove-option').addEventListener('click', function() {
                optionDiv.remove();
            });

            optionsList.appendChild(optionDiv);
        }

        // Save question button click
        saveQuestionBtn.addEventListener('click', function() {
            // Validate form
            if (!questionText.value.trim()) {
                alert('Please enter question text');
                return;
            }

            // Gather options if applicable
            let options = null;
            if (questionType.value === 'multiple_choice' || questionType.value === 'checkbox') {
                options = [];
                const optionInputs = optionsList.querySelectorAll('.option-input');

                if (optionInputs.length < 2) {
                    alert('Please add at least two options');
                    return;
                }

                optionInputs.forEach(input => {
                    if (input.value.trim()) {
                        options.push(input.value.trim());
                    }
                });

                if (options.length < 2) {
                    alert('Please provide at least two non-empty options');
                    return;
                }
            } else if (questionType.value === 'rating') {
                // For rating questions, store min, max, and labels
                const min = parseInt(minRating.value) || 1;
                const max = parseInt(maxRating.value) || 5;

                if (min >= max) {
                    alert('Maximum rating must be greater than minimum rating');
                    return;
                }

                options = {
                    min: min,
                    max: max,
                    minLabel: minLabel.value.trim(),
                    maxLabel: maxLabel.value.trim()
                };
            }

            // Prepare question data
            const question = {
                question_id: questionId.value || null,
                question_type: questionType.value,
                question_text: questionText.value.trim(),
                required: questionRequired.checked,
                options: options
            };

            // Add to DOM (for preview purposes)
            if (questionId.value) {
                // Update existing question
                updateQuestion(question);
            } else {
                // Create new question
                createQuestion(question);
            }

            // Close modal
            questionBuilderModal.hide();
        });

        // Create new question
        function createQuestion(question) {
            const trainingId = customFormTraining.value;
            if (!trainingId) {
                alert('Please select a training first');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_question');
            formData.append('training_id', trainingId);
            formData.append('question_type', question.question_type);
            formData.append('question_text', question.question_text);
            formData.append('required', question.required ? 1 : 0);

            if (question.options) {
                formData.append('options', JSON.stringify(question.options));
            }

            // Get the question order
            const currentQuestions = document.querySelectorAll('.question-card');
            formData.append('question_order', currentQuestions.length);

            fetch('/capstone-php/backend/routes/assessment_manager.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        // Set the question ID returned from server
                        question.question_id = data.question_id;
                        // Render the question card
                        renderQuestionCard(question);
                        // Re-initialize sortable
                        initSortable();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to save question. Please try again.');
                });
        }

        // Update existing question
        function updateQuestion(question) {
            const formData = new FormData();
            formData.append('action', 'update_question');
            formData.append('question_id', question.question_id);
            formData.append('question_type', question.question_type);
            formData.append('question_text', question.question_text);
            formData.append('required', question.required ? 1 : 0);

            if (question.options) {
                formData.append('options', JSON.stringify(question.options));
            }

            // Get the question's current order from the DOM
            const questionCards = Array.from(document.querySelectorAll('.question-card'));
            const questionCard = document.getElementById(`question-${question.question_id}`);
            const questionOrder = questionCards.indexOf(questionCard);
            formData.append('question_order', questionOrder >= 0 ? questionOrder : 0);

            fetch('/capstone-php/backend/routes/assessment_manager.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        // Update the question card in the DOM
                        const existingCard = document.getElementById(`question-${question.question_id}`);
                        if (existingCard) {
                            const newCard = createQuestionCardElement(question);
                            existingCard.replaceWith(newCard);
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to update question. Please try again.');
                });
        }

        // Delete question
        function deleteQuestion(questionId) {
            if (confirm('Are you sure you want to delete this question?')) {
                const formData = new FormData();
                formData.append('action', 'delete_question');
                formData.append('question_id', questionId);

                fetch('/capstone-php/backend/routes/assessment_manager.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            // Remove from DOM
                            const questionCard = document.getElementById(`question-${questionId}`);
                            if (questionCard) {
                                questionCard.remove();
                            }

                            // Check if there are no more questions
                            if (questionsContainer.children.length === 0) {
                                questionsContainer.innerHTML =
                                    '<p class="text-muted">No questions added yet. Click "Add New Question" to get started.</p>';
                            }
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Failed to delete question. Please try again.');
                    });
            }
        }

        // Edit question
        function editQuestion(questionId) {
            // Reset form
            questionForm.reset();

            // Find the question card
            const questionCard = document.getElementById(`question-${questionId}`);
            if (!questionCard) return;

            // Get question data from data attributes
            const questionData = {
                question_id: questionId,
                question_type: questionCard.getAttribute('data-type'),
                question_text: questionCard.getAttribute('data-text'),
                required: questionCard.getAttribute('data-required') === 'true',
                options: questionCard.getAttribute('data-options') ?
                    JSON.parse(questionCard.getAttribute('data-options')) : null
            };

            // Populate form
            questionId.value = questionData.question_id;
            questionType.value = questionData.question_type;
            questionText.value = questionData.question_text;
            questionRequired.checked = questionData.required;

            // Handle options based on question type
            optionsList.innerHTML = '';
            optionsContainer.style.display = 'none';
            ratingOptions.style.display = 'none';

            if (questionData.question_type === 'multiple_choice' || questionData.question_type === 'checkbox') {
                optionsContainer.style.display = 'block';

                if (Array.isArray(questionData.options)) {
                    questionData.options.forEach(option => {
                        addOption(option);
                    });
                } else {
                    // Add defaults if no options found
                    addOption('Option 1');
                    addOption('Option 2');
                }
            } else if (questionData.question_type === 'rating') {
                ratingOptions.style.display = 'block';

                if (questionData.options) {
                    minRating.value = questionData.options.min || 1;
                    maxRating.value = questionData.options.max || 5;
                    minLabel.value = questionData.options.minLabel || '';
                    maxLabel.value = questionData.options.maxLabel || '';
                }
            }

            // Show modal
            questionBuilderModal.show();
        }

        // Render question card
        function renderQuestionCard(question) {
            const questionCard = createQuestionCardElement(question);

            // If we have a "no questions" message, remove it
            const noQuestionsMsg = questionsContainer.querySelector('p.text-muted');
            if (noQuestionsMsg) {
                noQuestionsMsg.remove();
            }

            questionsContainer.appendChild(questionCard);
        }

        // Create question card element
        function createQuestionCardElement(question) {
            const card = document.createElement('div');
            card.className = 'question-card';
            card.id = `question-${question.question_id}`;

            // Store question data as attributes for easy access
            card.setAttribute('data-id', question.question_id);
            card.setAttribute('data-type', question.question_type);
            card.setAttribute('data-text', question.question_text);
            card.setAttribute('data-required', question.required);
            if (question.options) {
                card.setAttribute('data-options', JSON.stringify(question.options));
            }

            // Create question card content
            let optionsHtml = '';

            if (question.question_type === 'multiple_choice' || question.question_type === 'checkbox') {
                if (Array.isArray(question.options)) {
                    const inputType = question.question_type === 'multiple_choice' ? 'radio' : 'checkbox';
                    optionsHtml = '<div class="options-container mt-2">';
                    question.options.forEach((option, index) => {
                        optionsHtml += `
                            <div class="form-check">
                                <input class="form-check-input" type="${inputType}" disabled>
                                <label class="form-check-label">${option}</label>
                            </div>
                        `;
                    });
                    optionsHtml += '</div>';
                }
            } else if (question.question_type === 'rating' && question.options) {
                optionsHtml = `<div class="rating-container mt-2">
                    <div class="d-flex justify-content-between">
                        <span>${question.options.minLabel || question.options.min}</span>`;

                // Add rating buttons
                optionsHtml += '<div class="btn-group" role="group">';
                for (let i = question.options.min; i <= question.options.max; i++) {
                    optionsHtml +=
                        `<button type="button" class="btn btn-outline-secondary" disabled>${i}</button>`;
                }
                optionsHtml += '</div>';

                optionsHtml += `<span>${question.options.maxLabel || question.options.max}</span>
                    </div>
                </div>`;
            } else if (question.question_type === 'text') {
                optionsHtml =
                    '<input type="text" class="form-control mt-2" placeholder="Short answer text" disabled>';
            } else if (question.question_type === 'textarea') {
                optionsHtml =
                    '<textarea class="form-control mt-2" placeholder="Long answer text" rows="3" disabled></textarea>';
            }

            // Question type badge
            let typeBadge = '';
            switch (question.question_type) {
                case 'text':
                    typeBadge = '<span class="badge bg-primary">Short Answer</span>';
                    break;
                case 'textarea':
                    typeBadge = '<span class="badge bg-info">Paragraph</span>';
                    break;
                case 'multiple_choice':
                    typeBadge = '<span class="badge bg-success">Multiple Choice</span>';
                    break;
                case 'checkbox':
                    typeBadge = '<span class="badge bg-warning">Checkboxes</span>';
                    break;
                case 'rating':
                    typeBadge = '<span class="badge bg-secondary">Rating</span>';
                    break;
            }

            card.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="drag-handle me-2">
                        <i class="bi bi-grip-vertical"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-2">
                            ${typeBadge}
                            <h5 class="ms-2 mb-0">${question.question_text} ${question.required ? '<span class="question-required">*</span>' : ''}</h5>
                        </div>
                        ${optionsHtml}
                    </div>
                    <div class="question-actions">
                        <button type="button" class="btn btn-sm btn-outline-primary edit-question" data-id="${question.question_id}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-question" data-id="${question.question_id}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;

            // Add event listeners for edit and delete buttons
            card.querySelector('.edit-question').addEventListener('click', function() {
                editQuestion(question.question_id);
            });

            card.querySelector('.delete-question').addEventListener('click', function() {
                deleteQuestion(question.question_id);
            });

            return card;
        }

        // Initialize sortable to allow drag-and-drop reordering
        function initSortable() {
            if (questionsContainer.children.length > 1) {
                new Sortable(questionsContainer, {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: function(evt) {
                        updateQuestionOrder();
                    }
                });
            }
        }

        // Update question order after drag-and-drop
        function updateQuestionOrder() {
            const questionCards = document.querySelectorAll('.question-card');
            let orderPromises = [];

            questionCards.forEach((card, index) => {
                const questionId = card.getAttribute('data-id');

                const formData = new FormData();
                formData.append('action', 'update_question');
                formData.append('question_id', questionId);
                formData.append('question_order', index);
                formData.append('question_type', card.getAttribute('data-type'));
                formData.append('question_text', card.getAttribute('data-text'));
                formData.append('required', card.getAttribute('data-required') === 'true' ? 1 : 0);

                const options = card.getAttribute('data-options');
                if (options) {
                    formData.append('options', options);
                }

                const promise = fetch('/capstone-php/backend/routes/assessment_manager.php', {
                    method: 'POST',
                    body: formData
                }).then(response => response.json());

                orderPromises.push(promise);
            });

            Promise.all(orderPromises)
                .then(results => {
                    console.log('All questions reordered');
                })
                .catch(err => {
                    console.error('Failed to update question order', err);
                });
        }

        // Save questions button click
        saveQuestionsBtn.addEventListener('click', function() {
            const questionCards = document.querySelectorAll('.question-card');
            if (questionCards.length === 0) {
                alert('Please add at least one question to your assessment');
                return;
            }

            alert('Assessment questions have been saved successfully!');
        });

        // Preview assessment button click
        previewAssessmentBtn.addEventListener('click', function() {
            const questionCards = document.querySelectorAll('.question-card');
            if (questionCards.length === 0) {
                alert('Please add at least one question to your assessment');
                return;
            }

            const trainingTitle = customFormTraining.options[customFormTraining.selectedIndex].text;

            // Generate preview HTML
            let previewHtml = `
                <h4>${trainingTitle} - Assessment</h4>
                <p class="text-muted mb-4">Please complete the assessment below. Questions marked with * are required.</p>
                <form id="previewAssessmentForm">
            `;

            questionCards.forEach((card, index) => {
                const questionId = card.getAttribute('data-id');
                const questionType = card.getAttribute('data-type');
                const questionText = card.getAttribute('data-text');
                const required = card.getAttribute('data-required') === 'true';
                const options = card.getAttribute('data-options') ? JSON.parse(card
                    .getAttribute('data-options')) : null;

                previewHtml += `
                    <div class="preview-question">
                        <label class="form-label fw-bold">
                            ${index + 1}. ${questionText} ${required ? '<span class="question-required">*</span>' : ''}
                        </label>
                `;

                switch (questionType) {
                    case 'text':
                        previewHtml +=
                            `<input type="text" class="form-control" ${required ? 'required' : ''}>`;
                        break;
                    case 'textarea':
                        previewHtml +=
                            `<textarea class="form-control" rows="3" ${required ? 'required' : ''}></textarea>`;
                        break;
                    case 'multiple_choice':
                        if (Array.isArray(options)) {
                            options.forEach((option, optIdx) => {
                                previewHtml += `
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q${questionId}" id="q${questionId}_opt${optIdx}" value="${option}" ${required ? 'required' : ''}>
                                        <label class="form-check-label" for="q${questionId}_opt${optIdx}">${option}</label>
                                    </div>
                                `;
                            });
                        }
                        break;
                    case 'checkbox':
                        if (Array.isArray(options)) {
                            options.forEach((option, optIdx) => {
                                previewHtml += `
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="q${questionId}[]" id="q${questionId}_opt${optIdx}" value="${option}">
                                        <label class="form-check-label" for="q${questionId}_opt${optIdx}">${option}</label>
                                    </div>
                                `;
                            });
                        }
                        break;
                    case 'rating':
                        if (options) {
                            previewHtml += `<div class="rating-container">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>${options.minLabel || options.min}</span>
                                    <div class="btn-group" role="group">`;

                            for (let i = options.min; i <= options.max; i++) {
                                previewHtml += `
                                    <input type="radio" class="btn-check" name="q${questionId}" id="q${questionId}_rating${i}" value="${i}" ${required ? 'required' : ''}>
                                    <label class="btn btn-outline-primary" for="q${questionId}_rating${i}">${i}</label>
                                `;
                            }

                            previewHtml += `
                                    </div>
                                    <span>${options.maxLabel || options.max}</span>
                                </div>
                            </div>`;
                        }
                        break;
                }

                previewHtml += `</div>`;
            });

            previewHtml += `
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Submit Assessment</button>
                </div>
                </form>
            `;

            // Update preview modal and show it
            assessmentPreview.innerHTML = previewHtml;
            previewModal.show();

            // Add submit handler to the preview form (just for display)
            document.getElementById('previewAssessmentForm').addEventListener('submit', function(e) {
                e.preventDefault();
                alert(
                    'This is a preview. In the actual assessment, this would submit your responses.'
                    );
            });
        });
    });
    </script>
</body>

</html>
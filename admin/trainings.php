<?php
define('APP_INIT', true); // Added to enable proper access.
// admin/trainings.php
error_reporting(0);
ini_set('display_errors', 0);
require_once 'admin_header.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Trainings Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
    .form-section {
        margin: 20px 0;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 10px;
        background: #f9f9f9;
    }

    .content-item {
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 10px;
        margin-bottom: 10px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .content-item div {
        margin-right: 10px;
    }

    .trainer-group {
        margin-bottom: 30px;
    }

    .training-image {
        max-height: 100px;
        margin-right: 10px;
    }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <main class="container">
            <h1 class="text-center mt-4">Trainings Management</h1>

            <!-- Training Form for Creating/Editing -->
            <div class="form-section" id="manageTrainingSection">
                <h3>Create / Edit Training</h3>
                <form id="trainingForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="trainingTitle" class="form-label">Training Title</label>
                        <input type="text" id="trainingTitle" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="trainingDescription" class="form-label">Training Description</label>
                        <textarea id="trainingDescription" name="description" class="form-control" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="trainingSchedule" class="form-label">Schedule</label>
                        <input type="datetime-local" id="trainingSchedule" name="schedule" class="form-control"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="trainingCapacity" class="form-label">Capacity</label>
                        <input type="number" id="trainingCapacity" name="capacity" class="form-control" value="50"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="trainingModality" class="form-label">Modality</label>
                        <input type="text" id="trainingModality" name="modality" class="form-control"
                            placeholder="e.g., Zoom, Google Meet">
                    </div>
                    <div class="mb-3">
                        <label for="trainingModalityDetails" class="form-label">Modality Details</label>
                        <textarea id="trainingModalityDetails" name="modality_details" class="form-control"
                            placeholder="Link or instructions"></textarea>
                    </div>
                    <!-- Add Fee Field -->
                    <div class="mb-3">
                        <label for="trainingFee" class="form-label">Training Fee</label>
                        <input type="number" step="0.01" id="trainingFee" name="fee" class="form-control" value="0.00"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="trainingImage" class="form-label">Training Image</label>
                        <input type="file" id="trainingImage" name="image" class="form-control" accept="image/*">
                    </div>
                    <!-- Hidden field for training id -->
                    <input type="hidden" id="trainingId" name="id">
                    <!-- Hidden field for CSRF protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <button type="submit" class="btn btn-success">Save Training</button>
                </form>
            </div>

            <!-- Trainings List by Trainer with Upcoming/Past Tabs -->
            <div id="trainingsListSection">
                <h3>Trainings by Trainer</h3>
                <!-- Top-level tabs for each trainer -->
                <ul class="nav nav-tabs" id="trainerTabs" role="tablist">
                    <!-- Trainer tabs will be injected here by JS -->
                </ul>
                <div class="tab-content mt-3" id="trainerTabsContent">
                    <!-- Trainer tab panes will be injected here by JS -->
                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Added nonce attribute (using the $cspNonce from admin_header.php) to the inline script -->
    <script nonce="<?= $cspNonce ?>">
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch and display trainings grouped by trainer and by upcoming/past
        fetchTrainings();

        // Handle form submission for creating/updating training
        document.getElementById('trainingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const trainingId = document.getElementById('trainingId').value;
            const action = trainingId ? 'update_training' : 'add_training';
            formData.append('action', action);

            fetch('../backend/routes/content_manager.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(text => {
                    console.log("Raw response:", text);
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error("JSON parsing error:", e);
                        alert("Failed to parse server response: " + text);
                        return;
                    }
                    if (data.status) {
                        alert(data.message);
                        document.getElementById('trainingForm').reset();
                        document.getElementById('trainingId').value = '';
                        fetchTrainings();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to connect to the server.');
                });
        });

        // Fetch trainings from the backend and group by trainer.
        function fetchTrainings() {
            fetch('../backend/routes/content_manager.php?action=fetch')
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        // Group trainings by creator (trainer)
                        let grouped = {};
                        data.trainings.forEach(training => {
                            let trainerId = training.created_by;
                            let trainerName = (training.first_name || 'Unknown') + ' ' + (training
                                .last_name || '');
                            if (!grouped[trainerId]) {
                                grouped[trainerId] = {
                                    trainerName: trainerName,
                                    trainings: []
                                };
                            }
                            grouped[trainerId].trainings.push(training);
                        });

                        // Build the trainer tabs and tab panes
                        let trainerTabsHtml = '';
                        let trainerTabsContentHtml = '';
                        let first = true;
                        for (let trainerId in grouped) {
                            // Create a tab for each trainer
                            trainerTabsHtml += `
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link ${first ? 'active' : ''}" id="trainer-${trainerId}-tab" data-bs-toggle="tab" data-bs-target="#trainer-${trainerId}" type="button" role="tab">
                                        ${grouped[trainerId].trainerName}
                                    </button>
                                </li>
                            `;

                            // Within each trainer tab pane, split trainings into upcoming and past
                            let now = new Date();
                            let upcomingTrainings = grouped[trainerId].trainings.filter(t => new Date(t
                                .schedule) >= now);
                            let pastTrainings = grouped[trainerId].trainings.filter(t => new Date(t
                                .schedule) < now);

                            // Create sub-tabs for upcoming and past trainings
                            let subTabs = `
                                <ul class="nav nav-tabs" id="subTabs-${trainerId}" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="upcoming-${trainerId}-tab" data-bs-toggle="tab" data-bs-target="#upcoming-${trainerId}" type="button" role="tab">
                                            Upcoming Trainings
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="past-${trainerId}-tab" data-bs-toggle="tab" data-bs-target="#past-${trainerId}" type="button" role="tab">
                                            Past Trainings
                                        </button>
                                    </li>
                                </ul>
                            `;

                            // Build upcoming trainings HTML for this trainer
                            let upcomingHtml = upcomingTrainings.map(training => `
                                <div class="card mb-3">
                                    <div class="row g-0">
                                        <div class="col-md-4">
                                            <img src="../backend/routes/decrypt_image.php?image_url=${ encodeURIComponent(training.image || '/capstone-php/assets/default-training.jpeg') }" class="card-img-top" alt="Training image">
                                        </div>
                                        <div class="col-md-8">
                                            <div class="card-body">
                                                <h5 class="card-title">${training.title}</h5>
                                                <p class="card-text">${training.description}</p>
                                                <p><strong>Schedule:</strong> ${new Date(training.schedule).toLocaleString()}</p>
                                                <p><strong>Capacity:</strong> ${training.capacity}</p>
                                                <p><strong>Modality:</strong> ${training.modality || 'N/A'}</p>
                                                <p><strong>Fee:</strong> ${training.fee && parseFloat(training.fee) > 0 ? '₱' + training.fee : 'Free'}</p>
                                                <div>
                                                    <button class="btn btn-primary btn-sm edit-training" data-id="${training.training_id}">Edit</button>
                                                    <button class="btn btn-danger btn-sm delete-training" data-id="${training.training_id}">Delete</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `).join('');

                            // Build past trainings HTML for this trainer
                            let pastHtml = pastTrainings.map(training => `
                                <div class="card mb-3">
                                    <div class="row g-0">
                                        <div class="col-md-4">
                                            <img src="../backend/routes/decrypt_image.php?image_url=${ encodeURIComponent(training.image || '/capstone-php/assets/default-training.jpeg') }" class="card-img-top" alt="Training image">
                                        </div>
                                        <div class="col-md-8">
                                            <div class="card-body">
                                                <h5 class="card-title">${training.title}</h5>
                                                <p class="card-text">${training.description}</p>
                                                <p><strong>Schedule:</strong> ${new Date(training.schedule).toLocaleString()}</p>
                                                <p><strong>Capacity:</strong> ${training.capacity}</p>
                                                <p><strong>Modality:</strong> ${training.modality || 'N/A'}</p>
                                                <p><strong>Fee:</strong> ${training.fee && parseFloat(training.fee) > 0 ? '₱' + training.fee : 'Free'}</p>
                                                <div>
                                                    <button class="btn btn-primary btn-sm edit-training" data-id="${training.training_id}">Edit</button>
                                                    <button class="btn btn-danger btn-sm delete-training" data-id="${training.training_id}">Delete</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `).join('');

                            // Create the tab pane for this trainer with sub-tabs content
                            trainerTabsContentHtml += `
                                <div class="tab-pane fade ${first ? 'show active' : ''}" id="trainer-${trainerId}" role="tabpanel">
                                    ${subTabs}
                                    <div class="tab-content mt-3">
                                        <div class="tab-pane fade show active" id="upcoming-${trainerId}" role="tabpanel">
                                            ${upcomingHtml || '<p class="text-center text-muted">No upcoming trainings</p>'}
                                        </div>
                                        <div class="tab-pane fade" id="past-${trainerId}" role="tabpanel">
                                            ${pastHtml || '<p class="text-center text-muted">No past trainings</p>'}
                                        </div>
                                    </div>
                                </div>
                            `;
                            first = false;
                        }

                        // Inject the trainer tabs and content into the page
                        document.getElementById('trainerTabs').innerHTML = trainerTabsHtml;
                        document.getElementById('trainerTabsContent').innerHTML = trainerTabsContentHtml;

                        // Attach Edit and Delete Training Handlers
                        document.querySelectorAll('.edit-training').forEach(btn => {
                            btn.addEventListener('click', function() {
                                let id = this.getAttribute('data-id');
                                editTraining(id);
                            });
                        });
                        document.querySelectorAll('.delete-training').forEach(btn => {
                            btn.addEventListener('click', function() {
                                let id = this.getAttribute('data-id');
                                deleteTraining(id);
                            });
                        });
                    }
                })
                .catch(err => console.error(err));
        }

        // Load training data into the form for editing.
        function editTraining(id) {
            fetch(`../backend/routes/content_manager.php?action=get_training&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        let training = data.training;
                        document.getElementById('trainingId').value = training.training_id;
                        document.getElementById('trainingTitle').value = training.title;
                        document.getElementById('trainingDescription').value = training.description;
                        // Convert schedule to datetime-local format (replace space with 'T')
                        document.getElementById('trainingSchedule').value = training.schedule.replace(' ',
                            'T');
                        document.getElementById('trainingCapacity').value = training.capacity;
                        document.getElementById('trainingModality').value = training.modality || '';
                        document.getElementById('trainingModalityDetails').value = training
                            .modality_details || '';
                        document.getElementById('trainingFee').value = training.fee || '0.00';

                        // Scroll smoothly to the manage training form
                        document.getElementById('manageTrainingSection').scrollIntoView({
                            behavior: 'smooth'
                        });
                    } else {
                        alert('Error fetching training details.');
                    }
                })
                .catch(err => console.error(err));
        }

        // Delete Training function
        function deleteTraining(id) {
            if (confirm('Are you sure you want to delete this training?')) {
                const formData = new FormData();
                formData.append('action', 'delete_training');
                formData.append('id', id);
                fetch('../backend/routes/content_manager.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            alert(data.message);
                            fetchTrainings();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Failed to connect to the server.');
                    });
            }
        }
    });
    </script>
</body>

</html>
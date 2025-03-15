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
            <div class="form-section">
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
                        <label for="trainingImage" class="form-label">Training Image</label>
                        <input type="file" id="trainingImage" name="image" class="form-control" accept="image/*">
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
                    <!-- Hidden field for training id -->
                    <input type="hidden" id="trainingId" name="id">
                    <!-- Hidden field for CSRF protection -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <button type="submit" class="btn btn-success">Save Training</button>
                </form>
            </div>

            <!-- List of Trainers and Their Trainings -->
            <div id="trainingsListSection">
                <h3>All Trainings by Trainers</h3>
                <div id="trainingsList"></div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Added nonce attribute (using the $cspNonce from admin_header.php) to the inline script -->
    <script nonce="<?= $cspNonce ?>">
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch and display all trainings grouped by creator
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

            // Fetch trainings from the backend and group by creator.
            function fetchTrainings() {
                fetch('../backend/routes/content_manager.php?action=fetch')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            // Group trainings by the creator (trainer/admin)
                            let grouped = {};
                            data.trainings.forEach(training => {
                                let creatorId = training.created_by;
                                // Provide fallback for missing first or last names
                                let creatorName = (training.first_name || 'Unknown') + ' ' + (training
                                    .last_name || '');
                                if (!grouped[creatorId]) {
                                    grouped[creatorId] = {
                                        creatorName: creatorName,
                                        trainings: []
                                    };
                                }
                                grouped[creatorId].trainings.push(training);
                            });

                            let html = '';
                            for (let creatorId in grouped) {
                                html += `<div class="trainer-group card mb-4">
                          <div class="card-header">
                              <h5>Created by: ${grouped[creatorId].creatorName}</h5>
                          </div>
                          <div class="card-body">`;
                                grouped[creatorId].trainings.forEach(training => {
                                    html += `
                    <div class="content-item">
                      <div style="flex:1;">
                        <img src="${training.image ? training.image : 'assets/default-training.jpeg'}" alt="${training.title}" class="img-thumbnail training-image">
                        <h6>${training.title}</h6>
                        <p>${training.description}</p>
                        <p><strong>Schedule:</strong> ${new Date(training.schedule).toLocaleString()}</p>
                        <p><strong>Capacity:</strong> ${training.capacity}</p>
                        <p><strong>Modality:</strong> ${training.modality || 'N/A'}</p>
                        <p><strong>Modality Details:</strong> ${training.modality_details || 'N/A'}</p>
                      </div>
                      <div>
                        <button class="btn btn-sm btn-primary edit-training" data-id="${training.training_id}">Edit</button>
                        <button class="btn btn-sm btn-danger delete-training" data-id="${training.training_id}">Delete</button>
                      </div>
                    </div>
                  `;
                                });
                                html += `</div></div>`;
                            }
                            document.getElementById('trainingsList').innerHTML = html;
                            // Attach event listeners to edit buttons
                            document.querySelectorAll('.edit-training').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    let id = this.getAttribute('data-id');
                                    editTraining(id);
                                });
                            });
                            // Attach event listeners to delete buttons
                            document.querySelectorAll('.delete-training').forEach(btn => {
                                btn.addEventListener('click', function() {
                                    let id = this.getAttribute('data-id');
                                    deleteTraining(id);
                                });
                            });
                        } else {
                            document.getElementById('trainingsList').innerHTML = '<p>No trainings found.</p>';
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
                        } else {
                            alert('Error fetching training details.');
                        }
                    })
                    .catch(err => console.error(err));
            }

            // Delete training function
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
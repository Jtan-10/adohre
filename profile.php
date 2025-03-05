<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Member Link</title>
    <link rel="icon" href="assets/logo.png" type="image/jpg" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">

    <style>
    .profile-container {
        max-width: 650px;
        margin: auto;
        margin-top: 50px;
        margin-bottom: 50px;
    }

    .profile-image {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border-radius: 50%;
    }

    .toast-container {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1050;
    }

    input[readonly] {
        background-color: #e9ecef;
        /* Light grey background */
        color: #6c757d;
        /* Darker text for contrast */
        user-select: none;
        /* Prevent text selection */
        pointer-events: none;
        /* Disable interaction (clicking or focusing) */
    }
    </style>
</head>


<body>
    <div class="toast-container" id="toastContainer"></div>
    <header>
        <?php include('header.php'); ?>
        <?php
        // Check if the user is logged in
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
        ?>
    </header>
    <main>
        <div class="container profile-container">
            <h1 class="text-center">My Profile</h1>
            <?php include('profile_tabs.php'); ?>
        </div>
    </main>
    <?php include('footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const userId = "<?php echo $_SESSION['user_id']; ?>";

        // Fetch the current profile data
        fetch(`backend/routes/user.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    const user = data.data;
                    document.getElementById('first_name').value = user.first_name;
                    document.getElementById('last_name').value = user.last_name;
                    document.getElementById('email').value = user.email;

                    document.getElementById('role').value = user.role ?
                        user.role.charAt(0).toUpperCase() + user.role.slice(1).toLowerCase() :
                        '';

                    document.getElementById('profileImage').src = user.profile_image ||
                        'assets/default-profile.jpeg';
                    document.getElementById('virtualId').value = user.virtual_id || 'Not assigned';

                    // Update View Virtual ID link
                    const viewLink = document.getElementById('viewVirtualIdLink');
                    viewLink.href = `backend/models/generate_virtual_id.php?user_id=${userId}`;
                } else {
                    showToast('Failed to load profile data.', 'danger');
                }
            })
            .catch(() => showToast('Error fetching profile data.', 'danger'));

        // Update profile
        document.getElementById('updateProfileBtn').addEventListener('click', function() {
            const formData = new FormData(document.getElementById('profileForm'));

            fetch('backend/routes/user.php', {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        // Show success message
                        showToast(data.message, 'success');

                        // Update the profile image on the page
                        if (data.profile_image) {
                            document.getElementById('profileImage').src = data.profile_image;

                            // Also update the profile image in the header
                            const profileImageNav = document.getElementById('profileImageNav');
                            if (profileImageNav) {
                                profileImageNav.src =
                                    `${data.profile_image}?t=${new Date().getTime()}`; // Cache-busting
                            }
                        }
                    } else {
                        showToast(data.message, 'danger');
                    }
                })
                .catch(() => showToast('Error updating profile.', 'danger'));
        });



        // Regenerate Virtual ID
        document.getElementById('regenerateIdBtn').addEventListener('click', function() {
            fetch('backend/routes/user.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        regenerate_virtual_id: true
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        document.getElementById('virtualId').value = data.virtual_id;
                        showToast(data.message, 'success');

                        // Update the View Virtual ID link with the new ID
                        const viewLink = document.getElementById('viewVirtualIdLink');
                        viewLink.href = `backend/models/generate_virtual_id.php?user_id=${userId}`;
                    } else {
                        showToast(data.message, 'danger');
                    }
                })
                .catch(() => showToast('Error regenerating Virtual ID.', 'danger'));
        });

        function showToast(message, type) {
            const toastContainer = document.getElementById('toastContainer');
            const toastHTML = `
                    <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>`;
            toastContainer.innerHTML = toastHTML;
            const toast = new bootstrap.Toast(toastContainer.firstElementChild);
            toast.show();
        }

        // Fetch the joined events when the "Events" tab is clicked
        document.getElementById('events-tab').addEventListener('click', function() {
            fetch(`backend/routes/event_registration.php?action=get_joined_events&user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        const events = data.events;
                        const eventsList = events.map(event => `
                        <div class="card mb-3">
                            <div class="row g-0">
                                <div class="col-md-4">
                                    <img src="${event.image || 'assets/default-image.jpeg'}" class="img-fluid rounded-start" alt="${event.title}">
                                </div>
                                <div class="col-md-8">
                                    <div class="card-body">
                                        <h5 class="card-title">${event.title}</h5>
                                        <p class="card-text">${event.description}</p>
                                        <p><strong>Date:</strong> ${event.date}</p>
                                        <p><strong>Location:</strong> ${event.location}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).join('');
                        document.getElementById('joinedEventsList').innerHTML = eventsList ||
                            '<p>No joined events yet.</p>';
                    } else {
                        document.getElementById('joinedEventsList').innerHTML =
                            `<p>${data.message || 'Failed to load events.'}</p>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('joinedEventsList').innerHTML =
                        `<p>An error occurred while fetching events. Please try again later.</p>`;
                });
        });

        document.getElementById('trainings-tab').addEventListener('click', function() {
            fetch(`backend/routes/training_registration.php?action=get_joined_trainings`)
                .then(response => response.json())
                .then(data => {
                    const joinedTrainingsList = document.getElementById('joinedTrainingsList');
                    if (data.status) {
                        const trainings = data.trainings;
                        const trainingHTML = trainings.map(training => `
                    <div class="card mb-3">
                        <div class="row g-0">
                            <div class="col-md-4">
                                <img src="${training.image || 'assets/default-training.jpg'}" class="img-fluid rounded-start" alt="${training.title}">
                            </div>
                            <div class="col-md-8">
                                <div class="card-body">
                                    <h5 class="card-title">${training.title}</h5>
                                    <p class="card-text">${training.description}</p>
                                    <p><strong>Schedule:</strong> ${new Date(training.schedule).toLocaleString()}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
                        joinedTrainingsList.innerHTML = trainingHTML ||
                            '<p>No joined trainings yet.</p>';
                    } else {
                        joinedTrainingsList.innerHTML =
                            `<p>${data.message || 'Failed to load trainings.'}</p>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('joinedTrainingsList').innerHTML =
                        `<p>An error occurred while fetching trainings. Please try again later.</p>`;
                });
        });


    });
    </script>
</body>

</html>
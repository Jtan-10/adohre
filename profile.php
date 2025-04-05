<?php
// Set secure session cookie parameters (ensure your site uses HTTPS in production)
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Add security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
?>
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
        const userId = <?php echo json_encode($_SESSION['user_id']); ?>;
        // Global function to show payment details in a modal
        window.viewPaymentDetails = function(payment) {
            // Update receipt image: pass the stored image URL through the decryption endpoint
            const receiptHTML = payment.image ?
                `<img src="backend/routes/decrypt_image.php?image_url=${encodeURIComponent(payment.image)}" alt="Receipt" style="max-width: 100%;">` :
                'N/A';
            const detailsHTML = `
      <p><strong>Payment ID:</strong> ${payment.payment_id}</p>
      <p><strong>Type:</strong> ${payment.payment_type}</p>
      <p><strong>Amount:</strong> ${payment.amount}</p>
      <p><strong>Status:</strong> ${payment.status}</p>
      <p><strong>Due Date:</strong> ${payment.due_date}</p>
      <p><strong>Payment Date:</strong> ${payment.payment_date ? payment.payment_date : 'N/A'}</p>
      <p><strong>Reference Number:</strong> ${payment.reference_number ? payment.reference_number : 'N/A'}</p>
      <p><strong>Mode of Payment:</strong> ${payment.mode_of_payment ? payment.mode_of_payment : 'N/A'}</p>
      <p><strong>Receipt:</strong> ${receiptHTML}</p>
    `;
            document.getElementById('paymentDetailsBody').innerHTML = detailsHTML;
            const modal = new bootstrap.Modal(document.getElementById('paymentDetailsModal'));
            modal.show();
        };

        // Function to open the Pay Fee modal
        window.openPayFeeModal = function(paymentId) {
            document.getElementById('paymentIdForFee').value = paymentId;
            const modal = new bootstrap.Modal(document.getElementById('payFeeModal'));
            modal.show();
        };

        // Function to fetch payments based on status
        function fetchPayments() {
            // For this example, we assume the backend uses the get_payments action and pass status.
            const status = document.getElementById('paymentStatusFilter').value;
            fetch(`backend/routes/payment.php?action=get_payments&user_id=${userId}&status=${status}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        let paymentsHTML = '';
                        data.payments.forEach(payment => {
                            paymentsHTML += `
                        <tr>
                            <td>${payment.payment_id}</td>
                            <td>${payment.payment_type}</td>
                            <td>${payment.amount}</td>
                            <td>${payment.status}</td>
                            <td>
                              <button class="btn btn-info btn-sm" onclick='viewPaymentDetails(${JSON.stringify(payment)})'>View Details</button>
                              ${payment.status === "New" ? `<button class="btn btn-success btn-sm" onclick="openPayFeeModal(${payment.payment_id})">Pay Fees</button>` : ''}
                            </td>
                        </tr>
                    `;
                        });
                        document.getElementById('pendingPaymentsTable').innerHTML = paymentsHTML ||
                            '<tr><td colspan="5">No payments found</td></tr>';
                    } else {
                        document.getElementById('paymentInfo').innerHTML =
                            `<p>${data.message || 'Failed to load payment info.'}</p>`;
                    }
                })
                .catch(err => {
                    console.error(err);
                    document.getElementById('paymentInfo').innerHTML =
                        `<p>Error loading payment information.</p>`;
                });
        }

        // Load payments when the Payments tab is clicked
        document.getElementById('payments-tab').addEventListener('click', function() {
            fetchPayments();
        });

        // Listen for changes in the dropdown filter (if you include one)
        if (document.getElementById('paymentStatusFilter')) {
            document.getElementById('paymentStatusFilter').addEventListener('change', function() {
                fetchPayments();
            });
        }

        document.getElementById('payFeeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = document.getElementById('payFeeForm');
            const formData = new FormData(form);

            fetch('backend/routes/payment.php?action=update_payment_fee', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        showToast(data.message, 'success'); // Confirmation toast
                        fetchPayments(); // Refresh the payments list
                        // Hide the modal after a short delay (optional)
                        setTimeout(() => {
                            const modalEl = document.getElementById('payFeeModal');
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            modal.hide();
                        }, 1000);
                    } else {
                        showToast(data.message, 'danger');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Error updating payment.', 'danger');
                });
        });

        // Example toast function (if not defined elsewhere)
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

                    const baseUrl = window.location.origin + '/capstone-php';
                    document.getElementById('profileImage').src = user.profile_image ?
                        `${baseUrl}/backend/routes/decrypt_image.php?image_url=${encodeURIComponent(user.profile_image)}&t=${new Date().getTime()}` :
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

        // Update profile - allow updating only the profile image
        document.getElementById('updateProfileBtn').addEventListener('click', function() {
            // Only read the profile image file from the form.
            const fileInput = document.querySelector(
                '#profileForm input[type="file"][name="profile_image"]');
            const formData = new FormData();
            if (fileInput && fileInput.files[0]) {
                formData.append('profile_image', fileInput.files[0]);
                formData.append('update_profile_image', 'true'); // Extra flag for backend processing
            } else {
                showToast('Please select an image to update.', 'danger');
                return;
            }
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
                            const baseUrl = window.location.origin + '/capstone-php';
                            document.getElementById('profileImage').src =
                                `${baseUrl}/backend/routes/decrypt_image.php?image_url=${encodeURIComponent(data.profile_image)}&t=${new Date().getTime()}`; // cache-busting
                            const profileImageNav = document.getElementById('profileImageNav');
                            if (profileImageNav) {
                                profileImageNav.src =
                                    `${baseUrl}/backend/routes/decrypt_image.php?image_url=${encodeURIComponent(data.profile_image)}&t=${new Date().getTime()}`;
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

        // Fetch the joined events when the "Events" tab is clicked
        const eventsTabEl = document.getElementById('events-tab');
        if (eventsTabEl) {
            eventsTabEl.addEventListener('shown.bs.tab', function() {
                fetch(`backend/routes/event_registration.php?action=get_joined_events`)
                    .then(response => response.json())
                    .then(data => {
                        const joinedEventsList = document.getElementById('joinedEventsList');
                        if (data.status) {
                            const events = data.events;
                            if (events && events.length > 0) {
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
                                joinedEventsList.innerHTML = eventsList;
                            } else {
                                joinedEventsList.innerHTML = '<p>No joined events yet.</p>';
                            }
                        } else {
                            joinedEventsList.innerHTML =
                                `<p>${data.message || 'Failed to load events.'}</p>`;
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        document.getElementById('joinedEventsList').innerHTML =
                            `<p>An error occurred while fetching events. Please try again later.</p>`;
                    });
            });
        }

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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
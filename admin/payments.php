<?php
define('APP_INIT', true);
require_once 'admin_header.php';

// Ensure the user is logged in and is an admin.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Payments Management</title>
    <link rel="icon" href="../assets/logo.png" type="image/jpg" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        /* Add a dark overlay to the manage modal when dimmed */
        .modal-dimmed::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1050;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <div class="container mt-4">
            <h1 class="mb-4">Payments Management</h1>

            <!-- Alert Container for displaying messages -->
            <div id="alertContainer"></div>

            <!-- Users List -->
            <div class="card mb-4">
                <div class="card-header">Users with Payments</div>
                <div class="card-body">
                    <table class="table table-bordered" id="usersTable">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Manage Payments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Users will be dynamically loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal for managing a user's payments -->
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentModalLabel">User Payments</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered" id="userPaymentsTable">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Payment Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Payment details will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Details Modal (for viewing details) -->
        <div class="modal fade" id="paymentDetailsModal" tabindex="-1" aria-labelledby="paymentDetailsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="paymentDetailsModalLabel">Payment Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="paymentDetailsBody">
                        <!-- Detailed payment info will be inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script nonce="<?php echo $cspNonce; ?>">
        document.addEventListener('DOMContentLoaded', function() {
            // Global object to store payments grouped by user
            let paymentsByUser = {};

            // Fetch all payments from the API and group them by user
            function loadPayments() {
                fetch('../backend/routes/payment.php?action=get_all_payments', {
                        method: 'GET'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            paymentsByUser = {}; // Reset grouping
                            data.payments.forEach(payment => {
                                // Ensure mode_of_payment is available from backend.
                                if (!paymentsByUser[payment.user_id]) {
                                    paymentsByUser[payment.user_id] = {
                                        user_id: payment.user_id,
                                        first_name: payment.first_name,
                                        last_name: payment.last_name,
                                        email: payment.email,
                                        payments: []
                                    };
                                }
                                paymentsByUser[payment.user_id].payments.push(payment);
                            });
                            renderUsersTable();
                        } else {
                            showAlert(data.message, 'danger');
                        }
                    })
                    .catch(err => {
                        showAlert('Error loading payments.', 'danger');
                        console.error(err);
                    });
            }

            // Render the users table based on grouped payments
            function renderUsersTable() {
                const tbody = document.querySelector('#usersTable tbody');
                tbody.innerHTML = '';
                Object.values(paymentsByUser).forEach(user => {
                    const tr = document.createElement('tr');
                    // Removed inline onclick and added data attribute & class
                    tr.innerHTML = `
                    <td>${user.user_id}</td>
                    <td>${user.first_name} ${user.last_name}</td>
                    <td>${user.email}</td>
                    <td><button class="btn btn-primary btn-sm managePaymentsBtn" data-user-id="${user.user_id}">Manage</button></td>
                `;
                    tbody.appendChild(tr);
                });
                // Attach event listeners for Manage buttons
                document.querySelectorAll('.managePaymentsBtn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const userId = btn.getAttribute('data-user-id');
                        window.managePayments(userId);
                    });
                });
            }

            // Make managePayments globally available
            window.managePayments = function(userId) {
                const user = paymentsByUser[userId];
                if (!user) return;

                // Set modal title
                document.getElementById('paymentModalLabel').innerText =
                    `Payments for ${user.first_name} ${user.last_name} (ID: ${user.user_id})`;

                const tbody = document.querySelector('#userPaymentsTable tbody');
                tbody.innerHTML = '';
                user.payments.forEach(payment => {
                    const tr = document.createElement('tr');
                    // Removed inline onclicks; added data attributes and classes
                    let statusDropdown =
                        `<select class="form-select form-select-sm" id="status_select_${payment.payment_id}">` +
                        `<option value="New" ${payment.status === "New" ? "selected" : ""}>New</option>` +
                        `<option value="Pending" ${payment.status === "Pending" ? "selected" : ""}>Pending</option>` +
                        `<option value="Completed" ${payment.status === "Completed" ? "selected" : ""}>Completed</option>` +
                        `<option value="Canceled" ${payment.status === "Canceled" ? "selected" : ""}>Canceled</option>` +
                        `</select>`;
                    let actionsHTML =
                        `<button class="btn btn-info btn-sm viewPaymentDetailsBtn" data-payment='${encodeURIComponent(JSON.stringify(payment))}'>View Details</button>`;
                    actionsHTML +=
                        ` <button class="btn btn-secondary btn-sm updatePaymentStatusBtn" data-payment-id="${payment.payment_id}">Update Status</button>`;
                    tr.innerHTML = `
                    <td>${payment.payment_id}</td>
                    <td>${payment.payment_type}</td>
                    <td>${payment.amount}</td>
                    <td>${statusDropdown}</td>
                    <td>${payment.payment_date ? payment.payment_date : 'N/A'}</td>
                    <td>${actionsHTML}</td>
                `;
                    tbody.appendChild(tr);
                });
                // Attach event listeners for View Details buttons
                document.querySelectorAll('.viewPaymentDetailsBtn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const paymentData = JSON.parse(decodeURIComponent(btn.getAttribute(
                            'data-payment')));
                        window.viewPaymentDetailsAdmin(paymentData);
                    });
                });
                // Attach event listeners for Update Status buttons
                document.querySelectorAll('.updatePaymentStatusBtn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const paymentId = btn.getAttribute('data-payment-id');
                        window.updatePaymentStatus(paymentId);
                    });
                });

                // Show the Manage Payments modal
                const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
                paymentModal.show();
            };

            // Global function to show payment details (admin version)
            window.viewPaymentDetailsAdmin = function(payment) {
                // Add a dim overlay to the Manage Payments modal
                const manageModalEl = document.getElementById('paymentModal');
                manageModalEl.classList.add('modal-dimmed');

                const detailsHTML = `
                    <p><strong>Payment ID:</strong> ${payment.payment_id}</p>
                    <p><strong>Type:</strong> ${payment.payment_type}</p>
                    <p><strong>Amount:</strong> ${payment.amount}</p>
                    <p><strong>Status:</strong> ${payment.status}</p>
                    <p><strong>Due Date:</strong> ${payment.due_date}</p>
                    <p><strong>Payment Date:</strong> ${payment.payment_date ? payment.payment_date : 'N/A'}</p>
                    <p><strong>Reference Number:</strong> ${payment.reference_number ? payment.reference_number : 'N/A'}</p>
                    <p><strong>Mode of Payment:</strong> ${payment.mode_of_payment ? payment.mode_of_payment : 'N/A'}</p>
                    <p><strong>Receipt:</strong> ${payment.image ? `<img src="${payment.image}" alt="Receipt" style="max-width: 100%; cursor:pointer;">` : 'N/A'}</p>
                `;
                document.getElementById('paymentDetailsBody').innerHTML = detailsHTML;
                const detailsModalEl = document.getElementById('paymentDetailsModal');
                const detailsModal = new bootstrap.Modal(detailsModalEl);
                detailsModal.show();

                // Remove dim overlay when details modal is closed
                detailsModalEl.addEventListener('hidden.bs.modal', function() {
                    manageModalEl.classList.remove('modal-dimmed');
                }, {
                    once: true
                });
            };

            // Global function to update payment status via PUT request using the dropdown value
            window.updatePaymentStatus = function(paymentId) {
                // Retrieve the selected status from the dropdown
                const selectEl = document.getElementById(`status_select_${paymentId}`);
                const newStatus = selectEl ? selectEl.value : null;
                if (!newStatus) return;
                // Retrieve CSRF token from meta tag
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const payload = {
                    payment_id: paymentId,
                    status: newStatus,
                    csrf_token: csrfToken
                };
                fetch('../backend/routes/payment.php', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status) {
                            showAlert(`Payment ${paymentId} updated successfully.`, 'success');
                            loadPayments();
                        } else {
                            showAlert(data.message, 'danger');
                        }
                    })
                    .catch(err => {
                        showAlert('Error updating payment status.', 'danger');
                        console.error(err);
                    });
            };

            // Example toast/alert function
            function showAlert(message, type = 'success') {
                const alertContainer = document.getElementById('alertContainer');
                alertContainer.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 5000);
            }

            // Load payments on page load
            loadPayments();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
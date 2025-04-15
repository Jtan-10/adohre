<?php
define('APP_INIT', true);
require_once 'admin_header.php';

// Ensure the user is logged in and is an admin.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Payments Management</title>
    <link rel="icon" href="../assets/logo.png" type="image/jpg" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
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

    /* Add styles for archived payments */
    .archived-payment {
        background-color: #f8f9fa;
        color: #6c757d;
    }

    /* Badge styles */
    .status-badge {
        padding: 0.25em 0.6em;
        font-size: 75%;
        font-weight: 700;
        border-radius: 0.25rem;
    }

    .status-new {
        background-color: #0d6efd;
        color: white;
    }

    .status-pending {
        background-color: #ffc107;
        color: black;
    }

    .status-completed {
        background-color: #198754;
        color: white;
    }

    .status-canceled {
        background-color: #dc3545;
        color: white;
    }

    .status-archived {
        background-color: #6c757d;
        color: white;
    }

    /* Add a spinner for loading states */
    .spinner-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 2000;
        display: none;
    }

    .spinner-container {
        background-color: white;
        padding: 20px;
        border-radius: 5px;
        text-align: center;
    }
    </style>
</head>

<body>
    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner-container">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 mb-0">Processing...</p>
        </div>
    </div>

    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <div class="container mt-4">
            <h1 class="mb-4">Payments Management</h1>

            <!-- Alert Container for displaying messages -->
            <div id="alertContainer"></div>

            <!-- Tabs for Active/Archived -->
            <ul class="nav nav-tabs mb-4" id="paymentsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab"
                        data-bs-target="#active-payments" type="button" role="tab" aria-controls="active-payments"
                        aria-selected="true">
                        Active Payments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="archived-tab" data-bs-toggle="tab" data-bs-target="#archived-payments"
                        type="button" role="tab" aria-controls="archived-payments" aria-selected="false">
                        Archived Payments
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="paymentsTabsContent">
                <!-- Active Payments Tab -->
                <div class="tab-pane fade show active" id="active-payments" role="tabpanel"
                    aria-labelledby="active-tab">
                    <!-- Filter Controls for Active -->
                    <div class="card mb-4">
                        <div class="card-header">Filter Options</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <select class="form-select" id="statusFilter">
                                        <option value="all">All Statuses</option>
                                        <option value="New">New</option>
                                        <option value="Pending">Pending</option>
                                        <option value="Completed">Validated</option>
                                        <option value="Canceled">Canceled</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button id="refreshPayments" class="btn btn-primary">
                                        <i class="bi bi-arrow-clockwise"></i> Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Users List -->
                    <div class="card mb-4">
                        <div class="card-header">Users with Active Payments</div>
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

                <!-- Archived Payments Tab -->
                <div class="tab-pane fade" id="archived-payments" role="tabpanel" aria-labelledby="archived-tab">
                    <!-- Archived Controls -->
                    <div class="card mb-4">
                        <div class="card-header">Archive Management</div>
                        <div class="card-body">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label for="archiveDaysFilter" class="form-label">Delete archived payments older
                                        than:</label>
                                    <select class="form-select" id="archiveDaysFilter">
                                        <option value="7">7 days</option>
                                        <option value="14">14 days</option>
                                        <option value="30" selected>30 days</option>
                                        <option value="60">60 days</option>
                                        <option value="90">90 days</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button id="deleteOldArchived" class="btn btn-danger">
                                        <i class="bi bi-trash"></i> Delete Selected
                                    </button>
                                </div>
                                <div class="col-md-3">
                                    <button id="refreshArchivedPayments" class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-clockwise"></i> Refresh
                                    </button>
                                </div>
                                <div class="col-md-3 text-end">
                                    <span class="text-muted" id="archivedCount">0 archived payments</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Archived Users List -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Users with Archived Payments</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered" id="archivedUsersTable">
                                <thead>
                                    <tr>
                                        <th>User ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Archived Payments</th>
                                        <th>Oldest Archive Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Users with archived payments will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
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
                        <div class="mb-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="showArchivedUserPayments">
                                <label class="form-check-label" for="showArchivedUserPayments">Show Archived</label>
                            </div>
                        </div>
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
        // Global objects to store payments grouped by user
        let activePaymentsByUser = {};
        let archivedPaymentsByUser = {};
        let statusFilter = 'all';
        let activeTab = 'active';

        // Tab switching event listeners
        document.getElementById('active-tab').addEventListener('click', function() {
            activeTab = 'active';
        });

        document.getElementById('archived-tab').addEventListener('click', function() {
            activeTab = 'archived';
            renderArchivedUsersTable();
        });

        // Event listeners for filter controls
        document.getElementById('statusFilter').addEventListener('change', function() {
            statusFilter = this.value;
            renderUsersTable();
        });

        document.getElementById('refreshPayments').addEventListener('click', function() {
            loadPayments();
        });

        // Event listeners for archive tab controls
        document.getElementById('refreshArchivedPayments').addEventListener('click', function() {
            loadPayments();
        });

        document.getElementById('deleteOldArchived').addEventListener('click', function() {
            const daysOld = document.getElementById('archiveDaysFilter').value;
            deleteOldArchivedPayments(daysOld);
        });

        // Fetch all payments from the API and group them by user
        function loadPayments() {
            document.getElementById('loadingSpinner').style.display = 'flex';

            fetch('../backend/routes/payment.php?action=get_all_payments', {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        activePaymentsByUser = {};
                        archivedPaymentsByUser = {};

                        // Process active payments
                        if (data.payments.active) {
                            data.payments.active.forEach(payment => {
                                if (!activePaymentsByUser[payment.user_id]) {
                                    activePaymentsByUser[payment.user_id] = {
                                        user_id: payment.user_id,
                                        first_name: payment.first_name,
                                        last_name: payment.last_name,
                                        email: payment.email,
                                        payments: []
                                    };
                                }
                                activePaymentsByUser[payment.user_id].payments.push(payment);
                            });
                        }

                        // Process archived payments
                        if (data.payments.archived) {
                            data.payments.archived.forEach(payment => {
                                if (!archivedPaymentsByUser[payment.user_id]) {
                                    archivedPaymentsByUser[payment.user_id] = {
                                        user_id: payment.user_id,
                                        first_name: payment.first_name,
                                        last_name: payment.last_name,
                                        email: payment.email,
                                        payments: []
                                    };
                                }
                                archivedPaymentsByUser[payment.user_id].payments.push(payment);
                            });
                        }

                        document.getElementById('archivedCount').textContent =
                            `${Object.keys(archivedPaymentsByUser).length} archived payment${Object.keys(archivedPaymentsByUser).length !== 1 ? 's' : ''}`;

                        if (activeTab === 'active') {
                            renderUsersTable();
                        } else {
                            renderArchivedUsersTable();
                        }
                        document.getElementById('loadingSpinner').style.display = 'none';
                    } else {
                        showAlert(data.message, 'danger');
                        document.getElementById('loadingSpinner').style.display = 'none';
                    }
                })
                .catch(err => {
                    showAlert('Error loading payments.', 'danger');
                    console.error(err);
                    document.getElementById('loadingSpinner').style.display = 'none';
                });
        }

        // Render the active users table
        function renderUsersTable() {
            const tbody = document.querySelector('#usersTable tbody');
            tbody.innerHTML = '';

            if (Object.keys(activePaymentsByUser).length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-credit-card fs-4 d-block mb-2"></i>
                                No active payments found
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            let filteredUsers = Object.values(activePaymentsByUser);
            if (statusFilter !== 'all') {
                filteredUsers = filteredUsers.filter(user => {
                    return user.payments.some(payment => payment.status === statusFilter);
                });
            }

            if (filteredUsers.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-filter-circle fs-4 d-block mb-2"></i>
                                No payments found with status: ${statusFilter}
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            filteredUsers.forEach(user => {
                const filteredPayments = user.payments.filter(payment => {
                    return statusFilter === 'all' || payment.status === statusFilter;
                });

                if (filteredPayments.length === 0) return;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${user.user_id}</td>
                    <td>${user.first_name} ${user.last_name}</td>
                    <td>${user.email}</td>
                    <td>
                        <button class="btn btn-primary btn-sm managePaymentsBtn" data-user-id="${user.user_id}">
                            Manage <span class="badge bg-light text-dark">${filteredPayments.length}</span>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            document.querySelectorAll('.managePaymentsBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const userId = btn.getAttribute('data-user-id');
                    window.managePayments(userId);
                });
            });
        }

        // Render the archived users table
        function renderArchivedUsersTable() {
            const tbody = document.querySelector('#archivedUsersTable tbody');
            tbody.innerHTML = '';

            if (Object.keys(archivedPaymentsByUser).length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-archive-fill fs-4 d-block mb-2"></i>
                                No archived payments found
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            Object.values(archivedPaymentsByUser).forEach(user => {
                let oldestArchiveDate = null;
                user.payments.forEach(payment => {
                    if (payment.archive_date) {
                        const archiveDate = new Date(payment.archive_date);
                        if (!oldestArchiveDate || archiveDate < oldestArchiveDate) {
                            oldestArchiveDate = archiveDate;
                        }
                    }
                });

                const formattedOldestDate = oldestArchiveDate ?
                    oldestArchiveDate.toISOString().split('T')[0] : 'Unknown';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${user.user_id}</td>
                    <td>${user.first_name} ${user.last_name}</td>
                    <td>${user.email}</td>
                    <td>${user.payments.length}</td>
                    <td>${formattedOldestDate}</td>
                    <td>
                        <button class="btn btn-info btn-sm viewArchivedBtn" data-user-id="${user.user_id}">
                            View Archived
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            document.querySelectorAll('.viewArchivedBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const userId = btn.getAttribute('data-user-id');
                    window.viewArchivedPayments(userId);
                });
            });
        }

        // Define the renderUserPaymentsTable function
        function renderUserPaymentsTable(userId, showArchived = false) {
            const user = activePaymentsByUser[userId];
            if (!user) return;

            const tbody = document.querySelector('#userPaymentsTable tbody');
            tbody.innerHTML = '';

            let allPayments = [...user.payments];
            if (showArchived && archivedPaymentsByUser[userId]) {
                allPayments = [...allPayments, ...archivedPaymentsByUser[userId].payments];
            }

            if (allPayments.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-credit-card fs-4 d-block mb-2"></i>
                                No payments found
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            allPayments.sort((a, b) => {
                const dateA = a.payment_date ? new Date(a.payment_date) : new Date(0);
                const dateB = b.payment_date ? new Date(b.payment_date) : new Date(0);
                return dateB - dateA;
            });

            allPayments.forEach(payment => {
                const tr = document.createElement('tr');
                if (payment.is_archived) {
                    tr.classList.add('archived-payment');
                }

                const statusBadge = payment.is_archived ?
                    `<span class="badge status-archived">Archived</span>` :
                    `<span class="badge status-${payment.status.toLowerCase()}">${payment.status}</span>`;

                const formattedDate = payment.payment_date ?
                    new Intl.DateTimeFormat('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                    }).format(new Date(payment.payment_date)) :
                    'N/A';

                tr.innerHTML = `
                    <td>${payment.payment_id}</td>
                    <td>${payment.payment_type}</td>
                    <td>${payment.amount}</td>
                    <td>${statusBadge}</td>
                    <td>${formattedDate}</td>
                    <td>
                        <button class="btn btn-info btn-sm viewPaymentDetailsBtn" data-payment='${encodeURIComponent(
                            JSON.stringify(payment)
                        )}'>View</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Example alert/toast function
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertContainer.appendChild(alert);

            setTimeout(() => {
                if (alert.parentElement) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }

        // Define the managePayments function globally
        window.managePayments = function(userId) {
            const user = activePaymentsByUser[userId];
            if (!user) return;

            const modalLabel = document.getElementById('paymentModalLabel');
            modalLabel.innerText =
                `Payments for ${user.first_name} ${user.last_name} (ID: ${user.user_id})`;
            modalLabel.setAttribute('data-user-id', userId);
            modalLabel.setAttribute('data-view-type', 'active');

            const showArchivedCheckbox = document.getElementById('showArchivedUserPayments');
            showArchivedCheckbox.checked = false;
            showArchivedCheckbox.parentElement.style.display = 'inline-block';

            renderUserPaymentsTable(userId, false);

            const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            paymentModal.show();
        };

        // Load payments on page load
        loadPayments();
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
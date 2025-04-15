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
                                        <option value="Completed">Completed</option>
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
        let paymentsByUser = {};
        let archivedPaymentsByUser = {};
        let statusFilter = 'all';
        let activeTab = 'active';
        let totalArchivedCount = 0;

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

        // Show or hide archived payments in the user payments modal when in active view
        document.getElementById('showArchivedUserPayments').addEventListener('change', function() {
            const showArchived = this.checked;
            const userId = document.getElementById('paymentModalLabel').getAttribute('data-user-id');
            const viewType = document.getElementById('paymentModalLabel').getAttribute(
                'data-view-type') || 'active';

            if (userId && viewType === 'active') {
                renderUserPaymentsTable(userId, showArchived);
            }
        });

        // Fetch all payments from the API and group them by user
        function loadPayments() {
            document.getElementById('loadingSpinner').style.display = 'flex';
            totalArchivedCount = 0;

            fetch('../backend/routes/payment.php?action=get_all_payments', {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status && Array.isArray(data.payments)) { // Ensure payments is an array
                        paymentsByUser = {};
                        archivedPaymentsByUser = {};

                        // Sort payments by date (newest first)
                        data.payments.sort((a, b) => {
                            const dateA = a.payment_date ? new Date(a.payment_date) : new Date(0);
                            const dateB = b.payment_date ? new Date(b.payment_date) : new Date(0);
                            return dateB - dateA;
                        });

                        data.payments.forEach(payment => {
                            if (payment.is_archived === 1) { // Only archived payments
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
                                totalArchivedCount++;
                            } else { // Active payments
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
                            }
                        });

                        document.getElementById('archivedCount').textContent =
                            `${totalArchivedCount} archived payment${totalArchivedCount !== 1 ? 's' : ''}`;

                        if (activeTab === 'active') {
                            renderUsersTable();
                        } else {
                            renderArchivedUsersTable();
                        }
                        document.getElementById('loadingSpinner').style.display = 'none';
                    } else {
                        showAlert('Invalid data format received from the server.', 'danger');
                    }
                    document.getElementById('loadingSpinner').style.display = 'none';
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

            if (Object.keys(paymentsByUser).length === 0) {
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

            let filteredUsers = Object.values(paymentsByUser);
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

        // 1. Fix for renderUserPaymentsTable function
        function renderUserPaymentsTable(userId, showArchived = false) {
            const user = paymentsByUser[userId];
            if (!user) return;

            document.getElementById('paymentModalLabel').setAttribute('data-view-type', 'active');
            document.getElementById('showArchivedUserPayments').parentElement.style.display = 'inline-block';

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
            </tr>`;
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

                // Display the actual payment status regardless of archive status
                const statusBadge =
                    `<span class="badge status-${payment.status.toLowerCase()}">${payment.status}</span>`;

                // For archived payments, add an additional archived badge
                const archivedBadge = payment.is_archived ?
                    `<span class="badge status-archived ml-1">Archived</span>` : '';

                let statusDropdown = '';
                let actionsHTML = '';

                if (!payment.is_archived) {
                    statusDropdown = `
                <select class="form-select form-select-sm" id="status_select_${payment.payment_id}">
                    <option value="New" ${payment.status === "New" ? "selected" : ""}>New</option>
                    <option value="Pending" ${payment.status === "Pending" ? "selected" : ""}>Pending</option>
                    <option value="Completed" ${payment.status === "Completed" ? "selected" : ""}>Completed</option>
                    <option value="Canceled" ${payment.status === "Canceled" ? "selected" : ""}>Canceled</option>
                </select>
            `;

                    actionsHTML = `
                <button class="btn btn-info btn-sm viewPaymentDetailsBtn" data-payment='${encodeURIComponent(JSON.stringify(payment))}'>View</button>
                <button class="btn btn-secondary btn-sm updatePaymentStatusBtn" data-payment-id="${payment.payment_id}">Update</button>
            `;

                    if (payment.status === "Completed") {
                        actionsHTML += `
                    <button class="btn btn-outline-secondary btn-sm archivePaymentBtn" data-payment-id="${payment.payment_id}">Archive</button>
                `;
                    }
                } else {
                    // For archived payments, show the status badges instead of dropdown
                    statusDropdown = `${statusBadge} ${archivedBadge}`;

                    actionsHTML = `
                <button class="btn btn-info btn-sm viewPaymentDetailsBtn" data-payment='${encodeURIComponent(JSON.stringify(payment))}'>View</button>
                <button class="btn btn-outline-warning btn-sm restorePaymentBtn" data-payment-id="${payment.payment_id}">Restore</button>
            `;
                }

                let formattedDate = 'N/A';
                if (payment.payment_date) {
                    const date = new Date(payment.payment_date);
                    formattedDate = new Intl.DateTimeFormat('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }).format(date);
                }

                tr.innerHTML = `
            <td>${payment.payment_id}</td>
            <td>${payment.payment_type}</td>
            <td>${payment.amount}</td>
            <td>${statusDropdown}</td>
            <td>${formattedDate}</td>
            <td>${actionsHTML}</td>       `;
                tbody.appendChild(tr);
            });

            attachPaymentButtonListeners();
        }

        // 2. Fix for renderArchivedPaymentsTable function
        function renderArchivedPaymentsTable(userId) {
            const user = archivedPaymentsByUser[userId];
            if (!user) return;

            const tbody = document.querySelector('#userPaymentsTable tbody');
            tbody.innerHTML = '';

            if (user.payments.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="text-muted">
                                <i class="bi bi-archive fs-4 d-block mb-2"></i>
                                No archived payments found
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            user.payments.sort((a, b) => {
                const dateA = a.payment_date ? new Date(a.payment_date) : new Date(0);
                const dateB = b.payment_date ? new Date(b.payment_date) : new Date(0);
                return dateB - dateA;
            });

            user.payments.forEach(payment => {
                const tr = document.createElement('tr');
                tr.classList.add('archived-payment');

                const formattedArchiveDate = payment.archive_date ?
                    new Date(payment.archive_date).toLocaleDateString() : 'Unknown';

                let formattedPaymentDate = 'N/A';
                if (payment.payment_date) {
                    const date = new Date(payment.payment_date);
                    formattedPaymentDate = new Intl.DateTimeFormat('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    }).format(date);
                }

                const today = new Date();
                const archiveDate = new Date(payment.archive_date);
                const daysSinceArchive = Math.floor((today - archiveDate) / (1000 * 60 * 60 * 24));

                // Show both the payment status and the archived status
                const statusBadge =
                    `<span class="badge status-${payment.status.toLowerCase()}">${payment.status}</span>`;
                const archivedBadge = `<span class="badge status-archived ml-1">Archived</span>`;

                tr.innerHTML = `
                    <td>${payment.payment_id}</td>
                    <td>${payment.payment_type}</td>
                    <td>${payment.amount}</td>
                    <td>${statusBadge}</td>
                    <td>${formattedPaymentDate}</td>
                    <td>
                        <button class="btn btn-info btn-sm viewPaymentDetailsBtn"
                            data-payment='${encodeURIComponent(JSON.stringify(payment))}'>View</button>
                        <button class="btn btn-outline-warning btn-sm restorePaymentBtn"
                            data-payment-id="${payment.payment_id}">Restore</button>
                        <small class="d-block text-muted mt-1">Archived: ${formattedArchiveDate} (${daysSinceArchive} days ago)</small>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            attachPaymentButtonListeners();
        }

        // Helper function to attach button event listeners
        function attachPaymentButtonListeners() {
            document.querySelectorAll('.viewPaymentDetailsBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const paymentData = JSON.parse(decodeURIComponent(btn.getAttribute(
                        'data-payment')));
                    window.viewPaymentDetailsAdmin(paymentData);
                });
            });

            document.querySelectorAll('.updatePaymentStatusBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const paymentId = btn.getAttribute('data-payment-id');
                    window.updatePaymentStatus(paymentId);
                });
            });

            document.querySelectorAll('.archivePaymentBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const paymentId = btn.getAttribute('data-payment-id');
                    window.archivePayment(paymentId);
                });
            });

            document.querySelectorAll('.restorePaymentBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const paymentId = btn.getAttribute('data-payment-id');
                    window.restorePayment(paymentId);
                });
            });
        }

        // Make managePayments globally available
        window.managePayments = function(userId) {
            const user = paymentsByUser[userId];
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

        // Global function to view archived payments
        window.viewArchivedPayments = function(userId) {
            const user = archivedPaymentsByUser[userId];
            if (!user) return;

            const modalLabel = document.getElementById('paymentModalLabel');
            modalLabel.innerText =
                `Archived Payments for ${user.first_name} ${user.last_name} (ID: ${user.user_id})`;
            modalLabel.setAttribute('data-user-id', userId);
            modalLabel.setAttribute('data-view-type', 'archived');

            document.getElementById('showArchivedUserPayments').parentElement.style.display = 'none';

            renderArchivedPaymentsTable(userId);

            const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            paymentModal.show();
        };

        // Global function to show payment details (admin version)
        window.viewPaymentDetailsAdmin = function(payment) {
            const manageModalEl = document.getElementById('paymentModal');
            manageModalEl.classList.add('modal-dimmed');

            const receiptHTML = payment.image ?
                `<img src="/capstone-php/backend/routes/decrypt_image.php?image_url=${encodeURIComponent(payment.image)}" alt="Receipt" style="max-width: 100%; cursor:pointer;">` :
                'N/A';

            let formattedDueDate = payment.due_date ? new Date(payment.due_date).toLocaleDateString() :
                'N/A';
            let formattedPaymentDate = 'N/A';

            if (payment.payment_date) {
                const date = new Date(payment.payment_date);
                formattedPaymentDate = new Intl.DateTimeFormat('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }).format(date);
            }

            let archiveStatusHTML = '';
            if (payment.is_archived) {
                const archiveDate = new Date(payment.archive_date);
                const today = new Date();
                const daysSinceArchive = Math.floor((today - archiveDate) / (1000 * 60 * 60 * 24));

                archiveStatusHTML = `
                    <div class="alert alert-warning">
                        <strong>Archived Payment</strong><br>
                        Archived on: ${new Date(payment.archive_date).toLocaleDateString()}<br>
                        Days since archiving: ${daysSinceArchive} days
                    </div>
                `;
            }

            const detailsHTML = `
                ${archiveStatusHTML}
                <p><strong>Payment ID:</strong> ${payment.payment_id}</p>
                <p><strong>Type:</strong> ${payment.payment_type}</p>
                <p><strong>Amount:</strong> ${payment.amount}</p>
                <p><strong>Status:</strong> <span class="badge status-${payment.status.toLowerCase()}">${payment.status}</span></p>
                <p><strong>Due Date:</strong> ${formattedDueDate}</p>
                <p><strong>Payment Date:</strong> ${formattedPaymentDate}</p>
                <p><strong>Reference Number:</strong> ${payment.reference_number ? payment.reference_number : 'N/A'}</p>
                <p><strong>Mode of Payment:</strong> ${payment.mode_of_payment ? payment.mode_of_payment : 'N/A'}</p>
                <p><strong>Receipt:</strong> ${receiptHTML}</p>
            `;
            document.getElementById('paymentDetailsBody').innerHTML = detailsHTML;

            const detailsModalEl = document.getElementById('paymentDetailsModal');
            const detailsModal = new bootstrap.Modal(detailsModalEl);
            detailsModal.show();

            detailsModalEl.addEventListener('hidden.bs.modal', function() {
                manageModalEl.classList.remove('modal-dimmed');
            }, {
                once: true
            });
        };

        // Global function to update payment status via PUT request
        window.updatePaymentStatus = function(paymentId) {
            const selectEl = document.getElementById(`status_select_${paymentId}`);
            const newStatus = selectEl ? selectEl.value : null;
            if (!newStatus) return;

            document.getElementById('loadingSpinner').style.display = 'flex';

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

                        if (newStatus === 'Completed') {
                            window.archivePayment(paymentId, true);
                        } else {
                            loadPayments();
                            const modalEl = document.getElementById('paymentModal');
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) {
                                modal.hide();
                            }
                        }
                    } else {
                        showAlert(data.message, 'danger');
                        document.getElementById('loadingSpinner').style.display = 'none';
                    }
                })
                .catch(err => {
                    showAlert('Error updating payment status.', 'danger');
                    console.error(err);
                    document.getElementById('loadingSpinner').style.display = 'none';
                });
        };

        // Global function to archive a payment
        window.archivePayment = function(paymentId, isAutoArchive = false) {
            if (!isAutoArchive) {
                if (!confirm(
                        'Are you sure you want to archive this payment? It will be stored in the Archives tab.'
                    )) {
                    return;
                }
            }

            document.getElementById('loadingSpinner').style.display = 'flex';

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const payload = {
                payment_id: paymentId,
                action: 'archive',
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
                        if (!isAutoArchive) {
                            showAlert(`Payment ${paymentId} archived successfully.`, 'success');
                        } else {
                            showAlert(`Payment ${paymentId} marked as completed and archived.`,
                                'success');
                        }
                        const modalEl = document.getElementById('paymentModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) {
                            modal.hide();
                        }
                        loadPayments();
                    } else {
                        showAlert(data.message, 'danger');
                    }
                    document.getElementById('loadingSpinner').style.display = 'none';
                })
                .catch(err => {
                    showAlert('Error archiving payment.', 'danger');
                    console.error(err);
                    document.getElementById('loadingSpinner').style.display = 'none';
                });
        };

        // Global function to restore an archived payment
        window.restorePayment = function(paymentId) {
            if (!confirm('Are you sure you want to restore this archived payment?')) {
                return;
            }

            document.getElementById('loadingSpinner').style.display = 'flex';

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const payload = {
                payment_id: paymentId,
                action: 'restore',
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
                        showAlert(`Payment ${paymentId} restored successfully.`, 'success');
                        loadPayments(); // Reload payments to reflect changes
                    } else {
                        showAlert(data.message, 'danger');
                    }
                    document.getElementById('loadingSpinner').style.display = 'none';
                })
                .catch(err => {
                    showAlert('Error restoring payment.', 'danger');
                    console.error(err);
                    document.getElementById('loadingSpinner').style.display = 'none';
                });
        };

        // Global function to delete old archived payments
        function deleteOldArchivedPayments(daysOld) {
            if (!confirm(
                    `Are you sure you want to delete all archived payments older than ${daysOld} days? This action cannot be undone.`
                )) {
                return;
            }

            document.getElementById('loadingSpinner').style.display = 'flex';

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const payload = {
                days_old: daysOld,
                action: 'bulk_delete',
                csrf_token: csrfToken
            };

            fetch('../backend/routes/payment.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        showAlert(
                            `Successfully deleted ${data.deleted_count} archived payment(s) older than ${daysOld} days.`,
                            'success');
                        loadPayments();
                    } else {
                        showAlert(data.message, 'danger');
                    }
                    document.getElementById('loadingSpinner').style.display = 'none';
                })
                .catch(err => {
                    showAlert('Error deleting archived payments.', 'danger');
                    console.error(err);
                    document.getElementById('loadingSpinner').style.display = 'none';
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

        // Load payments on page load
        loadPayments();
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
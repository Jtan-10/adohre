<!-- Tabs -->
<ul class="nav nav-tabs" id="profileTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button"
            role="tab" aria-controls="account" aria-selected="true">Account Settings</button>
    </li>
    <?php if (isset($_SESSION['user_id']) && (isset($_SESSION['role']) && $_SESSION['role'] !== 'user')): ?>
    <li class="nav-item">
        <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab"
            aria-controls="events" aria-selected="false">Events</button>
    </li>
    <?php endif; ?>
    <li class="nav-item">
        <button class="nav-link" id="trainings-tab" data-bs-toggle="tab" data-bs-target="#trainings" type="button"
            role="tab" aria-controls="trainings" aria-selected="false">Trainings</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button"
            role="tab" aria-controls="payments" aria-selected="false">Payments</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications"
            type="button" role="tab" aria-controls="notifications" aria-selected="false">Notifications</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="virtual-id-tab" data-bs-toggle="tab" data-bs-target="#virtual-id" type="button"
            role="tab" aria-controls="virtual-id" aria-selected="false">Virtual ID</button>
    </li>
</ul>

<!-- Tab Contents -->
<div class="tab-content mt-4">
    <!-- Account Settings -->
    <div class="tab-pane fade show active" id="account" role="tabpanel" aria-labelledby="account-tab">
        <form id="profileForm" enctype="multipart/form-data">
            <?php // Adding CSRF token for security 
            ?>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="text-center mb-3">
                <img id="profileImage" src="assets/default-profile.jpeg" alt="Profile Image"
                    class="profile-image rounded-circle" width="150" height="150">
                <div class="mt-2">
                    <label for="profile_image" class="form-label">Change Profile Image</label>
                    <!-- Added accept attribute to allow only image files -->
                    <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/*">
                </div>
            </div>
            <div class="mb-3">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" name="first_name" id="first_name" class="form-control" readonly>
            </div>
            <div class="mb-3">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" name="last_name" id="last_name" class="form-control" readonly>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <input type="text" name="role" id="role" class="form-control" readonly>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" readonly>
            </div>
            <button type="button" id="updateProfileBtn" class="btn btn-success">Update Profile</button>
        </form>
    </div>

    <!-- Events -->
    <div class="tab-pane fade" id="events" role="tabpanel" aria-labelledby="events-tab">
        <h4>Joined Events</h4>
        <div id="joinedEventsList">
            <!-- List of events will be dynamically loaded here -->
        </div>
    </div>

    <!-- Trainings -->
    <div class="tab-pane fade" id="trainings" role="tabpanel" aria-labelledby="trainings-tab">
        <h4>Joined Trainings</h4>
        <div id="joinedTrainingsList">
        </div>
    </div>

    <!-- Payments -->
    <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">
        <h4>Payments</h4>
        <div id="paymentInfo">
            <!-- Dropdown for filtering payments by status -->
            <div class="mb-3">
                <label for="paymentStatusFilter" class="form-label">Filter by Status:</label>
                <select id="paymentStatusFilter" class="form-select" style="width: auto; display: inline-block;">
                    <option value="New" selected>New</option>
                    <option value="Pending">Pending</option>
                    <option value="Completed">Validated</option>
                </select>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pendingPaymentsTable">
                    <!-- Payment data will be dynamically loaded here -->
                </tbody>
            </table>
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


    <!-- Pay Fee Modal (for entering fee details) -->
    <div class="modal fade" id="payFeeModal" tabindex="-1" aria-labelledby="payFeeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="payFeeModalLabel">Pay Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger">You can only enter this once. Make sure that all details are correct.</p>
                    <form id="payFeeForm">
                        <?php // Adding CSRF token for security 
                        ?>
                        <input type="hidden" name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="mb-3">
                            <label for="modeOfPayment" class="form-label">Mode of Payment</label>
                            <input type="text" class="form-control" id="modeOfPayment" name="mode_of_payment"
                                placeholder="Enter mode of payment" required>
                        </div>
                        <div class="mb-3">
                            <label for="referenceNumber" class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="referenceNumber" name="reference_number"
                                placeholder="Enter reference number" required>
                        </div>
                        <div class="mb-3">
                            <label for="receiptImage" class="form-label">Receipt Image</label>
                            <input type="file" class="form-control" id="receiptImage" name="image" accept="image/*"
                                required>
                            <div class="form-text">Receipt is the confirmation image of a successful sending of the fee.
                            </div>
                        </div>
                        <!-- Hidden input to store the payment ID -->
                        <input type="hidden" id="paymentIdForFee" name="payment_id">
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
        <h4>Notifications</h4>
        <div id="notificationsList">
            <!-- Notifications will be loaded here -->
        </div>
        <script>
        // Load notifications when the Notifications tab is shown
        document.addEventListener("DOMContentLoaded", function() {
            var notificationsTab = document.getElementById('notifications-tab');
            notificationsTab.addEventListener('shown.bs.tab', function() {
                fetch('backend/routes/notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        var container = document.getElementById('notificationsList');
                        if (data.status && data.notifications.length > 0) {
                            let html = '';
                            data.notifications.forEach(function(notif) {
                                html += `<div class="card mb-2">
                                        <div class="card-body">
                                            <h5 class="card-title">${notif.subject}</h5>
                                            <p class="card-text">${notif.body}</p>
                                            <p class="card-text"><small class="text-muted">See full message in email</small></p>
                                            <p class="card-text"><small class="text-muted">${notif.sent_at}</small></p>
                                        </div>
                                    </div>`;
                            });
                            container.innerHTML = html;
                        } else {
                            container.innerHTML = '<p>No notifications found.</p>';
                        }
                    })
                    .catch(err => {
                        document.getElementById('notificationsList').innerHTML =
                            '<p>Error loading notifications.</p>';
                    });
            });
        });
        </script>
    </div>

    <!-- Virtual ID -->
    <div class="tab-pane fade" id="virtual-id" role="tabpanel" aria-labelledby="virtual-id-tab">
        <h4>Virtual ID</h4>
        <div class="form-group">
            <label for="virtualId">Virtual ID</label>
            <input type="text" id="virtualId" class="form-control" value="Loading..." readonly>
            <button class="btn btn-primary mt-2" id="regenerateIdBtn">Regenerate Virtual ID</button>
        </div>
    </div>
</div>
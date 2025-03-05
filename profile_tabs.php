<!-- Tabs -->
<ul class="nav nav-tabs" id="profileTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button"
            role="tab" aria-controls="account" aria-selected="true">Account Settings</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button" role="tab"
            aria-controls="events" aria-selected="false">Events</button>
    </li>
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
            <div class="text-center mb-3">
                <img id="profileImage" src="assets/default-profile.jpeg" alt="Profile Image"
                    class="profile-image rounded-circle" width="150" height="150">
                <div class="mt-2">
                    <label for="profile_image" class="form-label">Change Profile Image</label>
                    <input type="file" name="profile_image" id="profile_image" class="form-control">
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
        <p>Payment history and details.</p>
        <!-- Add your payment details here -->
    </div>

    <!-- Notifications -->
    <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
        <h4>Notifications</h4>
        <p>View recent notifications.</p>
        <!-- Add your notification details here -->
    </div>

    <!-- Virtual ID -->
    <div class="tab-pane fade" id="virtual-id" role="tabpanel" aria-labelledby="virtual-id-tab">
        <h4>Virtual ID</h4>
        <div class="form-group">
            <label for="virtualId">Virtual ID</label>
            <input type="text" id="virtualId" class="form-control" value="Loading..." readonly>
            <button class="btn btn-primary mt-2" id="regenerateIdBtn">Regenerate Virtual ID</button>
        </div>
        <div class="mt-3">
            <a id="viewVirtualIdLink" href="#" target="_blank" class="btn btn-primary">View Virtual ID
                Card</a>
        </div>

    </div>
</div>
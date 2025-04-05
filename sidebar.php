<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
$submenuActive = ($current_page == 'chat_assistance.php' || $current_page == 'appointments.php' || $current_page == 'medical_assistance.php');
?>
<style>
/* Modern Sidebar Styles */
#sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100%;
    width: 250px;
    background-color: #198754;
    color: #fff;
    transition: transform 0.3s ease;
    z-index: 1000;
    /* Center navigation vertically and horizontally */
    display: flex;
    justify-content: center;
    align-items: center;
}

/* When collapsed, slide completely off-screen */
#sidebar.collapsed {
    transform: translateX(-100%);
}

/* Remove default list styles and center links */
#sidebar ul.components {
    list-style: none;
    margin: 0;
    padding: 0;
    text-align: center;
}

#sidebar ul li {
    margin: 10px 0;
}

/* Sidebar links: centered text, no underline */
#sidebar ul li a {
    display: block;
    padding: 10px;
    font-size: 1.1em;
    color: #fff;
    text-decoration: none;
    transition: background 0.3s ease;
}

/* Hover and active state */
#sidebar ul li a:hover,
#sidebar ul li.active>a {
    background: #157347;
    border-radius: 4px;
}

/* Submenu styling */
#sidebar ul li ul.submenu {
    list-style: none;
    margin: 0;
    padding: 0;
    text-align: center;
    display: none;
}

#sidebar ul li ul.submenu li {
    margin: 5px 0;
}

#sidebar ul li ul.submenu li a {
    padding: 8px;
    font-size: 1em;
}

/* Toggle Button styling */
#sidebarCollapse {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    background: #198754;
    border: none;
    border-radius: 0 50% 50% 0;
    padding: 10px;
    cursor: pointer;
    z-index: 1001;
    color: #fff;
    font-size: 1.2em;
    transition: left 0.3s ease;
}
</style>

<div id="sidebar">
    <ul class="components">
        <li <?php if ($current_page == 'index.php') echo 'class="active"'; ?>><a href="index.php">Home</a></li>
        <li <?php if ($current_page == 'about.php') echo 'class="active"'; ?>><a href="about.php">About Us</a></li>
        <li <?php if ($current_page == 'news.php') echo 'class="active"'; ?>><a href="news.php">News</a></li>
        <li <?php if ($current_page == 'membership_form.php') echo 'class="active"'; ?>><a
                href="membership_form.php">Member Application</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
        <li>
            <a href="#" id="virtualIdLink" data-user-id="<?php echo $_SESSION['user_id']; ?>">Virtual ID</a>
        </li>
        <?php endif; ?>
        <li <?php if ($current_page == 'health.php') echo 'class="active"'; ?>>
            <a data-bs-toggle="offcanvas" href="#offcanvasHealth" role="button" aria-controls="offcanvasHealth">Health
                Tips</a>
        </li>
        <?php if (isset($_SESSION['user_id']) && (isset($_SESSION['role']) && $_SESSION['role'] !== 'user')): ?>
        <li <?php if ($current_page == 'member_services.php' || $submenuActive) echo 'class="active"'; ?>>
            <!-- Toggle the submenu using javascript -->
            <a href="javascript:void(0)" id="toggleMemberServices">
                Member Services <span
                    id="memberServicesArrow"><?php echo $submenuActive ? '&uarr;' : '&darr;'; ?></span>
            </a>
            <ul class="submenu" id="memberServicesSubmenu" <?php if ($submenuActive) echo 'style="display: block;"'; ?>>
                <li <?php if ($current_page == 'consultation.php') echo 'class="active"'; ?>><a
                        href="consultation.php">Chat Assistance</a></li>
                <li <?php if ($current_page == 'appointments.php') echo 'class="active"'; ?>><a
                        href="appointments.php">Appointments</a></li>
                <li <?php if ($current_page == 'medical_assistance.php') echo 'class="active"'; ?>><a
                        href="medical_assistance.php">Medical Assistance</a></li>
            </ul>
        </li>
        <li <?php if ($current_page == 'events.php') echo 'class="active"'; ?>><a href="events.php">Events</a></li>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_id'])): ?>
        <li <?php if ($current_page == 'trainings.php') echo 'class="active"'; ?>><a href="trainings.php">Trainings</a>
        </li>
        <?php endif; ?>
    </ul>
</div>

<!-- Off-Canvas Panel for Health and Wellness -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasHealth" aria-labelledby="offcanvasHealthLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasHealthLabel">Health and Wellness</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <?php include('health.php'); ?>
    </div>
</div>

<!-- PDF Password Modal (if needed for PDF generation) -->
<div class="modal fade" id="pdfPasswordModal" tabindex="-1" aria-labelledby="pdfPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfPasswordModalLabel">Your PDF Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="pdfPasswordText"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Face Validation Modal -->
<div class="modal fade" id="faceValidationModal" tabindex="-1" aria-labelledby="faceValidationModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="faceValidationModalLabel">Face Validation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Stored face reference -->
                <h4>Stored Face Reference</h4>
                <img id="storedFacePreview" src="" alt="Stored Face Reference"
                    style="max-width:320px; border:1px solid #ccc; margin-bottom:10px; display:block;">
                <!-- Live face capture -->
                <h4>Capture Your Face</h4>
                <video id="videoInput" width="320" height="240" autoplay muted style="border:1px solid #ccc;"></video>
                <br />
                <button type="button" class="btn btn-primary mt-2" id="validateFaceBtn">Validate Face</button>
                <canvas id="userFaceCanvas" style="display: none;"></canvas>
                <p id="faceValidationResult" style="margin-top:10px; font-weight:bold;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- (Optional) Response Modal for Notifications -->
<div class="modal fade" id="responseModal" tabindex="-1" aria-labelledby="responseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="responseModalLabel">Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="responseModalBody">
                <!-- Response message will be inserted here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Button for Sidebar -->
<button id="sidebarCollapse">&gt;</button>

<!-- Ensure that Bootstrap JS is loaded (if not already in your main template) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
</script>

<!-- External logic moved to js/sidebar.js -->
<script src="js/sidebar.js"></script>
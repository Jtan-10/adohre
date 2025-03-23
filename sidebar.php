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
        <li <?php if ($current_page == 'virtual_id.php') echo 'class="active"'; ?>>
            <a href="backend/models/generate_virtual_id.php?user_id=<?php echo $_SESSION['user_id']; ?>"
                target="_blank">Virtual ID</a>
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

<!-- Toggle Button -->
<button id="sidebarCollapse">&gt;</button>

<!-- Removed inline script; external logic moved to js/sidebar.js -->
<script src="js/sidebar.js"></script>
<?php
// Prevent direct access: ensure the app is properly initialized.
if (!defined('APP_INIT')) {
    header('HTTP/1.1 403 Forbidden');
    exit('No direct script access allowed');
}

// Start a session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional: Enforce admin authentication (adjust this logic as needed)
// if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
//     header('Location: /capstone-php/admin/login.php');
//     exit;
// }

// Get the current page name
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<style nonce="<?= $cspNonce ?>">
.sidebar {
    background-color: #198754;
    min-height: 100vh;
    color: white;
    transition: all 0.3s ease-in-out;
    width: 200px;
}

.sidebar-collapsed {
    width: 80px !important;
}

.sidebar .nav-link {
    color: white;
    font-weight: 500;
}

.sidebar .nav-link.active {
    background-color: #157347;
    font-weight: bold;
}

.sidebar-collapsed .nav-link {
    text-align: center;
}

.sidebar-collapsed .nav-link span {
    display: none;
}

.toggle-btn {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
}
</style>

<div id="sidebar" class="sidebar sidebar-collapsed p-3">
    <nav class="nav flex-column">
        <a href="dashboard.php" class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i> <span>Overview</span>
        </a>
        <a href="users.php" class="nav-link <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
            <i class="bi bi-people"></i> <span>Users</span>
        </a>
        <a href="reports.php" class="nav-link <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-bar-graph"></i> <span>Reports</span>
        </a>
        <a href="content_manager.php"
            class="nav-link <?php echo $currentPage === 'content_manager.php' ? 'active' : ''; ?>">
            <i class="bi bi-journal"></i> <span>Content Manager</span>
        </a>
        <a href="news.php" class="nav-link <?php echo $currentPage === 'news.php' ? 'active' : ''; ?>">
            <i class="bi bi-newspaper"></i> <span>News Management</span>
        </a>
        <!-- Payments tab added here -->
        <a href="payments.php" class="nav-link <?php echo $currentPage === 'payments.php' ? 'active' : ''; ?>">
            <i class="bi bi-credit-card"></i> <span>Payments</span>
        </a>
        <!-- Trainings tab added here -->
        <a href="trainings.php" class="nav-link <?php echo $currentPage === 'trainings.php' ? 'active' : ''; ?>">
            <i class="bi bi-mortarboard"></i> <span>Trainings</span>
        </a>
        <a href="assessments.php" class="nav-link <?php echo $currentPage === 'assessments.php' ? 'active' : ''; ?>">
            <i class="bi bi-card-checklist"></i> <span>Assessments</span>
        </a>
        <a href="membership_applications.php"
            class="nav-link <?php echo $currentPage === 'membership_applications.php' ? 'active' : ''; ?>">
            <i class="bi bi-file-earmark-person"></i> <span>Membership Applications</span>
        </a>
        <a href="consultations.php"
            class="nav-link <?php echo $currentPage === 'consultations.php' ? 'active' : ''; ?>">
            <i class="bi bi-chat-left-text"></i><span>Consultation Management</span>
        </a>
        <a href="appointments_management.php"
            class="nav-link <?php echo $currentPage === 'appointments_management.php' ? 'active' : ''; ?>">
            <i class="bi bi-calendar-check"></i><span>Appointments</span>
        </a>
        <a href="medical_assistance_management.php" class="nav-link <?php echo $currentPage === 'medical_assistance_management.php' ? 'active' : ''; ?>">
            <i class="bi bi-hospital"></i><span>Medical Assistance</span>
        </a>
        <a href="settings.php" class="nav-link <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
            <i class="bi bi-gear"></i> <span>Settings</span>
        </a>
        <a href="../index.php" class="nav-link <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">
            <i class="bi bi-box-arrow-right"></i> <span>Home</span>
        </a>
    </nav>
</div>

<script nonce="<?= $cspNonce ?>">
// Toggle Sidebar
const sidebar = document.getElementById('sidebar');
const toggleSidebar = document.getElementById('toggleSidebar');

// Initialize the sidebar in collapsed state
sidebar.classList.add('sidebar-collapsed');

if (toggleSidebar) { // defensive check
    toggleSidebar.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-collapsed');
    });
}
</script>
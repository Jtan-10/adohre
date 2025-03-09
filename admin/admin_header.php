<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../backend/db/db_connect.php';

// Check if the user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
?>
<nav class="navbar navbar-expand-lg bg-success text-white">
    <div class="container-fluid d-flex align-items-center">
        <!-- Hamburger icon to toggle the sidebar -->
        <!-- In your header/navbar -->
        <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'trainer'): ?>
        <button class="toggle-btn btn btn-success" id="toggleSidebar">
            <i class="bi bi-list"></i>
        </button>
        <?php else: ?>
        <div class="hamburger-placeholder"></div>
        <?php endif; ?>



        <!-- Logo -->
        <a class="navbar-brand d-flex align-items-center text-white" href="/capstone-php/admin/dashboard.php">
            <img src="/capstone-php/assets/logo.png" alt="ADOHRE Logo" width="30" height="28"
                class="d-inline-block align-text-top">
            <span class="ms-2">ADOHRE</span>
        </a>

        <!-- Adjust the nav to use flex utilities -->
        <ul class="navbar-nav ms-auto d-flex flex-row align-items-center">
            <?php if ($isLoggedIn): ?>
            <li class="nav-item dropdown mx-2 position-relative">
                <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    <img id="profileImageNav" src="<?php echo isset($_SESSION['profile_image']) 
         ? $_SESSION['profile_image'] 
         : '/capstone-php/assets/default-profile.jpeg'; ?>" alt="Profile Image" class="rounded-circle" width="30"
                        height="30">

                </a>
                <ul class="dropdown-menu dropdown-menu-end position-absolute" aria-labelledby="navbarDropdown">
                    <li><a class="dropdown-item" href="/capstone-php/index.php">Home</a></li>
                    <li><a class="dropdown-item" href="/capstone-php/profile.php">Profile</a></li>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a class="dropdown-item" href="/capstone-php/admin/dashboard.php">Admin Dashboard</a></li>
                    <?php endif; ?>
                    <?php if ($_SESSION['role'] === 'trainer'): ?>
                    <li><a class="dropdown-item" href="/capstone-php/admin/trainer/dashboard.php">Trainer Dashboard</a>
                    </li>
                    <?php endif; ?>
                    <li><a class="dropdown-item" href="/capstone-php/logout.php">Logout</a></li>
                </ul>
            </li>
            <?php else: ?>
            <li class="nav-item mx-2"><a href="/capstone-php/login.php" class="btn btn-light btn-sm">Login</a></li>
            <li class="nav-item mx-2"><a href="/capstone-php/signup.php" class="btn btn-light btn-sm">Sign Up</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
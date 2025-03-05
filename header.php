<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'backend/db/db_connect.php';

// Check if the user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

?>



<nav class="navbar navbar-expand-lg bg-success text-white">
    <div class="container">
        <a class="navbar-brand text-white" href="index.php">
            <img src="assets/logo.png" alt="ADOHRE Logo" width="30" height="28" class="d-inline-block align-text-top">
            ADOHRE
        </a>
        <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link text-white" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="about.php">About Us</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="news.php">News</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="events.php">Events</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="trainings.php">Trainings</a></li>
                <?php if ($isLoggedIn): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <img id="profileImageNav"
                            src="<?php echo $_SESSION['profile_image'] ?? './assets/default-profile.jpeg'; ?>"
                            alt="Profile Image" class="rounded-circle" width="30" height="30">

                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li><a class="dropdown-item" href="admin/dashboard.php">Admin Dashboard</a></li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'trainer'): ?>
                        <li><a class="dropdown-item" href="admin/trainer/dashboard.php">Trainer Dashboard</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item mt-1"><a href="login.php" class="btn btn-light btn-sm">Login</a></li>
                <li class="nav-item mt-1"><a href="signup.php" class="btn btn-light btn-sm">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
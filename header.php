<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'backend/db/db_connect.php';

// Update the user's role from the database on each page load:
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($currentRole);
    if ($stmt->fetch()) {
        $_SESSION['role'] = $currentRole;
    }
    $stmt->close();
    $conn->close();
}

// Check if the user is logged in.
$isLoggedIn = isset($_SESSION['user_id']);

// Reuse the nonce passed from the parent file; if not set, generate one.
if (!isset($nonce)) {
    $nonce = bin2hex(random_bytes(16)); // using same generation as news.php
}

// Set dynamic header name and logo, falling back to default values.
$headerName = $_SESSION['header_name'] ?? 'ADOHRE';
$headerLogo = $_SESSION['header_logo'] ?? 'assets/logo.png';
?>

<style>
/* Added profile-image styles */
.profile-image {
    width: 30px;
    height: 30px;
    object-fit: cover;
    border-radius: 50%;
}
</style>

<nav class="navbar navbar-expand-lg bg-success text-white">
    <div class="container">
        <a class="navbar-brand text-white" href="index.php">
            <img src="<?= htmlspecialchars($headerLogo, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($headerName, ENT_QUOTES, 'UTF-8') ?> Logo" width="30" height="28"
                class="d-inline-block align-text-top">
            <?= htmlspecialchars($headerName, ENT_QUOTES, 'UTF-8') ?>
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
                <?php if ($isLoggedIn && (isset($_SESSION['role']) && $_SESSION['role'] !== 'user')): ?>
                <li class="nav-item"><a class="nav-link text-white" href="events.php">Events</a></li>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item"><a class="nav-link text-white" href="trainings.php">Trainings</a></li>
                <?php endif; ?>
                <?php if ($isLoggedIn): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <img id="profileImageNav"
                            src="<?= htmlspecialchars($_SESSION['profile_image'] ?? './assets/default-profile.jpeg', ENT_QUOTES, 'UTF-8') ?>"
                            alt="Profile Image" class=" profile-image rounded-circle" width="30" height="30">
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
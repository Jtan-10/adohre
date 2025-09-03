<?php
require_once 'backend/db/db_connect.php';
// Ensure access control helpers are available
if (!function_exists('configureSessionSecurity')) {
    @require_once 'backend/utils/access_control.php';
}

if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('configureSessionSecurity')) {
        // Configure session security based on environment
        configureSessionSecurity();
    }
    session_start();
}

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
}

// Check if the user is logged in.
$isLoggedIn = isset($_SESSION['user_id']);

// Reuse the nonce passed from the parent file; if not set, generate one.
if (!isset($nonce)) {
    $nonce = bin2hex(random_bytes(16)); // using same generation as news.php
}

// Retrieve header settings from DB instead of session.
$headerName = 'ADOHRE';
$headerLogo = 'assets/logo.png';

$stmt = $conn->prepare("SELECT `key`, value FROM settings WHERE `key` IN ('header_name', 'header_logo')");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if ($row['key'] === 'header_name' && !empty($row['value'])) {
            $headerName = $row['value'];
        }
        if ($row['key'] === 'header_logo' && !empty($row['value'])) {
            $headerLogo = $row['value'];
        }
    }
    $stmt->close();
}
?>

<style>
    /* Added profile-image styles */
    .profile-image-header {
        width: 30px;
        height: 30px;
        object-fit: cover;
        border-radius: 50%;
    }

    /* Force nav link text to white */
    .navbar-nav .nav-link {
        color: #fff !important;
    }
</style>

<!-- Use navbar-dark so that text/icon colors are white by default -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <?php if (strpos($headerLogo, 's3proxy') !== false || strpos($headerLogo, 'amazonaws.com') !== false): ?>
                <!-- For S3 images that need decryption -->
                <img src="backend/routes/decrypt_image.php?image_url=<?= urlencode($headerLogo) ?>"
                    alt="<?= htmlspecialchars($headerName, ENT_QUOTES, 'UTF-8') ?> Logo" width="30" height="28"
                    class="d-inline-block align-text-top">
            <?php elseif (strpos($headerLogo, 'http') === 0): ?>
                <!-- For external image URLs -->
                <img src="<?= htmlspecialchars($headerLogo, ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($headerName, ENT_QUOTES, 'UTF-8') ?> Logo" width="30" height="28"
                    class="d-inline-block align-text-top">
            <?php else: ?>
                <!-- For regular images from the assets folder -->
                <?php
                // Normalize any stored absolute paths like "/capstone-php/assets/..." to relative "assets/..."
                $localLogo = $headerLogo;
                if (strpos($localLogo, '/capstone-php/') === 0) {
                    $localLogo = substr($localLogo, strlen('/capstone-php/'));
                }
                $localLogo = ltrim($localLogo, '/');
                ?>
                <img src="<?= htmlspecialchars($localLogo, ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($headerName, ENT_QUOTES, 'UTF-8') ?> Logo" width="30" height="28"
                    class="d-inline-block align-text-top">
            <?php endif; ?>
            <?= htmlspecialchars($headerName, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <!-- Toggler button using Bootstrap's data-bs attributes -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <!-- Collapsible nav links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="about.php">About Us</a></li>
                <li class="nav-item"><a class="nav-link" href="news.php">News</a></li>

                <!-- Grouped: Programs -->
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="programsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Programs</a>
                        <ul class="dropdown-menu" aria-labelledby="programsDropdown">
                            <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user'): ?>
                                <li><a class="dropdown-item" href="projects.php">Projects</a></li>
                            <?php endif; ?>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] !== 'user'): ?>
                                <li><a class="dropdown-item" href="events.php">Events</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="trainings.php">Trainings</a></li>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- Grouped: Resources -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="resourcesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Resources</a>
                    <ul class="dropdown-menu" aria-labelledby="resourcesDropdown">
                        <?php if ($isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'member'): ?>
                            <li><a class="dropdown-item" href="documents.php">Documents</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="faqs.php">FAQs</a></li>
                    </ul>
                </li>
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <img id="profileImageNav" src="<?= isset($_SESSION['profile_image'])
                                                                ? 'backend/routes/decrypt_image.php?image_url=' . urlencode($_SESSION['profile_image'])
                                                                : 'assets/default-profile.jpeg' ?>"
                                alt="Profile Image" class="profile-image-header rounded-circle" width="30" height="30">
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

<!-- Include the Bootstrap JS bundle (which includes Popper) so that the toggler works -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
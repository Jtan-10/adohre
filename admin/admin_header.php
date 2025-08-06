<?php
require_once '../backend/db/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    // Configure session security based on environment
    configureSessionSecurity();
    session_start();
}

// Add secure HTTP headers for production
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

$cspNonce = base64_encode(random_bytes(16)); // Generate the nonce
require_once __DIR__ . '/../backend/db/db_connect.php';

/* Begin update: Retrieve header settings from DB instead of session variables */
$headerName = 'ADOHRE';
$headerLogo = '/capstone-php/assets/logo.png';

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
/* End update */

// Check if the user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!-- Content Security Policy Meta Tag -->

<nav class="navbar navbar-expand-lg bg-success text-white">
    <div class="container-fluid d-flex align-items-center">
        <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'trainer'): ?>
            <button class="toggle-btn btn btn-success" id="toggleSidebar">
                <i class="bi bi-list"></i>
            </button>
        <?php else: ?>
            <div class="hamburger-placeholder"></div>
        <?php endif; ?>

        <a class="navbar-brand d-flex align-items-center text-white" href="/capstone-php/admin/dashboard.php">
            <img src="/capstone-php/backend/routes/decrypt_image.php?image_url=<?= urlencode($headerLogo) ?>"
                alt="<?= htmlspecialchars($headerName, ENT_QUOTES) ?> Logo" width="30" height="28"
                class="d-inline-block align-text-top">
            <span class="ms-2"><?= htmlspecialchars($headerName, ENT_QUOTES) ?></span>
        </a>

        <ul class="navbar-nav ms-auto d-flex flex-row align-items-center">
            <?php if ($isLoggedIn): ?>
                <li class="nav-item dropdown mx-2 position-relative">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <img id="profileImageNav" src="<?php echo isset($_SESSION['profile_image'])
                                                            ? '/capstone-php/backend/routes/decrypt_image.php?image_url=' . urlencode($_SESSION['profile_image'])
                                                            : '/capstone-php/assets/default-profile.jpeg'; ?>"
                            alt="Profile Image" class="rounded-circle" width="30" height="30">
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
                <li class="nav-item mx-2">
                    <a href="/capstone-php/login.php" class="btn btn-light btn-sm">Login</a>
                </li>
                <li class="nav-item mx-2">
                    <a href="/capstone-php/signup.php" class="btn btn-light btn-sm">Sign Up</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<!-- Include external sidebar script with nonce -->
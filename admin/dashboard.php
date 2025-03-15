<?php
// Remove separate nonce generation and use the $cspNonce from admin_header.php
define('APP_INIT', true);
require_once 'admin_header.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Production security headers with nonce for scripts using $cspNonce
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'nonce-$cspNonce'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net;");

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ADOHRE</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
    body {
        background-color: #f5f6fa;
        margin: 0;
        padding: 0;
        overflow-x: hidden;
    }

    .content {
        flex-grow: 1;
        padding: 20px;
        margin-right: 0;
        transition: margin-left 0.3s ease-in-out;
    }

    .card {
        border-radius: 10px;
        margin-bottom: 20px;
    }

    @media (max-width: 768px) {
        .card {
            margin-bottom: 15px;
        }
    }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php require_once 'admin_sidebar.php'; ?>

        <!-- Main Content -->
        <div id="content" class="content container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                <h1 class="mb-3">Admin Dashboard</h1>
                <div>
                    <span>Welcome,
                        <?php echo htmlspecialchars($_SESSION['first_name']) . ' ' . htmlspecialchars($_SESSION['last_name']); ?></span>
                    <?php
                    $imageSrc = '/assets/default-profile.jpeg'; // default local image
                    if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
                        $imageSrc = $_SESSION['profile_image'];
                    }
                    ?>
                    <img src="<?php echo $imageSrc; ?>" alt="Profile Image" class="rounded-circle" width="30">


                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="row mb-4 g-3">
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card p-3 text-center">
                        <h4>Total Users</h4>
                        <h2 id="totalUsers">...</h2>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card p-3 text-center">
                        <h4>Active Members</h4>
                        <h2 id="activeMembers">...</h2>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card p-3 text-center">
                        <h4>Total Announcements</h4>
                        <h2 id="totalAnnouncements">...</h2>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card p-3 text-center">
                        <h4>Total Trainings</h4>
                        <h2 id="totalTrainings">...</h2>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="card p-3 text-center">
                        <h4>Total Events</h4>
                        <h2 id="totalEvents">...</h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script nonce="<?= $cspNonce ?>">
    document.addEventListener('DOMContentLoaded', function() {
        fetch('../backend/routes/analytics.php')
            .then(response => response.json())
            .then(data => {
                if (data.status) {
                    document.getElementById('totalUsers').innerText = data.data.total_users || 0;
                    document.getElementById('activeMembers').innerText = data.data.active_members || 0;
                    document.getElementById('totalAnnouncements').innerText = data.data
                        .total_announcements || 0;
                    document.getElementById('totalTrainings').innerText = data.data.total_trainings || 0;
                    document.getElementById('totalEvents').innerText = data.data.total_events || 0;
                } else {
                    alert('Failed to fetch analytics data.');
                }
            })
            .catch(err => console.error('Error fetching analytics data:', err));
    });
    </script>
</body>

</html>
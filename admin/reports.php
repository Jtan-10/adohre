<?php
define('APP_INIT', true); // Added to enable proper access.
// Send the CSP header before any output.
header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net 'nonce-<?= \$cspNonce ?>';
style-src 'self' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net;");

require_once 'admin_header.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style nonce="<?= $cspNonce ?>">
        body {
            background-color: #f5f6fa;
        }

        .content {
            padding: 20px;
        }

        .chart-container canvas {
            max-width: 100%;
            height: 300px;
        }

        .card {
            border-radius: 10px;
            margin-bottom: 20px;
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
                <h1 class="mb-3">Reports</h1>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" id="exportPDF">Export as PDF</button>
                    <button class="btn btn-secondary" id="exportCSV">Export as CSV</button>
                    <button class="btn btn-success" id="exportExcel">Export as Excel</button>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>User Statistics</h5>
                        <canvas id="userChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>Event Statistics</h5>
                        <canvas id="eventChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>Training Statistics</h5>
                        <canvas id="trainingChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>Revenue Statistics</h5>
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            <!-- Summary Tables -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>Users</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                </tr>
                            </thead>
                            <tbody id="usersTable">
                                <!-- User data will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>Events</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th>Date</th>
                                    <th>Location</th>
                                </tr>
                            </thead>
                            <tbody id="eventsTable">
                                <!-- Event data will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>Trainings</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Title</th>
                                    <th>Schedule</th>
                                    <th>Capacity</th>
                                </tr>
                            </thead>
                            <tbody id="trainingsTable">
                                <!-- Training data will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>Announcements</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Text</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody id="announcementsTable">
                                <!-- Announcement data will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include external script instead of inline script -->
    <script src="reports.js" nonce="<?= $cspNonce ?>"></script>
</body>

</html>
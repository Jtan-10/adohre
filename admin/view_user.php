<?php
define('APP_INIT', true); // Added to enable proper access.
require_once 'admin_header.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$userId = $_GET['user_id'] ?? null;
if (!$userId) {
    echo "Invalid user ID.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - Admin Dashboard</title>

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- DataTables + Bootstrap 5 Integration CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <!-- Bootstrap JS (with nonce) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?= $cspNonce ?>">
    </script>

    <!-- jQuery (with nonce) -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" nonce="<?= $cspNonce ?>"></script>

    <!-- DataTables + Bootstrap 5 Integration JS (with nonce) -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js" nonce="<?= $cspNonce ?>"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js" nonce="<?= $cspNonce ?>"></script>
</head>

<body>
    <div class="container my-4">
        <!-- Page Title and Back Button -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h2 class="mb-0">Details for User ID: <?= htmlspecialchars($userId); ?></h2>
            <a href="users.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>

        <!-- Joined Events -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">Joined Events</h4>
            </div>
            <div class="card-body">
                <table id="userEventsTable" class="table table-striped table-bordered w-100">
                    <thead>
                        <tr>
                            <th>Event Title</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Joined Trainings -->
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Joined Trainings</h4>
            </div>
            <div class="card-body">
                <table id="userTrainingsTable" class="table table-striped table-bordered w-100">
                    <thead>
                        <tr>
                            <th>Training Title</th>
                            <th>Description</th>
                            <th>Schedule</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- DataTables Initialization (with nonce) -->
    <script nonce="<?= $cspNonce ?>">
    $(document).ready(function() {
        // Initialize DataTable for Events
        $('#userEventsTable').DataTable({
            processing: true,
            serverSide: false,
            pagingType: 'full_numbers', // or 'simple_numbers', 'simple', etc.
            ajax: {
                url: `../backend/routes/user.php?action=get_user_events&user_id=<?= $userId; ?>`,
                type: "GET",
                dataSrc: "data",
            },
            columns: [{
                    data: "title",
                    title: "Event Title"
                },
                {
                    data: "description",
                    title: "Description"
                },
                {
                    data: "date",
                    title: "Date"
                },
                {
                    data: "location",
                    title: "Location"
                },
            ],
        });

        // Initialize DataTable for Trainings
        $('#userTrainingsTable').DataTable({
            processing: true,
            serverSide: false,
            pagingType: 'full_numbers',
            ajax: {
                url: `../backend/routes/user.php?action=get_user_trainings&user_id=<?= $userId; ?>`,
                type: "GET",
                dataSrc: "data",
            },
            columns: [{
                    data: "title",
                    title: "Training Title"
                },
                {
                    data: "description",
                    title: "Description"
                },
                {
                    data: "schedule",
                    title: "Schedule"
                },
            ],
        });
    });
    </script>
</body>

</html>
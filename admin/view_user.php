<?php
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
</head>

<body>
    <div class="container my-4">
        <h2>Details for User ID: <?php echo htmlspecialchars($userId); ?></h2>

        <h4>Joined Events</h4>
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

        <h4 class="mt-4">Joined Trainings</h4>
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

    <script>
    $(document).ready(function() {
        $('#userEventsTable').DataTable({
            processing: true,
            serverSide: false, // No need for server-side processing
            ajax: {
                url: `../backend/routes/user.php?action=get_user_events&user_id=<?php echo $userId; ?>`,
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


        $('#userTrainingsTable').DataTable({
            processing: true,
            serverSide: false, // No need for server-side processing
            ajax: {
                url: `../backend/routes/user.php?action=get_user_trainings&user_id=<?php echo $userId; ?>`,
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
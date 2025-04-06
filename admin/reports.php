<?php
define('APP_INIT', true); // Added to enable proper access.

// Log that the reports page is being loaded.
error_log("DEBUG: reports.php is being loaded at " . date('Y-m-d H:i:s'));

// Send the CSP header before any output.
require_once 'admin_header.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log("DEBUG: Unauthorized access attempt detected. Session data: " . print_r($_SESSION, true));
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
    <style>
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

    /* Additional styling for new chart/table row */
    .analytics-breakdown {
        font-size: 0.9rem;
    }

    canvas {
        background-color: #fff;
        /* white background */
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

            <!-- New Registrations Chart Section -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card p-3">
                        <h5>Registrations Overview</h5>
                        <canvas id="registrationsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- New Breakdown Section -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>New Users Trend (Last 6 Months)</h5>
                        <canvas id="newUsersChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6 analytics-breakdown">
                    <div class="card p-3">
                        <h5>Additional Analytics</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Metric</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Total Chat Messages</td>
                                    <td id="totalChatMessagesTable">...</td>
                                </tr>
                                <tr>
                                    <td>Total Consultations</td>
                                    <td id="totalConsultationsTable">...</td>
                                </tr>
                                <tr>
                                    <td>Total Certificates</td>
                                    <td id="totalCertificatesTable">...</td>
                                </tr>
                            </tbody>
                        </table>
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

    <!-- Include external script -->
    <script>
    // Sanitize function to escape HTML characters
    function sanitize(str) {
        if (str === null || str === undefined) {
            return "";
        }
        return str.toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }

    document.addEventListener('DOMContentLoaded', function() {
        console.log("DEBUG: DOMContentLoaded event fired.");

        try {
            // Fetch analytics data and populate tables and charts
            fetch('../backend/routes/analytics.php')
                .then(response => {
                    console.log("DEBUG: Received response from analytics.php", response);
                    return response.json();
                })
                .then(data => {
                    console.log("DEBUG: Parsed JSON data:", data);
                    if (data.status) {
                        // Populate tables using sanitized data
                        function populateTable(tableId, rows) {
                            try {
                                const tableBody = document.getElementById(tableId);
                                if (!tableBody) {
                                    throw new Error("Table element with id " + tableId + " not found.");
                                }
                                tableBody.innerHTML = rows
                                    .map((row, index) => `
                                    <tr>
                                        <td>${index + 1}</td>
                                        ${Object.values(row)
                                            .map(value => `<td>${sanitize(value)}</td>`)
                                            .join('')}
                                    </tr>
                                `)
                                    .join('');
                                console.log("DEBUG: Populated table " + tableId);
                            } catch (e) {
                                console.error("Error populating table " + tableId + ":", e);
                            }
                        }
                        populateTable('usersTable', data.data.users);
                        populateTable('eventsTable', data.data.events);
                        populateTable('trainingsTable', data.data.trainings);
                        populateTable('announcementsTable', data.data.announcements);

                        // Update additional breakdown table
                        document.getElementById('totalChatMessagesTable').innerText = data.data
                            .total_chat_messages || 0;
                        document.getElementById('totalConsultationsTable').innerText = data.data
                            .total_consultations || 0;
                        document.getElementById('totalCertificatesTable').innerText = data.data
                            .total_certificates || 0;

                        // Function to get or destroy an existing chart instance
                        function getOrDestroyChart(ctx) {
                            try {
                                const existingChart = Chart.getChart(ctx.canvas);
                                if (existingChart) {
                                    console.log("DEBUG: Destroying existing chart on", ctx.canvas.id);
                                    existingChart.destroy();
                                }
                            } catch (e) {
                                console.error("Error in getOrDestroyChart for canvas " + ctx.canvas.id, e);
                            }
                        }

                        // New Users Trend Chart
                        try {
                            const newUsersCtx = document.getElementById('newUsersChart').getContext('2d');
                            getOrDestroyChart(newUsersCtx);
                            const newUsersData = data.data.new_users || [];
                            new Chart(newUsersCtx, {
                                type: 'line',
                                data: {
                                    labels: newUsersData.map(item => item.month),
                                    datasets: [{
                                        label: 'New Users',
                                        data: newUsersData.map(item => item.new_users),
                                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderWidth: 1,
                                        fill: true,
                                    }]
                                },
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                            console.log("DEBUG: Rendered New Users Trend Chart.");
                        } catch (e) {
                            console.error("Error rendering New Users Trend Chart:", e);
                        }

                        // User Chart (Doughnut)
                        try {
                            const userCtx = document.getElementById('userChart').getContext('2d');
                            getOrDestroyChart(userCtx);
                            new Chart(userCtx, {
                                type: 'doughnut',
                                data: {
                                    labels: ['Total Users', 'Active Members', 'Admins', 'Members'],
                                    datasets: [{
                                        data: [
                                            data.data.total_users,
                                            data.data.active_members,
                                            data.data.admin_count,
                                            data.data.member_count
                                        ],
                                        backgroundColor: ['#36a2eb', '#4bc0c0', '#ff6384',
                                            '#9966ff'
                                        ],
                                    }],
                                },
                            });
                            console.log("DEBUG: Rendered User Chart.");
                        } catch (e) {
                            console.error("Error rendering User Chart:", e);
                        }

                        // Event Chart (Bar)
                        try {
                            const eventCtx = document.getElementById('eventChart').getContext('2d');
                            getOrDestroyChart(eventCtx);
                            new Chart(eventCtx, {
                                type: 'bar',
                                data: {
                                    labels: ['Upcoming Events', 'Finished Events', 'Total Events'],
                                    datasets: [{
                                        label: 'Events',
                                        data: [
                                            data.data.upcoming_events,
                                            data.data.finished_events,
                                            data.data.total_events
                                        ],
                                        backgroundColor: ['#ff9f40', '#ff6384', '#36a2eb'],
                                    }],
                                },
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                            console.log("DEBUG: Rendered Event Chart.");
                        } catch (e) {
                            console.error("Error rendering Event Chart:", e);
                        }

                        // Training Chart (Bar)
                        try {
                            const trainingCtx = document.getElementById('trainingChart').getContext('2d');
                            getOrDestroyChart(trainingCtx);
                            new Chart(trainingCtx, {
                                type: 'bar',
                                data: {
                                    labels: ['Upcoming Trainings', 'Finished Trainings',
                                        'Total Trainings'
                                    ],
                                    datasets: [{
                                        label: 'Trainings',
                                        data: [
                                            data.data.upcoming_trainings,
                                            data.data.finished_trainings,
                                            data.data.total_trainings
                                        ],
                                        backgroundColor: ['#4bc0c0', '#9966ff', '#ffcd56'],
                                    }],
                                },
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                            console.log("DEBUG: Rendered Training Chart.");
                        } catch (e) {
                            console.error("Error rendering Training Chart:", e);
                        }

                        // Revenue Chart (Pie)
                        try {
                            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
                            getOrDestroyChart(revenueCtx);
                            new Chart(revenueCtx, {
                                type: 'pie',
                                data: {
                                    labels: ['Total Revenue'],
                                    datasets: [{
                                        data: [data.data.total_revenue],
                                        backgroundColor: ['#ffcd56'],
                                    }],
                                },
                            });
                            console.log("DEBUG: Rendered Revenue Chart.");
                        } catch (e) {
                            console.error("Error rendering Revenue Chart:", e);
                        }

                        // Registrations Overview Chart (Bar)
                        try {
                            const registrationsCtx = document.getElementById('registrationsChart')
                                .getContext('2d');
                            getOrDestroyChart(registrationsCtx);
                            new Chart(registrationsCtx, {
                                type: 'bar',
                                data: {
                                    labels: ['Joined Events', 'Joined Trainings',
                                        'Membership Applications'
                                    ],
                                    datasets: [{
                                        label: 'Registrations',
                                        data: [
                                            data.data.joined_events,
                                            data.data.joined_trainings,
                                            data.data.membership_applications,
                                        ],
                                        backgroundColor: ['#36a2eb', '#4bc0c0', '#ff6384'],
                                    }],
                                },
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            display: true
                                        },
                                        title: {
                                            display: false
                                        }
                                    }
                                }
                            });
                            console.log("DEBUG: Rendered Registrations Overview Chart.");
                        } catch (e) {
                            console.error("Error rendering Registrations Overview Chart:", e);
                        }
                    } else {
                        console.error("DEBUG: Analytics data fetch returned status false.");
                        alert('Failed to fetch analytics data.');
                    }
                })
                .catch(err => {
                    console.error('Error fetching analytics data:', err);
                });
        } catch (e) {
            console.error("Unhandled error during DOMContentLoaded processing:", e);
        }

        // Set up export buttons

        // For CSV and Excel, we use window.open as before.
        document.getElementById('exportCSV').addEventListener('click', () => {
            console.log("DEBUG: Export CSV button clicked.");
            window.open('../backend/routes/export.php?format=csv', '_blank');
        });
        document.getElementById('exportExcel').addEventListener('click', () => {
            console.log("DEBUG: Export Excel button clicked.");
            window.open('../backend/routes/export.php?format=excel', '_blank');
        });

        // Update Export PDF button: capture chart canvases as images and submit via a hidden form
        document.getElementById('exportPDF').addEventListener('click', function() {
            console.log("DEBUG: Export PDF button clicked.");
            try {
                // Define the list of canvas IDs representing your charts
                const canvasIds = [
                    'userChart',
                    'eventChart',
                    'trainingChart',
                    'revenueChart',
                    'registrationsChart',
                    'newUsersChart'
                ];

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../backend/routes/export.php?format=pdf';
                form.target = '_blank'; // This will open the PDF in a new tab

                // Process each canvas and add to form
                canvasIds.forEach(id => {
                    const canvas = document.getElementById(id);
                    if (canvas) {
                        try {
                            // Get image data with maximum quality
                            const imgData = canvas.toDataURL('image/png', 1.0);
                            console.log(
                                `DEBUG: Captured image for ${id}, data length: ${imgData.length}`
                            );

                            // Create input for this chart
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = id;
                            input.value = imgData;
                            form.appendChild(input);
                        } catch (canvasErr) {
                            console.error(`Error capturing ${id}:`, canvasErr);
                        }
                    } else {
                        console.error(`Canvas with id ${id} not found.`);
                    }
                });

                // Add a timestamp to prevent caching issues
                const timestampInput = document.createElement('input');
                timestampInput.type = 'hidden';
                timestampInput.name = 'timestamp';
                timestampInput.value = Date.now();
                form.appendChild(timestampInput);

                // Append form to the body and submit it
                document.body.appendChild(form);
                console.log("DEBUG: Submitting PDF export form with chart data.");
                form.submit();

                // Remove the form after submission
                setTimeout(() => {
                    document.body.removeChild(form);
                }, 1000);

            } catch (e) {
                console.error("Error during PDF export:", e);
                alert(
                    "There was an error preparing the PDF export. Please check the console for details."
                );
            }
        });
    });
    </script>
</body>

</html>
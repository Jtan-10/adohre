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

    /* Filter section styling */
    .filter-section {
        background-color: #fff;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .filter-section .form-check {
        margin-right: 15px;
    }

    .filter-section h5 {
        margin-bottom: 15px;
        color: #495057;
    }

    .section-toggle {
        cursor: pointer;
        transition: all 0.3s;
    }

    .section-toggle:hover {
        color: #0d6efd;
    }

    .hidden-section {
        display: none;
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

            <!-- Filter Section -->
            <div class="filter-section mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-funnel-fill me-2"></i>Filter Reports</h5>
                    <button class="btn btn-sm btn-outline-secondary" id="toggleAllFilters">
                        <i class="bi bi-check2-all me-1"></i>Select All
                    </button>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong class="d-block mb-2">Chart Sections:</strong>
                            <div class="d-flex flex-wrap">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox" value="user-stats"
                                        id="filterUserStats" checked>
                                    <label class="form-check-label" for="filterUserStats">User Statistics</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox" value="event-stats"
                                        id="filterEventStats" checked>
                                    <label class="form-check-label" for="filterEventStats">Event Statistics</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox"
                                        value="training-stats" id="filterTrainingStats" checked>
                                    <label class="form-check-label" for="filterTrainingStats">Training
                                        Statistics</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox" value="revenue-stats"
                                        id="filterRevenueStats" checked>
                                    <label class="form-check-label" for="filterRevenueStats">Revenue Statistics</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox"
                                        value="registration-overview" id="filterRegistrationOverview" checked>
                                    <label class="form-check-label" for="filterRegistrationOverview">Registration
                                        Overview</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox"
                                        value="new-users-trend" id="filterNewUsersTrend" checked>
                                    <label class="form-check-label" for="filterNewUsersTrend">New Users Trend</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <strong class="d-block mb-2">Table Sections:</strong>
                            <div class="d-flex flex-wrap">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox"
                                        value="additional-analytics" id="filterAdditionalAnalytics" checked>
                                    <label class="form-check-label" for="filterAdditionalAnalytics">Additional
                                        Analytics</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox" value="users-table"
                                        id="filterUsersTable" checked>
                                    <label class="form-check-label" for="filterUsersTable">Users Table</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox" value="events-table"
                                        id="filterEventsTable" checked>
                                    <label class="form-check-label" for="filterEventsTable">Events Table</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox"
                                        value="trainings-table" id="filterTrainingsTable" checked>
                                    <label class="form-check-label" for="filterTrainingsTable">Trainings Table</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input section-filter" type="checkbox"
                                        value="announcements-table" id="filterAnnouncementsTable" checked>
                                    <label class="form-check-label" for="filterAnnouncementsTable">Announcements
                                        Table</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-2">
                    <button class="btn btn-primary" id="applyFilters">Apply Filters</button>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row mb-4" id="user-stats-section">
                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>User Statistics</h5>
                        <canvas id="userChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6" id="event-stats-section">
                    <div class="card p-3">
                        <h5>Event Statistics</h5>
                        <canvas id="eventChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6" id="training-stats-section">
                    <div class="card p-3">
                        <h5>Training Statistics</h5>
                        <canvas id="trainingChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6" id="revenue-stats-section">
                    <div class="card p-3">
                        <h5>Revenue Statistics</h5>
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- New Registrations Chart Section -->
            <div class="row mb-4" id="registration-overview-section">
                <div class="col-md-12">
                    <div class="card p-3">
                        <h5>Registrations Overview</h5>
                        <canvas id="registrationsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- New Breakdown Section -->
            <div class="row mb-4">
                <div class="col-md-6" id="new-users-trend-section">
                    <div class="card p-3">
                        <h5>New Users Trend (Last 6 Months)</h5>
                        <canvas id="newUsersChart"></canvas>
                    </div>
                </div>
                <div class="col-md-6 analytics-breakdown" id="additional-analytics-section">
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
                <div class="col-md-6" id="users-table-section">
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
                <div class="col-md-6" id="events-table-section">
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
                <div class="col-md-6" id="trainings-table-section">
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
                <div class="col-md-6" id="announcements-table-section">
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

        // Filter management
        let activeFilters = {
            'user-stats': true,
            'event-stats': true,
            'training-stats': true,
            'revenue-stats': true,
            'registration-overview': true,
            'new-users-trend': true,
            'additional-analytics': true,
            'users-table': true,
            'events-table': true,
            'trainings-table': true,
            'announcements-table': true
        };

        // Toggle all filters
        const toggleAllBtn = document.getElementById('toggleAllFilters');
        let allSelected = true; // Start with all selected

        toggleAllBtn.addEventListener('click', function() {
            allSelected = !allSelected;
            document.querySelectorAll('.section-filter').forEach(checkbox => {
                checkbox.checked = allSelected;
            });

            // Update button text
            this.innerHTML = allSelected ?
                '<i class="bi bi-check2-all me-1"></i>Select All' :
                '<i class="bi bi-square me-1"></i>Select All';
        });

        // Apply filters button
        document.getElementById('applyFilters').addEventListener('click', function() {
            // Update activeFilters based on checkbox states
            document.querySelectorAll('.section-filter').forEach(checkbox => {
                activeFilters[checkbox.value] = checkbox.checked;
            });

            // Show/hide sections based on filter settings
            for (const [section, isVisible] of Object.entries(activeFilters)) {
                const sectionElement = document.getElementById(`${section}-section`);
                if (sectionElement) {
                    sectionElement.style.display = isVisible ? 'block' : 'none';
                }
            }

            // Refresh the charts that are visible
            if (activeFilters['user-stats']) updateChart('userChart');
            if (activeFilters['event-stats']) updateChart('eventChart');
            if (activeFilters['training-stats']) updateChart('trainingChart');
            if (activeFilters['revenue-stats']) updateChart('revenueChart');
            if (activeFilters['registration-overview']) updateChart('registrationsChart');
            if (activeFilters['new-users-trend']) updateChart('newUsersChart');
        });

        // Helper function to get the current active filters
        function getActiveFilters() {
            let filters = [];
            document.querySelectorAll('.section-filter:checked').forEach(checkbox => {
                filters.push(checkbox.value);
            });
            return filters;
        }

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

                        // Store chart creation functions for later use
                        const chartFunctions = {
                            newUsersChart: function() {
                                try {
                                    const newUsersCtx = document.getElementById('newUsersChart')
                                        .getContext('2d');
                                    getOrDestroyChart(newUsersCtx);
                                    const newUsersData = data.data.new_users || [];
                                    new Chart(newUsersCtx, {
                                        type: 'line',
                                        data: {
                                            labels: newUsersData.map(item => item.month),
                                            datasets: [{
                                                label: 'New Users',
                                                data: newUsersData.map(item => item
                                                    .new_users),
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
                            },
                            userChart: function() {
                                try {
                                    const userCtx = document.getElementById('userChart').getContext(
                                        '2d');
                                    getOrDestroyChart(userCtx);
                                    new Chart(userCtx, {
                                        type: 'doughnut',
                                        data: {
                                            labels: ['Total Users', 'Active Members',
                                                'Admins', 'Members'
                                            ],
                                            datasets: [{
                                                data: [
                                                    data.data.total_users,
                                                    data.data.active_members,
                                                    data.data.admin_count,
                                                    data.data.member_count
                                                ],
                                                backgroundColor: ['#36a2eb',
                                                    '#4bc0c0', '#ff6384',
                                                    '#9966ff'
                                                ],
                                            }],
                                        },
                                    });
                                    console.log("DEBUG: Rendered User Chart.");
                                } catch (e) {
                                    console.error("Error rendering User Chart:", e);
                                }
                            },
                            eventChart: function() {
                                try {
                                    const eventCtx = document.getElementById('eventChart')
                                        .getContext('2d');
                                    getOrDestroyChart(eventCtx);
                                    new Chart(eventCtx, {
                                        type: 'bar',
                                        data: {
                                            labels: ['Upcoming Events', 'Finished Events',
                                                'Total Events'
                                            ],
                                            datasets: [{
                                                label: 'Events',
                                                data: [
                                                    data.data.upcoming_events,
                                                    data.data.finished_events,
                                                    data.data.total_events
                                                ],
                                                backgroundColor: ['#ff9f40',
                                                    '#ff6384', '#36a2eb'
                                                ],
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
                            },
                            trainingChart: function() {
                                try {
                                    const trainingCtx = document.getElementById('trainingChart')
                                        .getContext('2d');
                                    getOrDestroyChart(trainingCtx);
                                    new Chart(trainingCtx, {
                                        type: 'bar',
                                        data: {
                                            labels: ['Upcoming Trainings',
                                                'Finished Trainings',
                                                'Total Trainings'
                                            ],
                                            datasets: [{
                                                label: 'Trainings',
                                                data: [
                                                    data.data
                                                    .upcoming_trainings,
                                                    data.data
                                                    .finished_trainings,
                                                    data.data.total_trainings
                                                ],
                                                backgroundColor: ['#4bc0c0',
                                                    '#9966ff', '#ffcd56'
                                                ],
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
                            },
                            revenueChart: function() {
                                try {
                                    const revenueCtx = document.getElementById('revenueChart')
                                        .getContext('2d');
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
                            },
                            registrationsChart: function() {
                                try {
                                    const registrationsCtx = document.getElementById(
                                            'registrationsChart')
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
                                                    data.data
                                                    .membership_applications,
                                                ],
                                                backgroundColor: ['#36a2eb',
                                                    '#4bc0c0', '#ff6384'
                                                ],
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
                                    console.error("Error rendering Registrations Overview Chart:",
                                        e);
                                }
                            }
                        };

                        // Function to update a specific chart if it's visible
                        function updateChart(chartId) {
                            const canvas = document.getElementById(chartId);
                            if (canvas && canvas.closest('[id$="-section"]').style.display !== 'none') {
                                if (chartFunctions[chartId]) {
                                    chartFunctions[chartId]();
                                }
                            }
                        }

                        // Initial chart rendering
                        chartFunctions.newUsersChart();
                        chartFunctions.userChart();
                        chartFunctions.eventChart();
                        chartFunctions.trainingChart();
                        chartFunctions.revenueChart();
                        chartFunctions.registrationsChart();
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

        // Export buttons - updated to include filter selection
        document.getElementById('exportCSV').addEventListener('click', () => {
            console.log("DEBUG: Export CSV button clicked.");
            const filters = getActiveFilters();
            window.open(`../backend/routes/export.php?format=csv&filters=${filters.join(',')}`,
                '_blank');
        });

        document.getElementById('exportExcel').addEventListener('click', () => {
            console.log("DEBUG: Export Excel button clicked.");
            const filters = getActiveFilters();
            window.open(`../backend/routes/export.php?format=excel&filters=${filters.join(',')}`,
                '_blank');
        });

        // Update Export PDF button: capture chart canvases as images and submit via a hidden form
        document.getElementById('exportPDF').addEventListener('click', function() {
            console.log("DEBUG: Export PDF button clicked.");
            try {
                // Define the list of canvas IDs representing your charts with their corresponding section IDs
                const chartConfig = [{
                        chartId: 'userChart',
                        sectionId: 'user-stats-section'
                    },
                    {
                        chartId: 'eventChart',
                        sectionId: 'event-stats-section'
                    },
                    {
                        chartId: 'trainingChart',
                        sectionId: 'training-stats-section'
                    },
                    {
                        chartId: 'revenueChart',
                        sectionId: 'revenue-stats-section'
                    },
                    {
                        chartId: 'registrationsChart',
                        sectionId: 'registration-overview-section'
                    },
                    {
                        chartId: 'newUsersChart',
                        sectionId: 'new-users-trend-section'
                    }
                ];

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../backend/routes/export.php?format=pdf';
                form.target = '_blank'; // This will open the PDF in a new tab

                // Add filters to the form
                const filtersInput = document.createElement('input');
                filtersInput.type = 'hidden';
                filtersInput.name = 'filters';
                filtersInput.value = getActiveFilters().join(',');
                form.appendChild(filtersInput);

                // Process each canvas and add to form if it's visible
                let chartCount = 0;
                chartConfig.forEach(({
                    chartId,
                    sectionId
                }) => {
                    // Only include canvases that are currently visible/active
                    const canvasElement = document.getElementById(chartId);
                    const sectionElement = document.getElementById(sectionId);

                    if (canvasElement && sectionElement) {
                        const isVisible = window.getComputedStyle(sectionElement).display !==
                            'none';

                        if (isVisible) {
                            try {
                                console.log(`DEBUG: Processing visible chart: ${chartId}`);
                                // Make sure the chart is rendered
                                if (typeof Chart !== 'undefined' && Chart.getChart(
                                        canvasElement)) {
                                    // Get image data with 0.8 quality (good balance between quality and size)
                                    const imgData = canvasElement.toDataURL('image/png', 0.8);
                                    console.log(
                                        `DEBUG: Captured image for ${chartId}, data length: ${imgData.length}`
                                    );

                                    // Create input for this chart
                                    const input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = chartId;
                                    input.value = imgData;
                                    form.appendChild(input);
                                    chartCount++;
                                } else {
                                    console.warn(`Chart instance not found for ${chartId}`);
                                }
                            } catch (canvasErr) {
                                console.error(`Error capturing ${chartId}:`, canvasErr);
                            }
                        } else {
                            console.log(`Skipping hidden chart: ${chartId}`);
                        }
                    } else {
                        console.error(
                            `Canvas or section with id ${chartId}/${sectionId} not found.`);
                    }
                });

                // Add a timestamp to prevent caching issues
                const timestampInput = document.createElement('input');
                timestampInput.type = 'hidden';
                timestampInput.name = 'timestamp';
                timestampInput.value = Date.now();
                form.appendChild(timestampInput);

                // Only submit if we have at least one chart
                if (chartCount > 0) {
                    // Append form to the body and submit it
                    document.body.appendChild(form);
                    console.log(`DEBUG: Submitting PDF export form with ${chartCount} charts.`);
                    form.submit();

                    // Remove the form after submission
                    setTimeout(() => {
                        document.body.removeChild(form);
                    }, 1000);
                } else {
                    console.warn("No visible charts to export to PDF");
                    alert(
                        "No charts are currently visible to export. Please adjust your filters to include at least one chart section.");
                }

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
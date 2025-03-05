<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health and Wellness - ADOHRE</title>
    <link rel="icon" href="assets/logo.png" type="image/jpg" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    /* Optional: Additional styling for the health dashboard */
    body {
        background-color: #f8f9fa;
    }

    .container {
        margin-top: 30px;
    }
    </style>
</head>

<body>
    <?php include('header.php'); ?>
    <!-- Include the Sidebar -->
    <?php include('sidebar.php'); ?>
    <div class="container">
        <h1 class="mb-4">Health and Wellness</h1>

        <div class="row">
            <!-- Health Profile Form -->
            <div class="col-md-6">
                <h3>Your Health Profile</h3>
                <form method="post" action="process_health_profile.php">
                    <div class="mb-3">
                        <label for="age" class="form-label">Age</label>
                        <input type="number" class="form-control" id="age" name="age" required>
                    </div>
                    <div class="mb-3">
                        <label for="weight" class="form-label">Weight (kg)</label>
                        <input type="number" class="form-control" id="weight" name="weight" required>
                    </div>
                    <div class="mb-3">
                        <label for="height" class="form-label">Height (cm)</label>
                        <input type="number" class="form-control" id="height" name="height" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Get Health Insights</button>
                </form>
            </div>

            <!-- Educational Health Content -->
            <div class="col-md-6">
                <h3>Health Educational Content</h3>
                <div id="health-content">
                    <!-- Content will be loaded dynamically (for example, via an API call or by reading a static file) -->
                    <p>Loading content...</p>
                </div>
            </div>
        </div>

        <!-- Health Tracking Data Visualization -->
        <div class="mt-5">
            <h3>Health Tracking Data</h3>
            <canvas id="healthChart"></canvas>
        </div>
    </div>

    <?php include('footer.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Simulate fetching educational content (this could be replaced with an AJAX call)
    document.addEventListener("DOMContentLoaded", function() {
        var healthContentDiv = document.getElementById('health-content');
        healthContentDiv.innerHTML =
            '<p>Here is some educational content on maintaining a healthy lifestyle, including tips on nutrition, exercise, and mental health.</p>';
    });

    // Sample data visualization using Chart.js
    var ctx = document.getElementById('healthChart').getContext('2d');
    var healthChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            datasets: [{
                label: 'Daily Steps',
                data: [3000, 5000, 4500, 6000, 7000, 8000, 7500],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                tension: 0.3
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
    </script>
</body>

</html>
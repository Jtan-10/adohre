// Helper function to send error logs to the server
function logErrorToServer(message) {
    fetch('../backend/routes/log_error.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ error: message, timestamp: new Date().toISOString() })
    }).catch(err => console.error("Failed to log error to server:", err));
}

// Sanitize function to escape HTML characters
function sanitize(str) {
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
                            logErrorToServer("Error populating table " + tableId + ": " + e.message);
                        }
                    }
                    populateTable('usersTable', data.data.users);
                    populateTable('eventsTable', data.data.events);
                    populateTable('trainingsTable', data.data.trainings);
                    populateTable('announcementsTable', data.data.announcements);

                    // Update additional breakdown table
                    document.getElementById('totalChatMessagesTable').innerText = data.data.total_chat_messages || 0;
                    document.getElementById('totalConsultationsTable').innerText = data.data.total_consultations || 0;
                    document.getElementById('totalCertificatesTable').innerText = data.data.total_certificates || 0;

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
                            logErrorToServer("Error in getOrDestroyChart for canvas " + ctx.canvas.id + ": " + e.message);
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
                            options: { scales: { y: { beginAtZero: true } } }
                        });
                        console.log("DEBUG: Rendered New Users Trend Chart.");
                    } catch (e) {
                        console.error("Error rendering New Users Trend Chart:", e);
                        logErrorToServer("Error rendering New Users Trend Chart: " + e.message);
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
                                    backgroundColor: ['#36a2eb', '#4bc0c0', '#ff6384', '#9966ff'],
                                }],
                            },
                        });
                        console.log("DEBUG: Rendered User Chart.");
                    } catch (e) {
                        console.error("Error rendering User Chart:", e);
                        logErrorToServer("Error rendering User Chart: " + e.message);
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
                            options: { scales: { y: { beginAtZero: true } } }
                        });
                        console.log("DEBUG: Rendered Event Chart.");
                    } catch (e) {
                        console.error("Error rendering Event Chart:", e);
                        logErrorToServer("Error rendering Event Chart: " + e.message);
                    }

                    // Training Chart (Bar)
                    try {
                        const trainingCtx = document.getElementById('trainingChart').getContext('2d');
                        getOrDestroyChart(trainingCtx);
                        new Chart(trainingCtx, {
                            type: 'bar',
                            data: {
                                labels: ['Upcoming Trainings', 'Finished Trainings', 'Total Trainings'],
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
                            options: { scales: { y: { beginAtZero: true } } }
                        });
                        console.log("DEBUG: Rendered Training Chart.");
                    } catch (e) {
                        console.error("Error rendering Training Chart:", e);
                        logErrorToServer("Error rendering Training Chart: " + e.message);
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
                        logErrorToServer("Error rendering Revenue Chart: " + e.message);
                    }

                    // Registrations Overview Chart (Bar)
                    try {
                        const registrationsCtx = document.getElementById('registrationsChart').getContext('2d');
                        getOrDestroyChart(registrationsCtx);
                        new Chart(registrationsCtx, {
                            type: 'bar',
                            data: {
                                labels: ['Joined Events', 'Joined Trainings', 'Membership Applications'],
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
                                scales: { y: { beginAtZero: true } },
                                plugins: { legend: { display: true }, title: { display: false } }
                            }
                        });
                        console.log("DEBUG: Rendered Registrations Overview Chart.");
                    } catch (e) {
                        console.error("Error rendering Registrations Overview Chart:", e);
                        logErrorToServer("Error rendering Registrations Overview Chart: " + e.message);
                    }
                } else {
                    console.error("DEBUG: Analytics data fetch returned status false.");
                    alert('Failed to fetch analytics data.');
                    logErrorToServer("Analytics data fetch returned status false.");
                }
            })
            .catch(err => {
                console.error('Error fetching analytics data:', err);
                logErrorToServer("Error fetching analytics data: " + err.message);
            });
    } catch (e) {
        console.error("Unhandled error during DOMContentLoaded processing:", e);
        logErrorToServer("Unhandled error during DOMContentLoaded processing: " + e.message);
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
            const images = {};
            canvasIds.forEach(id => {
                const canvas = document.getElementById(id);
                if (canvas) {
                    images[id] = canvas.toDataURL('image/png');
                    console.log("DEBUG: Captured image for canvas " + id);
                } else {
                    console.error("Canvas with id " + id + " not found.");
                    logErrorToServer("Canvas with id " + id + " not found during PDF export.");
                }
            });
            // Create a hidden form to POST the chart images to export.php
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../backend/routes/export.php?format=pdf';
            form.target = '_blank'; // This will open the PDF in a new tab

            // Append each chart image as a hidden input field
            for (let id in images) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = id; // e.g. "userChart", "eventChart", etc.
                input.value = images[id];
                form.appendChild(input);
            }
            // Append form to the body and submit it
            document.body.appendChild(form);
            form.submit();
            console.log("DEBUG: Submitted PDF export form.");
        } catch (e) {
            console.error("Error during PDF export:", e);
            logErrorToServer("Error during PDF export: " + e.message);
        }
    });
});

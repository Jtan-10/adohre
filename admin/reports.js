// Sanitize function to escape HTML characters
function sanitize(str) {
    return str.toString()
              .replace(/&/g, "&amp;")
              .replace(/</g, "&lt;")
              .replace(/>/g, "&gt;");
}

document.addEventListener('DOMContentLoaded', function() {
    // Fetch analytics data and populate tables and charts
    fetch('../backend/routes/analytics.php')
        .then(response => response.json())
        .then(data => {
            if (data.status) {
                // Populate tables using sanitized data
                function populateTable(tableId, rows) {
                    const tableBody = document.getElementById(tableId);
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
                }
                populateTable('usersTable', data.data.users);
                populateTable('eventsTable', data.data.events);
                populateTable('trainingsTable', data.data.trainings);
                populateTable('announcementsTable', data.data.announcements);

                // Initialize Charts
                function getOrDestroyChart(ctx) {
                    const existingChart = Chart.getChart(ctx.canvas);
                    if (existingChart) {
                        existingChart.destroy();
                    }
                }
                // Update additional breakdown table
                document.getElementById('totalChatMessagesTable').innerText = data.data.total_chat_messages || 0;
                document.getElementById('totalConsultationsTable').innerText = data.data.total_consultations || 0;
                document.getElementById('totalCertificatesTable').innerText = data.data.total_certificates || 0;

                // New Users Trend Chart
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

                // User Chart (Doughnut)
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

                // Event Chart (Bar)
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

                // Training Chart (Bar)
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

                // Revenue Chart (Pie)
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

                // Registrations Overview Chart (Bar)
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
            } else {
                alert('Failed to fetch analytics data.');
            }
        })
        .catch(err => console.error('Error fetching analytics data:', err));

    // Set up export buttons

    // For CSV and Excel, we use window.open as before.
    document.getElementById('exportCSV').addEventListener('click', () => {
        window.open('../backend/routes/export.php?format=csv', '_blank');
    });
    document.getElementById('exportExcel').addEventListener('click', () => {
        window.open('../backend/routes/export.php?format=excel', '_blank');
    });

    // Update Export PDF button: capture chart canvases as images and submit via a hidden form
    document.getElementById('exportPDF').addEventListener('click', function() {
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
    });
});

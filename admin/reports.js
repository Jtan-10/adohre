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

                // User Chart
                const userCtx = document.getElementById('userChart').getContext('2d');
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

                // Event Chart
                const eventCtx = document.getElementById('eventChart').getContext('2d');
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
                        scales: { y: { beginAtZero: true } }
                    }
                });

                // Training Chart
                const trainingCtx = document.getElementById('trainingChart').getContext('2d');
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
                    options: {
                        scales: { y: { beginAtZero: true } }
                    }
                });

                // Revenue Chart
                const revenueCtx = document.getElementById('revenueChart').getContext('2d');
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
            } else {
                alert('Failed to fetch analytics data.');
            }
        })
        .catch(err => console.error('Error fetching analytics data:', err));

    // Set up export buttons
    document.getElementById('exportPDF').addEventListener('click', () => {
        window.open('../backend/routes/export.php?format=pdf', '_blank');
    });
    document.getElementById('exportCSV').addEventListener('click', () => {
        window.open('../backend/routes/export.php?format=csv', '_blank');
    });
    document.getElementById('exportExcel').addEventListener('click', () => {
        window.open('../backend/routes/export.php?format=excel', '_blank');
    });
});

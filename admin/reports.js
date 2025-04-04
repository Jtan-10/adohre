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

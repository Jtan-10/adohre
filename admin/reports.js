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

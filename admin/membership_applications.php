<?php
require_once 'admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Applications</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <div class="container my-5">
            <h1>Membership Applications</h1>

            <!-- Filter Options -->
            <div class="mb-3">
                <form id="filter-form" class="d-flex align-items-center">
                    <label for="statusFilter" class="form-label me-2">Filter by Status:</label>
                    <select id="statusFilter" name="status" class="form-select w-auto">
                        <option value="">All</option>
                        <option value="Pending">Pending</option>

                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                    <button type="submit" class="btn btn-primary ms-2">Apply</button>
                </form>
            </div>

            <table class="table table-striped" id="applications-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Submitted On</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>

    <!-- View/Update Modal -->
    <div class="modal fade" id="applicationModal" tabindex="-1" aria-labelledby="applicationModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="applicationModalLabel">Application Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="update-form">
                        <input type="hidden" id="application-id">
                        <div id="application-details"></div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" class="form-select">
                                <option value="Pending">Pending</option>
                                <option value="Approved">Approved</option>
                                <option value="Rejected">Rejected</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Application Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="details-body">
                    <!-- Full details will be dynamically inserted here -->
                </div>
            </div>
        </div>
    </div>

    <script>
    const apiUrl = '../backend/routes/membership_applications.php';

    // Fetch applications
    async function fetchApplications(status = '') {
        const response = await axios.get(apiUrl, {
            params: {
                status
            }
        });
        const applications = response.data;
        const tableBody = document.querySelector('#applications-table tbody');
        tableBody.innerHTML = '';
        applications.forEach(app => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${app.name}</td>
                <td>${app.email}</td>
                <td>${app.created_at}</td>
                <td>
                    <span class="badge 
                        ${app.status === 'Pending' ? 'bg-warning' : ''}
                        ${app.status === 'Approved' ? 'bg-success' : ''}
                        ${app.status === 'Rejected' ? 'bg-danger' : ''}">
                        ${app.status}
                    </span>
                </td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="viewApplication(${app.application_id})">View</button>
                    <button class="btn btn-secondary btn-sm" onclick="viewDetails(${app.application_id})">Details</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteApplication(${app.application_id})">Delete</button>
                </td>
            `;
            tableBody.appendChild(row);
        });
    }

    // View Application
    async function viewApplication(id) {
        const response = await axios.get(`${apiUrl}?id=${id}`);
        const app = response.data;
        document.querySelector('#application-id').value = app.application_id;
        document.querySelector('#application-details').innerHTML = `
            <p><strong>Name:</strong> ${app.name}</p>
            <p><strong>Email:</strong> ${app.email}</p>
            <p><strong>Submitted On:</strong> ${app.created_at}</p>
            <p><strong>Status:</strong> ${app.status}</p>
        `;
        document.querySelector('#status').value = app.status;
        const modal = new bootstrap.Modal(document.querySelector('#applicationModal'));
        modal.show();
    }

    // Update Application
    document.querySelector('#update-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.querySelector('#application-id').value;
        const status = document.querySelector('#status').value;
        await axios.post(apiUrl, {
            id,
            status
        });
        if (status === 'Approved') {
            // Optional: Display additional message for role update
            console.log(`User with application ID ${id} was approved and their role was updated.`);
        }
        showMessage('Application updated successfully!', 'success');
        fetchApplications();
        bootstrap.Modal.getInstance(document.querySelector('#applicationModal')).hide();
    });

    // View Details
    async function viewDetails(id) {
        const response = await axios.get(`${apiUrl}?id=${id}`);
        const app = response.data;

        const detailsBody = document.querySelector('#details-body');
        detailsBody.innerHTML = `
            <p><strong>Name:</strong> ${app.name}</p>
            <p><strong>Date of Birth:</strong> ${app.dob}</p>
            <p><strong>Sex:</strong> ${app.sex}</p>
            <p><strong>Current Address:</strong> ${app.current_address}</p>
            <p><strong>Permanent Address:</strong> ${app.permanent_address || 'N/A'}</p>
            <p><strong>Email:</strong> ${app.email}</p>
            <p><strong>Mobile:</strong> ${app.mobile}</p>
            <p><strong>Place of Birth:</strong> ${app.place_of_birth}</p>
            <p><strong>Marital Status:</strong> ${app.marital_status || 'N/A'}</p>
            <p><strong>Emergency Contact:</strong> ${app.emergency_contact}</p>
            <p><strong>DOH Agency:</strong> ${app.doh_agency}</p>
            <p><strong>Employment Start:</strong> ${app.employment_start || 'N/A'}</p>
            <p><strong>Employment End:</strong> ${app.employment_end || 'N/A'}</p>
            <p><strong>School:</strong> ${app.school}</p>
            <p><strong>Degree:</strong> ${app.degree}</p>
            <p><strong>Year Graduated:</strong> ${app.year_graduated}</p>
            <p><strong>Current Engagement:</strong> ${app.current_engagement}</p>
            <p><strong>Key Expertise:</strong> ${app.key_expertise || 'N/A'}</p>
            <p><strong>Specific Field:</strong> ${app.specific_field || 'N/A'}</p>
            <p><strong>Special Skills:</strong> ${app.special_skills || 'N/A'}</p>
            <p><strong>Hobbies:</strong> ${app.hobbies || 'N/A'}</p>
            <p><strong>Committees:</strong> ${app.committees || 'N/A'}</p>
            <p><strong>Signature:</strong> <img src="${app.signature}" alt="Signature" style="max-width: 100%; height: auto;"></p>
        `;
        const modal = new bootstrap.Modal(document.querySelector('#detailsModal'));
        modal.show();
    }

    // Delete Application
    async function deleteApplication(id) {
        if (confirm('Are you sure you want to delete this application?')) {
            await axios.delete(apiUrl, {
                data: {
                    id
                }
            });
            showMessage('Application deleted successfully!', 'success');
            fetchApplications();
        }
    }

    // Filter Applications
    document.querySelector('#filter-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const status = document.querySelector('#statusFilter').value;
        fetchApplications(status);
    });

    // Show Message
    function showMessage(message, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type} mt-3`;
        messageDiv.textContent = message;
        document.querySelector('.container').prepend(messageDiv);
        setTimeout(() => messageDiv.remove(), 3000);
    }

    // Initial Fetch
    fetchApplications();
    </script>
</body>

</html>
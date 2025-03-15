<?php
define('APP_INIT', true); // Added to enable proper access.
require_once 'admin_header.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <!-- External scripts with the same nonce -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" nonce="<?= $cspNonce ?>">
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" nonce="<?= $cspNonce ?>"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.css" />
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.js" nonce="<?= $cspNonce ?>"></script>
    <!-- Inline script with nonce to set CSRF token -->
    <script nonce="<?= $cspNonce ?>">
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';
    </script>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php require_once 'admin_sidebar.php'; ?>
        <!-- Main Content -->
        <div id="content" class="content p-4" style="width: 100%;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="mb-0">List of System Users</h3>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="bi bi-plus"></i> Create New
                </button>
            </div>
            <div class="card">
                <div class="card-body">
                    <table id="usersTable" class="table table-striped table-bordered w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>First Name</th>
                                <th>Last Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Joined Events</h6>
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
                    <h6 class="mt-4">Joined Trainings</h6>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="createUserForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createUserModalLabel">Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="member">Member</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editUserForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="editUserId" name="user_id">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label for="editFirstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editLastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="editLastName" name="last_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="editRole" class="form-label">Role</label>
                            <select class="form-select" id="editRole" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="member">Member</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Inline Scripts with nonce -->
    <script nonce="<?= $cspNonce ?>">
    $(document).ready(function() {
        $('#usersTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: "../backend/routes/user.php",
                type: "GET",
                dataSrc: "data",
            },
            columns: [{
                    data: null,
                    render: (data, type, row, meta) => meta.row + 1,
                    orderable: false
                },
                {
                    data: "first_name"
                },
                {
                    data: "last_name"
                },
                {
                    data: "email"
                },
                {
                    data: "role"
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        return `
                                <div class="dropdown">
                                    <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Action
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="view_user.php?user_id=${row.user_id}">View</a></li>
                                        <li><a class="dropdown-item edit-user" href="#" data-user-id="${row.user_id}">Edit</a></li>
                                        <li><a class="dropdown-item text-danger delete-user" href="#" data-user-id="${row.user_id}">Delete</a></li>
                                    </ul>
                                </div>`;
                    },
                },
            ],
        });

        window.editUser = function(userId) {
            fetch(`../backend/routes/user.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status) {
                        $('#editUserId').val(data.data.user_id);
                        $('#editFirstName').val(data.data.first_name);
                        $('#editLastName').val(data.data.last_name);
                        $('#editEmail').val(data.data.email);
                        $('#editRole').val(data.data.role);
                        $('#editUserModal').modal('show');
                    } else {
                        alert(data.message || 'Failed to fetch user details.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching user:', error);
                    alert('An error occurred while fetching user details.');
                });
        };

        $('#editUserForm').on('submit', function(e) {
            e.preventDefault();
            const userId = $('#editUserId').val();
            const formData = {
                user_id: userId,
                first_name: $('#editFirstName').val(),
                last_name: $('#editLastName').val(),
                email: $('#editEmail').val(),
                role: $('#editRole').val(),
                csrf_token: CSRF_TOKEN
            };
            fetch(`../backend/routes/user.php`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    $('#editUserModal').modal('hide');
                    $('#usersTable').DataTable().ajax.reload();
                });
        });

        window.deleteUser = function(userId) {
            if (confirm("Are you sure you want to delete this user? This action cannot be undone.")) {
                fetch(`../backend/routes/user.php`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            user_id: userId,
                            csrf_token: CSRF_TOKEN
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.message) {
                            alert(data.message);
                        } else if (data.error) {
                            alert(data.error);
                        }
                        $('#usersTable').DataTable().ajax.reload();
                    })
                    .catch(error => {
                        console.error("Error deleting user:", error);
                        alert("An error occurred while deleting the user.");
                    });
            }
        };

        // Delegated event listeners to replace inline event handlers
        $(document).on('click', '.edit-user', function(e) {
            e.preventDefault();
            var userId = $(this).data('user-id');
            editUser(userId);
        });

        $(document).on('click', '.delete-user', function(e) {
            e.preventDefault();
            var userId = $(this).data('user-id');
            deleteUser(userId);
        });
    });
    </script>
</body>

</html>
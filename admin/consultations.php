<?php
require_once 'admin_header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Consultations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
</head>

<body>
    <div class="d-flex">
        <?php require_once 'admin_sidebar.php'; ?>
        <div class="container mt-5">
            <h1>Consultation Management</h1>
            <table class="table table-bordered mt-4">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="consultation-table">
                    <!-- Rows will be populated dynamically -->
                </tbody>
            </table>
        </div>
    </div>

    <script>
    // Fetch consultations from the API
    async function fetchConsultations() {
        try {
            const response = await fetch('../backend/routes/consultation_management.php');
            const consultations = await response.json();

            const tableBody = document.getElementById('consultation-table');
            tableBody.innerHTML = ''; // Clear existing rows

            if (consultations.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center">No consultations found.</td></tr>`;
                return;
            }

            consultations.forEach(consultation => {
                const row = document.createElement('tr');
                // Add data attributes for room ID and consultation owner ID
                row.setAttribute('data-room-id', consultation.chat_room_id);
                row.setAttribute('data-owner-id', consultation.user_id);

                // Use the returned user_first_name and user_last_name.
                // If these keys are missing, display a fallback value.
                const userName = (consultation.user_first_name && consultation.user_last_name) ?
                    `${consultation.user_first_name} ${consultation.user_last_name}` :
                    'N/A';

                // Format the created_at date
                const createdAt = new Date(consultation.created_at).toLocaleString();

                row.innerHTML = `
        <td>${consultation.consultation_id}</td>
        <td>${userName}</td>
        <td>${consultation.description}</td>
        <td>
          <select class="form-select form-select-sm" id="status-${consultation.consultation_id}">
            <option value="open" ${consultation.status === 'open' ? 'selected' : ''}>Open</option>
            <option value="closed" ${consultation.status === 'closed' ? 'selected' : ''}>Closed</option>
          </select>
        </td>
        <td>${createdAt}</td>
        <td>
          <button class="btn btn-primary btn-sm" onclick="openChatroom(${consultation.chat_room_id})">
            <i class="bi bi-chat-dots"></i> Open Chat Room
          </button>
          <button class="btn btn-success btn-sm mt-2" onclick="updateStatus(${consultation.consultation_id})">
            <i class="bi bi-arrow-repeat"></i> Update Status
          </button>
          ${consultation.status === 'closed' ? `
          <button class="btn btn-info btn-sm mt-2" onclick="downloadChat(${consultation.chat_room_id})">
            <i class="bi bi-download"></i> Download Chat
          </button>` : ''}
        </td>
    `;
                tableBody.appendChild(row);
            });

        } catch (error) {
            console.error('Error fetching consultations:', error);
        }
    }

    // Open the chat room for the consultation
    function openChatroom(chatRoomId) {
        if (!chatRoomId) {
            alert('No chat room is linked to this consultation.');
            return;
        }
        window.location.href = `chatroom.php?room_id=${chatRoomId}`;
    }

    // Update consultation status using the API.
    // If the status is set to "closed", call the close_ticket action.
    async function updateStatus(consultationId) {
        const statusSelect = document.getElementById(`status-${consultationId}`);
        const status = statusSelect.value;

        if (status === 'closed') {
            // Get room and owner IDs from the row's data attributes.
            const row = statusSelect.closest('tr');
            const roomId = row.getAttribute('data-room-id');
            const ownerId = row.getAttribute('data-owner-id');

            try {
                const response = await fetch('../backend/routes/consultation_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'close_ticket',
                        room_id: roomId,
                        user_id: ownerId
                    })
                });

                const result = await response.json();
                if (result.status === 'success') {
                    alert('Ticket closed successfully.');
                    fetchConsultations(); // Refresh the table
                } else {
                    alert(result.error || 'An error occurred.');
                }
            } catch (error) {
                console.error('Error closing ticket:', error);
            }
        } else {
            // Otherwise, update the consultation status normally.
            try {
                const response = await fetch('../backend/routes/consultation_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'update_status',
                        consultation_id: consultationId,
                        status: status
                    })
                });

                const result = await response.json();
                if (result.message) {
                    alert(result.message);
                    fetchConsultations(); // Refresh the table
                } else {
                    alert(result.error || 'An error occurred.');
                }
            } catch (error) {
                console.error('Error updating status:', error);
            }
        }
    }

    function downloadChat(chatRoomId) {
        if (!chatRoomId) {
            alert("No chat room is linked to this consultation.");
            return;
        }
        // Redirect to download_chat.php with mode=admin so that admin downloads show the sender's full name with (Admin).
        window.location.href = `../download_chat.php?room_id=${chatRoomId}&mode=admin`;
    }


    // Load consultations on page load
    document.addEventListener('DOMContentLoaded', fetchConsultations);
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
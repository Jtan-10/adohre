<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'backend/db/db_connect.php'; // Verify this path is correct
$userId = $_SESSION['user_id'];

// Fetch all active (open) support tickets for the user.
$activeTickets = [];
if ($conn) {
    $result = $conn->query("SELECT cr.id AS room_id, c.id AS consultation_id, c.created_at
                             FROM chat_rooms cr
                             JOIN consultations c ON cr.consultation_id = c.id
                             WHERE c.user_id = $userId AND c.status = 'open'
                             ORDER BY cr.created_at DESC");
    while ($row = $result->fetch_assoc()) {
         $activeTickets[] = $row;
    }
}

// Fetch closed tickets for the ticket history.
$closedTickets = [];
if ($conn) {
    $result = $conn->query("SELECT cr.id AS chat_room_id, c.id AS consultation_id, c.created_at, c.closed_at
                             FROM consultations c
                             JOIN chat_rooms cr ON c.id = cr.consultation_id
                             WHERE c.user_id = $userId AND c.status = 'closed'
                             ORDER BY c.closed_at DESC");
    while ($row = $result->fetch_assoc()) {
         $closedTickets[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation - ADOHRE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    .content {
        flex: 1;
        margin-bottom: 50px;
    }
    </style>
</head>

<body>
    <header><?php include('header.php'); ?></header>
    <!-- Include the Sidebar -->
    <?php include('sidebar.php'); ?>
    <div class="container mt-5 content">
        <div class="chat-system">
            <!-- Active Ticket / Create Ticket Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h3>Consultation Support</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($activeTickets)): ?>
                    <!-- No active ticket found: show button to create a new support ticket -->
                    <button class="btn btn-primary btn-lg" onclick="createSupportTicket()">
                        Open New Support Ticket
                    </button>
                    <?php else: ?>
                    <?php foreach ($activeTickets as $ticket): ?>
                    <div class="active-ticket-alert alert alert-success">
                        <h4>Active Support Ticket (#<?= htmlspecialchars($ticket['room_id']) ?>)</h4>
                        <p>Opened at: <?= htmlspecialchars($ticket['created_at']) ?></p>
                    </div>
                    <button class="btn btn-success btn-lg" onclick="goToChatroom(<?= $ticket['room_id'] ?>)">Go to
                        Chatroom</button>
                    <button class="btn btn-danger btn-lg" onclick="closeTicket(<?= $ticket['room_id'] ?>)">Close
                        Ticket</button>
                    <hr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ticket History Section -->
            <div class="ticket-history">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h3>Ticket History</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($closedTickets)): ?>
                        <p>No closed tickets found.</p>
                        <?php else: ?>
                        <!-- Warning about deletion after 30 days -->
                        <div class="alert alert-warning">
                            <strong>Note:</strong> Ticket history will be automatically deleted after 30 days.
                        </div>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Opened At</th>
                                    <th>Closed At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($closedTickets as $ticket): ?>
                                <tr>
                                    <td><?= htmlspecialchars($ticket['chat_room_id']) ?></td>
                                    <td><?= htmlspecialchars($ticket['created_at']) ?></td>
                                    <td><?= htmlspecialchars($ticket['closed_at']) ?></td>
                                    <td>
                                        <!-- Link to download the chat transcript -->
                                        <a href="download_chat.php?room_id=<?= urlencode($ticket['chat_room_id']) ?>"
                                            class="btn btn-secondary btn-sm">Download Chat</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include('footer.php'); ?>

    <script>
    // Global variables
    let currentRoom = null;
    let currentUser = {
        id: <?= json_encode($userId) ?>
    };

    // Create a new support ticket.
    function createSupportTicket() {
        fetch('backend/routes/consultation_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'create_ticket',
                    user_id: currentUser.id
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.room_id) {
                    alert('Support ticket created successfully.');
                    location.reload(); // Reload page to update active tickets.
                } else {
                    alert('Error creating support ticket.');
                }
            })
            .catch(err => {
                alert('An error occurred. Please try again later.');
                console.error(err);
            });
    }

    // Redirect to the chatroom.
    function goToChatroom(roomId) {
        if (!roomId) {
            alert('No chat room is linked to this consultation');
            return;
        }
        window.location.href = `chatroom.php?room_id=${roomId}`;
    }

    // Close the active support ticket.
    function closeTicket(roomId) {
        if (!confirm(
                "Are you sure you want to close this ticket? Once closed, the chat history will remain available in Ticket History for 30 days."
            )) {
            return;
        }
        fetch('backend/routes/consultation_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'close_ticket',
                    room_id: roomId,
                    user_id: currentUser.id
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Ticket closed successfully.');
                    location.reload();
                } else {
                    alert(data.error || 'An error occurred.');
                }
            })
            .catch(err => {
                alert('An error occurred while closing the ticket.');
                console.error(err);
            });
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
require_once 'backend/db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Generate a nonce for inline scripts.
$nonce = base64_encode(random_bytes(16));

// Set HTTP security headers with updated CSP using the nonce.
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");

// Define secret salt for meet room generation
define('SECRET_SALT', 'YOUR_SECRET_SALT');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'backend/db/db_connect.php';

// Validate room ID and verify access using the chat_rooms table.
$roomId = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$userId = $_SESSION['user_id'];

// Prepare the query to get the chat room details (including meet_room_name).
$stmt = $conn->prepare("SELECT * FROM chat_rooms WHERE id = ? AND owner_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("ii", $roomId, $userId);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$result = $stmt->get_result();
$chatRoom = $result->fetch_assoc();
if (!$chatRoom) {
    die("Access to this chat room is restricted");
}
$stmt->close();

// If the meeting room name is not set, generate it, store it, and then use it.
if (empty($chatRoom['meet_room_name'])) {
    // Use constant SECRET_SALT instead of a local salt variable.
    $meetRoomName = "SecureRoom-" . $roomId . "-" . substr(hash('sha256', $roomId . SECRET_SALT), 0, 8);
    // Store it in the database.
    $stmt = $conn->prepare("UPDATE chat_rooms SET meet_room_name = ? WHERE id = ?");
    $stmt->bind_param("si", $meetRoomName, $roomId);
    $stmt->execute();
    $stmt->close();
} else {
    $meetRoomName = $chatRoom['meet_room_name'];
}

// Prepare user details
$userName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatroom</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        /* Chat messages container */
        #chat-messages {
            height: 400px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #f9f9f9;
        }

        /* General message styling */
        .message {
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-radius: 5px;
        }

        /* Your own (normal) messages: green background, aligned right */
        .own-message {
            background-color: #e3f7d3;
            text-align: right;
        }

        /* Messages from others: neutral background, aligned left */
        .other-message {
            background-color: #f1f1f1;
            text-align: left;
        }

        /* Admin messages (flagged as is_admin): red background, aligned left */
        .admin-message {
            background-color: #f8d7da;
            text-align: left;
        }

        .message .message-info {
            font-size: 0.8rem;
            color: #777;
        }
    </style>
</head>

<body>
    <header>
        <?php include('header.php'); ?>
    </header>
    <div class="container mt-5">
        <div class="chat-room">
            <div class="card">
                <!-- Card header with chat title and video call button on the right -->
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Chatroom</h3>
                    <!-- Removed inline onclick; added an id -->
                    <button class="btn btn-info" id="start-video-call">
                        <i class="bi bi-camera-video"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div id="chat-messages"></div>
                    <textarea id="chat-input" class="form-control mt-3" placeholder="Type your message..."></textarea>
                    <!-- Removed inline onclick; added an id -->
                    <button class="btn btn-primary mt-2" id="send-message">Send</button>
                </div>
            </div>
        </div>
    </div>

    <script nonce="<?= $nonce ?>">
        // Define current room and user from PHP variables
        const roomId = <?= json_encode($roomId) ?>;
        const userId = <?= json_encode($userId) ?>;
        const userName = <?= json_encode($userName) ?>;
        const threshold = 50; // pixels: user is considered "at the bottom" if within this threshold

        // Load messages from the backend and preserve scroll position if user is not at the bottom.
        function loadMessages() {
            const chatMessages = document.getElementById('chat-messages');
            // Capture the current scroll position and height.
            const previousScrollTop = chatMessages.scrollTop;
            const previousScrollHeight = chatMessages.scrollHeight;
            // Determine if the user was near the bottom.
            const wasAtBottom = (previousScrollTop + chatMessages.clientHeight >= previousScrollHeight - threshold);

            fetch(`backend/routes/consultation_management.php?room_id=${roomId}`)
                .then(res => res.json())
                .then(messages => {
                    chatMessages.innerHTML = messages.map(msg => {
                        let displayName = `${msg.first_name} ${msg.last_name}`;
                        let messageClass = "";

                        // Check if this message was sent by the current user.
                        if (msg.user_id == userId) {
                            if (msg.is_admin == 1) {
                                // If the current user sent an admin message, display as "Admin"
                                displayName = "Admin";
                                messageClass = "admin-message";
                            } else {
                                // Otherwise, for a normal message, assign a class (e.g., own-message)
                                messageClass = "own-message";
                            }
                        } else {
                            // For messages from other users
                            if (msg.is_admin == 1) {
                                displayName = "Admin";
                                messageClass = "admin-message"; // for admin messages (displayed as "Admin")
                            } else {
                                messageClass = "other-message"; // for normal messages from others
                            }
                        }

                        return `
              <div class="message ${messageClass}">
                <div><strong>${displayName}</strong></div>
                <div>${msg.message}</div>
                <div class="message-info">${new Date(msg.sent_at).toLocaleString()}</div>
              </div>
            `;
                    }).join('');
                    // Get the new scroll height.
                    const newScrollHeight = chatMessages.scrollHeight;
                    // If the user was near the bottom before updating, scroll to the new bottom.
                    if (wasAtBottom) {
                        chatMessages.scrollTop = newScrollHeight;
                    } else {
                        // Otherwise, keep the previous scroll position.
                        chatMessages.scrollTop = previousScrollTop;
                    }
                })
                .catch(err => console.error('Error loading messages:', err));
        }

        // Send a new (normal) message from the user.
        function sendMessage() {
            const messageInput = document.getElementById('chat-input');
            const message = messageInput.value.trim();

            if (!message) {
                alert("Message cannot be empty.");
                return;
            }

            // Regular user messages are sent without the is_admin flag.
            fetch('backend/routes/consultation_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'send_message',
                        room_id: roomId,
                        user_id: userId,
                        message: message
                    })
                })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        messageInput.value = ''; // Clear the input field.
                        // Force scroll to the bottom since a new message was sent.
                        const chatMessages = document.getElementById('chat-messages');
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                        // Refresh messages.
                        loadMessages();
                    } else {
                        alert('Failed to send message.');
                    }
                })
                .catch(err => console.error('Error sending message:', err));
        }

        // Function to start a video call using Jitsi Meet in a new window.
        function startVideoCall() {
            // Use the deterministic meeting room name stored in the database.
            const meetingName = <?= json_encode($meetRoomName) ?>;
            const domain = 'meet.jit.si'; // Use the public Jitsi domain (or your own if self-hosting).
            const meetingUrl = `https://${domain}/${meetingName}`;
            const windowFeatures =
                'width=1200,height=800,menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes';
            const meetingWindow = window.open(meetingUrl, 'JitsiMeetWindow', windowFeatures);
            if (!meetingWindow) {
                alert('Please allow popups for this site to start the video call.');
            }
        }

        // Auto-refresh messages every 2 seconds.
        setInterval(loadMessages, 2000);

        // On initial load, force scroll to bottom.
        window.addEventListener('load', () => {
            loadMessages();
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });

        // Remove inline event handlers; attach event listeners instead:
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('start-video-call').addEventListener('click', startVideoCall);
            document.getElementById('send-message').addEventListener('click', sendMessage);
            // Auto-refresh and initial scroll handling remain unchanged.
            loadMessages();
            const chatMessages = document.getElementById('chat-messages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Add Jitsi Meet API -->
    <script src="https://meet.jit.si/external_api.js"></script>
</body>

</html>
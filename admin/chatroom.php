<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../backend/db/db_connect.php';

// Validate room ID and verify access using the chat_rooms table.
$roomId = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$userId = $_SESSION['user_id'];

$userRole = $_SESSION['role']; // Determine if user is admin or shared user

// Check if the user has access
$hasAccess = false;

// Admins automatically have access
if ($userRole === 'admin') {
    $hasAccess = true;
} else {
    // Check if the user was added as a participant
    $stmt = $conn->prepare("SELECT 1 FROM chat_participants WHERE room_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $roomId, $userId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $hasAccess = true;
    }
    $stmt->close();
}

if (!$hasAccess) {
    echo "You do not have permission to access this chatroom.";
    exit;
}

// If the meeting room name is not set, generate it, store it, and then use it.
if (empty($chatRoom['meet_room_name'])) {
    $salt = "YOUR_SECRET_SALT"; // Replace with a constant secret salt.
    $meetRoomName = "SecureRoom-" . $roomId . "-" . substr(hash('sha256', $roomId . $salt), 0, 8);
    // Store it in the database.
    $stmt = $conn->prepare("UPDATE chat_rooms SET meet_room_name = ? WHERE id = ?");
    $stmt->bind_param("si", $meetRoomName, $roomId);
    $stmt->execute();
    $stmt->close();
} else {
    $meetRoomName = $chatRoom['meet_room_name'];
}

// Prepare user details.
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

    /* Admin messages: right aligned with a green background */
    .message.admin {
        background-color: #d4edda;
        text-align: right;
    }

    /* User messages: left aligned with a red background */
    .message.user {
        background-color: #f8d7da;
        text-align: left;
    }

    .message .message-info {
        font-size: 0.8rem;
        color: #777;
    }

    #share-link,
    #add-participant {
        margin-top: 1rem;
    }

    /* Extra padding for the entire page content at the bottom */
    .page-bottom-padding {
        padding-bottom: 50px;
    }
    </style>
</head>

<body>
    <header class="p-3 bg-primary text-white">
        <h1>Chatroom</h1>
    </header>
    <div class="container mt-5 page-bottom-padding">
        <div class="chat-room">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3>Chat Room #<?= $roomId ?></h3>
                    <button class="btn btn-info" onclick="startVideoCall()">
                        <i class="bi bi-camera-video"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div id="chat-messages"></div>
                    <textarea id="chat-input" class="form-control mt-3" placeholder="Type your message..."></textarea>
                    <!-- Toggle for sending as Admin or as a regular user -->
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="toggleAdmin" checked>
                        <label class="form-check-label" for="toggleAdmin">
                            Send as Admin
                        </label>
                    </div>
                    <button class="btn btn-primary mt-2" onclick="sendMessage()">Send</button>
                </div>
            </div>

            <!-- Shareable Link for Admin -->
            <?php if ($userRole === 'admin') : 
        // Optionally, use a configuration value for your site URL.
        $baseUrl = 'http://localhost/capstone-php'; 
      ?>
            <div id="share-link">
                <h5>Share Chatroom:</h5>
                <input class="form-control" type="text" id="shareable-link" readonly
                    value="<?= $baseUrl . '/join_chatroom.php?room_id=' . $roomId . '&share=admin' ?>" />
                <button class="btn btn-secondary mt-2" onclick="copyLink()">Copy Link</button>
            </div>

            <!-- Add Participant Section with Dropdown -->
            <div id="add-participant">
                <h5>Add Participant:</h5>
                <?php
          // Retrieve a list of users to add (exclude the current admin)
          $users = [];
          $query = "SELECT user_id, first_name, last_name, email FROM users WHERE user_id != $userId ORDER BY first_name, last_name";
          $result = $conn->query($query);
          if($result && $result->num_rows > 0){
              while($row = $result->fetch_assoc()){
                  $users[] = $row;
              }
          }
        ?>
                <div class="input-group">
                    <select class="form-select" id="participant-user-id">
                        <option value="">Select a user</option>
                        <?php foreach($users as $user): ?>
                        <option value="<?= htmlspecialchars($user['user_id']) ?>">
                            <?= htmlspecialchars($user['first_name'] . " " . $user['last_name'] . " (" . $user['email'] . ")") ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-secondary" onclick="addParticipant()">Add</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Define global constants.
    const roomId = <?= json_encode($roomId) ?>;
    const userId = <?= json_encode($userId) ?>;
    const userRole = <?= json_encode($userRole) ?>;
    const threshold = 50; // pixels near the bottom to consider "at the bottom"

    // Function to load messages while preserving the user's scroll position.
    function loadMessages() {
        const chatMessages = document.getElementById('chat-messages');
        // Capture the current scroll state.
        const previousScrollTop = chatMessages.scrollTop;
        const previousScrollHeight = chatMessages.scrollHeight;
        // Determine if the user is near the bottom:
        const wasAtBottom = (previousScrollTop + chatMessages.clientHeight >= previousScrollHeight - threshold);

        fetch(`../backend/routes/consultation_management.php?room_id=${roomId}`)
            .then(res => res.json())
            .then(messages => {
                // Update the chat container.
                chatMessages.innerHTML = messages.map(msg => {
                    const displayName = (parseInt(msg.is_admin) === 1) ? 'Admin' :
                        `${msg.first_name} ${msg.last_name}`;
                    const messageClass = (parseInt(msg.is_admin) === 1) ? 'admin' : 'user';
                    return `
            <div class="message ${messageClass}">
              <div><strong>${displayName}</strong></div>
              <div>${msg.message}</div>
              <div class="message-info">${new Date(msg.sent_at).toLocaleString()}</div>
            </div>
          `;
                }).join('');

                // After updating, check the new scrollHeight.
                const newScrollHeight = chatMessages.scrollHeight;
                // If the user was near the bottom before updating, scroll to the new bottom.
                // Otherwise, restore their previous scroll position.
                if (wasAtBottom) {
                    chatMessages.scrollTop = newScrollHeight;
                } else {
                    chatMessages.scrollTop = previousScrollTop;
                }
            })
            .catch(err => console.error('Error loading messages:', err));
    }

    // Function to send a new message.
    function sendMessage() {
        const messageInput = document.getElementById('chat-input');
        const message = messageInput.value.trim();
        if (!message) {
            alert("Message cannot be empty.");
            return;
        }
        const sendAsAdmin = document.getElementById('toggleAdmin').checked;
        fetch('../backend/routes/consultation_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'send_message',
                    room_id: roomId,
                    user_id: userId,
                    message: message,
                    is_admin: sendAsAdmin
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.status === 'success') {
                    messageInput.value = ''; // Clear input.
                    // Force scroll to bottom because a new message was sent.
                    const chatMessages = document.getElementById('chat-messages');
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    // Optionally reload messages.
                    loadMessages();
                } else {
                    alert('Failed to send message.');
                }
            })
            .catch(err => console.error('Error sending message:', err));
    }

    // Copy the shareable link to clipboard.
    function copyLink() {
        const linkInput = document.getElementById('shareable-link');
        linkInput.select();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(linkInput.value)
                .then(() => alert('Link copied to clipboard!'))
                .catch(() => alert('Failed to copy link.'));
        } else {
            document.execCommand('copy');
            alert('Link copied to clipboard!');
        }
    }

    // Add participant function remains unchanged.
    function addParticipant() {
        const select = document.getElementById('participant-user-id');
        const participantUserId = select.value;
        if (!participantUserId) {
            alert("Please select a user.");
            return;
        }
        fetch('../backend/routes/consultation_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'add_participant',
                    room_id: roomId,
                    participant_user_id: participantUserId,
                    added_by: userId
                })
            })
            .then(res => res.json())
            .then(response => {
                if (response.status === 'success') {
                    alert('Participant added successfully.');
                    // Optionally remove the added user from the dropdown.
                    select.remove(select.selectedIndex);
                } else {
                    alert(response.error || 'Failed to add participant.');
                }
            })
            .catch(err => console.error('Error adding participant:', err));
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
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
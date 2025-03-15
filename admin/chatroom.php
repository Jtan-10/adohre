<?php
session_start();
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("X-Frame-Options: SAMEORIGIN");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self'; img-src 'self' data:;");

// Production environment settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Use with HTTPS only
ini_set('session.use_only_cookies', 1);
error_reporting(0); // Disable error reporting in production

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Initialize CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once '../backend/db/db_connect.php';

// Validate room ID and verify access using the chat_rooms table.
$roomId = isset($_GET['room_id']) ? filter_var($_GET['room_id'], FILTER_VALIDATE_INT) : 0;

if (!$roomId) {
    header("Location: ../index.php?error=" . urlencode("Invalid room ID"));
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role']; // Determine if user is admin or shared user

// Check if the user has access
$hasAccess = false;

// Admins automatically have access
if ($userRole === 'admin') {
    $hasAccess = true;
} else {
    // Check if the user was added as a participant
    try {
        $stmt = $conn->prepare("SELECT 1 FROM chat_participants WHERE room_id = ? AND user_id = ?");
        if (!$stmt) {
            throw new Exception("Database query preparation failed");
        }
        $stmt->bind_param("ii", $roomId, $userId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $hasAccess = true;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Access check error: " . $e->getMessage());
        header("Location: ../index.php?error=" . urlencode("Database error occurred"));
        exit;
    }
}

if (!$hasAccess) {
    header("Location: ../index.php?error=" . urlencode("You do not have permission to access this chatroom"));
    exit;
}

// Get chat room information
try {
    $stmt = $conn->prepare("SELECT meet_room_name FROM chat_rooms WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Database query preparation failed");
    }
    $stmt->bind_param("i", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    $chatRoom = $result->fetch_assoc();
    $stmt->close();

    if (!$chatRoom) {
        throw new Exception("Chat room not found");
    }
} catch (Exception $e) {
    error_log("Chat room retrieval error: " . $e->getMessage());
    header("Location: ../index.php?error=" . urlencode("Chat room not found"));
    exit;
}

// If the meeting room name is not set, generate it, store it, and then use it.
if (empty($chatRoom['meet_room_name'])) {
    // Use a secure random generator instead of a fixed salt
    $meetRoomName = "SecureRoom-" . $roomId . "-" . bin2hex(random_bytes(8));

    // Store it in the database.
    try {
        $stmt = $conn->prepare("UPDATE chat_rooms SET meet_room_name = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Database query preparation failed");
        }
        $stmt->bind_param("si", $meetRoomName, $roomId);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Meeting room update error: " . $e->getMessage());
        // Continue with the generated name even if storage fails
    }
} else {
    $meetRoomName = $chatRoom['meet_room_name'];
}

// Prepare user details with sanitization
$userName = htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']);

// Rate limiting variables
if (!isset($_SESSION['last_message_time'])) {
    $_SESSION['last_message_time'] = 0;
}

// Production URL (customize in production)
$baseUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self';
               script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;
               style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;
               font-src 'self' https://cdn.jsdelivr.net;
               connect-src 'self';
               img-src 'self' data:;">
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
                    <h3>Chat Room #<?= htmlspecialchars($roomId) ?></h3>
                    <button class="btn btn-info" onclick="startVideoCall()">
                        <i class="bi bi-camera-video"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div id="chat-messages"></div>
                    <textarea id="chat-input" class="form-control mt-3" placeholder="Type your message..."
                        maxlength="1000"></textarea>
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
            <?php if ($userRole === 'admin') : ?>
                <div id="share-link">
                    <h5>Share Chatroom:</h5>
                    <input class="form-control" type="text" id="shareable-link" readonly
                        value="<?= htmlspecialchars($baseUrl . '/join_chatroom.php?room_id=' . $roomId . '&share=admin') ?>" />
                    <button class="btn btn-secondary mt-2" onclick="copyLink()">Copy Link</button>
                </div>

                <!-- Add Participant Section with Dropdown -->
                <div id="add-participant">
                    <h5>Add Participant:</h5>
                    <?php
                    // Retrieve a list of users to add (exclude the current admin)
                    $users = [];
                    try {
                        $stmt = $conn->prepare("SELECT user_id, first_name, last_name, email FROM users WHERE user_id != ? ORDER BY first_name, last_name");
                        if (!$stmt) {
                            throw new Exception("Database query preparation failed");
                        }
                        $stmt->bind_param("i", $userId);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        while ($row = $result->fetch_assoc()) {
                            $users[] = $row;
                        }
                        $stmt->close();
                    } catch (Exception $e) {
                        error_log("User list retrieval error: " . $e->getMessage());
                    }
                    ?>
                    <div class="input-group">
                        <select class="form-select" id="participant-user-id">
                            <option value="">Select a user</option>
                            <?php foreach ($users as $user): ?>
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
        const csrf_token = <?= json_encode($_SESSION['csrf_token']) ?>;
        const threshold = 50; // pixels near the bottom to consider "at the bottom"

        // Rate limiting variables
        let lastMessageTime = 0;
        const MESSAGE_RATE_LIMIT = 1000; // 1 second between messages

        // Function to sanitize HTML to prevent XSS
        function sanitizeHTML(str) {
            const temp = document.createElement('div');
            temp.textContent = str;
            return temp.innerHTML;
        }

        // Function to load messages while preserving the user's scroll position.
        function loadMessages() {
            const chatMessages = document.getElementById('chat-messages');
            // Capture the current scroll state.
            const previousScrollTop = chatMessages.scrollTop;
            const previousScrollHeight = chatMessages.scrollHeight;
            // Determine if the user is near the bottom:
            const wasAtBottom = (previousScrollTop + chatMessages.clientHeight >= previousScrollHeight - threshold);

            fetch(
                    `../backend/routes/consultation_management.php?room_id=${roomId}&csrf_token=${encodeURIComponent(csrf_token)}`
                )
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return res.json();
                })
                .then(messages => {
                    // Update the chat container.
                    chatMessages.innerHTML = messages.map(msg => {
                        const displayName = (parseInt(msg.is_admin) === 1) ? 'Admin' :
                            sanitizeHTML(`${msg.first_name} ${msg.last_name}`);
                        const messageClass = (parseInt(msg.is_admin) === 1) ? 'admin' : 'user';
                        const messageText = sanitizeHTML(msg.message);
                        const messageTime = new Date(msg.sent_at).toLocaleString();

                        return `
                    <div class="message ${messageClass}">
                      <div><strong>${displayName}</strong></div>
                      <div>${messageText}</div>
                      <div class="message-info">${messageTime}</div>
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
                .catch(err => {
                    console.error('Error loading messages:', err);
                    // Don't show detailed errors to users in production
                    chatMessages.innerHTML +=
                        '<div class="alert alert-danger">Unable to load messages. Please refresh the page.</div>';
                });
        }

        // Function to send a new message.
        function sendMessage() {
            const now = Date.now();
            if (now - lastMessageTime < MESSAGE_RATE_LIMIT) {
                alert("Please wait a moment before sending another message.");
                return;
            }

            const messageInput = document.getElementById('chat-input');
            const message = messageInput.value.trim();
            if (!message) {
                alert("Message cannot be empty.");
                return;
            }

            // Update rate limiting
            lastMessageTime = now;

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
                        is_admin: sendAsAdmin,
                        csrf_token: csrf_token
                    })
                })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return res.json();
                })
                .then(response => {
                    if (response.status === 'success') {
                        messageInput.value = ''; // Clear input.
                        // Force scroll to bottom because a new message was sent.
                        const chatMessages = document.getElementById('chat-messages');
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                        // Optionally reload messages.
                        loadMessages();
                    } else {
                        alert('Failed to send message: ' + (response.error || 'Unknown error'));
                    }
                })
                .catch(err => {
                    console.error('Error sending message:', err);
                    alert('Failed to send message. Please try again later.');
                });
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

        // Add participant function with CSRF protection
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
                        added_by: userId,
                        csrf_token: csrf_token
                    })
                })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return res.json();
                })
                .then(response => {
                    if (response.status === 'success') {
                        alert('Participant added successfully.');
                        // Optionally remove the added user from the dropdown.
                        select.remove(select.selectedIndex);
                    } else {
                        alert(response.error || 'Failed to add participant.');
                    }
                })
                .catch(err => {
                    console.error('Error adding participant:', err);
                    alert('Failed to add participant. Please try again later.');
                });
        }

        // Function to start a video call using Jitsi Meet in a new window with security enhancements
        function startVideoCall() {
            const meetingName = <?= json_encode($meetRoomName) ?>;
            const domain = 'meet.jit.si'; // Use the public Jitsi domain (or your own if self-hosting).

            // Generate a random password for the meeting
            const password = generateSecurePassword(12);

            // Add password protection to the meeting URL
            const meetingUrl = `https://${domain}/${meetingName}#config.password="${password}"`;

            const windowFeatures =
                'width=1200,height=800,menubar=no,toolbar=no,location=yes,status=no,resizable=yes,scrollbars=yes';
            const meetingWindow = window.open(meetingUrl, 'JitsiMeetWindow', windowFeatures);

            if (!meetingWindow) {
                alert('Please allow popups for this site to start the video call.');
            } else {
                // Share the password with chat participants
                fetch('../backend/routes/consultation_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'send_message',
                        room_id: roomId,
                        user_id: userId,
                        message: `Video call started. Meeting password: ${password}`,
                        is_admin: true,
                        csrf_token: csrf_token
                    })
                });
            }
        }

        // Generate a secure password for the meeting
        function generateSecurePassword(length) {
            const charset = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()";
            let password = "";
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * charset.length);
                password += charset.charAt(randomIndex);
            }
            return password;
        }

        // Add event listener for Enter key in textarea
        document.getElementById('chat-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Auto-refresh messages every 5 seconds (increased from 2 for less server load)
        setInterval(loadMessages, 5000);

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
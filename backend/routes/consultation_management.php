<?php
require_once '../db/db_connect.php';
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get chat messages for a specific room
    if (isset($_GET['room_id'])) {
        $roomId = (int)$_GET['room_id'];
        $stmt = $conn->prepare("SELECT cm.message, cm.sent_at, cm.is_admin, u.first_name, u.last_name, cm.user_id FROM chat_messages cm JOIN users u ON cm.user_id = u.user_id WHERE cm.room_id = ? ORDER BY cm.sent_at ASC");
        $stmt->bind_param("i", $roomId);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        echo json_encode($messages);
        exit;
    }

    // Get consultations and associated chat room IDs (with user details)
    $query = "SELECT c.id AS consultation_id, 
                     c.user_id, 
                     u.first_name AS user_first_name, 
                     u.last_name AS user_last_name, 
                     c.description, 
                     c.status, 
                     c.created_at, 
                     cr.id AS chat_room_id
              FROM consultations c
              LEFT JOIN chat_rooms cr ON c.id = cr.consultation_id
              LEFT JOIN users u ON c.user_id = u.user_id
              ORDER BY c.created_at DESC";
    $result = $conn->query($query);

    $consultations = [];
    while ($row = $result->fetch_assoc()) {
        $consultations[] = $row;
    }
    echo json_encode($consultations);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Create a new support ticket (consultation and chat room)
    if ($input['action'] === 'create_ticket') {
        $userId = (int)$input['user_id'];
        $description = "User requested support.";
        
        // Insert into consultations using prepared statement
        $stmt = $conn->prepare("INSERT INTO consultations (user_id, description, status, created_at) VALUES (?, ?, 'open', NOW())");
        $stmt->bind_param("is", $userId, $description);
        if ($stmt->execute()) {
            $consultationId = $conn->insert_id;
            // Create chat room
            $stmt = $conn->prepare("INSERT INTO chat_rooms (consultation_id, owner_id, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("ii", $consultationId, $userId);
            if ($stmt->execute()) {
                $roomId = $conn->insert_id;
                // Audit log: record ticket creation
                recordAuditLog($userId, "Create Ticket", "Consultation ID $consultationId and Chat Room ID $roomId created for user ID $userId.");
                echo json_encode(['room_id' => $roomId]);
            } else {
                echo json_encode(['error' => 'Failed to create chat room.']);
            }
        } else {
            echo json_encode(['error' => 'Failed to create consultation.']);
        }
        exit;
    }

    // Save a new message in a chat room.
    if ($input['action'] === 'send_message') {
        $roomId  = (int)$input['room_id'];
        $userId  = (int)$input['user_id'];
        $message = $input['message'];
        $is_admin = (isset($input['is_admin']) && $input['is_admin'] === true) ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO chat_messages (room_id, user_id, message, is_admin) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $roomId, $userId, $message, $is_admin);
        if ($stmt->execute()) {
            // Audit log: record message sent
            recordAuditLog($userId, "Send Message", "User sent a message in room ID $roomId.");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to send message.']);
        }
        exit;
    }
    
    // Update consultation status
    if ($input['action'] === 'update_status') {
        $consultationId = (int)$input['consultation_id'];
        $status         = $input['status'];
        $stmt = $conn->prepare("UPDATE consultations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $consultationId);
        if ($stmt->execute()) {
            // Audit log: record status update
            recordAuditLog($input['user_id'] ?? 0, "Update Consultation Status", "Consultation ID $consultationId updated to status '$status'.");
            echo json_encode(['message' => 'Consultation status updated successfully.']);
        } else {
            echo json_encode(['error' => 'Failed to update consultation status.']);
        }
        exit;
    }

    // Close ticket action
    if ($input['action'] === 'close_ticket') {
        $roomId = (int)$input['room_id'];
        $userId = (int)$input['user_id'];
        $stmt = $conn->prepare("UPDATE consultations c JOIN chat_rooms cr ON c.id = cr.consultation_id SET c.status = 'closed', c.closed_at = NOW() WHERE cr.id = ? AND c.user_id = ?");
        $stmt->bind_param("ii", $roomId, $userId);
        if ($stmt->execute()) {
            // Audit log: record ticket closure
            recordAuditLog($userId, "Close Ticket", "Ticket (Room ID $roomId) closed by user ID $userId.");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to close ticket.']);
        }
        exit;
    }
    
    // Add participant action
    if ($input['action'] === 'add_participant') {
        $roomId = (int)$input['room_id'];
        $participantUserId = (int)$input['participant_user_id'];
        $addedBy = (int)$input['added_by'];

        // Check if the participant already exists
        $stmt = $conn->prepare("SELECT 1 FROM chat_participants WHERE room_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $roomId, $participantUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            echo json_encode(['error' => 'User is already a participant.']);
            exit;
        }
        
        // Insert the new participant
        $stmt = $conn->prepare("INSERT INTO chat_participants (room_id, user_id, added_by) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $roomId, $participantUserId, $addedBy);
        if ($stmt->execute()) {
            // Audit log: record participant addition
            recordAuditLog($addedBy, "Add Participant", "User ID $participantUserId added as participant in room ID $roomId.");
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to add participant.']);
        }
        exit;
    }
}

// Return a 405 Method Not Allowed for unsupported methods.
http_response_code(405);
echo json_encode(["error" => "Method not allowed."]);
<?php
require_once '../db/db_connect.php';
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get chat messages for a specific room
    if (isset($_GET['room_id'])) {
        $roomId = $conn->real_escape_string($_GET['room_id']);
        $query = "SELECT cm.message, cm.sent_at, cm.is_admin, u.first_name, u.last_name, cm.user_id
                  FROM chat_messages cm
                  JOIN users u ON cm.user_id = u.user_id
                  WHERE cm.room_id = $roomId
                  ORDER BY cm.sent_at ASC";
        $result = $conn->query($query);

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
        $userId = $conn->real_escape_string($input['user_id']);
        // Use a default description; you can later update this if needed.
        $description = $conn->real_escape_string("User requested support.");
        
        // Insert a new consultation record with status 'open'
        $query = "INSERT INTO consultations (user_id, description, status, created_at) 
                  VALUES ($userId, '$description', 'open', NOW())";
        if ($conn->query($query)) {
            $consultationId = $conn->insert_id;
            
            // Create a chat room for the consultation.
            // Assume owner_id is the user who requested support.
            $query = "INSERT INTO chat_rooms (consultation_id, owner_id, created_at) 
                      VALUES ($consultationId, $userId, NOW())";
            if ($conn->query($query)) {
                $roomId = $conn->insert_id;
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
        $roomId  = $conn->real_escape_string($input['room_id']);
        $userId  = $conn->real_escape_string($input['user_id']);
        $message = $conn->real_escape_string($input['message']);
        // Set the is_admin flag if provided (default is 0)
        $is_admin = (isset($input['is_admin']) && $input['is_admin'] === true) ? 1 : 0;

        $query = "INSERT INTO chat_messages (room_id, user_id, message, is_admin) 
                  VALUES ($roomId, $userId, '$message', $is_admin)";
        if ($conn->query($query)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to send message.']);
        }
        exit;
    }
    
    // Update consultation status
    if ($input['action'] === 'update_status') {
        $consultationId = $conn->real_escape_string($input['consultation_id']);
        $status         = $conn->real_escape_string($input['status']);

        $query = "UPDATE consultations SET status = '$status' WHERE id = $consultationId";
        if ($conn->query($query)) {
            echo json_encode(['message' => 'Consultation status updated successfully.']);
        } else {
            echo json_encode(['error' => 'Failed to update consultation status.']);
        }
        exit;
    }

    // Close ticket action
    if ($input['action'] === 'close_ticket') {
        $roomId = $conn->real_escape_string($input['room_id']);
        $userId = $conn->real_escape_string($input['user_id']);

        // Update the consultation status to 'closed' and set closed_at to the current timestamp.
        $query = "UPDATE consultations c 
                  JOIN chat_rooms cr ON c.id = cr.consultation_id
                  SET c.status = 'closed', c.closed_at = NOW()
                  WHERE cr.id = $roomId AND c.user_id = $userId";
        if ($conn->query($query)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to close ticket.']);
        }
        exit;
    }
    
    // Add participant action
    if ($input['action'] === 'add_participant') {
        $roomId = $conn->real_escape_string($input['room_id']);
        $participantUserId = $conn->real_escape_string($input['participant_user_id']);
        $addedBy = $conn->real_escape_string($input['added_by']);

        // Check if the participant already exists in the chat room
        $checkQuery = "SELECT 1 FROM chat_participants WHERE room_id = $roomId AND user_id = $participantUserId";
        $checkResult = $conn->query($checkQuery);
        if ($checkResult && $checkResult->num_rows > 0) {
            echo json_encode(['error' => 'User is already a participant.']);
            exit;
        }
        
        // Insert the new participant
        $query = "INSERT INTO chat_participants (room_id, user_id, added_by) VALUES ($roomId, $participantUserId, $addedBy)";
        if ($conn->query($query)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['error' => 'Failed to add participant: ' . $conn->error]);
        }
        exit;
    }


}

// Return a 405 Method Not Allowed for unsupported methods.
http_response_code(405);
echo json_encode(["error" => "Method not allowed."]);
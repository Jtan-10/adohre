<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');

// Ensure the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'You must be logged in to perform this action.']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($_GET['action']) ? $_GET['action'] : null;

    if ($action === 'join_event') {
        $userId = $_SESSION['user_id'];
        $eventId = $input['event_id'];

        // Check if the user is already registered
        $checkQuery = "SELECT * FROM event_registrations WHERE user_id = ? AND event_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ii', $userId, $eventId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo json_encode(['status' => false, 'message' => 'You have already joined this event.']);
        } else {
            // Register the user for the event
            $insertQuery = "INSERT INTO event_registrations (user_id, event_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param('ii', $userId, $eventId);
            $stmt->execute();

            echo json_encode(['status' => true, 'message' => 'Successfully joined the event.']);
        }
    } elseif ($action === 'get_joined_events') {
        $userId = $_SESSION['user_id'];

        // Fetch joined events for the logged-in user
        $query = "SELECT e.event_id, e.title, e.description, e.date, e.location, e.image
                  FROM event_registrations er
                  JOIN events e ON er.event_id = e.event_id
                  WHERE er.user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }

        echo json_encode(['status' => true, 'events' => $events]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid action specified.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');

// Ensure the user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'You must be logged in to access this feature.']);
    exit;
}

try {
    $action = isset($_GET['action']) ? $_GET['action'] : null;
    $userId = $_SESSION['user_id'];

    if ($action === 'join_training') {
        $input = json_decode(file_get_contents('php://input'), true);
        $trainingId = $input['training_id'];
    
        // Check if the user is already registered
        $checkQuery = "SELECT * FROM training_registrations WHERE user_id = ? AND training_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ii', $userId, $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            echo json_encode(['status' => false, 'message' => 'You have already joined this training.']);
        } else {
            // Register the user for the training
            $insertQuery = "INSERT INTO training_registrations (user_id, training_id) VALUES (?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param('ii', $userId, $trainingId);
            $stmt->execute();
    
            echo json_encode(['status' => true, 'message' => 'Successfully joined the training.']);
        }
    }   elseif ($action === 'get_joined_trainings') {
        // Fetch the trainings the user has joined
        $query = "SELECT t.title, t.description, t.schedule, t.image 
                  FROM training_registrations tr
                  INNER JOIN trainings t ON tr.training_id = t.training_id
                  WHERE tr.user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
    
        $trainings = [];
        while ($row = $result->fetch_assoc()) {
            $trainings[] = $row;
        }
    
        echo json_encode(['status' => true, 'trainings' => $trainings]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
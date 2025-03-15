<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');
session_start();

// Generate CSRF token if not present.
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper to verify CSRF token for POST requests.
function verifyCsrfToken($data) {
    if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['status' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
}

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => false, 'message' => 'You must be logged in to access this feature.']);
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Read raw input and decode JSON if available
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : (isset($input['action']) ? $input['action'] : null);

try {

    if ($action === 'get_assessment_form') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
            exit;
        }
        $trainingId = isset($_GET['training_id']) ? intval($_GET['training_id']) : null;
        if (!$trainingId) {
            echo json_encode(['status' => false, 'message' => 'Missing training_id.']);
            exit;
        }
        // If the user is not a trainer, check registration in training_registrations.
        if ($role !== 'trainer') {
            $checkQuery = "SELECT * FROM training_registrations WHERE user_id = ? AND training_id = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ii", $userId, $trainingId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['status' => false, 'message' => 'You are not registered for this training.']);
                exit;
            }
        }
        $stmt = $conn->prepare("SELECT form_link FROM assessment_forms WHERE training_id = ?");
        $stmt->bind_param("i", $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['status' => true, 'form_link' => $row['form_link']]);
        } else {
            echo json_encode(['status' => false, 'message' => 'No assessment form found.']);
        }
        exit;
    } elseif ($action === 'save_assessment_form') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
            exit;
        }
        verifyCsrfToken($_POST);
        // Save the assessment form link provided by the trainer.
        if ($role !== 'trainer') {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit;
        }
        $trainingId = isset($_POST['training_id']) ? intval($_POST['training_id']) : null;
        $formLink = isset($_POST['form_link']) ? $_POST['form_link'] : null;
        if (!$trainingId || !$formLink) {
            echo json_encode(['status' => false, 'message' => 'Missing training_id or form_link.']);
            exit;
        }
        $stmt = $conn->prepare("REPLACE INTO assessment_forms (training_id, form_link, updated_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $trainingId, $formLink);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo json_encode(['status' => true, 'message' => 'Assessment form released successfully.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to release assessment form.']);
        }
        exit;
    } elseif ($action === 'fetch_participants') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
            exit;
        }
        // Fetch participants for trainings.
        // For trainers, restrict to trainings created by them.
        // For admins, fetch for all trainings.
        if ($role !== 'trainer' && $role !== 'admin') {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit;
        }
        
        // Optional: Filter by training_id if provided in GET
        $trainingFilter = "";
        if (isset($_GET['training_id']) && !empty($_GET['training_id'])) {
            $trainingIdParam = intval($_GET['training_id']);
            $trainingFilter = " AND tr.training_id = " . $trainingIdParam;
        }
        
        if ($role === 'admin') {
            // Admin: no creator restriction.
            $query = "
              SELECT tr.training_id, u.user_id, u.first_name, u.last_name,
                     CASE 
                       WHEN tr.assessment_completed = 1 THEN 'Completed'
                       ELSE 'Not Completed'
                     END AS assessment_status,
                     CASE 
                       WHEN tr.certificate_issued = 1 THEN 'Certificate Released'
                       ELSE 'Not Released'
                     END AS certificate_status
              FROM training_registrations tr
              INNER JOIN trainings t ON tr.training_id = t.training_id
              INNER JOIN users u ON tr.user_id = u.user_id
              WHERE 1=1 $trainingFilter";
            $stmt = $conn->prepare($query);
        } else {
            // Trainer: restrict to trainings they created.
            $trainerId = $userId;
            $query = "
              SELECT tr.training_id, u.user_id, u.first_name, u.last_name,
                     CASE 
                       WHEN tr.assessment_completed = 1 THEN 'Completed'
                       ELSE 'Not Completed'
                     END AS assessment_status,
                     CASE 
                       WHEN tr.certificate_issued = 1 THEN 'Certificate Released'
                       ELSE 'Not Released'
                     END AS certificate_status
              FROM training_registrations tr
              INNER JOIN trainings t ON tr.training_id = t.training_id
              INNER JOIN users u ON tr.user_id = u.user_id
              WHERE t.created_by = ? $trainingFilter";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $trainerId);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $participants = [];
        while ($row = $result->fetch_assoc()) {
            $participants[] = $row;
        }
        echo json_encode(['status' => true, 'participants' => $participants]);
        exit;
    } elseif ($action === 'mark_assessment_done') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
            exit;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        verifyCsrfToken($input);
        // Ensure the user_id in the input matches the logged-in user
        if ($userId !== intval($input['user_id'])) {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit;
        }
        $participantUserId = $userId; // The logged in user
        $trainingId = intval($input['training_id']);
    
        // First, check if the assessment is already marked as completed.
        $checkQuery = "SELECT assessment_completed FROM training_registrations WHERE user_id = ? AND training_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ii', $participantUserId, $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            // Cast to integer in case it's stored as a string
            if ((int)$row['assessment_completed'] === 1) {
                echo json_encode(['status' => true, 'message' => 'Assessment already marked as completed.']);
                exit;
            }
        } else {
            echo json_encode(['status' => false, 'message' => 'Training registration not found.']);
            exit;
        }
    
        // If not already marked, update the record.
        $updateQuery = "UPDATE training_registrations SET assessment_completed = 1 WHERE user_id = ? AND training_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('ii', $participantUserId, $trainingId);
        $stmt->execute();
    
        // Recheck the value.
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ii', $participantUserId, $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        if (($row = $result->fetch_assoc()) && (int)$row['assessment_completed'] === 1) {
            echo json_encode(['status' => true, 'message' => 'Assessment marked as completed.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to mark assessment as completed.']);
        }
        exit;
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid action.']);
        exit;
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['status' => false, 'message' => 'An internal error occurred.']);
    exit;
}
?>
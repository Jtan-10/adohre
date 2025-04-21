<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');
session_start();

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

    // ORIGINAL FUNCTIONALITY - PRESERVED
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
            // Audit log: record the release of an assessment form
            recordAuditLog($userId, "Save Assessment Form", "Released assessment form for training ID $trainingId with link: $formLink");
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
        if ($role !== 'trainer' && $role !== 'admin') {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit;
        }

        $trainingFilter = "";
        if (isset($_GET['training_id']) && !empty($_GET['training_id'])) {
            $trainingIdParam = intval($_GET['training_id']);
            $trainingFilter = " AND tr.training_id = " . $trainingIdParam;
        }

        if ($role === 'admin') {
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

        if ($userId !== intval($input['user_id'])) {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit;
        }

        $participantUserId = $userId;
        $trainingId = intval($input['training_id']);

        $checkQuery = "SELECT assessment_completed FROM training_registrations WHERE user_id = ? AND training_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ii', $participantUserId, $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ((int)$row['assessment_completed'] === 1) {
                echo json_encode(['status' => true, 'message' => 'Assessment already marked as completed.']);
                exit;
            }
        } else {
            echo json_encode(['status' => false, 'message' => 'Training registration not found.']);
            exit;
        }

        $updateQuery = "UPDATE training_registrations SET assessment_completed = 1 WHERE user_id = ? AND training_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('ii', $participantUserId, $trainingId);
        $stmt->execute();

        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param('ii', $participantUserId, $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        if (($row = $result->fetch_assoc()) && (int)$row['assessment_completed'] === 1) {
            recordAuditLog($userId, "Mark Assessment Done", "User ID $userId marked assessment as completed for training ID $trainingId.");
            echo json_encode(['status' => true, 'message' => 'Assessment marked as completed.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to mark assessment as completed.']);
        }
        exit;
    } 
    
    // NEW CUSTOM ASSESSMENT FUNCTIONS
    elseif ($action === 'get_questions') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
            exit;
        }
        
        $trainingId = isset($_GET['training_id']) ? intval($_GET['training_id']) : null;
        if (!$trainingId) {
            echo json_encode(['status' => false, 'message' => 'Missing training_id.']);
            exit;
        }
        
        // Check access permission
        if ($role !== 'admin' && $role !== 'trainer') {
            // If the user is not an admin or trainer, check if they're registered for this training
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
        
        // Fetch questions for the training
        $stmt = $conn->prepare("SELECT * FROM assessment_questions WHERE training_id = ? ORDER BY question_order ASC");
        $stmt->bind_param("i", $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $questions = [];
        while ($row = $result->fetch_assoc()) {
            // If the options field is a JSON string, decode it
            if (isset($row['options']) && !is_null($row['options'])) {
                $row['options'] = json_decode($row['options'], true);
            }
            $questions[] = $row;
        }
        
        echo json_encode(['status' => true, 'questions' => $questions]);
        exit;
        
    } elseif ($action === 'add_question') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
            exit;
        }
        
        // Only trainers and admins can add questions
        if ($role !== 'admin' && $role !== 'trainer') {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit;
        }
        
        $trainingId = isset($_POST['training_id']) ? intval($_POST['training_id']) : null;
        $questionType = isset($_POST['question_type']) ? $_POST['question_type'] : null;
        $questionText = isset($_POST['question_text']) ? $_POST['question_text'] : null;
        $required = isset($_POST['required']) ? (bool)$_POST['required'] : true;
        $options = isset($_POST['options']) ? $_POST['options'] : null;
        $questionOrder = isset($_POST['question_order']) ? intval($_POST['question_order']) : 0;
        
        // Validate inputs
        if (!$trainingId || !$questionType || !$questionText) {
            echo json_encode(['status' => false, 'message' => 'Missing required fields.']);
            exit;
        }
        
        // If trainer, check if they created this training
        if ($role === 'trainer') {
            $checkOwnership = "SELECT * FROM trainings WHERE training_id = ? AND created_by = ?";
            $stmt = $conn->prepare($checkOwnership);
            $stmt->bind_param("ii", $trainingId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['status' => false, 'message' => 'You can only manage assessments for trainings you created.']);
                exit;
            }
        }
        
        // Encode options as JSON if provided
        $optionsJson = null;
        if ($options) {
            $optionsJson = is_array($options) ? json_encode($options) : $options;
        }
        
        // Insert the question
        $stmt = $conn->prepare("INSERT INTO assessment_questions (training_id, question_type, question_text, required, options, question_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issisi", $trainingId, $questionType, $questionText, $required, $optionsJson, $questionOrder);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $questionId = $stmt->insert_id;
            // Audit log
            recordAuditLog($userId, "Add Assessment Question", "Added question ID $questionId for training ID $trainingId");
            echo json_encode(['status' => true, 'message' => 'Question added successfully.', 'question_id' => $questionId]);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to add question.']);
        }
        exit;
        
    } elseif ($action === 'update_question') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
            exit;
        }
        
        // Only trainers and admins can update questions
        if ($role !== 'admin' && $role !== 'trainer') {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit;
        }
        
        $questionId = isset($_POST['question_id']) ? intval($_POST['question_id']) : null;
        $questionType = isset($_POST['question_type']) ? $_POST['question_type'] : null;
        $questionText = isset($_POST['question_text']) ? $_POST['question_text'] : null;
        $required = isset($_POST['required']) ? (bool)$_POST['required'] : true;
        $options = isset($_POST['options']) ? $_POST['options'] : null;
        $questionOrder = isset($_POST['question_order']) ? intval($_POST['question_order']) : 0;
        
        // Validate inputs
        if (!$questionId || !$questionType || !$questionText) {
            echo json_encode(['status' => false, 'message' => 'Missing required fields.']);
            exit;
        }
        
        // If trainer, check if they own the related training
        if ($role === 'trainer') {
            $checkOwnership = "SELECT t.created_by FROM assessment_questions q 
                              JOIN trainings t ON q.training_id = t.training_id 
                              WHERE q.question_id = ?";
            $stmt = $conn->prepare($checkOwnership);
            $stmt->bind_param("i", $questionId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if ($row['created_by'] != $userId) {
                    echo json_encode(['status' => false, 'message' => 'You can only manage questions for trainings you created.']);
                    exit;
                }
            } else {
                echo json_encode(['status' => false, 'message' => 'Question not found.']);
                exit;
            }
        }
        
        // Encode options as JSON if provided
        $optionsJson = null;
        if ($options) {
            $optionsJson = is_array($options) ? json_encode($options) : $options;
        }
        
        // Update the question
        $stmt = $conn->prepare("UPDATE assessment_questions SET question_type = ?, question_text = ?, required = ?, options = ?, question_order = ? WHERE question_id = ?");
        $stmt->bind_param("ssisii", $questionType, $questionText, $required, $optionsJson, $questionOrder, $questionId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0 || $stmt->errno === 0) {
            // Audit log
            recordAuditLog($userId, "Update Assessment Question", "Updated question ID $questionId");
            echo json_encode(['status' => true, 'message' => 'Question updated successfully.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to update question or no changes made.']);
        }
        exit;
        
    } elseif ($action === 'delete_question') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
            exit;
        }
        
        // Only trainers and admins can delete questions
        if ($role !== 'admin' && $role !== 'trainer') {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit;
        }
        
        $questionId = isset($_POST['question_id']) ? intval($_POST['question_id']) : null;
        
        if (!$questionId) {
            echo json_encode(['status' => false, 'message' => 'Missing question_id.']);
            exit;
        }
        
        // If trainer, check if they own the related training
        if ($role === 'trainer') {
            $checkOwnership = "SELECT t.created_by, t.training_id FROM assessment_questions q 
                              JOIN trainings t ON q.training_id = t.training_id 
                              WHERE q.question_id = ?";
            $stmt = $conn->prepare($checkOwnership);
            $stmt->bind_param("i", $questionId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if ($row['created_by'] != $userId) {
                    echo json_encode(['status' => false, 'message' => 'You can only manage questions for trainings you created.']);
                    exit;
                }
                $trainingId = $row['training_id'];
            } else {
                echo json_encode(['status' => false, 'message' => 'Question not found.']);
                exit;
            }
        }
        
        // Delete the question
        $stmt = $conn->prepare("DELETE FROM assessment_questions WHERE question_id = ?");
        $stmt->bind_param("i", $questionId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Audit log
            recordAuditLog($userId, "Delete Assessment Question", "Deleted question ID $questionId from training ID $trainingId");
            echo json_encode(['status' => true, 'message' => 'Question deleted successfully.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to delete question.']);
        }
        exit;
        
    } elseif ($action === 'submit_assessment') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
            exit;
        }
        
        // Parse the submitted responses
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['training_id']) || !isset($data['responses'])) {
            echo json_encode(['status' => false, 'message' => 'Missing required fields.']);
            exit;
        }
        
        $trainingId = intval($data['training_id']);
        $responses = $data['responses'];
        
        // Verify the user is registered for this training
        $checkRegistration = "SELECT * FROM training_registrations WHERE user_id = ? AND training_id = ?";
        $stmt = $conn->prepare($checkRegistration);
        $stmt->bind_param("ii", $userId, $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['status' => false, 'message' => 'You are not registered for this training.']);
            exit;
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Delete any previous responses from this user for this training's questions
            $stmt = $conn->prepare("DELETE ar FROM assessment_responses ar 
                                  JOIN assessment_questions aq ON ar.question_id = aq.question_id 
                                  WHERE ar.user_id = ? AND aq.training_id = ?");
            $stmt->bind_param("ii", $userId, $trainingId);
            $stmt->execute();
            
            // Insert new responses
            $insertResponse = $conn->prepare("INSERT INTO assessment_responses (user_id, question_id, response_text) VALUES (?, ?, ?)");
            
            foreach ($responses as $response) {
                $questionId = intval($response['question_id']);
                $responseText = $response['response_text'];
                
                $insertResponse->bind_param("iis", $userId, $questionId, $responseText);
                $insertResponse->execute();
            }
            
            // Record the submission
            $checkSubmission = $conn->prepare("SELECT * FROM assessment_submissions WHERE user_id = ? AND training_id = ?");
            $checkSubmission->bind_param("ii", $userId, $trainingId);
            $checkSubmission->execute();
            $result = $checkSubmission->get_result();
            
            if ($result->num_rows === 0) {
                // First time submission
                $insertSubmission = $conn->prepare("INSERT INTO assessment_submissions (user_id, training_id) VALUES (?, ?)");
                $insertSubmission->bind_param("ii", $userId, $trainingId);
                $insertSubmission->execute();
            } else {
                // Update existing submission time
                $updateSubmission = $conn->prepare("UPDATE assessment_submissions SET submission_date = CURRENT_TIMESTAMP WHERE user_id = ? AND training_id = ?");
                $updateSubmission->bind_param("ii", $userId, $trainingId);
                $updateSubmission->execute();
            }
            
            // Mark assessment as completed in training_registrations
            $markCompleted = $conn->prepare("UPDATE training_registrations SET assessment_completed = 1 WHERE user_id = ? AND training_id = ?");
            $markCompleted->bind_param("ii", $userId, $trainingId);
            $markCompleted->execute();
            
            // Commit the transaction
            $conn->commit();
            
            // Audit log
            recordAuditLog($userId, "Submit Assessment", "Submitted assessment for training ID $trainingId");
            echo json_encode(['status' => true, 'message' => 'Assessment submitted successfully.']);
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            echo json_encode(['status' => false, 'message' => 'Failed to submit assessment: ' . $e->getMessage()]);
        }
        exit;
        
    } elseif ($action === 'get_user_responses') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
            exit;
        }
        
        $trainingId = isset($_GET['training_id']) ? intval($_GET['training_id']) : null;
        $targetUserId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $userId; // Default to current user
        
        if (!$trainingId) {
            echo json_encode(['status' => false, 'message' => 'Missing training_id.']);
            exit;
        }
        
        // If trying to access another user's responses, must be admin or trainer
        if ($targetUserId !== $userId && $role !== 'admin' && $role !== 'trainer') {
            echo json_encode(['status' => false, 'message' => 'Access denied.']);
            exit;
        }
        
        // If trainer, check if they created this training
        if ($role === 'trainer' && $targetUserId !== $userId) {
            $checkOwnership = "SELECT * FROM trainings WHERE training_id = ? AND created_by = ?";
            $stmt = $conn->prepare($checkOwnership);
            $stmt->bind_param("ii", $trainingId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                echo json_encode(['status' => false, 'message' => 'You can only view responses for trainings you created.']);
                exit;
            }
        }
        
        // Get the responses
        $stmt = $conn->prepare("SELECT ar.*, aq.question_text, aq.question_type, aq.options 
                              FROM assessment_responses ar
                              JOIN assessment_questions aq ON ar.question_id = aq.question_id
                              WHERE ar.user_id = ? AND aq.training_id = ?
                              ORDER BY aq.question_order ASC");
        $stmt->bind_param("ii", $targetUserId, $trainingId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $responses = [];
        while ($row = $result->fetch_assoc()) {
            // Decode options JSON if needed
            if (isset($row['options']) && !is_null($row['options'])) {
                $row['options'] = json_decode($row['options'], true);
            }
            $responses[] = $row;
        }
        
        echo json_encode(['status' => true, 'responses' => $responses]);
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

// Helper function for audit logging
function recordAuditLog($userId, $action, $details) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $action, $details);
    $stmt->execute();
}
?>
<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
            
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

            // Retrieve training details
            $trainingQuery = "SELECT title, schedule, image, description FROM trainings WHERE training_id = ?";
            $stmtTraining = $conn->prepare($trainingQuery);
            $stmtTraining->bind_param("i", $trainingId);
            $stmtTraining->execute();
            $resultTraining = $stmtTraining->get_result();
            $training = $resultTraining->fetch_assoc();
            $stmtTraining->close();

            // Retrieve user details
            $userQuery = "SELECT email, first_name FROM users WHERE user_id = ?";
            $stmtUser = $conn->prepare($userQuery);
            $stmtUser->bind_param("i", $userId);
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();
            $user = $resultUser->fetch_assoc();
            $stmtUser->close();

            $mail = new PHPMailer(true);
            try {
                // Configure SMTP using environment variables or hard-coded settings
                $mail->isSMTP();
                $mail->Host       = $_ENV['SMTP_HOST']; // e.g., smtp.gmail.com
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['SMTP_USER'];
                $mail->Password   = $_ENV['SMTP_PASS'];
                $mail->SMTPSecure = $_ENV['SMTP_SECURE']; // e.g., TLS
                $mail->Port       = $_ENV['SMTP_PORT'];   // e.g., 587

                // Set sender and recipient
                $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
                $mail->addAddress($user['email']);

                // Email content
                $mail->isHTML(true);
                $mail->Subject = "Training Registration Confirmation";
                $imageHtml = '';
                if (!empty($training['image'])) {
                    $imageHtml = '<p><img src="' . htmlspecialchars($training['image']) . '" alt="Training Image" style="max-width:100%;"></p>';
                }
                $mail->Body = "
                    <h1>Hello " . htmlspecialchars($user['first_name']) . ",</h1>
                    <p>Thank you for joining our training!</p>
                    <p>You have successfully registered for the training: <strong>" . htmlspecialchars($training['title']) . "</strong>.</p>
                    <p><strong>Schedule:</strong> " . htmlspecialchars($training['schedule']) . "</p>
                    <p>" . htmlspecialchars($training['description']) . "</p>
                    {$imageHtml}
                    <p>For more details, please log in to your account.</p>";
                $mail->AltBody = strip_tags($mail->Body);

                $mail->send();
            } catch (Exception $e) {
                error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
            }

            echo json_encode(['status' => true, 'message' => 'Successfully joined the training.']);
        }
    } elseif ($action === 'get_joined_trainings') {
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
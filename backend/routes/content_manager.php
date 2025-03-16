<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Add secure HTTP headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

require_once __DIR__ . '/../db/db_connect.php';
require_once __DIR__ . '/../s3config.php'; // adjust path as needed

// Authentication helper for modifying actions
function ensureAuthenticated() {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Unauthorized']);
        exit();
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    if ($action === 'fetch') {
        // ----------------------------
        // FETCH EVENTS, ANNOUNCEMENTS, TRAININGS
        // ----------------------------

        // -- EVENTS --
        $eventsQuery = "SELECT * FROM events ORDER BY date DESC";
        $eventsResult = $conn->query($eventsQuery);
        $events = [];
        while ($row = $eventsResult->fetch_assoc()) {
            // Set a default image if none provided
            $row['image'] = $row['image'] ?: '../assets/default-image.jpg';
            $events[] = $row;
        }

        // -- ANNOUNCEMENTS --
        $announcementsQuery = "SELECT * FROM announcements ORDER BY created_at DESC";
        $announcementsResult = $conn->query($announcementsQuery);
        $announcements = $announcementsResult->fetch_all(MYSQLI_ASSOC);

        // -- TRAININGS --
        // Use the logged-in user's id to check training registrations.
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

        // Prepare the trainings query with a LEFT JOIN to the training_registrations table.
        $trainingsStmt = $conn->prepare("
            SELECT t.*, 
                IF(tr.registration_id IS NOT NULL, 1, 0) AS joined,
                u.first_name, u.last_name
            FROM trainings t
            LEFT JOIN training_registrations tr 
                ON t.training_id = tr.training_id AND tr.user_id = ?
            LEFT JOIN users u 
                ON t.created_by = u.user_id
            ORDER BY t.schedule ASC
        ");
        $trainingsStmt->bind_param("i", $user_id);
        $trainingsStmt->execute();
        $trainingsResult = $trainingsStmt->get_result();
        $trainings = $trainingsResult->fetch_all(MYSQLI_ASSOC);
        $trainingsStmt->close();

        echo json_encode([
            'status' => true,
            'events' => $events,
            'announcements' => $announcements,
            'trainings' => $trainings
        ]);
    } elseif ($action === 'add_event' || $action === 'update_event') {
        ensureAuthenticated();
        // ----------------------------
        // ADD OR UPDATE EVENT
        // ----------------------------
        $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($_POST['description'], ENT_QUOTES, 'UTF-8');
        $date = $_POST['date'];
        $location = htmlspecialchars($_POST['location'], ENT_QUOTES, 'UTF-8');
        $event_id = $_POST['id'] ?? null;
        $userId = $_SESSION['user_id'];

        // Image handling
        $relativeImagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Check file size (max 5MB)
            if ($_FILES['image']['size'] > 5242880) {
                echo json_encode(['status' => false, 'message' => 'File too large. Maximum size is 5MB.']);
                exit();
            }
            // Allowed file types
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }

            // Generate a unique filename for S3
            $imageName = time() . '_' . basename($_FILES['image']['name']);
            $s3Key = 'uploads/event_images/' . $imageName;

            try {
                // Upload to S3
                $result = $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key'    => $s3Key,
                    'Body'   => fopen($_FILES['image']['tmp_name'], 'rb'),
                    'ACL'    => 'public-read',
                    'ContentType' => $_FILES['image']['type']
                ]);

                $relativeImagePath = str_replace(
                    "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
                    "/s3proxy/",
                    $result['ObjectURL']
                );
            } catch (Aws\Exception\AwsException $e) {
                error_log("S3 upload error: " . $e->getMessage());
                echo json_encode(['status' => false, 'message' => 'Failed to upload image to S3.']);
                exit();
            }
        }

        if ($action === 'add_event') {
            // Insert new event
            $stmt = $conn->prepare("INSERT INTO events (title, description, date, location, image) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssss', $title, $description, $date, $location, $relativeImagePath);
            $stmt->execute();

            // Audit log for event addition
            recordAuditLog($userId, "Add Event", "Event '$title' added.");

            echo json_encode(['status' => true, 'message' => 'Event added successfully.']);
        } elseif ($action === 'update_event') {
            // Update existing event
            $stmt = $conn->prepare(
                "UPDATE events SET title = ?, description = ?, date = ?, location = ?, image = IFNULL(?, image) WHERE event_id = ?"
            );
            $stmt->bind_param('sssssi', $title, $description, $date, $location, $relativeImagePath, $event_id);
            $stmt->execute();

            // Audit log for event update
            recordAuditLog($userId, "Update Event", "Event ID $event_id updated with title '$title'.");

            echo json_encode(['status' => true, 'message' => 'Event updated successfully.']);
        }
    } elseif ($action === 'delete_event') {
        ensureAuthenticated();
        // ----------------------------
        // DELETE EVENT
        // ----------------------------
        $event_id = $_POST['id'];
        $userId = $_SESSION['user_id'];

        // Optionally, delete the associated image from the server
        $stmt = $conn->prepare("SELECT image FROM events WHERE event_id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $event = $result->fetch_assoc();
            if ($event['image'] && file_exists('../../' . $event['image'])) {
                unlink('../../' . $event['image']);
            }
        }
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();

        // Audit log for event deletion
        recordAuditLog($userId, "Delete Event", "Event ID $event_id deleted.");

        echo json_encode(['status' => true, 'message' => 'Event deleted successfully.']);
    } elseif ($action === 'get_event') {
        // ----------------------------
        // GET SINGLE EVENT
        // ----------------------------
        $event_id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $event = $result->fetch_assoc();
            echo json_encode(['status' => true, 'event' => $event]);
        } else {
            echo json_encode(['status' => false, 'message' => 'Event not found.']);
        }
    } elseif ($action === 'add_announcement' || $action === 'update_announcement') {
        ensureAuthenticated();
        // ----------------------------
        // ADD OR UPDATE ANNOUNCEMENT
        // ----------------------------
        $text = htmlspecialchars($_POST['text'], ENT_QUOTES, 'UTF-8');
        $announcement_id = $_POST['id'] ?? null;
        $userId = $_SESSION['user_id'];

        if ($action === 'add_announcement') {
            // Insert new announcement
            $stmt = $conn->prepare("INSERT INTO announcements (text) VALUES (?)");
            $stmt->bind_param('s', $text);
            $stmt->execute();

            // Audit log for announcement addition
            recordAuditLog($userId, "Add Announcement", "Announcement added: " . substr($text, 0, 50));

            echo json_encode(['status' => true, 'message' => 'Announcement added successfully.']);
        } elseif ($action === 'update_announcement') {
            // Update existing announcement
            $stmt = $conn->prepare("UPDATE announcements SET text = ? WHERE announcement_id = ?");
            $stmt->bind_param('si', $text, $announcement_id);
            $stmt->execute();

            // Audit log for announcement update
            recordAuditLog($userId, "Update Announcement", "Announcement ID $announcement_id updated.");

            echo json_encode(['status' => true, 'message' => 'Announcement updated successfully.']);
        }
    } elseif ($action === 'delete_announcement') {
        ensureAuthenticated();
        // ----------------------------
        // DELETE ANNOUNCEMENT
        // ----------------------------
        $announcement_id = $_POST['id'];
        $userId = $_SESSION['user_id'];

        $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
        $stmt->bind_param('i', $announcement_id);
        $stmt->execute();

        // Audit log for announcement deletion
        recordAuditLog($userId, "Delete Announcement", "Announcement ID $announcement_id deleted.");

        echo json_encode(['status' => true, 'message' => 'Announcement deleted successfully.']);
    } elseif ($action === 'get_announcement') {
        // ----------------------------
        // GET SINGLE ANNOUNCEMENT
        // ----------------------------
        $announcement_id = $_GET['id'];

        $stmt = $conn->prepare("SELECT * FROM announcements WHERE announcement_id = ?");
        $stmt->bind_param('i', $announcement_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $announcement = $result->fetch_assoc();
            echo json_encode(['status' => true, 'announcement' => $announcement]);
        } else {
            echo json_encode(['status' => false, 'message' => 'Announcement not found.']);
        }
    } elseif ($action === 'add_training' || $action === 'update_training') {
        ensureAuthenticated();
        // ----------------------------
        // ADD OR UPDATE TRAINING
        // ----------------------------
        $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($_POST['description'], ENT_QUOTES, 'UTF-8');
        $schedule = $_POST['schedule'];
        $capacity = intval($_POST['capacity']);
        $training_id = $_POST['id'] ?? null;
        // New fields for modality and modality details
        $modality = htmlspecialchars($_POST['modality'] ?? '', ENT_QUOTES, 'UTF-8');
        $modality_details = htmlspecialchars($_POST['modality_details'] ?? '', ENT_QUOTES, 'UTF-8');
        $userId = $_SESSION['user_id'];

        $relativeImagePath = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Check file size (max 5MB)
            if ($_FILES['image']['size'] > 5242880) {
                echo json_encode(['status' => false, 'message' => 'File too large. Maximum size is 5MB.']);
                exit();
            }
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }

            // Generate a unique name for the file in S3
            $imageName = time() . '_' . basename($_FILES['image']['name']);
            $s3Key = 'uploads/training_images/' . $imageName;

            try {
                // Upload to S3
                $result = $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key'    => $s3Key,
                    'Body'   => fopen($_FILES['image']['tmp_name'], 'rb'),
                    'ACL'    => 'public-read',
                    'ContentType' => $_FILES['image']['type']
                ]);

                $relativeImagePath = str_replace(
                    "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
                    "/s3proxy/",
                    $result['ObjectURL']
                );
            } catch (Aws\Exception\AwsException $e) {
                error_log("S3 upload error: " . $e->getMessage());
                echo json_encode(['status' => false, 'message' => 'Failed to upload image to S3.']);
                exit();
            }
        }

        if ($action === 'add_training') {
            $trainer_id = $_SESSION['user_id'] ?? 0;
            $stmt = $conn->prepare("INSERT INTO trainings (title, description, schedule, capacity, image, modality, modality_details, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssisssi', $title, $description, $schedule, $capacity, $relativeImagePath, $modality, $modality_details, $trainer_id);
            $stmt->execute();

            // Audit log for training addition
            recordAuditLog($trainer_id, "Add Training", "Training '$title' added.");

            echo json_encode(['status' => true, 'message' => 'Training added successfully.']);
        } elseif ($action === 'update_training') {
            $stmt = $conn->prepare("UPDATE trainings SET title = ?, description = ?, schedule = ?, capacity = ?, image = IFNULL(?, image), modality = ?, modality_details = ? WHERE training_id = ?");
            $stmt->bind_param('sssisssi', $title, $description, $schedule, $capacity, $relativeImagePath, $modality, $modality_details, $training_id);
            $stmt->execute();

            // Audit log for training update
            recordAuditLog($_SESSION['user_id'], "Update Training", "Training ID $training_id updated with title '$title'.");

            echo json_encode(['status' => true, 'message' => 'Training updated successfully.']);
            exit();
        }
    } elseif ($action === 'delete_training') {
        ensureAuthenticated();
        $training_id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM trainings WHERE training_id = ?");
        $stmt->bind_param('i', $training_id);
        $stmt->execute();

        // Audit log for training deletion
        recordAuditLog($_SESSION['user_id'], "Delete Training", "Training ID $training_id deleted.");

        echo json_encode(['status' => true, 'message' => 'Training deleted successfully.']);
    } elseif ($action === 'get_training') {
        $training_id = $_GET['id'];
        $stmt = $conn->prepare("
            SELECT t.*, u.first_name, u.last_name
            FROM trainings t
            LEFT JOIN users u ON t.created_by = u.user_id
            WHERE t.training_id = ?
        ");
        $stmt->bind_param('i', $training_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $training = $result->fetch_assoc();
            echo json_encode(['status' => true, 'training' => $training]);
        } else {
            echo json_encode(['status' => false, 'message' => 'Training not found.']);
        }
        exit();
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid action.']);
    }
} catch (Exception $e) {
    error_log("Internal error: " . $e->getMessage());
    echo json_encode([
        'status' => false,
        'message' => 'Internal Server Error.'
    ]);
}
?>
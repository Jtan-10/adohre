<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db/db_connect.php';
require_once __DIR__ . '/../s3config.php'; // adjust path as needed
header('Content-Type: application/json');

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
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        } else {
            // If for any reason no user_id is available, default to zero.
            $user_id = 0;
        }

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

        echo json_encode([
            'status' => true,
            'events' => $events,
            'announcements' => $announcements,
            'trainings' => $trainings
        ]);
    } elseif ($action === 'add_event' || $action === 'update_event') {
        // ----------------------------
        // ADD OR UPDATE EVENT
        // ----------------------------
        $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($_POST['description'], ENT_QUOTES, 'UTF-8');
        $date = $_POST['date'];
        $location = htmlspecialchars($_POST['location'], ENT_QUOTES, 'UTF-8');
        $event_id = $_POST['id'] ?? null;

        // Image handling
        $relativeImagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Allowed file types
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }

            // Generate a unique filename for S3
            $imageName = time() . '_' . basename($_FILES['image']['name']);
            $s3Key = 'uploads/event_images/' . $imageName; // The “folder” structure in S3

            try {
                // 3. Upload to S3
                $result = $s3->putObject([
                    'Bucket' => $bucketName,          // e.g., 'adohre-bucket'
                    'Key'    => $s3Key,               // 'uploads/event_images/<unique_filename>'
                    'Body'   => fopen($_FILES['image']['tmp_name'], 'rb'),
                    'ACL'    => 'public-read',        // or 'private', adjust as needed
                    'ContentType' => $_FILES['image']['type']
                ]);

                // Optionally, store the full URL or just the S3 key.
                // The relative path concept doesn't apply the same way as local storage,
                // but if you want to keep a "relative path" notion, you can do:
                $relativeImagePath = str_replace(
                    "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
                    "/s3proxy/",
                    $result['ObjectURL']
                );

                // Or you might store the public URL in the database:

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
            echo json_encode(['status' => true, 'message' => 'Event added successfully.']);
        } elseif ($action === 'update_event') {
            // Update existing event
            $stmt = $conn->prepare(
                "UPDATE events SET title = ?, description = ?, date = ?, location = ?, image = IFNULL(?, image) WHERE event_id = ?"
            );
            $stmt->bind_param('sssssi', $title, $description, $date, $location, $relativeImagePath, $event_id);
            $stmt->execute();
            echo json_encode(['status' => true, 'message' => 'Event updated successfully.']);
        }
    } elseif ($action === 'delete_event') {
        // ----------------------------
        // DELETE EVENT
        // ----------------------------
        $event_id = $_POST['id'];

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

        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();

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
        // ----------------------------
        // ADD OR UPDATE ANNOUNCEMENT
        // ----------------------------
        $text = htmlspecialchars($_POST['text'], ENT_QUOTES, 'UTF-8');
        $announcement_id = $_POST['id'] ?? null;

        if ($action === 'add_announcement') {
            // Insert new announcement
            $stmt = $conn->prepare("INSERT INTO announcements (text) VALUES (?)");
            $stmt->bind_param('s', $text);
            $stmt->execute();
            echo json_encode(['status' => true, 'message' => 'Announcement added successfully.']);
        } elseif ($action === 'update_announcement') {
            // Update existing announcement
            $stmt = $conn->prepare("UPDATE announcements SET text = ? WHERE announcement_id = ?");
            $stmt->bind_param('si', $text, $announcement_id);
            $stmt->execute();
            echo json_encode(['status' => true, 'message' => 'Announcement updated successfully.']);
        }
    } elseif ($action === 'delete_announcement') {
        // ----------------------------
        // DELETE ANNOUNCEMENT
        // ----------------------------
        $announcement_id = $_POST['id'];

        $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
        $stmt->bind_param('i', $announcement_id);
        $stmt->execute();

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

        $relativeImagePath = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }

            // Generate a unique name for the file in S3
            $imageName = time() . '_' . basename($_FILES['image']['name']);
            // We'll store it under "uploads/training_images/<uniqueName>"
            $s3Key = 'uploads/training_images/' . $imageName;

            try {
                // Upload to S3
                $result = $s3->putObject([
                    'Bucket' => $bucketName, // e.g. "my-s3-bucket"
                    'Key'    => $s3Key,
                    'Body'   => fopen($_FILES['image']['tmp_name'], 'rb'),
                    'ACL'    => 'public-read', // or 'private', adjust as needed
                    'ContentType' => $_FILES['image']['type']
                ]);

                // If you want to store the S3 object URL in your database:

                // Or just store the S3 key, so you can build the URL later:
                $relativeImagePath = str_replace(
                    "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
                    "/s3proxy/",
                    $result['ObjectURL']
                );
            } catch (Aws\Exception\AwsException $e) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Failed to upload image to S3: ' . $e->getMessage()
                ]);
                exit();
            }
        }

        if ($action === 'add_training') {
            // Make sure the session is started to access the user's ID.
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $trainer_id = $_SESSION['user_id'] ?? 0; // The logged-in user's ID

            // Insert new training including modality fields and created_by.
            $stmt = $conn->prepare("INSERT INTO trainings (title, description, schedule, capacity, image, modality, modality_details, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssisssi', $title, $description, $schedule, $capacity, $relativeImagePath, $modality, $modality_details, $trainer_id);
            $stmt->execute();
            echo json_encode(['status' => true, 'message' => 'Training added successfully.']);
        } elseif ($action === 'update_training') {
            // Update existing training including modality fields. The created_by field is not updated.
            $stmt = $conn->prepare("UPDATE trainings SET title = ?, description = ?, schedule = ?, capacity = ?, image = IFNULL(?, image), modality = ?, modality_details = ? WHERE training_id = ?");
            $stmt->bind_param('sssisssi', $title, $description, $schedule, $capacity, $relativeImagePath, $modality, $modality_details, $training_id);
            $stmt->execute();
            echo json_encode(['status' => true, 'message' => 'Training updated successfully.']);
            exit();
        }
    } elseif ($action === 'delete_training') {
        // ----------------------------
        // DELETE TRAINING
        // ----------------------------
        $training_id = $_POST['id'];

        $stmt = $conn->prepare("DELETE FROM trainings WHERE training_id = ?");
        $stmt->bind_param('i', $training_id);
        $stmt->execute();

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
    echo json_encode([
        'status' => false,
        'message' => 'Internal Server Error.',
        'error' => $e->getMessage()
    ]);
}
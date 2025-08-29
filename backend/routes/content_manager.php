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

// -------------------------
// Helper function to embed binary data into a valid PNG.
// -------------------------
if (!function_exists('embedDataInPng')) {
    /**
     * embedDataInPng:
     * Converts binary data into a valid PNG image by mapping every 3 bytes to a pixel (R, G, B).
     * Remaining pixels are padded with black.
     *
     * @param string $binaryData The binary data to embed.
     * @param int    $desiredWidth Desired width (used to compute a roughly square image)
     * @return GdImage A GD image resource.
     */
    function embedDataInPng($binaryData, $desiredWidth = 100): GdImage
    {
        $dataLen = strlen($binaryData);
        // Each pixel holds 3 bytes.
        $numPixels = ceil($dataLen / 3);
        // Create a roughly square image.
        $width = (int) floor(sqrt($numPixels));
        if ($width < 1) {
            $width = 1;
        }
        $height = (int) ceil($numPixels / $width);
        $img = imagecreatetruecolor($width, $height);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $black);
        $pos = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($pos < $dataLen) {
                    $r = ord($binaryData[$pos++]);
                    $g = ($pos < $dataLen) ? ord($binaryData[$pos++]) : 0;
                    $b = ($pos < $dataLen) ? ord($binaryData[$pos++]) : 0;
                    $color = imagecolorallocate($img, $r, $g, $b);
                    imagesetpixel($img, $x, $y, $color);
                } else {
                    imagesetpixel($img, $x, $y, $black);
                }
            }
        }
        return $img;
    }
}

// Authentication helper for modifying actions
function ensureAuthenticated()
{
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
        // Get the current user ID
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

        // Updated query to add a "joined" flag and a "pending_payment" flag.
        // The pending_payment flag is 1 if a payment record for this event exists for the user with status 'New'
        // and payment_type 'Event Registration'; otherwise, it is 0.
        $eventsQuery = "
            SELECT e.*,
                   IF(er.registration_id IS NOT NULL, 1, 0) AS joined,
                   IF(p.payment_id IS NOT NULL, 1, 0) AS pending_payment
            FROM events e
            LEFT JOIN event_registrations er 
                 ON e.event_id = er.event_id AND er.user_id = ?
            LEFT JOIN payments p
                 ON e.event_id = p.event_id 
                 AND p.user_id = ? 
                 AND p.status = 'New'
                 AND p.payment_type = 'Event Registration'
            ORDER BY e.date DESC
        ";

        // Bind the user id twice.
        $stmt = $conn->prepare($eventsQuery);
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $eventsResult = $stmt->get_result();
        $events = [];
        while ($row = $eventsResult->fetch_assoc()) {
            // Convert the joined and pending_payment flags to boolean for easier use on the front end
            $row['joined'] = (bool)$row['joined'];
            $row['pending_payment'] = (bool)$row['pending_payment'];
            // Set a default image if none provided (update path as needed)
            $row['image'] = $row['image'] ?: '../assets/default-image.jpg';
            $events[] = $row;
        }
        $stmt->close();

        // -- ANNOUNCEMENTS --
        $announcementsQuery = "SELECT announcement_id, title, text, created_at FROM announcements ORDER BY created_at DESC";
        $announcementsResult = $conn->query($announcementsQuery);
        $announcements = $announcementsResult->fetch_all(MYSQLI_ASSOC);

        // -- TRAININGS --
        // Use the logged-in user's id to check training registrations.
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

        // Prepare the trainings query with a LEFT JOIN to the training_registrations table.
        $trainingsStmt = $conn->prepare("
            SELECT t.*, 
                IF(tr.registration_id IS NOT NULL, 1, 0) AS joined,
                u.first_name, u.last_name,
                IF(p.payment_id IS NOT NULL, 1, 0) AS pending_payment
            FROM trainings t
            LEFT JOIN training_registrations tr 
                ON t.training_id = tr.training_id AND tr.user_id = ?
            LEFT JOIN users u 
                ON t.created_by = u.user_id
            LEFT JOIN payments p 
                ON t.training_id = p.training_id 
                   AND p.user_id = ? 
                   AND p.status = 'New'
                   AND p.payment_type = 'Training Registration'
            ORDER BY t.schedule ASC
        ");
        $trainingsStmt->bind_param("ii", $user_id, $user_id);
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
    } elseif ($action === 'fetch_projects') {
        // Fetch projects list (new schema: partner, date, status, end_date, image)
        $result = $conn->query("SELECT project_id, title, partner, date, status, end_date, image FROM projects ORDER BY COALESCE(date, created_at) DESC");
        $projects = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $projects[] = $row;
            }
        }
        echo json_encode(['status' => true, 'projects' => $projects]);
    } elseif ($action === 'add_event' || $action === 'update_event') {
        ensureAuthenticated();
        // ----------------------------
        // ADD OR UPDATE EVENT
        // ----------------------------
        // Store raw text rather than HTML-escaped text.
        $title = $_POST['title'];
        $description = $_POST['description'];
        $partner = $_POST['partner'] ?? null;
        $timeframe = $_POST['timeframe'] ?? null;
        $date = $_POST['date'];
        $location = $_POST['location'];
        $event_id = $_POST['id'] ?? null;
        $userId = $_SESSION['user_id'];

        // Image handling with encryption and S3 replacement
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

            // -------------------------
            // ENCRYPTION & EMBEDDING STEP
            // -------------------------
            $clearImageData = file_get_contents($_FILES['image']['tmp_name']);
            $cipher = "AES-256-CBC";
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
            $rawKey = getenv('ENCRYPTION_KEY');
            $encryptionKey = hash('sha256', $rawKey, true);
            $encryptedData = openssl_encrypt($clearImageData, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
            $encryptedImageData = $iv . $encryptedData;
            $pngImage = embedDataInPng($encryptedImageData, 100);
            $finalEncryptedPngFile = tempnam(sys_get_temp_dir(), 'enc_png_') . '.png';
            imagepng($pngImage, $finalEncryptedPngFile);
            imagedestroy($pngImage);
            // -------------------------

            // If updating an event, check if an existing image exists and delete it from S3.
            if ($action === 'update_event') {
                $stmtCheck = $conn->prepare("SELECT image FROM events WHERE event_id = ?");
                $stmtCheck->bind_param("i", $event_id);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();
                if ($resultCheck->num_rows > 0) {
                    $existingEvent = $resultCheck->fetch_assoc();
                    if (!empty($existingEvent['image'])) {
                        // Remove the proxy prefix and then urldecode to match the S3 key
                        $existingS3Key = urldecode(str_replace('/s3proxy/', '', $existingEvent['image']));
                        try {
                            $s3->deleteObject([
                                'Bucket' => $bucketName,
                                'Key'    => $existingS3Key
                            ]);
                        } catch (Aws\Exception\AwsException $e) {
                            error_log("S3 deletion error: " . $e->getMessage());
                        }
                    }
                }
                $stmtCheck->close();
            }

            // Upload the encrypted PNG to S3.
            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => fopen($finalEncryptedPngFile, 'rb'),
                    'ACL'         => 'public-read',
                    // Even though the original file might have a different type,
                    // after encryption the file is a PNG.
                    'ContentType' => 'image/png'
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
            @unlink($finalEncryptedPngFile);
        }

        if ($action === 'add_event') {
            // New: retrieve fee; default to 0 if not provided
            $fee = isset($_POST['fee']) && is_numeric($_POST['fee']) ? floatval($_POST['fee']) : 0.00;

            // Insert new event with fee column included
            $stmt = $conn->prepare("INSERT INTO events (title, description, date, location, fee, image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssds', $title, $description, $date, $location, $fee, $relativeImagePath);
            $stmt->execute();

            // Audit log for event addition
            recordAuditLog($userId, "Add Event", "Event '$title' added.");
            echo json_encode(['status' => true, 'message' => 'Event added successfully.']);
        } elseif ($action === 'update_event') {
            // New: retrieve fee; default to 0 if not provided
            $fee = isset($_POST['fee']) && is_numeric($_POST['fee']) ? floatval($_POST['fee']) : 0.00;

            // Update existing event; if no new image provided, the old image remains.
            $stmt = $conn->prepare(
                "UPDATE events SET title = ?, description = ?, date = ?, location = ?, fee = ?, image = IFNULL(?, image) WHERE event_id = ?"
            );
            $stmt->bind_param('ssssdsi', $title, $description, $date, $location, $fee, $relativeImagePath, $event_id);
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

        // Retrieve the event record to check for an existing image.
        $stmt = $conn->prepare("SELECT image FROM events WHERE event_id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $event = $result->fetch_assoc();
            if (!empty($event['image'])) {
                // Check if the image is stored on S3 (assuming it starts with '/s3proxy/')
                if (strpos($event['image'], '/s3proxy/') === 0) {
                    // Remove the proxy prefix and decode the URL to obtain the original S3 key.
                    $existingS3Key = urldecode(str_replace('/s3proxy/', '', $event['image']));
                    try {
                        $s3->deleteObject([
                            'Bucket' => $bucketName,
                            'Key'    => $existingS3Key
                        ]);
                    } catch (Aws\Exception\AwsException $e) {
                        error_log("S3 deletion error: " . $e->getMessage());
                    }
                } else {
                    // Otherwise, attempt to delete it locally.
                    if (file_exists('../../' . $event['image'])) {
                        unlink('../../' . $event['image']);
                    }
                }
            }
        }
        $stmt->close();

        // Now delete the event record from the database.
        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->bind_param('i', $event_id);
        $stmt->execute();

        // Audit log for event deletion.
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
        $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
        $text  = htmlspecialchars($_POST['text'], ENT_QUOTES, 'UTF-8');
        $announcement_id = $_POST['id'] ?? null;
        $userId = $_SESSION['user_id'];

        if ($action === 'add_announcement') {
            $stmt = $conn->prepare("INSERT INTO announcements (title, text) VALUES (?, ?)");
            $stmt->bind_param('ss', $title, $text);
            $stmt->execute();

            recordAuditLog($userId, "Add Announcement", "Announcement added: " . substr($text, 0, 50));
            echo json_encode(['status' => true, 'message' => 'Announcement added successfully.']);
        } elseif ($action === 'update_announcement') {
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, text = ? WHERE announcement_id = ?");
            $stmt->bind_param('ssi', $title, $text, $announcement_id);
            $stmt->execute();

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

        recordAuditLog($userId, "Delete Announcement", "Announcement ID $announcement_id deleted.");
        echo json_encode(['status' => true, 'message' => 'Announcement deleted successfully.']);
    } elseif ($action === 'get_announcement') {
        // ----------------------------
        // GET SINGLE ANNOUNCEMENT
        // ----------------------------
        $announcement_id = $_GET['id'];
        $stmt = $conn->prepare("SELECT announcement_id, title, text, created_at FROM announcements WHERE announcement_id = ?");
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
        // Store raw text for trainings.
        $title = $_POST['title'];
        $description = $_POST['description'];
        $schedule = $_POST['schedule'];
        $capacity = intval($_POST['capacity']);
        $training_id = $_POST['id'] ?? null;
        $modality = $_POST['modality'] ?? '';
        $modality_details = $_POST['modality_details'] ?? '';
        $userId = $_SESSION['user_id'];

        $relativeImagePath = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['image']['size'] > 5242880) {
                echo json_encode(['status' => false, 'message' => 'File too large. Maximum size is 5MB.']);
                exit();
            }
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }

            $imageName = time() . '_' . basename($_FILES['image']['name']);
            $s3Key = 'uploads/training_images/' . $imageName;

            // -------------------------
            // ENCRYPTION & EMBEDDING STEP for training image
            // -------------------------
            $clearImageData = file_get_contents($_FILES['image']['tmp_name']);
            $cipher = "AES-256-CBC";
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
            $rawKey = getenv('ENCRYPTION_KEY');
            $encryptionKey = hash('sha256', $rawKey, true);
            $encryptedData = openssl_encrypt($clearImageData, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
            $encryptedImageData = $iv . $encryptedData;
            $pngImage = embedDataInPng($encryptedImageData, 100);
            $finalEncryptedPngFile = tempnam(sys_get_temp_dir(), 'enc_png_') . '.png';
            imagepng($pngImage, $finalEncryptedPngFile);
            imagedestroy($pngImage);
            // -------------------------

            // If updating training, check for an existing image and delete it from S3.
            if ($action === 'update_training') {
                $stmtCheck = $conn->prepare("SELECT image FROM trainings WHERE training_id = ?");
                $stmtCheck->bind_param("i", $training_id);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();
                if ($resultCheck->num_rows > 0) {
                    $existingTraining = $resultCheck->fetch_assoc();
                    if (!empty($existingTraining['image'])) {
                        $existingS3Key = urldecode(str_replace('/s3proxy/', '', $existingTraining['image']));
                        try {
                            $s3->deleteObject([
                                'Bucket' => $bucketName,
                                'Key'    => $existingS3Key
                            ]);
                        } catch (Aws\Exception\AwsException $e) {
                            error_log("S3 deletion error: " . $e->getMessage());
                        }
                    }
                }
                $stmtCheck->close();
            }

            // Upload the encrypted training image to S3.
            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => fopen($finalEncryptedPngFile, 'rb'),
                    'ACL'         => 'public-read',
                    'ContentType' => 'image/png'
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
            @unlink($finalEncryptedPngFile);
        }
    } elseif ($action === 'add_project' || $action === 'update_project') {
        ensureAuthenticated();
        $title = $_POST['title'];
        $partner = $_POST['partner'] ?? null;
        $date = $_POST['date'] ?? null; // YYYY-MM-DD (start date or relevant date)
        $status = $_POST['status'] ?? 'scheduling';
        $end_date = $_POST['end_date'] ?? null; // Only for finished
        $project_id = $_POST['id'] ?? null;
        $userId = $_SESSION['user_id'];

        $relativeImagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['image']['size'] > 5242880) {
                echo json_encode(['status' => false, 'message' => 'File too large. Maximum size is 5MB.']);
                exit();
            }
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }

            $imageName = time() . '_' . basename($_FILES['image']['name']);
            $s3Key = 'uploads/project_images/' . $imageName;

            // Encrypt and embed
            $clearImageData = file_get_contents($_FILES['image']['tmp_name']);
            $cipher = "AES-256-CBC";
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
            $rawKey = getenv('ENCRYPTION_KEY');
            $encryptionKey = hash('sha256', $rawKey, true);
            $encryptedData = openssl_encrypt($clearImageData, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
            $encryptedImageData = $iv . $encryptedData;
            $pngImage = embedDataInPng($encryptedImageData, 100);
            $finalEncryptedPngFile = tempnam(sys_get_temp_dir(), 'enc_png_') . '.png';
            imagepng($pngImage, $finalEncryptedPngFile);
            imagedestroy($pngImage);

            if ($action === 'update_project') {
                $stmtCheck = $conn->prepare("SELECT image FROM projects WHERE project_id = ?");
                $stmtCheck->bind_param("i", $project_id);
                $stmtCheck->execute();
                $resultCheck = $stmtCheck->get_result();
                if ($resultCheck->num_rows > 0) {
                    $existing = $resultCheck->fetch_assoc();
                    if (!empty($existing['image'])) {
                        $existingS3Key = urldecode(str_replace('/s3proxy/', '', $existing['image']));
                        try {
                            $s3->deleteObject(['Bucket' => $bucketName, 'Key' => $existingS3Key]);
                        } catch (Aws\Exception\AwsException $e) {
                            error_log($e->getMessage());
                        }
                    }
                }
                $stmtCheck->close();
            }

            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => fopen($finalEncryptedPngFile, 'rb'),
                    'ACL'         => 'public-read',
                    'ContentType' => 'image/png'
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
            @unlink($finalEncryptedPngFile);
        }

        if ($action === 'add_project') {
            $stmt = $conn->prepare("INSERT INTO projects (title, partner, date, status, end_date, image, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssi', $title, $partner, $date, $status, $end_date, $relativeImagePath, $userId);
            $stmt->execute();
            recordAuditLog($userId, 'Add Project', "Project '$title' added.");
            echo json_encode(['status' => true, 'message' => 'Project added successfully.']);
        } else {
            $stmt = $conn->prepare("UPDATE projects SET title = ?, partner = ?, date = ?, status = ?, end_date = ?, image = IFNULL(?, image) WHERE project_id = ?");
            $stmt->bind_param('ssssssi', $title, $partner, $date, $status, $end_date, $relativeImagePath, $project_id);
            $stmt->execute();
            recordAuditLog($userId, 'Update Project', "Project ID $project_id updated.");
            echo json_encode(['status' => true, 'message' => 'Project updated successfully.']);
        }
    } elseif ($action === 'delete_project') {
        ensureAuthenticated();
        $project_id = $_POST['id'];
        $userId = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT image FROM projects WHERE project_id = ?");
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (!empty($row['image']) && strpos($row['image'], '/s3proxy/') === 0) {
                $existingS3Key = urldecode(str_replace('/s3proxy/', '', $row['image']));
                try {
                    $s3->deleteObject(['Bucket' => $bucketName, 'Key' => $existingS3Key]);
                } catch (Aws\Exception\AwsException $e) {
                    error_log($e->getMessage());
                }
            }
        }
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM projects WHERE project_id = ?");
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        recordAuditLog($userId, 'Delete Project', "Project ID $project_id deleted.");
        echo json_encode(['status' => true, 'message' => 'Project deleted successfully.']);
    } elseif ($action === 'get_project') {
        $project_id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM projects WHERE project_id = ?");
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            echo json_encode(['status' => true, 'project' => $res->fetch_assoc()]);
        } else {
            echo json_encode(['status' => false, 'message' => 'Project not found.']);
        }

        if ($action === 'add_training') {
            $trainer_id = $_SESSION['user_id'] ?? 0;
            // Retrieve fee; default to 0 if not provided
            $fee = isset($_POST['fee']) && is_numeric($_POST['fee']) ? floatval($_POST['fee']) : 0.00;
            $stmt = $conn->prepare("INSERT INTO trainings (title, description, schedule, capacity, fee, image, modality, modality_details, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssidsssi', $title, $description, $schedule, $capacity, $fee, $relativeImagePath, $modality, $modality_details, $trainer_id);
            $stmt->execute();

            recordAuditLog($trainer_id, "Add Training", "Training '$title' added.");
            echo json_encode(['status' => true, 'message' => 'Training added successfully.']);
        } elseif ($action === 'update_training') {
            // Retrieve fee; default to 0 if not provided
            $fee = isset($_POST['fee']) && is_numeric($_POST['fee']) ? floatval($_POST['fee']) : 0.00;
            $stmt = $conn->prepare("UPDATE trainings SET title = ?, description = ?, schedule = ?, capacity = ?, fee = ?, image = IFNULL(?, image), modality = ?, modality_details = ? WHERE training_id = ?");
            $stmt->bind_param('sssidsssi', $title, $description, $schedule, $capacity, $fee, $relativeImagePath, $modality, $modality_details, $training_id);
            $stmt->execute();

            recordAuditLog($_SESSION['user_id'], "Update Training", "Training ID $training_id updated with title '$title'.");
            echo json_encode(['status' => true, 'message' => 'Training updated successfully.']);
            exit();
        }
    } elseif ($action === 'delete_training') {
        ensureAuthenticated();
        $training_id = $_POST['id'];

        // Check if there is an existing image and delete it from S3
        $stmtCheck = $conn->prepare("SELECT image FROM trainings WHERE training_id = ?");
        $stmtCheck->bind_param("i", $training_id);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        if ($resultCheck->num_rows > 0) {
            $existingTraining = $resultCheck->fetch_assoc();
            if (!empty($existingTraining['image'])) {
                $existingS3Key = urldecode(str_replace('/s3proxy/', '', $existingTraining['image']));
                try {
                    $s3->deleteObject([
                        'Bucket' => $bucketName,
                        'Key'    => $existingS3Key
                    ]);
                } catch (Aws\Exception\AwsException $e) {
                    error_log("S3 deletion error: " . $e->getMessage());
                }
            }
        }
        $stmtCheck->close();

        // Now delete the training record from the database.
        $stmt = $conn->prepare("DELETE FROM trainings WHERE training_id = ?");
        $stmt->bind_param('i', $training_id);
        $stmt->execute();

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

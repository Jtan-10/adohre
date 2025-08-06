<?php
require_once '../db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

require_once '../s3config.php';
require_once '../controllers/authController.php';

if (!function_exists('embedDataInPng')) {
    // Helper function to embed binary data into a valid PNG.
    function embedDataInPng($binaryData, $desiredWidth = 100): GdImage
    {
        $dataLen = strlen($binaryData);
        $numPixels = ceil($dataLen / 3);
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

header('Content-Type: application/json');
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

// Get the HTTP request method.
$method = $_SERVER['REQUEST_METHOD'];

// Retrieve authenticated user details.
$auth_user_id   = $_SESSION['user_id'] ?? null;
$auth_user_role = $_SESSION['role'] ?? null;

if (!$auth_user_id) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit();
}


try {
    if ($method === 'GET') {
        // --- ADMIN DataTables request ---
        if ($auth_user_role === 'admin' && isset($_GET['draw'])) {
            $draw   = isset($_GET['draw']) ? intval($_GET['draw']) : 0;
            $start  = isset($_GET['start']) ? intval($_GET['start']) : 0;
            $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
            $searchValue = trim($_GET['search']['value'] ?? '');

            // Get total records count.
            $totalRecordsQuery = "SELECT COUNT(*) as total FROM users";
            $totalRecordsResult = $conn->query($totalRecordsQuery);
            $totalRecordsRow = $totalRecordsResult->fetch_assoc();
            $totalRecords = $totalRecordsRow ? $totalRecordsRow['total'] : 0;

            // Count filtered records.
            $filteredRecordsQuery = "SELECT COUNT(*) as total FROM users WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?";
            $searchTerm = "%" . $conn->real_escape_string($searchValue) . "%";
            $stmt = $conn->prepare($filteredRecordsQuery);
            $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
            $stmt->execute();
            $filteredRecordsResult = $stmt->get_result();
            $filteredRow = $filteredRecordsResult->fetch_assoc();
            $filteredRecords = $filteredRow ? $filteredRow['total'] : 0;
            $stmt->close();

            // Fetch paginated records.
            $query = "SELECT user_id, first_name, last_name, email, role FROM users WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sssii', $searchTerm, $searchTerm, $searchTerm, $length, $start);
            $stmt->execute();
            $result = $stmt->get_result();
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            $stmt->close();

            echo json_encode([
                "draw"            => $draw,
                "recordsTotal"    => intval($totalRecords),
                "recordsFiltered" => intval($filteredRecords),
                "data"            => $users
            ]);
            exit();
        }
        // --- Fetch user events or trainings ---
        elseif (isset($_GET['action'])) {
            $action = $_GET['action'];
            $user_id = intval($_GET['user_id'] ?? 0);

            // Allow non-admin users to access only their own data.
            if ($auth_user_role !== 'admin' && $user_id !== $auth_user_id) {
                http_response_code(403);
                echo json_encode(['status' => false, 'message' => 'Forbidden']);
                exit();
            }

            if ($action === 'get_user_events') {
                $stmt = $conn->prepare("SELECT e.title, e.description, e.date, e.location 
                                         FROM event_registrations er
                                         JOIN events e ON er.event_id = e.event_id
                                         WHERE er.user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $events = [];
                while ($row = $result->fetch_assoc()) {
                    $events[] = $row;
                }
                $stmt->close();
                echo json_encode(['data' => $events]);
                exit();
            } elseif ($action === 'get_user_trainings') {
                $stmt = $conn->prepare("SELECT t.title, t.description, t.schedule 
                                         FROM training_registrations tr
                                         JOIN trainings t ON tr.training_id = t.training_id
                                         WHERE tr.user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $trainings = [];
                while ($row = $result->fetch_assoc()) {
                    $trainings[] = $row;
                }
                $stmt->close();
                echo json_encode(['data' => $trainings]);
                exit();
            }
        }
        // --- Fetch single user details ---
        elseif (isset($_GET['user_id'])) {
            $user_id = intval($_GET['user_id']);
            // Non-admin users can only view their own details.
            if ($auth_user_role !== 'admin' && $user_id !== $auth_user_id) {
                http_response_code(403);
                echo json_encode(['status' => false, 'message' => 'Forbidden']);
                exit();
            }
            $stmt = $conn->prepare('SELECT user_id, first_name, last_name, email, role, profile_image, virtual_id FROM users WHERE user_id = ?');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $userData = $result->fetch_assoc();
                $stmt->close();
                echo json_encode(['status' => true, 'data' => $userData]);
            } else {
                $stmt->close();
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'User not found.']);
            }
            exit();
        }
    } elseif ($method === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'create_user') {
            // Validate inputs
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name  = trim($_POST['last_name'] ?? '');
            $email      = trim($_POST['email'] ?? '');
            $role       = trim($_POST['role'] ?? '');

            if (!$first_name || !$last_name || !$email || !$role) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'All fields are required.']);
                exit();
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
                exit();
            }

            // Include the controller that contains generateVirtualId()
            // Generate a unique virtual ID using the helper function.
            $virtual_id = generateVirtualId(16);

            // Insert new user record into the database including the virtual_id
            $stmt = $conn->prepare('INSERT INTO users (first_name, last_name, email, role, virtual_id) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $first_name, $last_name, $email, $role, $virtual_id);

            if ($stmt->execute()) {
                // Get the newly inserted user's ID
                $newUserId = $conn->insert_id;
                $stmt->close();

                // If the new user is a member, add a record to the members table
                if ($role === 'member') {
                    $initialStatus = 'inactive'; // Change to "active" if needed
                    $stmtMember = $conn->prepare("INSERT INTO members (user_id, membership_status) VALUES (?, ?)");
                    $stmtMember->bind_param("is", $newUserId, $initialStatus);
                    $stmtMember->execute();
                    $stmtMember->close();
                }

                // Record an audit log for the creation event.
                recordAuditLog($auth_user_id, 'Admin Create User', "Admin created new user: $first_name $last_name, email: $email, role: $role, virtual_id: $virtual_id");
                echo json_encode(['status' => true, 'message' => 'User created successfully.']);
            } else {
                error_log('DB insert error: ' . $stmt->error);
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Error creating user.']);
            }
            exit();
        }
        // --- Profile update for logged-in user ---
        // If a file upload is included, process the profile image.
        if (!empty($_FILES['profile_image']['name'])) {
            if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'File upload error.']);
                exit();
            }

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_file_size = 2 * 1024 * 1024; // 2 MB

            // Verify MIME type using finfo.
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($_FILES['profile_image']['tmp_name']);
            if (!in_array($mimeType, $allowed_types)) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Invalid file type.']);
                exit();
            }

            if ($_FILES['profile_image']['size'] > $max_file_size) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'File size exceeds 2 MB limit.']);
                exit();
            }

            // Delete previous S3 image if exists.
            $stmt = $conn->prepare("SELECT profile_image FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $auth_user_id);
            $stmt->execute();
            $resultImg = $stmt->get_result();
            if ($resultImg->num_rows > 0) {
                $userRow = $resultImg->fetch_assoc();
                if (!empty($userRow['profile_image']) && strpos($userRow['profile_image'], '/s3proxy/') === 0) {
                    $existingS3Key = urldecode(str_replace('/s3proxy/', '', $userRow['profile_image']));
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
            $stmt->close();

            // -------------------------
            // ENCRYPTION & EMBEDDING STEP for profile image
            // -------------------------
            $clearImageData = file_get_contents($_FILES['profile_image']['tmp_name']);
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

            // Generate a secure unique file name.
            $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $file_name = $auth_user_id . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $s3Key = 'uploads/profile_images/' . $file_name;

            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => fopen($finalEncryptedPngFile, 'rb'),
                    'ACL'         => 'public-read',
                    'ContentType' => $mimeType
                ]);
                $profile_image_path = str_replace(
                    "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
                    "/s3proxy/",
                    $result['ObjectURL']
                );
                // Update the profile image in the database.
                $stmt = $conn->prepare('UPDATE users SET profile_image = ? WHERE user_id = ?');
                $stmt->bind_param('si', $profile_image_path, $auth_user_id);
                if (!$stmt->execute()) {
                    error_log('DB update error: ' . $stmt->error);
                    http_response_code(500);
                    echo json_encode(['status' => false, 'message' => 'Failed to update profile image.']);
                    exit();
                }
                $_SESSION['profile_image'] = $profile_image_path;
                $stmt->close();

                // Audit log for profile image update.
                recordAuditLog($auth_user_id, 'Profile Image Update', 'User updated profile image.');
            } catch (Aws\Exception\AwsException $e) {
                error_log('S3 upload error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Error uploading profile image.']);
                exit();
            }
            @unlink($finalEncryptedPngFile);

            // If only updating the profile image, exit now.
            if (isset($_POST['update_profile_image']) && $_POST['update_profile_image'] === 'true') {
                echo json_encode(['status' => true, 'message' => 'Profile image updated successfully.', 'profile_image' => $profile_image_path]);
                exit();
            }
        }

        // Update other profile details.
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');

        if (!$first_name || !$last_name || !$email) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'All fields are required.']);
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
            exit();
        }

        // Sanitize text inputs.
        $first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
        $last_name  = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');

        $stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?');
        $stmt->bind_param('sssi', $first_name, $last_name, $email, $auth_user_id);
        if ($stmt->execute()) {
            $stmt->close();
            // Audit log for profile update.
            recordAuditLog($auth_user_id, 'Profile Update', 'User updated profile details.');
            echo json_encode(['status' => true, 'message' => 'Profile updated successfully.']);
        } else {
            error_log('DB update error: ' . $stmt->error);
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Error updating profile.']);
        }
    } elseif ($method === 'PUT') {
        // Decode the JSON payload at the very start
        $data = json_decode(file_get_contents("php://input"), true);

        // Check if the request is to regenerate the virtual ID (allow any user to do so)
        if (isset($data['regenerate_virtual_id']) && filter_var($data['regenerate_virtual_id'], FILTER_VALIDATE_BOOLEAN)) {
            $new_virtual_id = generateVirtualId(16);
            $stmt = $conn->prepare("UPDATE users SET virtual_id = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_virtual_id, $auth_user_id);
            if ($stmt->execute()) {
                $stmt->close();
                echo json_encode([
                    'status' => true,
                    'message' => 'Virtual ID regenerated successfully.',
                    'virtual_id' => $new_virtual_id
                ]);
                exit();
            } else {
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Error regenerating Virtual ID.']);
                exit();
            }
        }

        // --- Admin updating other users ---
        if ($auth_user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Forbidden']);
            exit();
        }

        $user_id    = intval($data['user_id'] ?? 0);
        $first_name = trim($data['first_name'] ?? '');
        $last_name  = trim($data['last_name'] ?? '');
        $email      = trim($data['email'] ?? '');
        $role       = trim($data['role'] ?? '');

        if (!$user_id || !$first_name || !$last_name || !$email || !$role) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'All fields are required.']);
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
            exit();
        }

        // --- New check: Prevent non-super-admins from editing other admins or super admins ---
        $stmt_check = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows === 1) {
            $target_user = $result_check->fetch_assoc();
            if (($target_user['role'] === 'admin')
                && $user_id !== $auth_user_id
            ) {
                http_response_code(403);
                echo json_encode(['status' => false, 'message' => 'You are not allowed to edit another admin.']);
                exit();
            }
        }
        $stmt_check->close();

        // Sanitize inputs.
        $first_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
        $last_name  = htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8');
        $role       = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');

        $stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ? WHERE user_id = ?');
        $stmt->bind_param('ssssi', $first_name, $last_name, $email, $role, $user_id);
        if ($stmt->execute()) {
            $stmt->close();
            recordAuditLog($auth_user_id, 'Admin User Update', "Admin updated user {$user_id}: {$first_name} {$last_name}, email: {$email}, role: {$role}.");

            // After updating the user, if the new role is 'member',
            // ensure a corresponding record exists in the members table.
            if ($role === 'member') {
                $stmt_member = $conn->prepare("SELECT * FROM members WHERE user_id = ?");
                $stmt_member->bind_param("i", $user_id);
                $stmt_member->execute();
                $result_member = $stmt_member->get_result();
                if ($result_member->num_rows === 0) {
                    // Insert a record in the members table with an initial status, e.g., "inactive"
                    $initialStatus = 'inactive';
                    $stmt_insert = $conn->prepare("INSERT INTO members (user_id, membership_status) VALUES (?, ?)");
                    $stmt_insert->bind_param("is", $user_id, $initialStatus);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                $stmt_member->close();
            }

            echo json_encode(['status' => true, 'message' => 'User updated successfully.']);
        } else {
            error_log('DB update error: ' . $stmt->error);
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Error updating user.']);
        }
    } elseif ($method === 'DELETE') {
        // --- Admin deleting a user ---
        if ($auth_user_role !== 'admin') {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Forbidden']);
            exit();
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (!$data) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Invalid input.']);
            exit();
        }

        $user_id = intval($data['user_id'] ?? 0);
        if (!$user_id) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'User ID is required.']);
            exit();
        }

        // --- New check: Prevent non-super-admins from deleting other admins or super admins ---
        $stmt_check = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt_check->bind_param("i", $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows === 1) {
            $target_user = $result_check->fetch_assoc();
            if (($target_user['role'] === 'admin')
                && $user_id !== $auth_user_id
            ) {
                http_response_code(403);
                echo json_encode(['status' => false, 'message' => 'You are not allowed to delete another admin.']);
                exit();
            }
        }
        $stmt_check->close();

        $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
        $stmt->bind_param('i', $user_id);
        if ($stmt->execute()) {
            $stmt->close();
            recordAuditLog($auth_user_id, 'Admin User Deletion', "Admin deleted user with ID {$user_id}.");
            echo json_encode(['status' => true, 'message' => 'User deleted successfully.']);
        } else {
            error_log('DB delete error: ' . $stmt->error);
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Error deleting user.']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Method not allowed.']);
    }
} catch (Exception $e) {
    error_log('Unhandled exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Internal server error.']);
}

$conn->close();
exit();

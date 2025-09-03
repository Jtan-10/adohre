<?php
// Production best practices: disable error display and log errors instead.
// (Ensure in your php.ini, display_errors is Off.)
ini_set('display_errors', '0');  // Do not display errors to users
ini_set('log_errors', '1');      // Enable error logging
error_reporting(E_ALL);          // Report all errors (they will be logged, not displayed)

// Include database connection.
require_once __DIR__ . '/../db/db_connect.php';

// Configure session security based on environment
configureSessionSecurity();
session_start();

// Set Content-Type to JSON.
header('Content-Type: application/json');

// Only allow access to authenticated admin users.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Access denied.']);
    exit;
}

// Include S3 configuration and AWS SDK initialization.
require_once __DIR__ . '/../s3config.php';

// -----------------------------
// embedDataInPng helper function
// -----------------------------
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
    function embedDataInPng($binaryData, $desiredWidth = 100)
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

// Sanitize the "action" parameter.
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? '';

switch ($action) {

    case 'update_header_settings':
        $headerName = trim($_POST['header_name'] ?? '');
        $message = "";
        $headerLogoUrl = null;

        // Update header name if provided.
        if (!empty($headerName)) {
            $stmt = $conn->prepare("
                INSERT INTO settings (`key`, value) 
                VALUES ('header_name', ?) 
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            if ($stmt) {
                $stmt->bind_param("s", $headerName);
                if ($stmt->execute()) {
                    $message .= "Header name updated. ";
                    $_SESSION['header_name'] = $headerName;
                } else {
                    error_log("Failed to update header name: " . $stmt->error);
                    $message .= "Failed to update header name. ";
                }
                $stmt->close();
            }
        }

        if (isset($_FILES['header_logo']) && $_FILES['header_logo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = mime_content_type($_FILES['header_logo']['tmp_name']);

            if (!in_array($fileType, $allowedTypes)) {
                $message .= "Invalid logo file type. ";
            } else {
                // ----------------------------
                // Delete previous header logo from S3 if it exists.
                // ----------------------------
                $stmtExisting = $conn->prepare("SELECT value FROM settings WHERE `key` = 'header_logo'");
                if ($stmtExisting) {
                    $stmtExisting->execute();
                    $resultExisting = $stmtExisting->get_result();
                    if ($resultExisting->num_rows > 0) {
                        $rowExisting = $resultExisting->fetch_assoc();
                        $oldLogoUrl = $rowExisting['value'];
                        if (!empty($oldLogoUrl) && strpos($oldLogoUrl, '/s3proxy/') === 0) {
                            $existingS3Key = urldecode(str_replace('/s3proxy/', '', $oldLogoUrl));
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
                    $stmtExisting->close();
                }

                $ext = pathinfo($_FILES['header_logo']['name'], PATHINFO_EXTENSION);
                $s3Key = 'uploads/settings/header_logo_' . time() . '.png';

                // Encrypt and embed the image into a PNG.
                $clearImageData = file_get_contents($_FILES['header_logo']['tmp_name']);

                // Encryption setup.
                $cipher = "AES-256-CBC";
                $ivlen = openssl_cipher_iv_length($cipher);
                $iv = openssl_random_pseudo_bytes($ivlen);
                $rawKey = getenv('ENCRYPTION_KEY'); // Same method as your existing code.
                $encryptionKey = hash('sha256', $rawKey, true);

                // Encrypt the image.
                $encryptedData = openssl_encrypt($clearImageData, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
                $encryptedImageData = $iv . $encryptedData;

                // Use your existing embedDataInPng function (assumed to be available via an include or require).
                $pngImage = embedDataInPng($encryptedImageData, 100);
                $encryptedTempPath = tempnam(sys_get_temp_dir(), 'enc_logo_') . '.png';
                imagepng($pngImage, $encryptedTempPath);
                imagedestroy($pngImage);

                try {
                    $result = $s3->putObject([
                        'Bucket' => $bucketName,
                        'Key' => $s3Key,
                        'Body' => fopen($encryptedTempPath, 'rb'),
                        'ACL' => 'public-read',
                        'ContentType' => 'image/png'
                    ]);
                    @unlink($encryptedTempPath);

                    $headerLogoUrl = str_replace(
                        "https://{$bucketName}.s3." . $_ENV['AWS_REGION'] . ".amazonaws.com/",
                        "/s3proxy/",
                        $result['ObjectURL']
                    );

                    $stmt = $conn->prepare("
                        INSERT INTO settings (`key`, value) 
                        VALUES ('header_logo', ?) 
                        ON DUPLICATE KEY UPDATE value = VALUES(value)
                    ");
                    if ($stmt) {
                        $stmt->bind_param("s", $headerLogoUrl);
                        if ($stmt->execute()) {
                            $message .= "Header logo updated. ";
                            $_SESSION['header_logo'] = $headerLogoUrl;
                        } else {
                            error_log("Failed to update header logo: " . $stmt->error);
                            $message .= "Failed to update header logo. ";
                        }
                        $stmt->close();
                    }
                } catch (Exception $e) {
                    error_log("S3 upload error: " . $e->getMessage());
                    $message .= "S3 upload error. ";
                }
            }
        }

        recordAuditLog($_SESSION['user_id'], 'Update Header Settings', $message);

        echo json_encode([
            'status' => true,
            'message' => $message,
            'header_name' => $headerName,
            'header_logo' => $headerLogoUrl
        ]);
        break;

    case 'update_security_settings':
        // Get the face validation setting
        $faceValidation = $_POST['face_validation'] ?? '0';
        $faceValidation = filter_var($faceValidation, FILTER_VALIDATE_INT) ? $faceValidation : '0';

        try {
            // Update or insert the face_validation setting
            $stmt = $conn->prepare("INSERT INTO settings (`key`, value) VALUES ('face_validation', ?) 
                                  ON DUPLICATE KEY UPDATE value = ?");
            $stmt->bind_param('ss', $faceValidation, $faceValidation);
            $success = $stmt->execute();
            $stmt->close();

            if (!$success) {
                throw new Exception("Failed to update face validation setting: " . $conn->error);
            }

            $message = "Security settings updated successfully.";

            recordAuditLog(
                $_SESSION['user_id'],
                'Update Security Settings',
                "Face validation setting updated to: " . ($faceValidation == '1' ? 'Enabled' : 'Disabled')
            );

            echo json_encode([
                'status' => true,
                'message' => $message
            ]);
        } catch (Exception $e) {
            error_log("Error updating security settings: " . $e->getMessage());
            echo json_encode([
                'status' => false,
                'message' => "Failed to update security settings: " . $e->getMessage()
            ]);
        }
        break;

    case 'backup_database':
        // Remove any previously set Content-Type header so we can output file data.
        header_remove('Content-Type');

        // Use credentials from db_connect.php.
        $dbHost = $servername;
        $dbUser = $username;
        $dbPass = $password;
        $dbName = $dbname;

        // Use system's temporary directory for backups
        $backupDir = sys_get_temp_dir() . '/capstone_backups/';
        if (!is_dir($backupDir)) {
            if (!mkdir($backupDir, 0755, true)) {
                $error = error_get_last();
                error_log("Failed to create backup directory: " . print_r($error, true));
                header('Content-Type: text/plain');
                echo "Failed to create backup directory. Check permissions on: $backupDir";
                exit;
            }
        }

        // Generate a backup file name.
        $backupFile = $backupDir . "backup_" . date('Ymd_His') . ".sql";

        // Determine the mysqldump command path.
        $mysqldumpPath = '/opt/bitnami/mysql/bin/mysqldump';
        if (!file_exists($mysqldumpPath)) {
            $mysqldumpPath = '/opt/lampp/bin/mysqldump';
            if (!file_exists($mysqldumpPath)) {
                // Fallback to system command; ensure that mysqldump is in the server's PATH.
                $mysqldumpPath = 'mysqldump';
            }
        }

        // Build the command using proper escaping.
        $command = $mysqldumpPath .
            " --host=" . escapeshellarg($dbHost) .
            " --user=" . escapeshellarg($dbUser) .
            " --password=" . escapeshellarg($dbPass) .
            " " . escapeshellarg($dbName) .
            " > " . escapeshellarg($backupFile);
        error_log("Starting database backup. Command: $command");

        // Execute the command.
        exec($command, $output, $returnVar);

        if ($returnVar === 0 && file_exists($backupFile)) {
            // Generate a secure random encryption password
            $encryptionPassword = bin2hex(random_bytes(16)); // 32 character hex string

            // Encrypt the backup file
            $encryptedBackupFile = $backupFile . '.enc';
            $encryptCmd = "openssl enc -aes-256-cbc -salt -in " . escapeshellarg($backupFile) .
                " -out " . escapeshellarg($encryptedBackupFile) .
                " -pass pass:" . escapeshellarg($encryptionPassword);
            exec($encryptCmd, $encryptOutput, $encryptReturnVar);

            // Remove the original unencrypted backup
            unlink($backupFile);

            if ($encryptReturnVar === 0 && file_exists($encryptedBackupFile)) {
                // Store the encryption password in the session for retrieval
                $_SESSION['last_backup_encryption_password'] = $encryptionPassword;

                // Send the encrypted backup file
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="backup_' . date('Ymd_His') . '.sql.enc"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($encryptedBackupFile));
                flush();
                readfile($encryptedBackupFile);

                // Remove the encrypted backup file
                unlink($encryptedBackupFile);
                exit();
            } else {
                header('Content-Type: text/plain');
                http_response_code(500);
                echo "Database backup encryption failed.";
                exit();
            }
        } else {
            header('Content-Type: text/plain');
            http_response_code(500);
            echo "Database backup failed.";
            exit();
        }
        break;

    case 'get_backup_password':
        // Retrieve the backup encryption password from the session
        if (isset($_SESSION['last_backup_encryption_password'])) {
            $password = $_SESSION['last_backup_encryption_password'];

            // Clear the password after retrieval (one-time use)
            unset($_SESSION['last_backup_encryption_password']);

            // Set content type back to JSON
            header('Content-Type: application/json');

            echo json_encode([
                'status' => true,
                'encryption_password' => $password
            ]);
        } else {
            // Set content type back to JSON
            header('Content-Type: application/json');

            echo json_encode([
                'status' => false,
                'message' => 'No recent backup password found.'
            ]);
        }
        break;
    case 'get_page_content':
        $page = $_GET['page'] ?? '';
        $allowedKeys = [];
        if ($page === 'home') {
            $allowedKeys = [
                'home_hero_title',
                'home_hero_subtitle',
                'home_about_html',
                'home_contact_address'
            ];
        } elseif ($page === 'about') {
            $allowedKeys = [
                'about_hero_title',
                'about_hero_subtitle',
                'about_purpose_text',
                'about_mission_text',
                'about_vision_text',
                'about_objectives_html'
            ];
        } else {
            echo json_encode(['status' => false, 'message' => 'Unknown page']);
            break;
        }
        if (empty($allowedKeys)) {
            echo json_encode(['status' => true, 'data' => []]);
            break;
        }
        $placeholders = rtrim(str_repeat('?,', count($allowedKeys)), ',');
        $types = str_repeat('s', count($allowedKeys));
        $stmt = $conn->prepare("SELECT `key`, value FROM settings WHERE `key` IN ($placeholders)");
        if (!$stmt) {
            echo json_encode(['status' => false, 'message' => 'DB error']);
            break;
        }
        $stmt->bind_param($types, ...$allowedKeys);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = array_fill_keys($allowedKeys, null);
        while ($row = $res->fetch_assoc()) {
            $data[$row['key']] = $row['value'];
        }
        $stmt->close();
        echo json_encode(['status' => true, 'data' => $data]);
        break;

    case 'update_page_content':
        // Only admins (already enforced at top)
        $page = $_POST['page'] ?? '';
        $dataRaw = $_POST['data'] ?? '';
        $payload = json_decode($dataRaw, true);
        if (!is_array($payload)) {
            echo json_encode(['status' => false, 'message' => 'Invalid data']);
            break;
        }
        $allowed = [];
        if ($page === 'home') {
            $allowed = ['home_hero_title', 'home_hero_subtitle', 'home_about_html', 'home_contact_address'];
        } elseif ($page === 'about') {
            $allowed = ['about_hero_title', 'about_hero_subtitle', 'about_purpose_text', 'about_mission_text', 'about_vision_text', 'about_objectives_html'];
        } else {
            echo json_encode(['status' => false, 'message' => 'Unknown page']);
            break;
        }
        // Upsert each allowed key present in payload
        $stmt = $conn->prepare("INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        if (!$stmt) {
            echo json_encode(['status' => false, 'message' => 'DB error']);
            break;
        }
        foreach ($payload as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            $stmt->bind_param('ss', $k, $v);
            $stmt->execute();
        }
        $stmt->close();
        recordAuditLog($_SESSION['user_id'], 'Update Page Content', 'Updated page: ' . $page);
        echo json_encode(['status' => true, 'message' => 'Saved']);
        break;

    case 'get_membership_form_schema':
        // Try to get schema from settings; if none, return defaults
        $schema = null;
        $stmt = $conn->prepare("SELECT value FROM settings WHERE `key`='membership_form_schema' LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $stmt->bind_result($val);
            if ($stmt->fetch()) {
                $schema = json_decode($val, true);
            }
            $stmt->close();
        }
        if (!$schema) {
            $schema = [
                'section1' => [
                    'title' => '1. Personal Information',
                    'fields' => [
                        'name' => ['label' => 'Name (Family Name, Given Name, Middle Name)', 'required' => true, 'type' => 'text', 'maxlength' => 255],
                        'dob' => ['label' => 'Date of Birth', 'required' => true, 'type' => 'date'],
                        'sex' => ['label' => 'Sex', 'required' => true, 'type' => 'select', 'options' => ['Male', 'Female']],
                        'current_address' => ['label' => 'Current Address', 'required' => true],
                        'permanent_address' => ['label' => 'Permanent Address', 'required' => false],
                        'email' => ['label' => 'Email Address', 'required' => true, 'type' => 'email'],
                        'landline' => ['label' => 'Landline #', 'required' => false],
                        'mobile' => ['label' => 'Mobile Phone #', 'required' => true],
                        'place_of_birth' => ['label' => 'Place of Birth', 'required' => true],
                        'marital_status' => ['label' => 'Marital Status', 'required' => false],
                        'emergency_contact' => ['label' => 'Emergency Contact', 'required' => true, 'placeholder' => 'Full Name, Relationship, Phone, Email']
                    ]
                ],
                'section2' => [
                    'title' => '2. Employment Record with DOH',
                    'fields' => [
                        'doh_agency' => ['label' => 'DOH Agency', 'required' => true],
                        'address' => ['label' => 'Address', 'required' => false],
                        'employment_start' => ['label' => 'Start Date', 'required' => true, 'type' => 'month'],
                        'employment_end' => ['label' => 'End Date', 'required' => false, 'type' => 'month']
                    ]
                ],
                'section3' => [
                    'title' => '3. Highest Educational Background',
                    'fields' => [
                        'school' => ['label' => 'School', 'required' => true],
                        'degree' => ['label' => 'Degree/Course Attained', 'required' => true],
                        'year_graduated' => ['label' => 'Year Graduated', 'required' => true, 'type' => 'number', 'min' => 1900, 'max' => 2100]
                    ]
                ],
                'section4' => [
                    'title' => '4. Current Engagement',
                    'group' => [
                        'name' => 'current_engagement',
                        'type' => 'radio',
                        'options' => ['None', 'Working Full-time', 'Working Part-time', 'Civic Activities'],
                        'includeOthers' => true,
                        'othersLabel' => 'Others (Specify):'
                    ]
                ],
                'section5' => [
                    'title' => '5. Key Expertise',
                    'groups' => [
                        [
                            'name' => 'key_expertise',
                            'label' => 'Key Expertise',
                            'type' => 'radio',
                            'required' => true,
                            'options' => ['Research', 'Training/Teaching/Facilitation', 'Monitoring & Evaluation', 'Statistics', 'Finance Management', 'Procurement & Supply Chain', 'HR/Personnel Development', 'Policy Development', 'Planning', 'Project Management', 'Project Proposal Development', 'Digital Health', 'Administration'],
                            'includeOthers' => true,
                            'othersLabel' => 'Others (Specify):'
                        ],
                        [
                            'name' => 'specific_field',
                            'label' => 'Indicate specific field:',
                            'type' => 'radio',
                            'required' => true,
                            'options' => ['Clinical Care', 'Public Health', 'Health Regulation', 'Health System'],
                            'includeOthers' => true,
                            'othersLabel' => 'Others (Specify):'
                        ]
                    ]
                ],
                'section6' => [
                    'title' => '6. Other Skills',
                    'fields' => [
                        'special_skills' => ['label' => 'a. Special Skills', 'required' => false, 'placeholder' => 'Enter your special skills'],
                        'hobbies' => ['label' => 'b. Hobbies', 'required' => false, 'placeholder' => 'Enter your hobbies']
                    ]
                ],
                'section7' => [
                    'title' => '7. Committees',
                    'group' => [
                        'name' => 'committees',
                        'type' => 'radio',
                        'required' => true,
                        'options' => ['Membership & Training Committee', 'Advocacy Committee', 'Finance Committee', 'Project Committee', 'Ad Hoc Committee'],
                        'includeOthers' => true,
                        'othersLabel' => 'Others (Specify):'
                    ]
                ]
            ];
        }
        echo json_encode(['status' => true, 'schema' => $schema]);
        break;

    case 'update_membership_form_schema':
        $dataRaw = $_POST['schema'] ?? '';
        $schema = json_decode($dataRaw, true);
        if (!is_array($schema)) {
            echo json_encode(['status' => false, 'message' => 'Invalid schema']);
            break;
        }
        $json = json_encode($schema, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("INSERT INTO settings (`key`, value) VALUES ('membership_form_schema', ?) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        if (!$stmt) {
            echo json_encode(['status' => false, 'message' => 'DB error']);
            break;
        }
        $stmt->bind_param('s', $json);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            recordAuditLog($_SESSION['user_id'], 'Update Membership Form Schema');
            echo json_encode(['status' => true, 'message' => 'Schema saved']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to save']);
        }
        break;

    case 'restore_database':
        if (isset($_FILES['restore_file']) && $_FILES['restore_file']['error'] === UPLOAD_ERR_OK) {
            // Get the encryption password
            $encryptionPassword = $_POST['encryption_password'] ?? null;
            if (!$encryptionPassword) {
                echo json_encode([
                    'status'  => false,
                    'message' => "Encryption password is required."
                ]);
                exit;
            }

            $dbHost = $servername;
            $dbUser = $username;
            $dbPass = $password;
            $dbName = $dbname;

            // Move the uploaded encrypted file to a temporary location
            $tempEncryptedRestore = tempnam(sys_get_temp_dir(), 'restore_') . '.sql.enc';
            if (!move_uploaded_file($_FILES['restore_file']['tmp_name'], $tempEncryptedRestore)) {
                error_log("Failed to move uploaded encrypted restore file.");
                echo json_encode([
                    'status'  => false,
                    'message' => "Failed to move uploaded restore file."
                ]);
                exit;
            }

            // Decrypt the file
            $tempRestore = tempnam(sys_get_temp_dir(), 'restore_') . '.sql';
            $decryptCmd = "openssl enc -aes-256-cbc -d -salt -in " . escapeshellarg($tempEncryptedRestore) .
                " -out " . escapeshellarg($tempRestore) .
                " -pass pass:" . escapeshellarg($encryptionPassword);

            exec($decryptCmd, $decryptOutput, $decryptReturnVar);

            // Remove the encrypted file
            unlink($tempEncryptedRestore);

            if ($decryptReturnVar !== 0 || !file_exists($tempRestore)) {
                error_log("Decryption failed");
                echo json_encode([
                    'status'  => false,
                    'message' => "Decryption failed. Check your encryption password."
                ]);
                exit;
            }

            error_log("Starting database restore from decrypted file: $tempRestore");

            // Escape shell arguments
            $dbHostEscaped      = escapeshellarg($dbHost);
            $dbUserEscaped      = escapeshellarg($dbUser);
            $dbPassEscaped      = escapeshellarg($dbPass);
            $dbNameEscaped      = escapeshellarg($dbName);
            $tempRestoreEscaped = escapeshellarg($tempRestore);

            // Use the full path to the mysql client
            $mysqlPath = '/opt/bitnami/mariadb/bin/mysql';
            $command = "sh -c '" . $mysqlPath . " --host={$dbHostEscaped} --user={$dbUserEscaped} --password={$dbPassEscaped} {$dbNameEscaped} < {$tempRestoreEscaped}'";
            error_log("Restore command: $command");

            $output = [];
            exec($command, $output, $returnVar);

            // Remove the decrypted temporary file
            unlink($tempRestore);

            if ($returnVar === 0) {
                error_log("Database restore successful.");
                // Record the restore action using the audit log helper
                recordAuditLog($_SESSION['user_id'], 'Database Restore', 'Encrypted database restored from uploaded file');

                echo json_encode([
                    'status'  => true,
                    'message' => "Database restore successful."
                ]);
            } else {
                $errorMessage = implode("\n", $output);
                error_log("Database restore failed: " . $errorMessage);
                echo json_encode([
                    'status'  => false,
                    'message' => "Database restore failed."
                ]);
            }
        } else {
            error_log("No restore file uploaded.");
            echo json_encode([
                'status'  => false,
                'message' => "No restore file uploaded."
            ]);
        }
        break;

    case 'get_audit_logs':
        $auditLogs = [];
        $result = $conn->query("
            SELECT al.*, u.first_name, u.last_name 
            FROM audit_logs al 
            LEFT JOIN users u ON al.user_id = u.user_id 
            ORDER BY al.created_at DESC 
            LIMIT 100
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $auditLogs[] = $row;
            }
            echo json_encode(['status' => true, 'audit_logs' => $auditLogs]);
        } else {
            error_log("Failed to fetch audit logs: " . $conn->error);
            echo json_encode([
                'status'  => false,
                'message' => "Failed to fetch audit logs."
            ]);
        }
        break;

    default:
        error_log("Invalid action requested: " . $action);
        echo json_encode([
            'status'  => false,
            'message' => "Invalid action."
        ]);
        break;
}

// Close the database connection.
$conn->close();

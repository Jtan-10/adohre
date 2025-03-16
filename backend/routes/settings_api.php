<?php
// backend/routes/settings_api.php

// Secure session settings (adjust as needed).
session_set_cookie_params([
    'lifetime' => 0,
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();
header('Content-Type: application/json');

// Only allow admin access.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Access denied.']);
    exit;
}

// Include database connection.
require_once __DIR__ . '/../db/db_connect.php';

// Include s3config.php for S3 uploads.
require_once __DIR__ . '/../s3config.php';

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'update_header_settings':
        $headerName     = $_POST['header_name'] ?? '';
        $message        = "";
        $headerLogoUrl  = null;  // Will hold the new S3 URL if we upload a logo.

        // 1) Update header name in the 'settings' table.
        if (!empty($headerName)) {
            $stmt = $conn->prepare("
                INSERT INTO settings (`key`, value) 
                VALUES ('header_name', ?) 
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");
            $stmt->bind_param("s", $headerName);
            if ($stmt->execute()) {
                $message .= "Header name updated. ";
                // Update session so your header.php or admin_header.php can show it immediately.
                $_SESSION['header_name'] = $headerName;
            } else {
                $message .= "Failed to update header name. ";
            }
            $stmt->close();
        }

        // 2) Process header logo upload if provided.
        if (isset($_FILES['header_logo']) && $_FILES['header_logo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType     = mime_content_type($_FILES['header_logo']['tmp_name']);

            if (!in_array($fileType, $allowedTypes)) {
                $message .= "Invalid logo file type. ";
            } else {
                // Generate a unique S3 object key for the uploaded file.
                $ext   = pathinfo($_FILES['header_logo']['name'], PATHINFO_EXTENSION);
                $s3Key = 'uploads/settings/header_logo_' . time() . '.' . $ext;

                try {
                    // Upload to S3 with public-read so the logo is accessible.
                    $result = $s3->putObject([
                        'Bucket'      => $bucketName,
                        'Key'         => $s3Key,
                        'Body'        => fopen($_FILES['header_logo']['tmp_name'], 'rb'),
                        'ACL'         => 'public-read',
                        'ContentType' => $fileType
                    ]);

                    // Convert the full S3 URL to your local /s3proxy/ path (if desired).
                    // Or store the actual S3 URL directly if you prefer.
                    $headerLogoUrl = str_replace(
                        "https://{$bucketName}.s3." . $_ENV['AWS_REGION'] . ".amazonaws.com/",
                        "/s3proxy/",
                        $result['ObjectURL']
                    );

                    // Save the new logo path to the 'settings' table.
                    $stmt = $conn->prepare("
                        INSERT INTO settings (`key`, value) 
                        VALUES ('header_logo', ?) 
                        ON DUPLICATE KEY UPDATE value = VALUES(value)
                    ");
                    $stmt->bind_param("s", $headerLogoUrl);
                    if ($stmt->execute()) {
                        $message .= "Header logo updated. ";
                        // Update session so the new logo is used immediately.
                        $_SESSION['header_logo'] = $headerLogoUrl;
                    } else {
                        $message .= "Failed to update header logo. ";
                    }
                    $stmt->close();

                } catch (Exception $e) {
                    $message .= "S3 upload error: " . $e->getMessage();
                }
            }
        }

        // 3) Log the update to audit_logs.
        $auditStmt = $conn->prepare("
            INSERT INTO audit_logs (user_id, action, details) 
            VALUES (?, 'Update Header Settings', ?)
        ");
        $userId = $_SESSION['user_id'];
        $auditStmt->bind_param("is", $userId, $message);
        $auditStmt->execute();
        $auditStmt->close();

        // 4) Return the updated values in JSON so the front end can refresh the UI without reloading.
        echo json_encode([
            'status'      => true,
            'message'     => $message,
            'header_name' => $headerName,
            'header_logo' => $headerLogoUrl
        ]);
        break;

    case 'backup_database':
        // Pull credentials from db_connect.php (assuming $servername, $username, $password, $dbname).
        $dbHost = $servername;
        $dbUser = $username;
        $dbPass = $password;
        $dbName = $dbname;

        $backupDir  = __DIR__ . '/../../backups/';
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $backupFile = $backupDir . "backup_" . date('Ymd_His') . ".sql";

        // Run mysqldump (ensure mysqldump is in your PATH).
        $command = "mysqldump --host={$dbHost} --user={$dbUser} --password={$dbPass} {$dbName} > "
                 . escapeshellarg($backupFile);
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            // Upload backup file to S3.
            $s3Key = 'backups/' . basename($backupFile);
            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => fopen($backupFile, 'rb'),
                    'ACL'         => 'private', // or 'public-read'
                    'ContentType' => 'application/sql'
                ]);
                // Remove local backup file after successful upload.
                unlink($backupFile);

                $s3BackupUrl   = $result['ObjectURL'];
                $backupMessage = "Backup created on S3: " . $s3BackupUrl;

                // Log backup action.
                $auditStmt = $conn->prepare("
                    INSERT INTO audit_logs (user_id, action, details) 
                    VALUES (?, 'Database Backup', ?)
                ");
                $userId = $_SESSION['user_id'];
                $auditStmt->bind_param("is", $userId, $backupMessage);
                $auditStmt->execute();
                $auditStmt->close();

                echo json_encode(['status' => true, 'message' => $backupMessage]);
            } catch (Exception $e) {
                echo json_encode([
                    'status'  => false,
                    'message' => "Backup upload failed: " . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                'status'  => false,
                'message' => "Database backup failed."
            ]);
        }
        break;

    case 'restore_database':
        if (isset($_FILES['restore_file']) && $_FILES['restore_file']['error'] === UPLOAD_ERR_OK) {
            // Use credentials from db_connect.php.
            $dbHost = $servername;
            $dbUser = $username;
            $dbPass = $password;
            $dbName = $dbname;

            // Move the uploaded file to a temp path for restore.
            $tempRestore = tempnam(sys_get_temp_dir(), 'restore_') . '.sql';
            if (!move_uploaded_file($_FILES['restore_file']['tmp_name'], $tempRestore)) {
                echo json_encode([
                    'status'  => false,
                    'message' => "Failed to move uploaded restore file."
                ]);
                exit;
            }

            // Use mysql command to restore.
            $command = "mysql --host={$dbHost} --user={$dbUser} --password={$dbPass} {$dbName} < "
                     . escapeshellarg($tempRestore);
            exec($command, $output, $returnVar);

            // Remove the temp file.
            unlink($tempRestore);

            if ($returnVar === 0) {
                // Log restore action.
                $auditStmt = $conn->prepare("
                    INSERT INTO audit_logs (user_id, action, details) 
                    VALUES (?, 'Database Restore', 'Database restored from uploaded file')
                ");
                $userId = $_SESSION['user_id'];
                $auditStmt->bind_param("i", $userId);
                $auditStmt->execute();
                $auditStmt->close();

                echo json_encode([
                    'status'  => true,
                    'message' => "Database restore successful."
                ]);
            } else {
                echo json_encode([
                    'status'  => false,
                    'message' => "Database restore failed."
                ]);
            }
        } else {
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
            echo json_encode([
                'status'  => false,
                'message' => "Failed to fetch audit logs."
            ]);
        }
        break;

    default:
        echo json_encode([
            'status'  => false,
            'message' => "Invalid action."
        ]);
        break;
}

// Close the database connection.
$conn->close();
<?php
// Production best practices: disable error display and log errors instead.
// (Ensure in your php.ini, display_errors is Off.)
ini_set('display_errors', '0');  // Do not display errors to users
ini_set('log_errors', '1');      // Enable error logging
error_reporting(E_ALL);          // Report all errors (they will be logged, not displayed)

// Set secure session cookie parameters.
session_set_cookie_params([
    'lifetime' => 0,
    'secure'   => true,   // Ensure HTTPS is used.
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Set Content-Type to JSON.
header('Content-Type: application/json');

// Only allow access to authenticated admin users.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Access denied.']);
    exit;
}

// Include database connection.
require_once __DIR__ . '/../db/db_connect.php';

// Include S3 configuration and AWS SDK initialization.
require_once __DIR__ . '/../s3config.php';

// Sanitize the "action" parameter.
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? '';

switch ($action) {

    case 'update_header_settings':
        $headerName    = trim($_POST['header_name'] ?? '');
        $message       = "";
        $headerLogoUrl = null;  // Will store the new S3 URL if a logo is uploaded.

        // 1) Update header name if provided.
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
            } else {
                error_log("Prepare failed for header name update: " . $conn->error);
            }
        }

        // 2) Process header logo upload if provided.
        if (isset($_FILES['header_logo']) && $_FILES['header_logo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType     = mime_content_type($_FILES['header_logo']['tmp_name']);

            if (!in_array($fileType, $allowedTypes)) {
                $message .= "Invalid logo file type. ";
                error_log("Invalid logo file type: " . $fileType);
            } else {
                // Generate a unique S3 key.
                $ext   = pathinfo($_FILES['header_logo']['name'], PATHINFO_EXTENSION);
                $s3Key = 'uploads/settings/header_logo_' . time() . '.' . $ext;

                try {
                    // Upload the file to S3 with public-read ACL.
                    $result = $s3->putObject([
                        'Bucket'      => $bucketName,
                        'Key'         => $s3Key,
                        'Body'        => fopen($_FILES['header_logo']['tmp_name'], 'rb'),
                        'ACL'         => 'public-read',
                        'ContentType' => $fileType
                    ]);

                    // Convert the full S3 URL to a local proxy URL if desired.
                    $headerLogoUrl = str_replace(
                        "https://{$bucketName}.s3." . $_ENV['AWS_REGION'] . ".amazonaws.com/",
                        "/s3proxy/",
                        $result['ObjectURL']
                    );

                    // Save the logo URL in the database.
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
                            error_log("Failed to update header logo in DB: " . $stmt->error);
                            $message .= "Failed to update header logo. ";
                        }
                        $stmt->close();
                    } else {
                        error_log("Prepare failed for header logo update: " . $conn->error);
                    }
                } catch (Exception $e) {
                    error_log("S3 upload error: " . $e->getMessage());
                    $message .= "S3 upload error. ";
                }
            }
        }

        // 3) Record the action using the audit log helper function.
        recordAuditLog($_SESSION['user_id'], 'Update Header Settings', $message);

        echo json_encode([
            'status'      => true,
            'message'     => $message,
            'header_name' => $headerName,
            'header_logo' => $headerLogoUrl
        ]);
        break;

    case 'backup_database':
        // Use credentials from db_connect.php.
        $dbHost = $servername;
        $dbUser = $username;
        $dbPass = $password;
        $dbName = $dbname;

        // Define backup directory (ensure it is outside web root if possible).
        $backupDir = __DIR__ . '/../../backups/';
        if (!file_exists($backupDir) && !mkdir($backupDir, 0755, true)) {
            $error = error_get_last();
            error_log("Failed to create backup directory: " . print_r($error, true));
            echo json_encode([
                'status'  => false,
                'message' => "Failed to create backup directory."
            ]);
            exit;
        }

        $backupFile = $backupDir . "backup_" . date('Ymd_His') . ".sql";

        // Escape shell arguments.
        $dbHostEscaped = escapeshellarg($dbHost);
        $dbUserEscaped = escapeshellarg($dbUser);
        $dbNameEscaped = escapeshellarg($dbName);
        $backupFileEscaped = escapeshellarg($backupFile);

        // Full path to mysqldump.
        $mysqldumpPath = '/opt/lampp/bin/mysqldump';
        $command = "$mysqldumpPath --host={$dbHostEscaped} --user={$dbUserEscaped} --password={$dbPass} {$dbNameEscaped} > $backupFileEscaped";
        error_log("Starting database backup. Command: $command");

        $output = [];
        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $s3Key = 'backups/' . basename($backupFile);
            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => fopen($backupFile, 'rb'),
                    'ACL'         => 'private',
                    'ContentType' => 'application/sql'
                ]);
                // Remove the local backup file after upload.
                unlink($backupFile);

                $s3BackupUrl   = $result['ObjectURL'];
                $backupMessage = "Backup created on S3.";
                error_log("Database backup successful. S3 URL: " . $s3BackupUrl);

                // Record the backup action using the audit log helper.
                recordAuditLog($_SESSION['user_id'], 'Database Backup', $backupMessage);

                echo json_encode(['status' => true, 'message' => $backupMessage]);
            } catch (Exception $e) {
                error_log("Backup upload failed: " . $e->getMessage());
                echo json_encode([
                    'status'  => false,
                    'message' => "Backup upload failed."
                ]);
            }
        } else {
            $errorMessage = implode("\n", $output);
            error_log("Database backup command failed: " . $errorMessage);
            echo json_encode([
                'status'  => false,
                'message' => "Database backup failed."
            ]);
        }
        break;

    case 'restore_database':
        if (isset($_FILES['restore_file']) && $_FILES['restore_file']['error'] === UPLOAD_ERR_OK) {
            $dbHost = $servername;
            $dbUser = $username;
            $dbPass = $password;
            $dbName = $dbname;

            // Move the uploaded file to a temporary location.
            $tempRestore = tempnam(sys_get_temp_dir(), 'restore_') . '.sql';
            if (!move_uploaded_file($_FILES['restore_file']['tmp_name'], $tempRestore)) {
                error_log("Failed to move uploaded restore file.");
                echo json_encode([
                    'status'  => false,
                    'message' => "Failed to move uploaded restore file."
                ]);
                exit;
            }

            error_log("Starting database restore from file: $tempRestore");

            // Escape shell arguments.
            $dbHostEscaped      = escapeshellarg($dbHost);
            $dbUserEscaped      = escapeshellarg($dbUser);
            $dbNameEscaped      = escapeshellarg($dbName);
            $tempRestoreEscaped = escapeshellarg($tempRestore);

            // Use the full path to the mysql client.
            $mysqlPath = '/opt/lampp/bin/mysql';
            $command = "$mysqlPath --host={$dbHostEscaped} --user={$dbUserEscaped} --password={$dbPass} {$dbNameEscaped} < $tempRestoreEscaped";
            error_log("Restore command: $command");

            $output = [];
            exec($command, $output, $returnVar);
            unlink($tempRestore); // Remove the temporary file.

            if ($returnVar === 0) {
                error_log("Database restore successful.");
                // Record the restore action using the audit log helper.
                recordAuditLog($_SESSION['user_id'], 'Database Restore', 'Database restored from uploaded file');

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
?>
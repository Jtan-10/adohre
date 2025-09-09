<?php
require_once '../db/db_connect.php';
require_once '../s3config.php';
header('Content-Type: application/json');
session_start();
$method = $_SERVER['REQUEST_METHOD'];

// Helper: check if a column exists in current DB schema
if (!function_exists('ma_hasColumn')) {
    function ma_hasColumn(mysqli $conn, string $table, string $column): bool
    {
        $dbRes = $conn->query('SELECT DATABASE() AS db');
        if (!$dbRes) return false;
        $dbRow = $dbRes->fetch_assoc();
        $db = $dbRow ? $dbRow['db'] : null;
        if (!$db) return false;
        $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        if (!$stmt) return false;
        $stmt->bind_param('sss', $db, $table, $column);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row && intval($row['cnt']) > 0;
    }
}

try {
    switch ($method) {
        case 'GET':
            // Fetch applications
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            $q = isset($_GET['q']) ? trim($_GET['q']) : '';

            // Face image support removed; do not join/select it
            $sql = "SELECT m.* FROM membership_applications m LEFT JOIN users u ON m.user_id = u.user_id";

            $whereParts = [];
            if ($id) {
                $whereParts[] = "m.application_id = " . intval($id);
            }
            if ($status !== null && $status !== '') {
                // Whitelist statuses per schema
                $allowedStatuses = ['Pending', 'Reviewed', 'Approved', 'Rejected'];
                if (!in_array($status, $allowedStatuses, true)) {
                    http_response_code(400);
                    echo json_encode(['status' => false, 'message' => 'Invalid status filter']);
                    break;
                }
                $whereParts[] = "m.status = '" . $conn->real_escape_string($status) . "'";
            }
            $params = [];
            $types = '';

            // Text search across selected columns if q present
            if ($q !== '') {
                // Limit length to prevent abuse
                if (strlen($q) > 100) {
                    $q = substr($q, 0, 100);
                }
                $searchable = [
                    'm.name',
                    'm.email',
                    'm.current_address',
                    'm.permanent_address',
                    'm.mobile',
                    'm.place_of_birth',
                    'm.marital_status',
                    'm.emergency_contact',
                    'm.doh_agency',
                    'm.doh_address',
                    'm.school',
                    'm.degree',
                    'm.current_engagement',
                    'm.key_expertise',
                    'm.specific_field',
                    'm.special_skills',
                    'm.hobbies',
                    'm.committees'
                ];
                $likeParts = [];
                foreach ($searchable as $col) {
                    $likeParts[] = "$col LIKE ?";
                    $params[] = '%' . $q . '%';
                    $types .= 's';
                }
                $whereParts[] = '(' . implode(' OR ', $likeParts) . ')';
            }

            if ($whereParts) {
                $sql .= ' WHERE ' . implode(' AND ', $whereParts);
            }
            $sql .= ' ORDER BY m.created_at DESC';

            try {
                if (!empty($params)) {
                    // Use prepared statement when search is involved
                    $stmt = $conn->prepare($sql);
                    if ($stmt === false) {
                        throw new Exception('Prepare failed: ' . $conn->error);
                    }
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $conn->query($sql);
                }

                if ($id) {
                    $data = $result->fetch_assoc();
                } else {
                    $data = [];
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                }
                echo json_encode($data);
                if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                    $stmt->close();
                }
            } catch (Throwable $e) {
                error_log('Membership applications GET error: ' . $e->getMessage());
                echo json_encode([]);
            }
            break;

        case 'POST':
            // Update application
            $data = json_decode(file_get_contents('php://input'), true);

            // Full details update
            if (isset($data['action']) && $data['action'] === 'update_details' && isset($data['id'])) {
                $applicationId = intval($data['id']);
                // Whitelist fields for update
                $allowed = [
                    'name',
                    'dob',
                    'sex',
                    'current_address',
                    'permanent_address',
                    'email',
                    'landline',
                    'mobile',
                    'place_of_birth',
                    'marital_status',
                    'emergency_contact',
                    'doh_agency',
                    'doh_address',
                    'employment_start',
                    'employment_end',
                    'school',
                    'degree',
                    'year_graduated',
                    'current_engagement',
                    'key_expertise',
                    'specific_field',
                    'special_skills',
                    'hobbies',
                    'committees',
                    'status'
                ];
                $setParts = [];
                $values = [];
                $types = '';
                foreach ($allowed as $field) {
                    if (array_key_exists($field, $data)) {
                        $setParts[] = "$field = ?";
                        $values[] = $data[$field];
                        // Infer type: year_graduated is numeric, everything else string except dob which is date (string to DB)
                        if ($field === 'year_graduated') {
                            $types .= 'i';
                        } else {
                            $types .= 's';
                        }
                    }
                }
                if (empty($setParts)) {
                    echo json_encode(['status' => false, 'message' => 'No fields to update']);
                    break;
                }
                $sql = "UPDATE membership_applications SET " . implode(', ', $setParts) . " WHERE application_id = ?";
                $stmt = $conn->prepare($sql);
                $types .= 'i';
                $values[] = $applicationId;
                $stmt->bind_param($types, ...$values);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) {
                    $adminId = $_SESSION['user_id'] ?? null;
                    if ($adminId) {
                        recordAuditLog($adminId, 'Update Membership Details', "Application ID {$applicationId} fields updated.");
                    }
                    echo json_encode(['status' => true, 'message' => 'Application details updated.']);
                } else {
                    echo json_encode(['status' => false, 'message' => 'Failed to update details.']);
                }
                break;
            }

            // Status-only update (legacy path)
            if (isset($data['id']) && isset($data['status'])) {
                $adminId = $_SESSION['user_id']; // admin performing the action

                $conn->begin_transaction(); // Start transaction

                // Update the application's status
                $stmt = $conn->prepare("UPDATE membership_applications SET status = ? WHERE application_id = ?");
                $stmt->bind_param("si", $data['status'], $data['id']);
                $success = $stmt->execute();
                $stmt->close();

                if ($success && $data['status'] === 'Approved') {
                    // Fetch the user_id of the application submitter
                    $fetchUserIdStmt = $conn->prepare("SELECT user_id FROM membership_applications WHERE application_id = ?");
                    $fetchUserIdStmt->bind_param("i", $data['id']);
                    $fetchUserIdStmt->execute();
                    $fetchUserIdStmt->bind_result($userId);
                    $fetchUserIdStmt->fetch();
                    $fetchUserIdStmt->close();

                    if ($userId) {
                        // Update the user's role to 'member'
                        $userStmt = $conn->prepare("UPDATE users SET role = 'member' WHERE user_id = ?");
                        $userStmt->bind_param("i", $userId);
                        $userSuccess = $userStmt->execute();
                        $userStmt->close();

                        if ($userSuccess) {
                            // Seed membership_profiles if absent, deriving age/year
                            try {
                                $resApp = $conn->query("SELECT dob,doh_agency FROM membership_applications WHERE application_id = " . intval($data['id']));
                                $dobRow = $resApp ? $resApp->fetch_assoc() : null;
                                $ageUpon = null;
                                if ($dobRow && !empty($dobRow['dob']) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dobRow['dob'])) {
                                    $dobDT = new DateTime($dobRow['dob']);
                                    $nowDT = new DateTime();
                                    $ageUpon = (int)$dobDT->diff($nowDT)->y;
                                }
                                $yearMembership = (int)date('Y');
                                $conn->query("CREATE TABLE IF NOT EXISTS membership_profiles (\n    user_id INT(11) NOT NULL PRIMARY KEY,\n    year_of_membership YEAR NULL,\n    age_upon_membership INT(11) NULL,\n    certification ENUM('Honorary','Regular') DEFAULT 'Regular',\n    membership_fee DECIMAL(10,2) DEFAULT NULL,\n    previous_office VARCHAR(255) NULL,\n    is_lifetime TINYINT(1) NOT NULL DEFAULT 0,\n    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n    CONSTRAINT fk_mp_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
                                $profStmt = $conn->prepare("INSERT INTO membership_profiles (user_id, year_of_membership, age_upon_membership, certification, previous_office) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE year_of_membership=VALUES(year_of_membership), age_upon_membership=VALUES(age_upon_membership), previous_office=IF(COALESCE(previous_office,'')='', VALUES(previous_office), previous_office)");
                                if ($profStmt) {
                                    $certDefault = 'Regular';
                                    $ageParam = $ageUpon !== null ? $ageUpon : null;
                                    $prevOfficeSeed = ($dobRow && !empty($dobRow['doh_agency'])) ? $dobRow['doh_agency'] : null;
                                    $profStmt->bind_param('iiiss', $userId, $yearMembership, $ageParam, $certDefault, $prevOfficeSeed);
                                    $profStmt->execute();
                                    $profStmt->close();
                                }
                            } catch (Throwable $eSeed) {
                                error_log('membership_applications approval profile seed error: ' . $eSeed->getMessage());
                            }
                            // Check if a membership record exists for this user
                            $stmtMember = $conn->prepare("SELECT COUNT(*) as count FROM members WHERE user_id = ?");
                            $stmtMember->bind_param("i", $userId);
                            $stmtMember->execute();
                            $stmtMember->bind_result($memberCount);
                            $stmtMember->fetch();
                            $stmtMember->close();

                            if (intval($memberCount) === 0) {
                                // No record exists: insert a new record with membership_status 'active'
                                $stmtInsert = $conn->prepare("INSERT INTO members (user_id, membership_status) VALUES (?, 'inactive')");
                                $stmtInsert->bind_param("i", $userId);
                                $stmtInsert->execute();
                                $stmtInsert->close();
                            } else {
                                // Optionally, update existing record to active
                                $stmtUpdate = $conn->prepare("UPDATE members SET membership_status = 'inactive' WHERE user_id = ?");
                                $stmtUpdate->bind_param("i", $userId);
                                $stmtUpdate->execute();
                                $stmtUpdate->close();
                            }

                            $conn->commit(); // Commit transaction
                            // Record audit log for approval and role update.
                            recordAuditLog($adminId, 'Approve Membership Application', "Application ID {$data['id']} approved; user role updated to member and membership activated.");
                            echo json_encode(['status' => true, 'message' => 'Application approved, user role updated, and membership activated.']);
                        } else {
                            $conn->rollback(); // Rollback transaction
                            echo json_encode(['status' => false, 'message' => 'Application approved, but failed to update user role.']);
                        }
                    } else {
                        $conn->rollback(); // Rollback transaction
                        echo json_encode(['status' => false, 'message' => 'Application approved, but user ID not found.']);
                    }
                } else {
                    if ($success) {
                        $conn->commit(); // Commit transaction
                        // Record audit log for status update (non-approval).
                        recordAuditLog($adminId, 'Update Membership Application', "Application ID {$data['id']} updated to status {$data['status']}.");
                    } else {
                        $conn->rollback(); // Rollback transaction
                    }
                    echo json_encode(['status' => $success, 'message' => $success ? 'Application updated.' : 'Failed to update application.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Invalid request.']);
            }
            break;

        case 'DELETE':
            // Delete application (and its S3 files, e.g., valid_id_url)
            parse_str(file_get_contents('php://input'), $data);

            if (isset($data['id'])) {
                $appId = intval($data['id']);

                // Fetch S3-backed fields to clean up (currently: valid_id_url)
                $stmtSel = $conn->prepare("SELECT valid_id_url FROM membership_applications WHERE application_id = ?");
                $stmtSel->bind_param("i", $appId);
                $stmtSel->execute();
                $resSel = $stmtSel->get_result();
                if ($resSel && $row = $resSel->fetch_assoc()) {
                    $validIdUrl = $row['valid_id_url'] ?? '';
                    if (!empty($validIdUrl) && strpos($validIdUrl, '/s3proxy/') === 0) {
                        $existingS3Key = urldecode(str_replace('/s3proxy/', '', $validIdUrl));
                        try {
                            $s3->deleteObject(['Bucket' => $bucketName, 'Key' => $existingS3Key]);
                        } catch (Aws\Exception\AwsException $e) {
                            error_log('S3 deletion error (valid_id_url): ' . $e->getMessage());
                        }
                    }
                }
                $stmtSel->close();

                // Now delete the application record
                $stmt = $conn->prepare("DELETE FROM membership_applications WHERE application_id = ?");
                $stmt->bind_param("i", $appId);
                $success = $stmt->execute();
                if ($success) {
                    // Record audit log for deletion.
                    $adminId = $_SESSION['user_id'];
                    recordAuditLog($adminId, 'Delete Membership Application', "Application ID {$appId} deleted.");
                    echo json_encode(['status' => true, 'message' => 'Application deleted.']);
                } else {
                    echo json_encode(['status' => false, 'message' => 'Failed to delete application.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'Invalid request.']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['status' => false, 'message' => 'Method not allowed.']);
            break;
    }
} catch (Throwable $e) {
    error_log($e->getMessage()); // Log detailed error on the server for debugging
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Keep UI functional for reads
        echo json_encode([]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Internal Server Error']);
    }
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

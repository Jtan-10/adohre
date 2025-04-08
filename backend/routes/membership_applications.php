<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');
session_start();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Fetch applications
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;

            // Updated SQL query to include face_image from users table:
            $sql = "SELECT m.*, u.face_image FROM membership_applications m LEFT JOIN users u ON m.user_id = u.user_id";
            $conditions = [];
            $params = [];
            $types = '';

            if ($id) {
                $conditions[] = "m.application_id = ?";
                $params[] = $id;
                $types .= "i";
            }
            if ($status) {
                $conditions[] = "m.status = ?";
                $params[] = $status;
                $types .= "s";
            }

            if ($conditions) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
            $sql .= " ORDER BY m.created_at DESC";

            $stmt = $conn->prepare($sql);
            if ($types) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $id ? $result->fetch_assoc() : $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($data);
            break;

        case 'POST':
            // Update application
            $data = json_decode(file_get_contents('php://input'), true);

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
                            // Check if a membership record exists for this user
                            $stmtMember = $conn->prepare("SELECT COUNT(*) as count FROM members WHERE user_id = ?");
                            $stmtMember->bind_param("i", $userId);
                            $stmtMember->execute();
                            $resultMember = $stmtMember->get_result()->fetch_assoc();
                            $stmtMember->close();

                            if ($resultMember['count'] == 0) {
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
            // Delete application
            parse_str(file_get_contents('php://input'), $data);

            if (isset($data['id'])) {
                $stmt = $conn->prepare("DELETE FROM membership_applications WHERE application_id = ?");
                $stmt->bind_param("i", $data['id']);
                $success = $stmt->execute();
                if ($success) {
                    // Record audit log for deletion.
                    $adminId = $_SESSION['user_id'];
                    recordAuditLog($adminId, 'Delete Membership Application', "Application ID {$data['id']} deleted.");
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
} catch (Exception $e) {
    error_log($e->getMessage()); // Log detailed error on the server for debugging
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Internal Server Error']);
} finally {
    $conn->close();
}

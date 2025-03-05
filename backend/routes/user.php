<?php
session_start();
require_once '../db/db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD']; // Request method
$auth_user_id = $_SESSION['user_id'] ?? null; // Logged-in user ID
$auth_user_role = $_SESSION['role'] ?? null; // Logged-in user role

if (!$auth_user_id) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    if ($method === 'GET') {
        // Check if it's an admin DataTables request
        if ($auth_user_role === 'admin' && isset($_GET['draw'])) {
            $draw = isset($_GET['draw']) ? intval($_GET['draw']) : 0;
            $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
            $length = isset($_GET['length']) ? intval($_GET['length']) : 10;
            $searchValue = $_GET['search']['value'] ?? '';
    
            // Total records count
            $totalRecordsQuery = "SELECT COUNT(*) as total FROM users";
            $totalRecordsResult = $conn->query($totalRecordsQuery);
            $totalRecords = $totalRecordsResult->fetch_assoc()['total'];
    
            // Filtered records count
            $filteredRecordsQuery = "SELECT COUNT(*) as total FROM users WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?";
            $searchTerm = "%$searchValue%";
            $stmt = $conn->prepare($filteredRecordsQuery);
            $stmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
            $stmt->execute();
            $filteredRecordsResult = $stmt->get_result();
            $filteredRecords = $filteredRecordsResult->fetch_assoc()['total'];
    
            // Fetch filtered records
            $query = "SELECT user_id, first_name, last_name, email, role FROM users WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('sssii', $searchTerm, $searchTerm, $searchTerm, $length, $start);
            $stmt->execute();
            $result = $stmt->get_result();
    
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
    
            echo json_encode([
                "draw" => $draw,
                "recordsTotal" => $totalRecords,
                "recordsFiltered" => $filteredRecords,
                "data" => $users
            ]);
            exit();
        } elseif (isset($_GET['action'])) {
            $action = $_GET['action'];
            $user_id = intval($_GET['user_id'] ?? 0);
    
            if ($action === 'get_user_events') {
                // Fetch joined events
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
    
                echo json_encode(['data' => $events]);
                exit();
            } elseif ($action === 'get_user_trainings') {
                // Fetch joined trainings
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
    
                echo json_encode(['data' => $trainings]);
                exit();
            }
        } elseif (isset($_GET['user_id'])) {
            // Fetch a single user's details
            $user_id = intval($_GET['user_id']);
            $stmt = $conn->prepare('SELECT user_id, first_name, last_name, email, role, profile_image, virtual_id FROM users WHERE user_id = ?');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
    
            if ($result->num_rows === 1) {
                echo json_encode(['status' => true, 'data' => $result->fetch_assoc()]);
            } else {
                http_response_code(404);
                echo json_encode(['status' => false, 'message' => 'User not found.']);
            }
        }
    } elseif ($method === 'POST') {
        // Handle profile updates for the logged-in user
        if (!empty($_FILES['profile_image']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_file_size = 2 * 1024 * 1024;

            if (!in_array($_FILES['profile_image']['type'], $allowed_types)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type.']);
                exit();
            }

            if ($_FILES['profile_image']['size'] > $max_file_size) {
                echo json_encode(['status' => false, 'message' => 'File size exceeds 2 MB limit.']);
                exit();
            }

            $target_dir = '../../uploads/profile_images/';
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_name = $auth_user_id . '_' . time() . '_' . basename($_FILES['profile_image']['name']);
            $target_file = $target_dir . $file_name;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                $profile_image_path = 'uploads/profile_images/' . $file_name;

                $stmt = $conn->prepare('UPDATE users SET profile_image = ? WHERE user_id = ?');
                $stmt->bind_param('si', $profile_image_path, $auth_user_id);

                if ($stmt->execute()) {
                    $_SESSION['profile_image'] = $profile_image_path;
                } else {
                    http_response_code(500);
                    echo json_encode(['status' => false, 'message' => 'Failed to update profile image.']);
                    exit();
                }
            } else {
                echo json_encode(['status' => false, 'message' => 'Error uploading profile image.']);
                exit();
            }
        }

        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (!$first_name || !$last_name || !$email) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'All fields are required.']);
            exit();
        }

        $stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?');
        $stmt->bind_param('sssi', $first_name, $last_name, $email, $auth_user_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => true, 'message' => 'Profile updated successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => false, 'message' => 'Error updating profile.']);
        }
    } elseif ($method === 'PUT') {
        // Admin updating other users
        if ($auth_user_role === 'admin') {
            $data = json_decode(file_get_contents("php://input"), true);

            $user_id = intval($data['user_id'] ?? 0);
            $first_name = trim($data['first_name'] ?? '');
            $last_name = trim($data['last_name'] ?? '');
            $email = trim($data['email'] ?? '');
            $role = trim($data['role'] ?? '');

            if (!$user_id || !$first_name || !$last_name || !$email || !$role) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'All fields are required.']);
                exit();
            }

            $stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ? WHERE user_id = ?');
            $stmt->bind_param('ssssi', $first_name, $last_name, $email, $role, $user_id);

            if ($stmt->execute()) {
                echo json_encode(['status' => true, 'message' => 'User updated successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Error updating user.']);
            }
        } else {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Forbidden']);
        }
    } elseif ($method === 'DELETE') {
        // Admin deleting a user
        if ($auth_user_role === 'admin') {
            $data = json_decode(file_get_contents("php://input"), true);
            $user_id = intval($data['user_id'] ?? 0);

            if (!$user_id) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'User ID is required.']);
                exit();
            }

            $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
            $stmt->bind_param('i', $user_id);

            if ($stmt->execute()) {
                echo json_encode(['status' => true, 'message' => 'User deleted successfully.']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => false, 'message' => 'Error deleting user.']);
            }
        } else {
            http_response_code(403);
            echo json_encode(['status' => false, 'message' => 'Forbidden']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Method not allowed.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Internal server error.', 'details' => $e->getMessage()]);
}
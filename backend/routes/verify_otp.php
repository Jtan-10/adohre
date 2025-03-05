<?php
require_once '../controllers/authController.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $email = $data['email'];
    $otp = $data['otp'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    if (verifyOTP($email, $otp)) {
        // Fetch user data for session
        global $conn;
        $stmt = $conn->prepare("SELECT user_id, first_name,last_name, profile_image, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            session_start();
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['profile_image'] = $user['profile_image'];
            $_SESSION['role'] = $user['role'];

            echo json_encode(['status' => true, 'message' => 'Login successful!']);
        } else {
            echo json_encode(['status' => false, 'message' => 'User not found.']);
        }
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid OTP.']);
    }
}

?>
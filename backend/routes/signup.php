<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../controllers/authController.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data)) {
        echo json_encode(['status' => false, 'message' => 'No data received.']);
        exit;
    }

    // Initial step of signup: Handling email input
    if (isset($data['email']) && !isset($data['otp']) && !isset($data['first_name'])) {
        $email = trim($data['email']);

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // Add a new record if the email does not exist
        if (!emailExists($email)) {
            global $conn;
            
            // Retrieve the visually impaired flag from the session (defaulting to 0 if not set)
            $visually_impaired = (isset($_SESSION['visually_impaired']) && $_SESSION['visually_impaired']) ? 1 : 0;
            $virtual_id = generateVirtualId(); // Generate virtual ID
            $role = 'user'; // Default role

            // Note: The INSERT query now includes the visually_impaired column.
            $stmt = $conn->prepare("INSERT INTO users (email, role, virtual_id, visually_impaired) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $email, $role, $virtual_id, $visually_impaired);

            if (!$stmt->execute()) {
                error_log("Failed to create a temporary user record for email: $email");
                echo json_encode(['status' => false, 'message' => 'Error creating user record.']);
                exit;
            }
        }

        // Generate OTP and send email
        if (generateOTP($email)) {
            echo json_encode(['status' => true, 'message' => 'OTP sent to your email.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to send OTP.']);
        }
        exit;
    }

    // OTP verification step
    elseif (isset($data['otp'], $data['email']) && !isset($data['first_name'])) {
        $email = trim($data['email']);
        $otp = trim($data['otp']);

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // Verify OTP
        if (verifyOTP($email, $otp)) {
            echo json_encode(['status' => true, 'message' => 'OTP verified. Proceed to enter details.']);
        } else {
            echo json_encode(['status' => false, 'message' => 'Invalid or expired OTP.']);
        }
        exit;
    }

    // Final signup step: Collecting additional details (first name and last name)
    elseif (isset($data['first_name'], $data['last_name'], $data['email'])) {
        $email = trim($data['email']);
        $first_name = trim($data['first_name']);
        $last_name = trim($data['last_name']);

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => false, 'message' => 'Invalid email format.']);
            exit;
        }

        // Ensure first name and last name are not empty
        if (empty($first_name) || empty($last_name)) {
            echo json_encode(['status' => false, 'message' => 'First name and last name are required.']);
            exit;
        }

        global $conn;

        // Update user details in the database
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE email = ?");
        $stmt->bind_param("sss", $first_name, $last_name, $email);

        if ($stmt->execute()) {
            echo json_encode(['status' => true, 'message' => 'Signup successful! You can now log in.']);
        } else {
            error_log("Failed to update user details for email: $email");
            echo json_encode(['status' => false, 'message' => 'Failed to update account details.']);
        }
        exit;
    }

    // If request doesn't match any of the above
    else {
        echo json_encode(['status' => false, 'message' => 'Invalid request.']);
        exit;
    }
} else {
    // Handle invalid HTTP methods
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
}
?>
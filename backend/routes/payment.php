<?php
// backend/routes/payment.php

header('Content-Type: application/json');
session_start();
require_once '../db/db_connect.php';
require_once '../s3config.php';

// Ensure the user is logged in and is an admin.
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Default action is to get all payments
    $action = isset($_GET['action']) ? $_GET['action'] : 'get_all_payments';
    
    if ($action === 'get_all_payments') {
        $payments = [];
        // Retrieve all payments with corresponding user details
        $query = "SELECT p.payment_id, p.user_id, p.payment_type, p.amount, p.status, p.payment_date, p.due_date, p.reference_number, p.image, p.mode_of_payment, u.first_name, u.last_name, u.email
                  FROM payments p 
                  JOIN users u ON p.user_id = u.user_id 
                  ORDER BY p.payment_date DESC";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            echo json_encode(['status' => true, 'payments' => $payments]);
            exit;
        } else {
            echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
    } elseif ($action === 'get_pending_payments') {
        // Get user_id from GET parameter
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        if ($user_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        $payments = [];
        // Retrieve only pending payments for the given user
        $query = "SELECT * FROM payments WHERE user_id = ? AND status = 'Pending' ORDER BY due_date ASC";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        $stmt->close();
        
        // Return the pending payments as JSON
        echo json_encode([
            'status' => true,
            'pendingPayments' => $payments
        ]);
        exit;
    
    } elseif ($action === 'get_payments') {
        // New branch for filtering by status
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $status = isset($_GET['status']) ? $_GET['status'] : 'New';
        if ($user_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        $payments = [];
        $query = "SELECT * FROM payments WHERE user_id = ? AND status = ? ORDER BY due_date ASC";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("is", $user_id, $status);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        $stmt->close();
        echo json_encode(['status' => true, 'payments' => $payments]);
        exit;
    } else {
        echo json_encode(['status' => false, 'message' => 'Invalid GET action']);
        exit;
    } 
} elseif ($method === 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'push_payment';
    
    // New branch: update payment fee details
    if ($action === 'update_payment_fee') {
        // Retrieve and validate required fields for fee update
        $payment_id = isset($_POST['payment_id']) ? trim($_POST['payment_id']) : '';
        $reference_number = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';
        $mode_of_payment = isset($_POST['mode_of_payment']) ? trim($_POST['mode_of_payment']) : '';
        if (empty($payment_id) || empty($reference_number) || empty($mode_of_payment)) {
            echo json_encode(['status' => false, 'message' => 'Payment ID, Reference Number, and Mode of Payment are required.']);
            exit();
        }
        
        // Process image upload via S3 if provided (using your provided code)
        $relativeImagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }
            $imageName = time() . '_' . basename($_FILES['image']['name']);
            $s3Key = 'uploads/training_images/' . $imageName;
            try {
                $result = $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key'    => $s3Key,
                    'Body'   => fopen($_FILES['image']['tmp_name'], 'rb'),
                    'ACL'    => 'public-read',
                    'ContentType' => $_FILES['image']['type']
                ]);
                $relativeImagePath = str_replace(
                    "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
                    "/s3proxy/",
                    $result['ObjectURL']
                );
            } catch (Aws\Exception\AwsException $e) {
                echo json_encode([
                    'status' => false,
                    'message' => 'Failed to upload image to S3: ' . $e->getMessage()
                ]);
                exit();
            }
        }
        
        // Automatically generate the payment date (current timestamp)
        $payment_date = date('Y-m-d H:i:s');
        
        // Update the payment record: set reference_number, image, mode_of_payment, payment_date and change status to 'Pending'
        $stmt = $conn->prepare("UPDATE payments SET reference_number = ?, image = ?, mode_of_payment = ?, status = 'Pending', payment_date = ? WHERE payment_id = ?");
        if ($stmt === false) {
            echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("ssssi", $reference_number, $relativeImagePath, $mode_of_payment, $payment_date, $payment_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => true, 'message' => 'Payment updated successfully.']);
            exit();
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to update payment: ' . $stmt->error]);
            exit();
        }
    } else { 
        // Required input from admin: user_id, payment_type, amount.
        $user_id      = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
        $payment_type = isset($_POST['payment_type']) ? trim($_POST['payment_type']) : '';
        $amount       = isset($_POST['amount']) ? trim($_POST['amount']) : '';

        // Validate required fields (due_date is not required from the client)
        if (empty($user_id) || empty($payment_type) || empty($amount)) {
            echo json_encode(['status' => false, 'message' => 'User ID, Payment Type, and Amount are required.']);
            exit;
        }

        // Automatically generate due_date as one year from today (YYYY-MM-DD)
        $due_date = date('Y-m-d', strtotime('+1 year'));

        // Set payment_date and reference_number to NULL
        $payment_date = null;
        $reference_number = null;
        $status = 'New';
        $image = null;

        // Process image upload if an image file is provided
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "../uploads/payments/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $fileName    = time() . "_" . basename($_FILES['image']['name']);
            $target_file = $target_dir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image = $target_file;
            } else {
                echo json_encode(['status' => false, 'message' => 'Failed to upload image.']);
                exit;
            }
        }

        // Insert new payment record using a prepared statement
        $stmt = $conn->prepare("INSERT INTO payments (user_id, payment_type, amount, status, payment_date, due_date, reference_number, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("isdsssss", $user_id, $payment_type, $amount, $status, $payment_date, $due_date, $reference_number, $image);
        if ($stmt->execute()) {
            echo json_encode(['status' => true, 'message' => 'Payment pushed successfully.']);
            exit;
        } else {
            echo json_encode(['status' => false, 'message' => 'Failed to push payment: ' . $stmt->error]);
            exit;
        }
    }
} elseif ($method === 'PUT') {
    // Get the raw input data and decode JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['payment_id']) || !isset($input['status'])) {
        echo json_encode(['status' => false, 'message' => 'Invalid input']);
        exit;
    }
    $payment_id = $input['payment_id'];
    $newStatus = $input['status'];

    // Update payment status using a prepared statement
    $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE payment_id = ?");
    if ($stmt === false) {
        echo json_encode(['status' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("si", $newStatus, $payment_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => true, 'message' => 'Payment status updated successfully.']);
        exit;
    } else {
        echo json_encode(['status' => false, 'message' => 'Failed to update payment status: ' . $stmt->error]);
        exit;
    }
} else {
    echo json_encode(['status' => false, 'message' => 'Unsupported request method']);
    exit;
}
?>
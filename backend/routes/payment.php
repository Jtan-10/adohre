<?php
require_once '../db/db_connect.php';
require_once '../s3config.php';
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Add secure session cookie settings for production
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '', // set your domain if needed
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Helper function to embed binary data into a valid PNG.
 * Converts binary data into a valid PNG image by mapping every 3 bytes to a pixel (R, G, B).
 * Remaining pixels are padded with black.
 *
 * @param string $binaryData The binary data to embed.
 * @param int    $desiredWidth Desired width (used to compute a roughly square image)
 * @return GdImage A GD image resource.
 */
if (!function_exists('embedDataInPng')) {
    function embedDataInPng($binaryData, $desiredWidth = 100) {
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

if ($method === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : 'get_all_payments';

    if ($action === 'get_all_payments') {
        $payments = [];
        // Updated query: join events and trainings to retrieve a title when applicable.
        $query = "SELECT p.payment_id, p.user_id, p.payment_type, p.amount, p.status, p.payment_date, p.due_date, p.reference_number, p.image, p.mode_of_payment,
                         u.first_name, u.last_name, u.email,
                         CASE 
                             WHEN p.payment_type = 'Event Registration' THEN e.title
                             WHEN p.payment_type = 'Training Registration' THEN t.title
                             ELSE NULL
                         END AS title
                  FROM payments p 
                  JOIN users u ON p.user_id = u.user_id 
                  LEFT JOIN events e ON (p.payment_type = 'Event Registration' AND p.event_id = e.event_id)
                  LEFT JOIN trainings t ON (p.payment_type = 'Training Registration' AND p.training_id = t.training_id)
                  ORDER BY p.payment_date DESC";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $payments[] = $row;
            }
            echo json_encode(['status' => true, 'payments' => $payments]);
            exit;
        } else {
            error_log("Database error in get_all_payments: " . $conn->error);
            echo json_encode(['status' => false, 'message' => 'Internal server error']);
            exit;
        }
    } elseif ($action === 'get_pending_payments') {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        if ($user_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        $payments = [];
        // Here, pending payments are those with status 'Pending'
        $query = "SELECT * FROM payments WHERE user_id = ? AND status = 'Pending' ORDER BY due_date ASC";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Database error in get_pending_payments: " . $conn->error);
            echo json_encode(['status' => false, 'message' => 'Internal server error']);
            exit;
        }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        $stmt->close();

        echo json_encode([
            'status' => true,
            'pendingPayments' => $payments
        ]);
        exit;
    } elseif ($action === 'get_payments') {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $status = isset($_GET['status']) ? $_GET['status'] : 'New';
        if ($user_id <= 0) {
            echo json_encode(['status' => false, 'message' => 'Invalid user ID']);
            exit;
        }
        $payments = [];
        // Updated query: join events/trainings for title data
        $query = "SELECT p.*, 
                         CASE 
                             WHEN p.payment_type = 'Event Registration' THEN e.title
                             WHEN p.payment_type = 'Training Registration' THEN t.title
                             ELSE NULL
                         END AS title
                  FROM payments p
                  LEFT JOIN events e ON (p.payment_type = 'Event Registration' AND p.event_id = e.event_id)
                  LEFT JOIN trainings t ON (p.payment_type = 'Training Registration' AND p.training_id = t.training_id)
                  WHERE p.user_id = ? AND p.status = ?
                  ORDER BY p.due_date ASC";
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Database error in get_payments: " . $conn->error);
            echo json_encode(['status' => false, 'message' => 'Internal server error']);
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

    if ($action === 'update_payment_fee') {
        // Retrieve and validate required fields for fee update
        $payment_id = isset($_POST['payment_id']) ? trim($_POST['payment_id']) : '';
        $reference_number = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : '';
        $mode_of_payment = isset($_POST['mode_of_payment']) ? trim($_POST['mode_of_payment']) : '';
        if (empty($payment_id) || empty($reference_number) || empty($mode_of_payment)) {
            echo json_encode(['status' => false, 'message' => 'Payment ID, Reference Number, and Mode of Payment are required.']);
            exit();
        }

        // Process image upload via S3 with encryption if provided
        $relativeImagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }
            $imageName = time() . '_' . basename($_FILES['image']['name']);
            $s3Key = 'uploads/payments/' . $imageName;

            // Encrypt and embed the image into a PNG.
            $clearImageData = file_get_contents($_FILES['image']['tmp_name']);
            $cipher = "AES-256-CBC";
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
            $rawKey = getenv('ENCRYPTION_KEY');
            $encryptionKey = hash('sha256', $rawKey, true);
            $encryptedData = openssl_encrypt($clearImageData, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
            $encryptedImageData = $iv . $encryptedData;
            
            $pngImage = embedDataInPng($encryptedImageData, 100);
            $encryptedTempPath = tempnam(sys_get_temp_dir(), 'enc_pay_') . '.png';
            imagepng($pngImage, $encryptedTempPath);
            imagedestroy($pngImage);
            
            // Before uploading the new image, delete any previously stored image for this payment.
            $stmtOld = $conn->prepare("SELECT image FROM payments WHERE payment_id = ?");
            if ($stmtOld) {
                $stmtOld->bind_param("i", $payment_id);
                $stmtOld->execute();
                $resultOld = $stmtOld->get_result();
                if ($resultOld && $rowOld = $resultOld->fetch_assoc()) {
                    $oldImage = $rowOld['image'];
                    if (!empty($oldImage) && strpos($oldImage, '/s3proxy/') === 0) {
                        $oldS3Key = urldecode(str_replace('/s3proxy/', '', $oldImage));
                        try {
                            $s3->deleteObject([
                                'Bucket' => $bucketName,
                                'Key'    => $oldS3Key
                            ]);
                        } catch (Aws\Exception\AwsException $e) {
                            error_log("Failed to delete old image from S3: " . $e->getMessage());
                        }
                    }
                }
                $stmtOld->close();
            }

            try {
                $result = $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key'    => $s3Key,
                    'Body'   => fopen($encryptedTempPath, 'rb'),
                    'ACL'    => 'public-read',
                    'ContentType' => 'image/png'
                ]);
                @unlink($encryptedTempPath);
                $relativeImagePath = str_replace(
                    "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
                    "/s3proxy/",
                    $result['ObjectURL']
                );
            } catch (Aws\Exception\AwsException $e) {
                error_log('Failed to upload image to S3: ' . $e->getMessage());
                echo json_encode([
                    'status' => false,
                    'message' => 'Internal server error'
                ]);
                exit();
            }
        }

        // Automatically generate the payment date (current timestamp)
        $payment_date = date('Y-m-d H:i:s');

        // Update the payment record: set reference_number, image, mode_of_payment, payment_date and change status to 'Pending'
        $stmt = $conn->prepare("UPDATE payments SET reference_number = ?, image = ?, mode_of_payment = ?, status = 'Pending', payment_date = ? WHERE payment_id = ?");
        if ($stmt === false) {
            error_log("Database error in update_payment_fee: " . $conn->error);
            echo json_encode(['status' => false, 'message' => 'Internal server error']);
            exit();
        }
        $stmt->bind_param("ssssi", $reference_number, $relativeImagePath, $mode_of_payment, $payment_date, $payment_id);
        if ($stmt->execute()) {
            // After updating, retrieve user details for notification
            $query = "SELECT u.email, u.first_name, p.payment_type, p.amount, p.mode_of_payment, p.payment_date, p.user_id
                      FROM payments p
                      JOIN users u ON p.user_id = u.user_id
                      WHERE p.payment_id = ?";
            $stmtUser = $conn->prepare($query);
            $stmtUser->bind_param("i", $payment_id);
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();
            $details = $resultUser->fetch_assoc();
            $stmtUser->close();

            // Log the email sending attempt
            error_log("Sending payment update email to: " . $details['email'] . " at " . date('Y-m-d H:i:s'));

            // Rate limiting for email sending: allow maximum 10 emails per recipient within a 1-hour window.
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $window = 3600; // 1 hour in seconds
            $maxEmails = 10;
            $now = time();
            $recipient = $details['email'];

            if (!isset($_SESSION['email_send_requests'])) {
                $_SESSION['email_send_requests'] = [];
            }
            if (!isset($_SESSION['email_send_requests'][$recipient])) {
                $_SESSION['email_send_requests'][$recipient] = [
                    'count' => 0,
                    'first_request_time' => $now
                ];
            }
            // Reset counter if the time window has expired
            if ($now - $_SESSION['email_send_requests'][$recipient]['first_request_time'] > $window) {
                $_SESSION['email_send_requests'][$recipient]['count'] = 0;
                $_SESSION['email_send_requests'][$recipient]['first_request_time'] = $now;
            }
            // Check if rate limit is reached
            if ($_SESSION['email_send_requests'][$recipient]['count'] >= $maxEmails) {
                error_log("Rate limit exceeded for sending payment update emails to: " . $recipient);
                // Optionally, you can decide to skip sending the email or notify the user.
            } else {
                // Increment count and proceed to send the email.
                $_SESSION['email_send_requests'][$recipient]['count']++;

                $mail = new PHPMailer(true);
                try {
                    // SMTP configuration using environment variables.
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['SMTP_HOST'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['SMTP_USER'];
                    $mail->Password   = $_ENV['SMTP_PASS'];
                    $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
                    $mail->Port       = $_ENV['SMTP_PORT'];

                    // Enforce secure SMTP options.
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer'      => true,
                            'verify_peer_name' => true,
                            'allow_self_signed' => false,
                        ],
                    ];

                    $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_FROM_NAME']);
                    $mail->addAddress($details['email']);

                    $mail->isHTML(true);
                    $mail->Subject = "Payment Update Notification";
                    $mail->Body    = "
                        <h1>Hello " . htmlspecialchars($details['first_name'], ENT_QUOTES, 'UTF-8') . ",</h1>
                        <p>Your payment has been updated to <strong>Pending</strong>.</p>
                        <p><strong>Payment Type:</strong> " . htmlspecialchars($details['payment_type'], ENT_QUOTES, 'UTF-8') . "</p>
                        <p><strong>Amount:</strong> " . htmlspecialchars($details['amount'], ENT_QUOTES, 'UTF-8') . "</p>
                        <p><strong>Mode of Payment:</strong> " . htmlspecialchars($details['mode_of_payment'], ENT_QUOTES, 'UTF-8') . "</p>
                        <p><strong>Payment Date:</strong> " . htmlspecialchars($details['payment_date'], ENT_QUOTES, 'UTF-8') . "</p>
                        <p>Please check your account for further details.</p>";
                    $mail->AltBody = strip_tags($mail->Body);

                    $mail->send();
                    // Log the sent email into the database
                    $stmtLog = $conn->prepare("INSERT INTO email_notifications (user_id, subject, body) VALUES (?, ?, ?)");
                    $subjectLog = "Payment Update Notification";
                    $bodyLog = $mail->Body;
                    // Using user_id from the retrieved details ($details array)
                    $stmtLog->bind_param("iss", $details['user_id'], $subjectLog, $bodyLog);
                    $stmtLog->execute();
                    $stmtLog->close();
                } catch (Exception $e) {
                    error_log("Email could not be sent. Mailer Error: " . $mail->ErrorInfo);
                }
            }

            // Audit log the payment fee update
            recordAuditLog($_SESSION['user_id'], 'Update Payment Fee', "Payment ID $payment_id updated to Pending. Reference: $reference_number, Mode: $mode_of_payment, Payment Date: $payment_date");
            
            echo json_encode(['status' => true, 'message' => 'Payment updated successfully.']);
            exit();
        } else {
            error_log("Failed to update payment: " . $stmt->error);
            echo json_encode(['status' => false, 'message' => 'Internal server error']);
            exit();
        }
    } else {
        // New Payment Push branch (for admin usage)
        $user_id      = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
        $payment_type = isset($_POST['payment_type']) ? trim($_POST['payment_type']) : '';
        $amount       = isset($_POST['amount']) ? trim($_POST['amount']) : '';

        if (empty($user_id) || empty($payment_type) || empty($amount)) {
            echo json_encode(['status' => false, 'message' => 'User ID, Payment Type, and Amount are required.']);
            exit();
        }

        $due_date = date('Y-m-d', strtotime('+1 year'));
        $payment_date = null;
        $reference_number = null;
        $status = 'New';
        $image = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF allowed.']);
                exit();
            }
            $imageName = time() . '_' . basename($_FILES['image']['name']);
            $s3Key = 'uploads/payments/' . $imageName;
            
            // Encrypt and embed the image into a PNG.
            $clearImageData = file_get_contents($_FILES['image']['tmp_name']);
            $cipher = "AES-256-CBC";
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
            $rawKey = getenv('ENCRYPTION_KEY');
            $encryptionKey = hash('sha256', $rawKey, true);
            $encryptedData = openssl_encrypt($clearImageData, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
            $encryptedImageData = $iv . $encryptedData;
            
            $pngImage = embedDataInPng($encryptedImageData, 100);
            $encryptedTempPath = tempnam(sys_get_temp_dir(), 'enc_pay_') . '.png';
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
                $image = str_replace(
                    "https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/",
                    "/s3proxy/",
                    $result['ObjectURL']
                );
            } catch (Aws\Exception\AwsException $e) {
                error_log("Failed to upload image to S3: " . $e->getMessage());
                echo json_encode(['status' => false, 'message' => 'Internal server error']);
                exit();
            }
        }

        $stmt = $conn->prepare("INSERT INTO payments (user_id, payment_type, amount, status, payment_date, due_date, reference_number, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            error_log("Database error in payment push: " . $conn->error);
            echo json_encode(['status' => false, 'message' => 'Internal server error']);
            exit();
        }
        $stmt->bind_param("isdsssss", $user_id, $payment_type, $amount, $status, $payment_date, $due_date, $reference_number, $image);
        if ($stmt->execute()) {
            recordAuditLog($_SESSION['user_id'], 'New Payment Created', "Payment created with type: $payment_type, amount: $amount, due date: $due_date");
            echo json_encode(['status' => true, 'message' => 'Payment pushed successfully.']);
            exit();
        } else {
            error_log("Failed to push payment: " . $stmt->error);
            echo json_encode(['status' => false, 'message' => 'Internal server error']);
            exit();
        }
    }
} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['payment_id']) || !isset($input['status'])) {
        echo json_encode(['status' => false, 'message' => 'Invalid input']);
        exit();
    }
    $payment_id = $input['payment_id'];
    $newStatus = $input['status'];

    $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE payment_id = ?");
    if ($stmt === false) {
        error_log("Database error in PUT request: " . $conn->error);
        echo json_encode(['status' => false, 'message' => 'Internal server error']);
        exit();
    }
    $stmt->bind_param("si", $newStatus, $payment_id);
    if ($stmt->execute()) {
        // Audit log the payment status update.
        recordAuditLog($_SESSION['user_id'], 'Update Payment Status', "Payment ID $payment_id updated to status: $newStatus");

        // If the new status is "Completed", update membership and register for event/training.
        if (strtolower($newStatus) === 'completed') {
            // Retrieve full payment record
            $stmtPayment = $conn->prepare("SELECT user_id, payment_type, event_id, training_id FROM payments WHERE payment_id = ?");
            $stmtPayment->bind_param("i", $payment_id);
            $stmtPayment->execute();
            $resultPayment = $stmtPayment->get_result();
            if ($resultPayment->num_rows === 1) {
                $paymentRecord = $resultPayment->fetch_assoc();
                // For event registration, insert into event_registrations table if not exists.
                if ($paymentRecord['payment_type'] === 'Event Registration' && !empty($paymentRecord['event_id'])) {
                    $stmtReg = $conn->prepare("INSERT IGNORE INTO event_registrations (user_id, event_id) VALUES (?, ?)");
                    $stmtReg->bind_param("ii", $paymentRecord['user_id'], $paymentRecord['event_id']);
                    $stmtReg->execute();
                    $stmtReg->close();
                }
                // For training registration, insert into training_registrations table if not exists.
                elseif ($paymentRecord['payment_type'] === 'Training Registration' && !empty($paymentRecord['training_id'])) {
                    $stmtReg = $conn->prepare("INSERT IGNORE INTO training_registrations (user_id, training_id) VALUES (?, ?)");
                    $stmtReg->bind_param("ii", $paymentRecord['user_id'], $paymentRecord['training_id']);
                    $stmtReg->execute();
                    $stmtReg->close();
                }
            }
            $stmtPayment->close();

            // (Optional) Update membership_status if needed
            $stmtUser = $conn->prepare("SELECT user_id FROM payments WHERE payment_id = ?");
            $stmtUser->bind_param("i", $payment_id);
            $stmtUser->execute();
            $resultUser = $stmtUser->get_result();
            if ($resultUser->num_rows === 1) {
                $row = $resultUser->fetch_assoc();
                $user_id = $row['user_id'];
                $stmtUpdateMember = $conn->prepare("UPDATE members SET membership_status = 'active' WHERE user_id = ?");
                $stmtUpdateMember->bind_param("i", $user_id);
                $stmtUpdateMember->execute();
                $stmtUpdateMember->close();
            }
            $stmtUser->close();
        }

        echo json_encode(['status' => true, 'message' => 'Payment status updated successfully.']);
        exit();
    } else {
        error_log("Failed to update payment status: " . $stmt->error);
        echo json_encode(['status' => false, 'message' => 'Internal server error']);
        exit();
    }
} else {
    echo json_encode(['status' => false, 'message' => 'Unsupported request method']);
    exit();
}
?>
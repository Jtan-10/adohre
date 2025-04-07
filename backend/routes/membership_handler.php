<?php
// Production security settings
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db/db_connect.php';
require_once '../s3config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Ensure our helper function embedDataInPng exists
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
    function embedDataInPng($binaryData, $desiredWidth = 100): GdImage {
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

if ($method === 'POST') {
    $stmt = null;

    try {
        $data = $_POST;

        // Ensure user_id is captured from session
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['status' => false, 'message' => 'User not authenticated.']);
            exit;
        }
        $user_id = $_SESSION['user_id'];

        $permanent_address = !empty($data['permanent_address']) ? $data['permanent_address'] : null;
        $landline = !empty($data['landline']) ? $data['landline'] : null;
        $employment_end = !empty($data['employment_end']) ? $data['employment_end'] : null;
        $others_engagement_specify = !empty($data['others_engagement_specify']) ? $data['others_engagement_specify'] : null;
        $others_expertise_specify = !empty($data['others_expertise_specify']) ? $data['others_expertise_specify'] : null;
        $others_specific_field_specify = !empty($data['others_specific_field_specify']) ? $data['others_specific_field_specify'] : null;
        $others_committee_specify = !empty($data['others_committee_specify']) ? $data['others_committee_specify'] : null;

        // Prepare data from POST
        $name = $data['name'];
        $dob = $data['dob'];
        $sex = $data['sex'];
        $current_address = $data['current_address'];
        $email = $data['email'];
        $mobile = $data['mobile'];
        $place_of_birth = $data['place_of_birth'];
        $marital_status = $data['marital_status'];
        $emergency_contact = $data['emergency_contact'];
        $doh_agency = $data['doh_agency'];
        $doh_address = $data['address'];
        $employment_start = $data['employment_start'];
        $school = $data['school'];
        $degree = $data['degree'];
        $year_graduated = $data['year_graduated'];
        $current_engagement = $data['current_engagement'] === 'Others' ? $others_engagement_specify : $data['current_engagement'];
        $key_expertise = $data['key_expertise'] === 'Others' ? $others_expertise_specify : $data['key_expertise'];
        $specific_field = $data['specific_field'] === 'Others' ? $others_specific_field_specify : $data['specific_field'];
        $special_skills = $data['special_skills'];
        $hobbies = $data['hobbies'];
        $committees = $data['committees'] === 'Others' ? $others_committee_specify : $data['committees'];
        $status = 'Pending';
        $signature = isset($data['signature']) ? $data['signature'] : null;

        // -------------------------
        // New Block: Process Valid ID Image Upload
        // -------------------------
        $valid_id_url = null;  // Default to null if no valid id image is provided.
        if (isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] === UPLOAD_ERR_OK) {
            // Validate file size (max 5MB)
            if ($_FILES['valid_id']['size'] > 5 * 1024 * 1024) {
                echo json_encode(['status' => false, 'message' => 'Valid ID file is too large. Maximum size is 5MB.']);
                exit;
            }
            // Allowed file types
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['valid_id']['type'], $allowedTypes)) {
                echo json_encode(['status' => false, 'message' => 'Invalid file type for Valid ID. Only JPG, PNG, and GIF are allowed.']);
                exit;
            }
            // Generate a unique S3 key (preserve the file extension)
            $ext = pathinfo($_FILES['valid_id']['name'], PATHINFO_EXTENSION);
            $s3Key = 'uploads/valid_ids/' . uniqid() . '.' . $ext;

            // Read file content
            $fileContent = file_get_contents($_FILES['valid_id']['tmp_name']);

            // Encrypt and embed the image into a PNG
            $cipher = "AES-256-CBC";
            $ivlen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivlen);
            $rawKey = getenv('ENCRYPTION_KEY');
            $encryptionKey = hash('sha256', $rawKey, true);
            $encryptedData = openssl_encrypt($fileContent, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
            $encryptedImageData = $iv . $encryptedData;

            $pngImage = embedDataInPng($encryptedImageData, 100);
            $finalEncryptedPngFile = tempnam(sys_get_temp_dir(), 'enc_png_validid_') . '.png';
            imagepng($pngImage, $finalEncryptedPngFile);
            imagedestroy($pngImage);

            try {
                $result = $s3->putObject([
                    'Bucket'      => $bucketName,
                    'Key'         => $s3Key,
                    'Body'        => fopen($finalEncryptedPngFile, 'rb'),
                    'ACL'         => 'public-read',
                    'ContentType' => 'image/png'
                ]);
                $valid_id_url = str_replace("https://adohre-bucket.s3.ap-southeast-1.amazonaws.com/", "/s3proxy/", $result['ObjectURL']);
            } catch (Aws\Exception\AwsException $e) {
                error_log('S3 Upload Error (Valid ID): ' . $e->getMessage());
                echo json_encode(['status' => false, 'message' => 'Failed to upload valid ID image.']);
                exit;
            }
            @unlink($finalEncryptedPngFile);
        }
        // -------------------------
        // End Valid ID Block
        // -------------------------

        // -------------------------
        // Prepare SQL query (UPDATED to include valid_id_url)
        // -------------------------
        $stmt = $conn->prepare("
        INSERT INTO membership_applications (
            user_id, name, dob, sex, current_address, permanent_address, email, landline, mobile,
            place_of_birth, marital_status, emergency_contact, doh_agency, doh_address,
            employment_start, employment_end, school, degree, year_graduated, current_engagement,
            key_expertise, specific_field, special_skills, hobbies, committees, signature, status, valid_id_url
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['status' => false, 'message' => 'An error occurred.']);
            exit;
        }

        // Bind the variables (28 parameters: 1 integer, 27 strings)
        $stmt->bind_param(
            'isssssssssssssssssssssssssss', 
            $user_id, $name, $dob, $sex, $current_address, $permanent_address, $email, $landline, $mobile,
            $place_of_birth, $marital_status, $emergency_contact, $doh_agency, $doh_address,
            $employment_start, $employment_end, $school, $degree, $year_graduated, $current_engagement,
            $key_expertise, $specific_field, $special_skills, $hobbies, $committees, $signature, $status, $valid_id_url
        );

        if ($stmt->execute()) {
            // Audit log: record membership application submission.
            recordAuditLog($user_id, 'Submit Membership Application', "Application submitted for $name ($email).");
            echo json_encode(['status' => true, 'message' => 'Application submitted successfully!']);
        } else {
            error_log("Execution failed: " . $stmt->error);
            echo json_encode(['status' => false, 'message' => 'An error occurred.']);
        }
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        echo json_encode(['status' => false, 'message' => 'An error occurred.']);
    } finally {
        if ($stmt) {
            $stmt->close();
        }
        $conn->close();
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method not allowed.']);
}
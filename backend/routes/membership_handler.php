<?php
// Production security settings
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db/db_connect.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

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

        // Prepare data
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

        // Prepare SQL query
        $stmt = $conn->prepare("
        INSERT INTO membership_applications (
            user_id, name, dob, sex, current_address, permanent_address, email, landline, mobile,
            place_of_birth, marital_status, emergency_contact, doh_agency, doh_address,
            employment_start, employment_end, school, degree, year_graduated, current_engagement,
            key_expertise, specific_field, special_skills, hobbies, committees, signature, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['status' => false, 'message' => 'An error occurred.']);
            exit;
        }

        // Bind the variables (27 parameters: 1 integer, 26 strings)
        $stmt->bind_param(
            'issssssssssssssssssssssssss', 
            $user_id, $name, $dob, $sex, $current_address, $permanent_address, $email, $landline, $mobile,
            $place_of_birth, $marital_status, $emergency_contact, $doh_agency, $doh_address,
            $employment_start, $employment_end, $school, $degree, $year_graduated, $current_engagement,
            $key_expertise, $specific_field, $special_skills, $hobbies, $committees, $signature, $status
        );

        if ($stmt->execute()) {
            // Audit log: record membership application submission.
            // recordAuditLog() is defined in db_connect.php.
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
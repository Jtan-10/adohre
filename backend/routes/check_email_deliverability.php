<?php
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['email'])) {
    echo json_encode(['deliverable' => false, 'message' => 'Email not provided.']);
    exit();
}
$email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    echo json_encode(['deliverable' => false, 'message' => 'Invalid email format.']);
    exit();
}
list(, $domain) = explode('@', $email);
if (getmxrr($domain, $mxhosts)) {
    echo json_encode(['deliverable' => true]);
} else {
    echo json_encode(['deliverable' => false, 'message' => 'No MX records found.']);
}

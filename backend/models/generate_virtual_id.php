<?php
http_response_code(410);
header('Content-Type: application/json');
echo json_encode([
    'status' => false,
    'message' => 'Virtual ID feature has been removed.'
]);

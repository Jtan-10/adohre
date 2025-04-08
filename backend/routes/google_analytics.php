<?php
// File: /capstone-php/backend/routes/google_analytics.php

// Optionally include your DB connection (adjust path as needed)
require_once __DIR__ . '/../backend/db/db_connect.php';

// Include the Composer autoloader to load Google API client libraries.
require_once __DIR__ . '/../../vendor/autoload.php'; // Adjust this path if necessary.

// Set the content type to JSON.
header('Content-Type: application/json');

// Validate that the 'action' parameter is 'get_analytics'.
if (!isset($_GET['action']) || $_GET['action'] !== 'get_analytics') {
    echo json_encode(['status' => false, 'message' => 'Invalid action']);
    exit;
}

// Validate the 'sheet_id' parameter.
if (!isset($_GET['sheet_id']) || empty($_GET['sheet_id'])) {
    echo json_encode(['status' => false, 'message' => 'No sheet id provided']);
    exit;
}
$spreadsheetId = $_GET['sheet_id'];  // Use the manually provided Google Sheet ID

// Create and configure a new Google Client.
$client = new Google_Client();
$client->setApplicationName('Your App Name'); // Replace with your application name.
$client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);

// Authenticate using your service account credentials.
// Adjust the path to point to your credentials JSON file.
$client->setAuthConfig(__DIR__ . '/../../path/to/credentials.json');
$client->setAccessType('offline');

// Instantiate the Google Sheets service.
$service = new Google_Service_Sheets($client);

// Define the range where responses are stored.
// Typically, the sheet is named "Form Responses 1" (adjust if needed).
$range = 'Form Responses 1';

try {
    // Retrieve data from the spreadsheet.
    $response = $service->spreadsheets_values->get($spreadsheetId, $range);
    $values = $response->getValues();

    // Calculate the total number of responses (assuming the first row is the header).
    $totalResponses = !empty($values) ? (count($values) - 1) : 0;
    
    // Optional: Calculate an average score if numeric scores are stored.
    // This example assumes scores are in the fourth column (index 3). Adjust as needed.
    $sumScores = 0;
    $scoreCount = 0;
    $scoreColumnIndex = 3;
    if (!empty($values) && count($values) > 1) {
        for ($i = 1; $i < count($values); $i++) {
            if (isset($values[$i][$scoreColumnIndex]) && is_numeric($values[$i][$scoreColumnIndex])) {
                $sumScores += floatval($values[$i][$scoreColumnIndex]);
                $scoreCount++;
            }
        }
    }
    $avgScore = ($scoreCount > 0) ? $sumScores / $scoreCount : 0;

    // Return the analytics data as JSON.
    echo json_encode([
        'status' => true,
        'analytics' => [
            'total_responses' => $totalResponses,
            'avg_score'       => round($avgScore, 2)
        ]
    ]);
    exit;
} catch (Exception $e) {
    // Return an error message if something goes wrong.
    echo json_encode(['status' => false, 'message' => 'Error fetching analytics: ' . $e->getMessage()]);
    exit;
}
?>
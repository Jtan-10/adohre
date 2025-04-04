<?php
require_once '../db/db_connect.php';
require_once '../../vendor/autoload.php'; // For PDF and Excel libraries like TCPDF and PhpSpreadsheet
session_start();

// Determine export format
$format = $_GET['format'] ?? 'csv';
// Validate export format
$allowedFormats = ['csv', 'pdf', 'excel'];
if (!in_array(strtolower($format), $allowedFormats)) {
    // Default to CSV if an invalid format is provided
    $format = 'csv';
}

try {
    // Ensure user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        echo json_encode(['status' => false, 'message' => 'User not logged in.']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $userRole = ucfirst($_SESSION['role']); // Capitalize the role

    // Fetch user details
    $userQuery = "SELECT first_name, last_name FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($userQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userResult = $stmt->get_result();
    $user = $userResult->fetch_assoc();

    if (!$user) {
        echo json_encode(['status' => false, 'message' => 'User not found.']);
        exit;
    }

    $userName = $user['first_name'] . ' ' . $user['last_name'];

    // Fetch data for the aggregated metrics
    $metricsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'admin') AS admin_count,
            (SELECT COUNT(*) FROM users WHERE role = 'member') AS member_count,
            (SELECT COUNT(*) FROM members WHERE membership_status = 'active') AS active_members,
            (SELECT COUNT(*) FROM events WHERE date >= CURDATE()) AS upcoming_events,
            (SELECT COUNT(*) FROM events WHERE date < CURDATE()) AS finished_events,
            (SELECT COUNT(*) FROM trainings WHERE schedule >= NOW()) AS upcoming_trainings,
            (SELECT COUNT(*) FROM trainings WHERE schedule < NOW()) AS finished_trainings,
            (SELECT COUNT(*) FROM announcements) AS total_announcements,
            (SELECT COUNT(*) FROM events) AS total_events,
            (SELECT COUNT(*) FROM trainings) AS total_trainings,
            (SELECT SUM(amount) FROM payments) AS total_revenue,
            (SELECT COUNT(*) FROM event_registrations) AS joined_events,
            (SELECT COUNT(*) FROM training_registrations) AS joined_trainings
    ";
    $metricsResult = $conn->query($metricsQuery);
    $metrics = $metricsResult->fetch_assoc();

    // Fetch detailed datasets
    $users = $conn->query("SELECT first_name, last_name, email, role FROM users")->fetch_all(MYSQLI_ASSOC);
    $events = $conn->query("SELECT title, date, location FROM events")->fetch_all(MYSQLI_ASSOC);
    $trainings = $conn->query("SELECT title, schedule, capacity FROM trainings")->fetch_all(MYSQLI_ASSOC);
    $announcements = $conn->query("SELECT text, created_at FROM announcements")->fetch_all(MYSQLI_ASSOC);

    $datasets = [
        'metrics' => $metrics,
        'users' => $users,
        'events' => $events,
        'trainings' => $trainings,
        'announcements' => $announcements,
    ];

    // Record an audit log for the export event.
    recordAuditLog($userId, "Export Report", "User exported report in " . strtoupper($format) . " format.");

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report.csv"');
        $output = fopen('php://output', 'w');

        // Add a detailed header
        fputcsv($output, ['ADOHRE System Report']);
        fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, ['Exported by: ' . $userName . ' (' . $userRole . ')']);
        fputcsv($output, []); // Blank line

        foreach ($datasets as $section => $rows) {
            fputcsv($output, [ucfirst($section)]); // Section header
            if ($section === 'metrics') {
                foreach ($rows as $key => $value) {
                    fputcsv($output, [ucfirst(str_replace('_', ' ', $key)), $value]);
                }
            } else {
                if (!empty($rows)) {
                    fputcsv($output, array_keys($rows[0])); // Column headers
                    foreach ($rows as $row) {
                        fputcsv($output, $row);
                    }
                } else {
                    fputcsv($output, ['No data available']);
                }
            }
            fputcsv($output, []); // Add an empty line between sections
        }

        // Add a footer
        fputcsv($output, []);
        fputcsv($output, ['End of Report']);
        fclose($output);
        exit;
    } elseif ($format === 'pdf') {
        // Set no-cache headers for the PDF output
        header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");
        header('Content-Type: application/pdf');

        // PDF Export using TCPDF
        $pdf = new \TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($userName);
        $pdf->SetTitle('ADOHRE Detailed Report');
        $pdf->SetSubject('System Report');
        $pdf->SetKeywords('report, PDF');

        // Configure page and auto-break
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // Add custom header page
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 0, 'ADOHRE System Report', 0, 1, 'C');
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 0, 'Generated on: ' . date('Y-m-d H:i:s') . ' | Exported by: ' . $userName . ' (' . $userRole . ')', 0, 1, 'L');
        $pdf->Ln(10);

        // ----------------------
        // Charts Overview Section
        // ----------------------
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 0, 'Reports Charts Overview', 0, 1, 'C');
        $pdf->Ln(10);

        // Define chart keys and titles.
        // We will check if a Base64 image was passed via POST for each chart.
        $charts = [
            'User Statistics'        => 'userChart',
            'Event Statistics'       => 'eventChart',
            'Training Statistics'    => 'trainingChart',
            'Revenue Statistics'     => 'revenueChart',
            'Registrations Overview' => 'registrationsChart',
            'New Users Trend'        => 'newUsersChart',
        ];
        foreach ($charts as $title => $chartKey) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 0, $title, 0, 1, 'L');
            $pdf->Ln(5);

            $chartImage = false;
            // Check if the chart image data is provided via POST (from reports.js)
            if (isset($_POST[$chartKey])) {
                $postedData = $_POST[$chartKey];
                // Log the first 50 characters to verify MIME type and data
                error_log("DEBUG: Data for {$chartKey} starts with: " . substr($postedData, 0, 50));
                error_log("DEBUG: POST data length for {$chartKey} = " . strlen($postedData));
                if (preg_match('/^data:image\/png;base64,/', $postedData)) {
                    // Remove the data URI scheme and decode
                    $decodedData = base64_decode(str_replace('data:image/png;base64,', '', $postedData));
                    if ($decodedData !== false && strlen($decodedData) > 0) {
                        // Optionally, write to a file for manual inspection:
                        // file_put_contents('/path/to/debug_' . $chartKey . '.png', $decodedData);
                        // Use the '@' prefix to tell TCPDF to load image from string
                        $chartImage = '@' . $decodedData;
                        $imgFormat = 'PNG';
                    } else {
                        error_log("DEBUG: Decoded data for {$chartKey} is empty.");
                    }
                } else {
                    error_log("DEBUG: Data for {$chartKey} did not match the expected format.");
                }
            }

            // Use fallback file if POST data is not available
            if (!$chartImage) {
                $fallbackPath = "/opt/lampp/htdocs/capstone-php/admin/charts/{$chartKey}.png";
                if (file_exists($fallbackPath)) {
                    $chartImage = $fallbackPath;
                    $imgFormat = 'PNG';
                }
            }

            if ($chartImage) {
                // Adjust the width and height as needed (example: 100x60 mm)
                $pdf->Image($chartImage, '', '', 100, 60, $imgFormat, '', 'T', false, 300);
            } else {
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 0, 'Chart image not available.', 0, 1, 'L');
            }
            $pdf->Ln(10);
        }

        // ----------------------
        // Detailed Datasets Section
        // ----------------------
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 0, 'Detailed Report', 0, 1, 'C');
        $pdf->Ln(10);

        foreach ($datasets as $section => $rows) {
            $pdf->writeHTML("<h3>" . ucfirst($section) . "</h3>", true, false, true, false, 'C');
            if ($section === 'metrics') {
                $html = '<table border="1" cellpadding="5" cellspacing="0"><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
                foreach ($rows as $key => $value) {
                    $html .= "<tr><td style='padding:5px;'>" . ucfirst(str_replace('_', ' ', $key)) . "</td><td style='padding:5px;'>" . htmlspecialchars($value) . "</td></tr>";
                }
                $html .= '</tbody></table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            } else {
                if (!empty($rows)) {
                    $html = '<table border="1" cellpadding="5" cellspacing="0"><thead><tr>';
                    foreach (array_keys($rows[0]) as $header) {
                        $html .= '<th style="padding:5px;">' . htmlspecialchars($header) . '</th>';
                    }
                    $html .= '</tr></thead><tbody>';
                    foreach ($rows as $row) {
                        $html .= '<tr>';
                        foreach ($row as $value) {
                            $html .= '<td style="padding:5px;">' . htmlspecialchars($value) . '</td>';
                        }
                        $html .= '</tr>';
                    }
                    $html .= '</tbody></table>';
                    $pdf->writeHTML($html, true, false, true, false, '');
                } else {
                    $html = '<p>No data available</p>';
                    $pdf->writeHTML($html, true, false, true, false, '');
                }
            }
            $pdf->Ln(10);
        }

        $pdf->Output('report.pdf', 'I');
        exit;
    } elseif ($format === 'excel') {
        // Excel Export using PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $row = 1;

        // Add a detailed header
        $sheet->setCellValue("A{$row}", 'ADOHRE System Report');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue("A{$row}", 'Generated on: ' . date('Y-m-d H:i:s'));
        $row++;
        $sheet->setCellValue("A{$row}", 'Exported by: ' . $userName . ' (' . $userRole . ')');
        $row += 2;

        foreach ($datasets as $section => $rows) {
            $sheet->setCellValue("A{$row}", ucfirst($section));
            $row++;
            if ($section === 'metrics') {
                $sheet->setCellValue("A{$row}", 'Metric');
                $sheet->setCellValue("B{$row}", 'Value');
                $row++;
                foreach ($rows as $key => $value) {
                    $sheet->setCellValue("A{$row}", ucfirst(str_replace('_', ' ', $key)));
                    $sheet->setCellValue("B{$row}", $value);
                    $row++;
                }
            } else {
                if (!empty($rows)) {
                    $col = 'A';
                    foreach (array_keys($rows[0]) as $header) {
                        $sheet->setCellValue("{$col}{$row}", ucfirst($header));
                        $col++;
                    }
                    $row++;
                    foreach ($rows as $rowData) {
                        $col = 'A';
                        foreach ($rowData as $value) {
                            $sheet->setCellValue("{$col}{$row}", $value);
                            $col++;
                        }
                        $row++;
                    }
                } else {
                    $sheet->setCellValue("A{$row}", 'No data available');
                    $row++;
                }
            }
            $row++; // Add an empty row between sections
        }

        // Add a footer
        $sheet->setCellValue("A{$row}", 'End of Report');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="report.xlsx"');
        $writer->save('php://output');
        exit;
    } else {
        throw new Exception('Invalid format requested.');
    }
} catch (Exception $e) {
    // Log the actual error message internally if needed
    error_log("ERROR: " . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'An error occurred. Please try again later.']);
    exit;
}
?>
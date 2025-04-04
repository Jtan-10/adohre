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

        // Debug info capture
        $debugInfo = "Chart data processing for PDF export:\n";
        
        // PDF Export using TCPDF
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($userName);
        $pdf->SetTitle('ADOHRE Detailed Report');
        $pdf->SetSubject('System Report');
        $pdf->SetKeywords('report, PDF');

        // Set document information
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);

        // Add custom header page
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'ADOHRE System Report', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Exported by: ' . $userName . ' (' . $userRole . ')', 0, 1, 'C');
        $pdf->Ln(10);

        // ----------------------
        // Charts Overview Section
        // ----------------------
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Reports Charts Overview', 0, 1, 'C');
        $pdf->Ln(5);

        $charts = [
            'User Statistics'        => 'userChart',
            'Event Statistics'       => 'eventChart',
            'Training Statistics'    => 'trainingChart',
            'Revenue Statistics'     => 'revenueChart',
            'Registrations Overview' => 'registrationsChart',
            'New Users Trend'        => 'newUsersChart',
        ];

        $currentY = $pdf->GetY();
        $leftMargin = 15; // x coordinate

        foreach ($charts as $title => $chartKey) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, $title, 0, 1, 'L');

            if (isset($_POST[$chartKey]) && !empty($_POST[$chartKey])) {
                $postedData = $_POST[$chartKey];
                $debugInfo .= "$chartKey: " . strlen($postedData) . " bytes\n";
                
                if (preg_match('/^data:image\/png;base64,/', $postedData)) {
                    try {
                        $base64Image = str_replace('data:image/png;base64,', '', $postedData);
                        $imageData = base64_decode($base64Image);
                        
                        if ($imageData !== false) {
                            // Create a temporary file to store the image
                            $tempFile = tempnam(sys_get_temp_dir(), 'chart_');
                            file_put_contents($tempFile, $imageData);
                            $fileSize = filesize($tempFile);
                            error_log("DEBUG: Temporary file $tempFile size: " . $fileSize . " bytes");

                            // Place the image at (15, currentY) with width 160mm and height 80mm, using 96 DPI
                            $pdf->Image($tempFile, $leftMargin, $currentY, 160, 80, 'PNG', '', '', false, 96);
                            
                            // Advance currentY by 90mm to leave some space below the image
                            $currentY += 90;
                            $pdf->SetY($currentY);
                            
                            unlink($tempFile);
                            $debugInfo .= "  - Successfully processed image via temp file\n";
                        } else {
                            $pdf->Cell(0, 5, 'Failed to decode chart image.', 0, 1, 'L');
                            $debugInfo .= "  - Failed to decode base64 image data\n";
                        }
                    } catch (Exception $e) {
                        $pdf->Cell(0, 5, 'Error processing chart image.', 0, 1, 'L');
                        $debugInfo .= "  - Exception: " . $e->getMessage() . "\n";
                    }
                } else {
                    $pdf->Cell(0, 5, 'Invalid chart data format.', 0, 1, 'L');
                    $debugInfo .= "  - Invalid data format (not base64 PNG)\n";
                }
            } else {
                $debugInfo .= "$chartKey: No data received\n";
                $pdf->Cell(0, 5, 'Chart data not available.', 0, 1, 'L');
            }
            
            $pdf->Ln(5); // Add a little extra space after each chart section
        }

        
        // Log debug info
        error_log($debugInfo);

        // ----------------------
        // Detailed Datasets Section
        // ----------------------
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Detailed Report', 0, 1, 'C');
        $pdf->Ln(5);

        foreach ($datasets as $section => $rows) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, ucfirst($section), 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            
            if ($section === 'metrics') {
                $html = '<table border="1" cellpadding="5" cellspacing="0" width="100%">
                         <thead><tr style="background-color:#f0f0f0;"><th width="70%">Metric</th><th width="30%">Value</th></tr></thead><tbody>';
                foreach ($rows as $key => $value) {
                    $html .= "<tr><td>" . ucfirst(str_replace('_', ' ', $key)) . "</td><td>" . htmlspecialchars($value ?? 'N/A') . "</td></tr>";
                }
                $html .= '</tbody></table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            } else {
                if (!empty($rows)) {
                    // Create table header
                    $html = '<table border="1" cellpadding="5" cellspacing="0" width="100%"><thead><tr style="background-color:#f0f0f0;">';
                    foreach (array_keys($rows[0]) as $header) {
                        $html .= '<th>' . htmlspecialchars(ucfirst($header)) . '</th>';
                    }
                    $html .= '</tr></thead><tbody>';
                    
                    // Create table rows
                    foreach ($rows as $row) {
                        $html .= '<tr>';
                        foreach ($row as $value) {
                            $html .= '<td>' . htmlspecialchars($value ?? 'N/A') . '</td>';
                        }
                        $html .= '</tr>';
                    }
                    $html .= '</tbody></table>';
                    $pdf->writeHTML($html, true, false, true, false, '');
                } else {
                    $pdf->Cell(0, 5, 'No data available', 0, 1, 'L');
                }
            }
            $pdf->Ln(5); // Add space between sections
        }

        // Add a footer
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'End of Report - Generated by ADOHRE System', 0, 0, 'C');

        // Output the PDF
        $pdf->Output('ADOHRE_Report.pdf', 'I');
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
    // Log the actual error message internally
    error_log("ERROR: " . $e->getMessage());
    echo json_encode(['status' => false, 'message' => 'An error occurred. Please try again later.']);
    exit;
}

// Helper function to record audit logs (assumed to exist elsewhere)
function recordAuditLog($userId, $action, $details) {
    // This is a placeholder function - implementation assumed to exist elsewhere
    // For completeness, you might want to implement this if it doesn't exist
}
?>
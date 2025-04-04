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

        // ----------------------------------------------------------------------
        // 1) Define a custom TCPDF class for a custom footer (or header if needed)
        // ----------------------------------------------------------------------
        class CustomPDF extends \TCPDF {
            public function Footer() {
                $this->SetY(-15);
                $this->SetFont('helvetica', 'I', 8);
                $this->Cell(
                    0,
                    10,
                    'ADOHRE System Report - Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(),
                    0,
                    false,
                    'C'
                );
            }
        }

        // ---------------------------------------------------
        // 2) Instantiate ONLY the CustomPDF object one time
        // ---------------------------------------------------
        $pdf = new CustomPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($userName);
        $pdf->SetTitle('ADOHRE Detailed Report');
        $pdf->SetSubject('System Report');
        $pdf->SetKeywords('report, PDF');

        // Set document information and styling
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(true);

        // Set default font
        $pdf->SetFont('helvetica', '', 10);

        // ---------------------------------------------------
        // 3) First page: Title Page
        // ---------------------------------------------------
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->Cell(0, 15, 'ADOHRE System Report', 0, 1, 'C');

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Comprehensive System Analytics', 0, 1, 'C');

        // Position for some info
        $pdf->SetY(100);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
        $pdf->Cell(0, 8, 'Exported by: ' . $userName . ' (' . $userRole . ')', 0, 1, 'C');

        $pdf->SetY(150);
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 8, 'This report contains confidential information', 0, 1, 'C');
        $pdf->Cell(0, 8, 'Please handle with appropriate care', 0, 1, 'C');

        // ---------------------------------------------------
        // 4) Charts Overview Section
        // ---------------------------------------------------
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(0, 10, 'Reports Charts Overview', 0, 1, 'C', true);
        $pdf->Ln(5);

        // Charts to display (map them to your $_POST data keys)
        $charts = [
            'User Statistics'        => 'userChart',
            'Event Statistics'       => 'eventChart',
            'Training Statistics'    => 'trainingChart',
            'Revenue Statistics'     => 'revenueChart',
            'Registrations Overview' => 'registrationsChart',
            'New Users Trend'        => 'newUsersChart',
        ];

        // Layout settings for a 2x3 grid
        $chartWidth  = 80;  // chart display width (mm)
        $chartHeight = 70;  // chart display height (mm)
        $colSpacing  = 10;
        $rowSpacing  = 15;
        $marginLeft  = 15;
        $marginTop   = $pdf->GetY() + 5;

        $col = 0;
        $row = 0;
        $maxCols = 2; // two charts per row

        foreach ($charts as $title => $chartKey) {
            // Calculate X and Y positions for the chart
            $x = $marginLeft + ($col * ($chartWidth + $colSpacing));
            $y = $marginTop + ($row * ($chartHeight + $rowSpacing));

            // Check for page overflow and add a new page if needed
            if ($y + $chartHeight > $pdf->getPageHeight() - 20) {
                $pdf->AddPage();
                $marginTop = 20; // reset top margin on new page
                $y = $marginTop;
                $row = 0;
            }

            // Chart title
            $pdf->SetXY($x, $y);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell($chartWidth, 7, $title, 0, 2, 'L');
            $y += 8; // space after title

            // Place the chart if data is available
            if (isset($_POST[$chartKey]) && !empty($_POST[$chartKey])) {
                $postedData = $_POST[$chartKey];

                if (preg_match('/^data:image\/png;base64,/', $postedData)) {
                    try {
                        // Remove the data URI prefix and decode
                        $base64Image = str_replace('data:image/png;base64,', '', $postedData);
                        $imageData   = base64_decode($base64Image);
                        
                        if ($imageData !== false && strlen($imageData) > 0) {
                            // Use the inline method to load the image directly into TCPDF
                            $chartImage = '@' . $imageData;
                            // Render the image using inline data at 96 DPI (adjust DPI if needed)
                            $pdf->Image($chartImage, $x, $y, $chartWidth, $chartHeight, 'PNG', '', 'T', true, 96);
                        } else {
                            $pdf->SetXY($x, $y);
                            $pdf->SetFont('helvetica', '', 9);
                            $pdf->Cell($chartWidth, 5, 'Image decoding failed', 0, 1, 'L');
                        }
                    } catch (Exception $e) {
                        $pdf->SetXY($x, $y);
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell($chartWidth, 5, 'Error processing chart', 0, 1, 'L');
                    }
                } else {
                    $pdf->SetXY($x, $y);
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->Cell($chartWidth, 5, 'Invalid image format', 0, 1, 'L');
                }
            } else {
                // If no data is available, show a placeholder box
                $pdf->SetXY($x, $y);
                $pdf->SetDrawColor(200, 200, 200);
                $pdf->SetFillColor(245, 245, 245);
                $pdf->Cell($chartWidth, $chartHeight, 'Chart data not available', 1, 0, 'C', true);
            }

            // Move to next column or wrap to next row
            $col++;
            if ($col >= $maxCols) {
                $col = 0;
                $row++;
            }
        }

        // ----------------------
        // Executive Summary
        // ----------------------
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(0, 10, 'Executive Summary', 0, 1, 'C', true);
        $pdf->Ln(5);
        
        // Brief summary paragraph
        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 6, 'This report provides a comprehensive overview of system metrics and activities. The data covers various aspects including user statistics, events, trainings, announcements, and financial metrics.', 0, 'L');
        $pdf->Ln(5);
        
        // Key metrics highlights
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Key Metrics Highlights', 0, 1, 'L');
        $pdf->Ln(2);
        
        // Highlight boxes for key metrics
        $boxWidth = 85;
        $boxHeight = 40;
        $spacing = 10;
        $startX = ($pdf->getPageWidth() - 2*$boxWidth - $spacing) / 2;
        $startY = $pdf->GetY();
        
        // Define key metrics to highlight
        $keyMetrics = [
            ['Total Users', $metrics['total_users'] ?? 0],
            ['Active Members', $metrics['active_members'] ?? 0],
            ['Upcoming Events', $metrics['upcoming_events'] ?? 0],
            ['Total Revenue', '$' . number_format($metrics['total_revenue'] ?? 0, 2)]
        ];
        
        $colors = [
            [230, 230, 250], // Light lavender
            [220, 240, 220], // Light green
            [240, 230, 220], // Light orange
            [230, 240, 250]  // Light blue
        ];
        
        // Draw metric boxes
        for ($i = 0; $i < 4; $i++) {
            $col = $i % 2;
            $row = floor($i / 2);
            
            $x = $startX + $col * ($boxWidth + $spacing);
            $y = $startY + $row * ($boxHeight + 5);
            
            $pdf->SetFillColor($colors[$i][0], $colors[$i][1], $colors[$i][2]);
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->RoundedRect($x, $y, $boxWidth, $boxHeight, 3.50, '1111', 'DF');
            
            // Title
            $pdf->SetXY($x + 5, $y + 5);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell($boxWidth - 10, 8, $keyMetrics[$i][0], 0, 1, 'C');
            
            // Value
            $pdf->SetXY($x + 5, $y + 18);
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell($boxWidth - 10, 10, $keyMetrics[$i][1], 0, 1, 'C');
        }
        
        $pdf->SetY($startY + 2*$boxHeight + 15);

        // ----------------------
        // Detailed Datasets Section - IMPROVED FORMATTING
        // ----------------------
        foreach ($datasets as $section => $rows) {
            // Start each major section on a new page for cleaner layout
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(0, 10, ucfirst($section) . ' Detail', 0, 1, 'C', true);
            $pdf->Ln(5);
            
            if ($section === 'metrics') {
                // Create a styled metrics table with alternating row colors
                $pdf->SetFont('helvetica', 'B', 10);
                $html = '<table border="0" cellpadding="5" cellspacing="0" width="100%" style="border-collapse: collapse;">';
                $html .= '<thead><tr style="background-color:#4682B4; color: white;"><th width="60%">Metric</th><th width="40%">Value</th></tr></thead><tbody>';
                
                $rowCount = 0;
                foreach ($rows as $key => $value) {
                    // Format value based on type
                    if (strpos($key, 'revenue') !== false && $value) {
                        $formattedValue = '$' . number_format($value, 2);
                    } else {
                        $formattedValue = $value ?? 'N/A';
                    }
                    
                    // Alternate row colors
                    $bgColor = ($rowCount % 2 === 0) ? '#f9f9f9' : '#ffffff';
                    $html .= '<tr style="background-color:' . $bgColor . ';">';
                    $html .= '<td style="border-bottom: 1px solid #dddddd;">' . ucfirst(str_replace('_', ' ', $key)) . '</td>';
                    $html .= '<td style="border-bottom: 1px solid #dddddd;">' . htmlspecialchars($formattedValue) . '</td>';
                    $html .= '</tr>';
                    $rowCount++;
                }
                
                $html .= '</tbody></table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            } else {
                if (!empty($rows)) {
                    // Build section description
                    $sectionDescriptions = [
                        'users' => 'List of all system users with their roles and contact information.',
                        'events' => 'All events with their scheduled dates and locations.',
                        'trainings' => 'Training sessions with their schedules and capacity information.',
                        'announcements' => 'System announcements with their creation timestamps.'
                    ];
                    
                    if (isset($sectionDescriptions[$section])) {
                        $pdf->SetFont('helvetica', '', 10);
                        $pdf->MultiCell(0, 5, $sectionDescriptions[$section], 0, 'L');
                        $pdf->Ln(3);
                    }
                    
                    // Create styled table with improved formatting
                    $pdf->SetFont('helvetica', '', 9); // Smaller font for tables with potentially more data
                    
                    // Generate header colors based on section
                    switch($section) {
                        case 'users': $headerColor = '#4682B4'; break;       // Blue
                        case 'events': $headerColor = '#2E8B57'; break;      // Sea Green
                        case 'trainings': $headerColor = '#8B4513'; break;   // Brown
                        case 'announcements': $headerColor = '#4B0082'; break; // Indigo
                        default: $headerColor = '#708090'; break;            // Slate Gray
                    }
                    
                    // Start HTML Table
                    $html = '<table border="0" cellpadding="4" cellspacing="0" width="100%" style="border-collapse: collapse;">';
                    $html .= '<thead><tr style="background-color:' . $headerColor . '; color: white;">';
                    
                    // Create table header with proper styling
                    foreach (array_keys($rows[0]) as $header) {
                        $html .= '<th>' . htmlspecialchars(ucfirst($header)) . '</th>';
                    }
                    $html .= '</tr></thead><tbody>';
                    
                    // Create table rows with alternating background colors
                    $rowCount = 0;
                    foreach ($rows as $row) {
                        $bgColor = ($rowCount % 2 === 0) ? '#f9f9f9' : '#ffffff';
                        $html .= '<tr style="background-color:' . $bgColor . ';">';
                        
                        foreach ($row as $value) {
                            $html .= '<td style="border-bottom: 1px solid #dddddd;">' . htmlspecialchars($value ?? 'N/A') . '</td>';
                        }
                        
                        $html .= '</tr>';
                        $rowCount++;
                    }
                    
                    $html .= '</tbody></table>';
                    $pdf->writeHTML($html, true, false, true, false, '');
                } else {
                    $pdf->SetFont('helvetica', 'I', 10);
                    $pdf->Cell(0, 10, 'No data available for this section', 0, 1, 'C');
                }
            }
        }

        // Final summary page
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Report Summary', 0, 1, 'C');
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 5, 'This report was generated automatically from the ADOHRE system database. The information contained within represents a snapshot of system data at the time of export. For any questions regarding this report, please contact the system administrator.', 0, 'L');
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'Report Generation Information:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(40, 5, 'Date and Time:', 0, 0, 'L');
        $pdf->Cell(0, 5, date('Y-m-d H:i:s'), 0, 1, 'L');
        $pdf->Cell(40, 5, 'Generated By:', 0, 0, 'L');
        $pdf->Cell(0, 5, $userName . ' (' . $userRole . ')', 0, 1, 'L');
        $pdf->Cell(40, 5, 'Document Format:', 0, 0, 'L');
        $pdf->Cell(0, 5, 'PDF Export', 0, 1, 'L');

        // Output the PDF
        $pdf->Output('ADOHRE_Report.pdf', 'I');
        exit;
    } elseif ($format === 'excel') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="report.xlsx"');
        
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

?>
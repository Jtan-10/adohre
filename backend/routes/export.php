<?php
// Enable error reporting for debugging
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1);     // But do log them
error_reporting(E_ALL);       // Report all error types

try {
    // Check if session is already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require_once '../db/db_connect.php';
    require_once '../../vendor/autoload.php'; // For PDF and Excel libraries
    
    // Check if the user is logged in and is an admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        error_log("DEBUG: Unauthorized export attempt detected. Session data: " . print_r($_SESSION, true));
        header('Content-Type: application/json');
        echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
        exit;
    }

    // Determine export format
    $format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';
    // Validate export format
    $allowedFormats = ['csv', 'pdf', 'excel'];
    if (!in_array($format, $allowedFormats)) {
        // Default to CSV if an invalid format is provided
        $format = 'csv';
    }
} catch (Exception $e) {
    error_log("Export initialization error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'message' => 'Error initializing export process.']);
    exit;
}

// Get filters from the request parameters
$requestFilters = [];
if (isset($_GET['filters']) && !empty($_GET['filters'])) {
    $requestFilters = explode(',', $_GET['filters']);
} elseif (isset($_POST['filters']) && !empty($_POST['filters'])) {
    $requestFilters = explode(',', $_POST['filters']);
}

// Define all available sections for filtering
$allSections = [
    'user-stats',
    'event-stats',
    'training-stats',
    'revenue-stats',
    'registration-overview',
    'new-users-trend',
    'additional-analytics',
    'users-table',
    'events-table',
    'trainings-table',
    'announcements-table'
];

// If no filters specified, include all sections by default
if (empty($requestFilters)) {
    $activeFilters = $allSections;
} else {
    // Ensure we only include valid filters
    $activeFilters = array_intersect($requestFilters, $allSections);
}

// Helper function to check if a section should be included
function shouldIncludeSection($sectionKey) {
    global $activeFilters;
    return in_array($sectionKey, $activeFilters);
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

    // Fetch data for the aggregated metrics - Using prepared statements for better security
    $metricsQuery = "
        SELECT 
            (SELECT COUNT(*) FROM chat_messages) AS total_chat_messages,
            (SELECT COUNT(*) FROM consultations) AS total_consultations,
            (SELECT COUNT(*) FROM certificates) AS total_certificates,
            (SELECT COUNT(*) FROM membership_applications) AS membership_applications,
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
            (SELECT COUNT(*) FROM training_registrations) AS joined_trainings,
            (SELECT COUNT(*) FROM news) AS total_news,
            (SELECT COUNT(*) FROM payments) AS total_payments
    ";
    $metricsResult = $conn->query($metricsQuery);
    if (!$metricsResult) {
        throw new Exception("Error executing metrics query: " . $conn->error);
    }
    $metrics = $metricsResult->fetch_assoc();

    // Fetch detailed datasets using prepared statements for safety
    $usersStmt = $conn->prepare("SELECT first_name, last_name, email, role FROM users LIMIT 100");
    $usersStmt->execute();
    $users = $usersStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $eventsStmt = $conn->prepare("SELECT title, date, location FROM events LIMIT 100");
    $eventsStmt->execute();
    $events = $eventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $trainingsStmt = $conn->prepare("SELECT title, schedule, capacity FROM trainings LIMIT 100");
    $trainingsStmt->execute();
    $trainings = $trainingsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $announcementsStmt = $conn->prepare("SELECT text, created_at FROM announcements LIMIT 100");
    $announcementsStmt->execute();
    $announcements = $announcementsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Create filtered datasets based on active filters
    $datasets = [];
    
    // Only include metrics if at least one of the chart sections is active
    $chartSections = ['user-stats', 'event-stats', 'training-stats', 'revenue-stats', 'registration-overview', 'new-users-trend'];
    $includeMetrics = false;
    foreach ($chartSections as $section) {
        if (shouldIncludeSection($section)) {
            $includeMetrics = true;
            break;
        }
    }
    
    if ($includeMetrics) {
        $datasets['metrics'] = $metrics;
    }
    
    // Include other datasets based on active filters
    if (shouldIncludeSection('users-table')) {
        $datasets['users'] = $users;
    }
    
    if (shouldIncludeSection('events-table')) {
        $datasets['events'] = $events;
    }
    
    if (shouldIncludeSection('trainings-table')) {
        $datasets['trainings'] = $trainings;
    }
    
    if (shouldIncludeSection('announcements-table')) {
        $datasets['announcements'] = $announcements;
    }
    
    // Include additional analytics data
    if (shouldIncludeSection('additional-analytics')) {
        // Create a formatted additional analytics dataset
        $additionalAnalytics = [
            ['Metric' => 'Total Chat Messages', 'Value' => $metrics['total_chat_messages'] ?? 0],
            ['Metric' => 'Total Consultations', 'Value' => $metrics['total_consultations'] ?? 0],
            ['Metric' => 'Total Certificates', 'Value' => $metrics['total_certificates'] ?? 0],
            ['Metric' => 'Total News', 'Value' => $metrics['total_news'] ?? 0],
            ['Metric' => 'Total Payments', 'Value' => $metrics['total_payments'] ?? 0]
        ];
        $datasets['additional_analytics'] = $additionalAnalytics;
    }

    // Record an audit log for the export event
    recordAuditLog($userId, "Export Report", "User exported filtered report in " . strtoupper($format) . " format.");

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="adohre_report_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');

        // Add a detailed header
        fputcsv($output, ['ADOHRE System Report']);
        fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, ['Exported by: ' . htmlspecialchars($userName) . ' (' . htmlspecialchars($userRole) . ')']);
        fputcsv($output, []); // Blank line

        foreach ($datasets as $section => $rows) {
            fputcsv($output, [ucfirst($section)]); // Section header
            if ($section === 'metrics') {
                // Filter metrics based on active filters
                $metricsMapping = [
                    'user-stats' => ['total_users', 'active_members', 'admin_count', 'member_count'],
                    'event-stats' => ['upcoming_events', 'finished_events', 'total_events'],
                    'training-stats' => ['upcoming_trainings', 'finished_trainings', 'total_trainings'],
                    'revenue-stats' => ['total_revenue'],
                    'registration-overview' => ['joined_events', 'joined_trainings', 'membership_applications'],
                    'additional-analytics' => ['total_chat_messages', 'total_consultations', 'total_certificates']
                ];
                
                $includedMetrics = [];
                foreach ($metricsMapping as $sectionKey => $metricKeys) {
                    if (shouldIncludeSection($sectionKey)) {
                        $includedMetrics = array_merge($includedMetrics, $metricKeys);
                    }
                }
                
                foreach ($rows as $key => $value) {
                    if (in_array($key, $includedMetrics)) {
                        fputcsv($output, [ucfirst(str_replace('_', ' ', $key)), $value]);
                    }
                }
            } else {
                if (!empty($rows)) {
                    fputcsv($output, array_keys($rows[0])); // Column headers
                    foreach ($rows as $row) {
                        // Sanitize data before output
                        $sanitizedRow = array_map(function($value) {
                            return htmlspecialchars($value ?? 'N/A', ENT_QUOTES, 'UTF-8');
                        }, $row);
                        fputcsv($output, $sanitizedRow);
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
        header('Content-Disposition: attachment; filename="adohre_report_' . date('Y-m-d') . '.pdf"');

        // ----------------------------------------------------------------------
        // 1) Define a custom TCPDF class for a custom footer (or header if needed)
        // ----------------------------------------------------------------------
        class CustomPDF extends \TCPDF {
            protected $filterInfo;
            
            public function setFilterInfo($filterInfo) {
                $this->filterInfo = $filterInfo;
            }
            
            public function Footer() {
                $this->SetY(-15);
                $this->SetFont('helvetica', 'I', 8);
                
                // Add filter information to footer if available
                $footerText = 'ADOHRE System Report - Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages();
                if (!empty($this->filterInfo)) {
                    $footerText .= ' | Filtered: ' . $this->filterInfo;
                }
                
                $this->Cell(
                    0,
                    10,
                    $footerText,
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
        
        // Add filter information to the PDF footer
        if (!empty($activeFilters)) {
            $filterNames = array_map(function($filter) {
                return ucwords(str_replace('-', ' ', $filter));
            }, $activeFilters);
            
            // Limit to first 3 filters to avoid too long footer
            if (count($filterNames) > 3) {
                $footerText = implode(', ', array_slice($filterNames, 0, 3)) . '...';
            } else {
                $footerText = implode(', ', $filterNames);
            }
            
            $pdf->setFilterInfo($footerText);
        }
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(htmlspecialchars($userName));
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
        $pdf->Cell(0, 8, 'Exported by: ' . htmlspecialchars($userName) . ' (' . htmlspecialchars($userRole) . ')', 0, 1, 'C');

        $pdf->SetY(150);
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 8, 'This report contains confidential information', 0, 1, 'C');
        $pdf->Cell(0, 8, 'Please handle with appropriate care', 0, 1, 'C');

        // ---------------------------------------------------
        // 4) Charts Overview Section (with Filtering)
        // ---------------------------------------------------
        $includeChartPage = false;
        foreach (['user-stats', 'event-stats', 'training-stats', 'revenue-stats', 'registration-overview', 'new-users-trend'] as $section) {
            if (shouldIncludeSection($section)) {
                $includeChartPage = true;
                break;
            }
        }
        
        if ($includeChartPage) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(0, 10, 'Reports Charts Overview', 0, 1, 'C', true);
            $pdf->Ln(5);

            // Charts to display (map them to your $_POST data keys) - with filtering
            $charts = [];
            
            if (shouldIncludeSection('user-stats')) {
                $charts['User Statistics'] = 'userChart';
            }
            
            if (shouldIncludeSection('event-stats')) {
                $charts['Event Statistics'] = 'eventChart';
            }
            
            if (shouldIncludeSection('training-stats')) {
                $charts['Training Statistics'] = 'trainingChart';
            }
            
            if (shouldIncludeSection('revenue-stats')) {
                $charts['Revenue Statistics'] = 'revenueChart';
            }
            
            if (shouldIncludeSection('registration-overview')) {
                $charts['Registrations Overview'] = 'registrationsChart';
            }
            
            if (shouldIncludeSection('new-users-trend')) {
                $charts['New Users Trend'] = 'newUsersChart';
            }

            // Layout settings
            $chartWidth     = 130;  // chart display width (mm)
            $chartHeight    = 100;  // chart display height (mm)
            $headingHeight  = 8;    // approximate height for chart title
            $blockHeight    = $headingHeight + $chartHeight; // total vertical space for each "title + chart"
            $colSpacing     = 10;
            $rowSpacing     = 15;
            $marginLeft     = 35;
            $marginTop      = $pdf->GetY() + 5;

            $col = 0;
            $row = 0;
            $maxCols = 1; // One chart per row for better display

            // Inside the loop over $charts in the PDF generation block:
            foreach ($charts as $title => $chartKey) {
                // Calculate X and Y positions for this chart block
                $x = $marginLeft + ($col * ($chartWidth + $colSpacing));
                $y = $marginTop + ($row * ($blockHeight + $rowSpacing));

                // Check if the entire block (title + chart) will overflow the page
                if (($y + $blockHeight) > ($pdf->getPageHeight() - 20)) {
                    $pdf->AddPage();
                    $marginTop = 20;
                    $row = 0;
                    $col = 0;
                    $x = $marginLeft;
                    $y = $marginTop;
                }

                // Print the chart title
                $pdf->SetXY($x, $y);
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell($chartWidth, $headingHeight, $title, 0, 2, 'L');

                // Position for the chart image after the title
                $chartY = $y + $headingHeight;
                if (isset($_POST[$chartKey]) && !empty($_POST[$chartKey])) {
                    $postedData = $_POST[$chartKey];

                    // Check if the posted data is a valid PNG base64 image
                    if (preg_match('/^data:image\/png;base64,/', $postedData)) {
                        try {
                            // Remove the data URI prefix and decode
                            $base64Image = str_replace('data:image/png;base64,', '', $postedData);
                            $imageData = base64_decode($base64Image);

                            if ($imageData !== false && strlen($imageData) > 0) {
                                // Use the inline image method (no temporary file needed)
                                $pdf->Image('@' . $imageData, $x, $chartY, $chartWidth, $chartHeight, 'PNG');
                            } else {
                                $pdf->SetXY($x, $chartY);
                                $pdf->SetFont('helvetica', '', 9);
                                $pdf->Cell($chartWidth, 5, 'Image decoding failed', 0, 1, 'L');
                            }
                        } catch (Exception $e) {
                            error_log("Chart processing error: " . $e->getMessage());
                            $pdf->SetXY($x, $chartY);
                            $pdf->SetFont('helvetica', '', 9);
                            $pdf->Cell($chartWidth, 5, 'Error processing chart', 0, 1, 'L');
                        }
                    } else {
                        $pdf->SetXY($x, $chartY);
                        $pdf->SetFont('helvetica', '', 9);
                        $pdf->Cell($chartWidth, 5, 'Invalid image format', 0, 1, 'L');
                    }
                } else {
                    // No chart data available; show a placeholder
                    $pdf->SetXY($x, $chartY);
                    $pdf->SetDrawColor(200, 200, 200);
                    $pdf->SetFillColor(245, 245, 245);
                    $pdf->Cell($chartWidth, $chartHeight, 'Chart data not available', 1, 0, 'C', true);
                }

                // Move to the next column, or wrap to the next row if needed
                $col++;
                if ($col >= $maxCols) {
                    $col = 0;
                    $row++;
                }
            }
        }

        // ----------------------
        // Executive Summary (only if we have metrics included)
        // ----------------------
        if (isset($datasets['metrics'])) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(0, 10, 'Executive Summary', 0, 1, 'C', true);
            $pdf->Ln(5);
            
            // Brief summary paragraph
            $pdf->SetFont('helvetica', '', 11);
            $pdf->MultiCell(0, 6, 'This report provides a comprehensive overview of system metrics and activities based on the selected filters. The data covers various aspects including user statistics, events, trainings, announcements, and financial metrics.', 0, 'L');
            $pdf->Ln(5);
            
            // Key metrics highlights - only show metrics from included sections
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Key Metrics Highlights', 0, 1, 'L');
            $pdf->Ln(2);
            
            // Define key metrics to highlight based on active filters
            $keyMetrics = [];
            
            if (shouldIncludeSection('user-stats')) {
                $keyMetrics[] = ['Total Users', $metrics['total_users'] ?? 0];
                $keyMetrics[] = ['Active Members', $metrics['active_members'] ?? 0];
            }
            
            if (shouldIncludeSection('event-stats')) {
                $keyMetrics[] = ['Upcoming Events', $metrics['upcoming_events'] ?? 0];
            }
            
            if (shouldIncludeSection('revenue-stats')) {
                $keyMetrics[] = ['Total Revenue', 'PHP ' . number_format($metrics['total_revenue'] ?? 0, 2)];
            }
            
            // If we have less than 4 metrics due to filtering, add some from other sections
            if (count($keyMetrics) < 4) {
                if (shouldIncludeSection('training-stats') && count($keyMetrics) < 4) {
                    $keyMetrics[] = ['Total Trainings', $metrics['total_trainings'] ?? 0];
                }
                
                if (shouldIncludeSection('registration-overview') && count($keyMetrics) < 4) {
                    $keyMetrics[] = ['Joined Events', $metrics['joined_events'] ?? 0];
                }
            }
            
            // Ensure we have at most 4 metrics
            $keyMetrics = array_slice($keyMetrics, 0, 4);
            
            // Highlight boxes for key metrics (if we have any)
            if (!empty($keyMetrics)) {
                $boxWidth = 85;
                $boxHeight = 40;
                $spacing = 10;
                $startX = ($pdf->getPageWidth() - 2*$boxWidth - $spacing) / 2;
                $startY = $pdf->GetY();
                
                $colors = [
                    [230, 230, 250], // Light lavender
                    [220, 240, 220], // Light green
                    [240, 230, 220], // Light orange
                    [230, 240, 250]  // Light blue
                ];
                
                // Draw metric boxes
                for ($i = 0; $i < count($keyMetrics); $i++) {
                    $col = $i % 2;
                    $row = floor($i / 2);
                    
                    $x = $startX + $col * ($boxWidth + $spacing);
                    $y = $startY + $row * ($boxHeight + 5);
                    
                    $pdf->SetFillColor($colors[$i][0], $colors[$i][1], $colors[$i][2]);
                    $pdf->SetDrawColor(180, 180, 180);
                    
                    // Check if RoundedRect method exists (some TCPDF versions don't include it)
                    if (method_exists($pdf, 'RoundedRect')) {
                        $pdf->RoundedRect($x, $y, $boxWidth, $boxHeight, 3.50, '1111', 'DF');
                    } else {
                        // Fallback to regular rectangle if RoundedRect doesn't exist
                        $pdf->Rect($x, $y, $boxWidth, $boxHeight, 'DF');
                    }
                    
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
            }
        }

        // ----------------------
        // Detailed Datasets Section - WITH FILTERING
        // ----------------------
        foreach ($datasets as $section => $rows) {
            // Skip section if it shouldn't be included
            if ($section === 'metrics') {
                continue; // Metrics are handled in the Executive Summary
            }
            
            // Map dataset to filter key
            $sectionFilterMapping = [
                'users' => 'users-table',
                'events' => 'events-table',
                'trainings' => 'trainings-table',
                'announcements' => 'announcements-table'
            ];
            
            if (isset($sectionFilterMapping[$section]) && !shouldIncludeSection($sectionFilterMapping[$section])) {
                continue; // Skip this section as it's not in active filters
            }
            
            // Start each major section on a new page for cleaner layout
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(0, 10, ucfirst($section) . ' Detail', 0, 1, 'C', true);
            $pdf->Ln(5);
            
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
                
                // Check if writeHTML method exists (to handle different TCPDF installations)
                if (method_exists($pdf, 'writeHTML')) {
                    $pdf->writeHTML($html, true, false, true, false, '');
                } else {
                    // Fallback to basic table if writeHTML doesn't exist
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->Cell(0, 10, 'Advanced table rendering not available.', 0, 1, 'C');
                    
                    // Create a simpler table
                    $colWidths = array_fill(0, count(array_keys($rows[0])), 40);
                    
                    // Headers
                    foreach (array_keys($rows[0]) as $i => $header) {
                        $pdf->Cell($colWidths[$i], 10, ucfirst($header), 1, 0, 'C');
                    }
                    $pdf->Ln();
                    
                    // Data rows
                    foreach ($rows as $row) {
                        foreach ($row as $i => $value) {
                            $pdf->Cell($colWidths[$i], 10, substr($value ?? 'N/A', 0, 20), 1, 0, 'L');
                        }
                        $pdf->Ln();
                    }
                }
            } else {
                $pdf->SetFont('helvetica', 'I', 10);
                $pdf->Cell(0, 10, 'No data available for this section', 0, 1, 'C');
            }
        }

        // Final summary page
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Report Summary', 0, 1, 'C');
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(0, 5, 'This report was generated automatically from the ADOHRE system database based on your selected filters. The information contained within represents a snapshot of system data at the time of export. For any questions regarding this report, please contact the system administrator.', 0, 'L');
        $pdf->Ln(5);
        
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'Report Generation Information:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(40, 5, 'Date and Time:', 0, 0, 'L');
        $pdf->Cell(0, 5, date('Y-m-d H:i:s'), 0, 1, 'L');
        $pdf->Cell(40, 5, 'Generated By:', 0, 0, 'L');
        $pdf->Cell(0, 5, htmlspecialchars($userName) . ' (' . htmlspecialchars($userRole) . ')', 0, 1, 'L');
        $pdf->Cell(40, 5, 'Document Format:', 0, 0, 'L');
        $pdf->Cell(0, 5, 'PDF Export', 0, 1, 'L');
        
        // Include a list of active filters
        $pdf->Cell(40, 5, 'Applied Filters:', 0, 0, 'L');
        if (!empty($activeFilters)) {
            $filterNames = array_map(function($filter) {
                return ucwords(str_replace('-', ' ', $filter));
            }, $activeFilters);
            $pdf->MultiCell(0, 5, implode(', ', $filterNames), 0, 'L');
        } else {
            $pdf->Cell(0, 5, 'All sections included', 0, 1, 'L');
        }

        // Output the PDF
        $pdf->Output('ADOHRE_Report_' . date('Y-m-d') . '.pdf', 'I');
        exit;
    } elseif ($format === 'excel') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="adohre_report_' . date('Y-m-d') . '.xlsx"');
        
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
        $sheet->setCellValue("A{$row}", 'Exported by: ' . htmlspecialchars($userName) . ' (' . htmlspecialchars($userRole) . ')');
        $row++;
        
        // Include information about applied filters
        $sheet->setCellValue("A{$row}", 'Applied Filters:');
        if (!empty($activeFilters)) {
            $filterNames = array_map(function($filter) {
                return ucwords(str_replace('-', ' ', $filter));
            }, $activeFilters);
            $sheet->setCellValue("B{$row}", implode(', ', $filterNames));
        } else {
            $sheet->setCellValue("B{$row}", 'All sections included');
        }
        $row += 2;

        foreach ($datasets as $section => $rows) {
            // Add section title
            $sheet->setCellValue("A{$row}", ucfirst($section));
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
            
            if ($section === 'metrics') {
                $sheet->setCellValue("A{$row}", 'Metric');
                $sheet->setCellValue("B{$row}", 'Value');
                $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
                $row++;
                
                // Filter metrics based on active filters
                $metricsMapping = [
                    'user-stats' => ['total_users', 'active_members', 'admin_count', 'member_count'],
                    'event-stats' => ['upcoming_events', 'finished_events', 'total_events'],
                    'training-stats' => ['upcoming_trainings', 'finished_trainings', 'total_trainings'],
                    'revenue-stats' => ['total_revenue'],
                    'registration-overview' => ['joined_events', 'joined_trainings', 'membership_applications'],
                    'additional-analytics' => ['total_chat_messages', 'total_consultations', 'total_certificates']
                ];
                
                $includedMetrics = [];
                foreach ($metricsMapping as $sectionKey => $metricKeys) {
                    if (shouldIncludeSection($sectionKey)) {
                        $includedMetrics = array_merge($includedMetrics, $metricKeys);
                    }
                }
                
                foreach ($rows as $key => $value) {
                    if (in_array($key, $includedMetrics)) {
                        $metricName = ucfirst(str_replace('_', ' ', $key));
                        $sheet->setCellValue("A{$row}", $metricName);
                        
                        // Format revenue with currency
                        if (strpos($key, 'revenue') !== false) {
                            $sheet->setCellValue("B{$row}", $value);
                            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('PHP #,##0.00');
                        } else {
                            $sheet->setCellValue("B{$row}", $value);
                        }
                        $row++;
                    }
                }
            } else {
                if (!empty($rows)) {
                    // Add column headers
                    $col = 'A';
                    foreach (array_keys($rows[0]) as $header) {
                        $sheet->setCellValue("{$col}{$row}", ucfirst($header));
                        $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
                        $col++;
                    }
                    $row++;
                    
                    // Add data rows
                    foreach ($rows as $rowData) {
                        $col = 'A';
                        foreach ($rowData as $value) {
                            // Sanitize data before adding to the spreadsheet
                            $sanitizedValue = htmlspecialchars($value ?? 'N/A', ENT_QUOTES, 'UTF-8');
                            $sheet->setCellValue("{$col}{$row}", $sanitizedValue);
                            $col++;
                        }
                        $row++;
                    }
                } else {
                    $sheet->setCellValue("A{$row}", 'No data available');
                    $row++;
                }
            }
            
            // Add empty row between sections
            $row++;
        }

        // Add a footer
        $sheet->setCellValue("A{$row}", 'End of Report');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
        
        // Auto-size columns for better readability
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Apply some basic styling to the whole sheet
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD'],
                ],
            ],
        ];
        
        // Apply styling to the data area, but handle possible exceptions
        try {
            $sheet->getStyle('A1:E' . ($row - 1))->applyFromArray($styleArray);
        } catch (Exception $e) {
            // Log the styling error but continue with export
            error_log("Excel styling error: " . $e->getMessage());
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } else {
        throw new Exception('Invalid format requested.');
    }
} catch (Exception $e) {
    // Log the detailed error information
    error_log("EXPORT ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Set appropriate headers
    header('Content-Type: application/json');
    header("Cache-Control: no-cache, must-revalidate");
    
    // Return a user-friendly error message
    echo json_encode([
        'status' => false, 
        'message' => 'An error occurred during export. Please try again or contact support if the issue persists.',
        'error_code' => 'EXP' . date('YmdHis') // Unique error code for tracking
    ]);
    exit;
}

// Helper function to record audit logs
function recordAuditLog($userId, $action, $details) {
    global $conn;
    try {
        $query = "INSERT INTO audit_logs (user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($query);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt->bind_param("isss", $userId, $action, $details, $ipAddress);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Error recording audit log: " . $e->getMessage());
        // Continue execution even if audit logging fails
        return false;
    }
}
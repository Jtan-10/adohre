<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

try {
    // Total Users
    $totalUsersQuery = "SELECT COUNT(*) as total_users FROM users";
    $totalUsersResult = $conn->query($totalUsersQuery);
    $totalUsers = $totalUsersResult->fetch_assoc()['total_users'];

    // Total Trainings
    $totalTrainingsQuery = "SELECT COUNT(*) AS total_trainings FROM trainings";
    $totalTrainingsResult = $conn->query($totalTrainingsQuery);
    $totalTrainings = $totalTrainingsResult->fetch_assoc()['total_trainings'];

    // Total Events
    $totalEventsQuery = "SELECT COUNT(*) AS total_events FROM events";
    $totalEventsResult = $conn->query($totalEventsQuery);
    $totalEvents = $totalEventsResult->fetch_assoc()['total_events'];

    // Active Members
    $activeMembersQuery = "SELECT COUNT(*) as active_members FROM members WHERE membership_status = 'active'";
    $activeMembersResult = $conn->query($activeMembersQuery);
    $activeMembers = $activeMembersResult->fetch_assoc()['active_members'];

    // Finished Events
    $finishedEventsQuery = "SELECT COUNT(*) AS finished_events FROM events WHERE date < CURDATE()";
    $finishedEventsResult = $conn->query($finishedEventsQuery);
    $finishedEvents = $finishedEventsResult->fetch_assoc()['finished_events'];

    // Finished Trainings
    $finishedTrainingsQuery = "SELECT COUNT(*) AS finished_trainings FROM trainings WHERE schedule < NOW()";
    $finishedTrainingsResult = $conn->query($finishedTrainingsQuery);
    $finishedTrainings = $finishedTrainingsResult->fetch_assoc()['finished_trainings'];

    // Total Announcements
    $announcementsQuery = "SELECT COUNT(*) AS total_announcements FROM announcements";
    $announcementsResult = $conn->query($announcementsQuery);
    $totalAnnouncements = $announcementsResult->fetch_assoc()['total_announcements'];

    // Upcoming Events
    $upcomingEventsQuery = "SELECT COUNT(*) as upcoming_events FROM events WHERE date >= CURDATE()";
    $upcomingEventsResult = $conn->query($upcomingEventsQuery);
    $upcomingEvents = $upcomingEventsResult->fetch_assoc()['upcoming_events'];

    // Upcoming Trainings
    $upcomingTrainingsQuery = "SELECT COUNT(*) as upcoming_trainings FROM trainings WHERE schedule >= NOW()";
    $upcomingTrainingsResult = $conn->query($upcomingTrainingsQuery);
    $upcomingTrainings = $upcomingTrainingsResult->fetch_assoc()['upcoming_trainings'];

    // Revenue (Completed payments only) and breakdown by source
    $totalRevenueQuery = "SELECT SUM(amount) as total_revenue FROM payments WHERE status = 'Completed'";
    $totalRevenueResult = $conn->query($totalRevenueQuery);
    $totalRevenue = $totalRevenueResult->fetch_assoc()['total_revenue'] ?? 0;

    $revenueEventsQuery = "SELECT SUM(amount) as revenue_events FROM payments WHERE status = 'Completed' AND event_id IS NOT NULL";
    $revenueEventsResult = $conn->query($revenueEventsQuery);
    $revenueEvents = $revenueEventsResult->fetch_assoc()['revenue_events'] ?? 0;

    $revenueTrainingsQuery = "SELECT SUM(amount) as revenue_trainings FROM payments WHERE status = 'Completed' AND training_id IS NOT NULL";
    $revenueTrainingsResult = $conn->query($revenueTrainingsQuery);
    $revenueTrainings = $revenueTrainingsResult->fetch_assoc()['revenue_trainings'] ?? 0;

    // Monthly New Users (Last 6 Months)
    $newUsersQuery = "
        SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as new_users 
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
    ";
    $newUsersResult = $conn->query($newUsersQuery);
    $newUsers = [];
    while ($row = $newUsersResult->fetch_assoc()) {
        $newUsers[] = $row;
    }

    // Admin and Member Counts
    $adminCountQuery = "SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'";
    $adminCountResult = $conn->query($adminCountQuery);
    $adminCount = $adminCountResult->fetch_assoc()['admin_count'];

    $memberCountQuery = "SELECT COUNT(*) as member_count FROM users WHERE role = 'member'";
    $memberCountResult = $conn->query($memberCountQuery);
    $memberCount = $memberCountResult->fetch_assoc()['member_count'];

    // Joined Events
    $joinedEventsQuery = "SELECT COUNT(*) as joined_events FROM event_registrations";
    $joinedEventsResult = $conn->query($joinedEventsQuery);
    $joinedEvents = $joinedEventsResult->fetch_assoc()['joined_events'];

    // Joined Trainings
    $joinedTrainingsQuery = "SELECT COUNT(*) as joined_trainings FROM training_registrations";
    $joinedTrainingsResult = $conn->query($joinedTrainingsQuery);
    $joinedTrainings = $joinedTrainingsResult->fetch_assoc()['joined_trainings'];

    // New Analytics: Total Chat Messages
    $totalChatMessagesQuery = "SELECT COUNT(*) as total_chat_messages FROM chat_messages";
    $totalChatMessagesResult = $conn->query($totalChatMessagesQuery);
    $totalChatMessages = $totalChatMessagesResult->fetch_assoc()['total_chat_messages'];

    // New Analytics: Total Consultations
    $totalConsultationsQuery = "SELECT COUNT(*) as total_consultations FROM consultations";
    $totalConsultationsResult = $conn->query($totalConsultationsQuery);
    $totalConsultations = $totalConsultationsResult->fetch_assoc()['total_consultations'];

    // New Analytics: Total Certificates
    $totalCertificatesQuery = "SELECT COUNT(*) as total_certificates FROM certificates";
    $totalCertificatesResult = $conn->query($totalCertificatesQuery);
    $totalCertificates = $totalCertificatesResult->fetch_assoc()['total_certificates'];

    // New Analytics: Total News
    $totalNewsQuery = "SELECT COUNT(*) as total_news FROM news";
    $totalNewsResult = $conn->query($totalNewsQuery);
    $totalNews = $totalNewsResult->fetch_assoc()['total_news'];

    // New Analytics: Total Payments (count of payment records)
    $totalPaymentsQuery = "SELECT COUNT(*) as total_payments FROM payments";
    $totalPaymentsResult = $conn->query($totalPaymentsQuery);
    $totalPayments = $totalPaymentsResult->fetch_assoc()['total_payments'];

    // Payment status counts and overdue
    $paymentsPending = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status = 'Pending' AND is_archived = 0")
        ->fetch_assoc()['c'] ?? 0;
    $paymentsCompleted = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status = 'Completed' AND is_archived = 0")
        ->fetch_assoc()['c'] ?? 0;
    $paymentsNew = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE status = 'New' AND is_archived = 0")
        ->fetch_assoc()['c'] ?? 0;
    $paymentsOverdue = $conn->query("SELECT COUNT(*) AS c FROM payments WHERE due_date < CURDATE() AND status <> 'Completed' AND is_archived = 0")
        ->fetch_assoc()['c'] ?? 0;

    // New Analytics: Membership Applications
    $membershipApplicationsQuery = "SELECT COUNT(*) as membership_applications FROM membership_applications";
    $membershipApplicationsResult = $conn->query($membershipApplicationsQuery);
    $membershipApplications = $membershipApplicationsResult->fetch_assoc()['membership_applications'];

    // Membership applications by status
    $appsPending = $conn->query("SELECT COUNT(*) AS c FROM membership_applications WHERE status = 'Pending'")
        ->fetch_assoc()['c'] ?? 0;
    $appsReviewed = $conn->query("SELECT COUNT(*) AS c FROM membership_applications WHERE status = 'Reviewed'")
        ->fetch_assoc()['c'] ?? 0;
    $appsApproved = $conn->query("SELECT COUNT(*) AS c FROM membership_applications WHERE status = 'Approved'")
        ->fetch_assoc()['c'] ?? 0;
    $appsRejected = $conn->query("SELECT COUNT(*) AS c FROM membership_applications WHERE status = 'Rejected'")
        ->fetch_assoc()['c'] ?? 0;

    // Consultation status breakdown
    $consultationsOpen = $conn->query("SELECT COUNT(*) AS c FROM consultations WHERE status = 'open'")
        ->fetch_assoc()['c'] ?? 0;
    $consultationsClosed = $conn->query("SELECT COUNT(*) AS c FROM consultations WHERE status = 'closed'")
        ->fetch_assoc()['c'] ?? 0;

    // Training KPIs
    $assessmentsCompleted = $conn->query("SELECT COUNT(*) AS c FROM training_registrations WHERE assessment_completed = 1")
        ->fetch_assoc()['c'] ?? 0;
    $trainingRegistrations = $conn->query("SELECT COUNT(*) AS c FROM training_registrations")
        ->fetch_assoc()['c'] ?? 0;

    // Top lists
    $topEvents = $conn->query("SELECT e.title, COUNT(r.registration_id) AS registrations FROM events e LEFT JOIN event_registrations r ON e.event_id = r.event_id GROUP BY e.event_id ORDER BY registrations DESC, e.date DESC LIMIT 5")
        ->fetch_all(MYSQLI_ASSOC);
    $topTrainings = $conn->query("SELECT t.title, COUNT(r.registration_id) AS registrations FROM trainings t LEFT JOIN training_registrations r ON t.training_id = r.training_id GROUP BY t.training_id ORDER BY registrations DESC, t.schedule DESC LIMIT 5")
        ->fetch_all(MYSQLI_ASSOC);
    $topNews = $conn->query("SELECT title, views FROM news ORDER BY views DESC, published_date DESC LIMIT 5")
        ->fetch_all(MYSQLI_ASSOC);

    // Response
    echo json_encode([
        'status' => true,
        'data' => [
            'total_users' => $totalUsers,
            'active_members' => $activeMembers,
            'upcoming_events' => $upcomingEvents,
            'upcoming_trainings' => $upcomingTrainings,
            'finished_events' => $finishedEvents,
            'finished_trainings' => $finishedTrainings,
            'total_announcements' => $totalAnnouncements,
            'total_trainings' => $totalTrainings,
            'total_events' => $totalEvents,
            'new_users' => $newUsers,
            'total_revenue' => $totalRevenue,
            'revenue_events' => $revenueEvents,
            'revenue_trainings' => $revenueTrainings,
            'admin_count' => $adminCount,
            'member_count' => $memberCount,
            'joined_events' => $joinedEvents,
            'joined_trainings' => $joinedTrainings,
            // Existing new stats
            'total_chat_messages' => $totalChatMessages,
            'total_consultations' => $totalConsultations,
            'consultations_open' => $consultationsOpen,
            'consultations_closed' => $consultationsClosed,
            'total_certificates' => $totalCertificates,
            // Additional analytics
            'total_news' => $totalNews,
            'total_payments' => $totalPayments,
            'payments_pending' => $paymentsPending,
            'payments_completed' => $paymentsCompleted,
            'payments_new' => $paymentsNew,
            'payments_overdue' => $paymentsOverdue,
            'membership_applications' => $membershipApplications,
            'membership_pending' => $appsPending,
            'membership_reviewed' => $appsReviewed,
            'membership_approved' => $appsApproved,
            'membership_rejected' => $appsRejected,
            'assessments_completed' => $assessmentsCompleted,
            'training_registrations' => $trainingRegistrations,
            'users' => $conn->query("SELECT first_name, last_name, email, role FROM users")->fetch_all(MYSQLI_ASSOC),
            'events' => $conn->query("SELECT title, date, location FROM events")->fetch_all(MYSQLI_ASSOC),
            'trainings' => $conn->query("SELECT title, schedule, capacity FROM trainings")->fetch_all(MYSQLI_ASSOC),
            'announcements' => $conn->query("SELECT text, created_at FROM announcements")->fetch_all(MYSQLI_ASSOC),
            'top_events' => $topEvents,
            'top_trainings' => $topTrainings,
            'top_news' => $topNews,
        ]
    ]);
} catch (Exception $e) {
    // Log the error details internally without exposing them publicly
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch analytics data.'
    ]);
}

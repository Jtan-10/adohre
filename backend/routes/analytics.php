<?php
require_once '../db/db_connect.php';
header('Content-Type: application/json');

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

    // Revenue (from Payments)
    $totalRevenueQuery = "SELECT SUM(amount) as total_revenue FROM payments";
    $totalRevenueResult = $conn->query($totalRevenueQuery);
    $totalRevenue = $totalRevenueResult->fetch_assoc()['total_revenue'] ?? 0;

    // Monthly New Users (Last 6 Months)
    $newUsersQuery = "
        SELECT MONTHNAME(created_at) as month, COUNT(*) as new_users 
        FROM users 
        WHERE created_at >= NOW() - INTERVAL 6 MONTH 
        GROUP BY MONTH(created_at)
        ORDER BY created_at ASC
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
            'admin_count' => $adminCount,
            'member_count' => $memberCount,
            'joined_events' => $joinedEvents,
            'joined_trainings' => $joinedTrainings,
            'users' => $conn->query("SELECT first_name, last_name, email, role FROM users")->fetch_all(MYSQLI_ASSOC),
            'events' => $conn->query("SELECT title, date, location FROM events")->fetch_all(MYSQLI_ASSOC),
            'trainings' => $conn->query("SELECT title, schedule, capacity FROM trainings")->fetch_all(MYSQLI_ASSOC),
            'announcements' => $conn->query("SELECT text, created_at FROM announcements")->fetch_all(MYSQLI_ASSOC),
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => false,
        'message' => 'Failed to fetch analytics data.',
        'error' => $e->getMessage(),
    ]);
}
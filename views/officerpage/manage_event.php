<?php
// MUST BE FIRST - NO SPACES BEFORE <?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require "../../Connection/connection.php";

// Auth check
if (!isset($_SESSION['officer_id'])) {
    header("Location: ../../officer_Login.php");
    exit();
}

// Handle Delete Action - BEFORE ANY OUTPUT
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $event_id = intval($_GET['delete']);
    
    // Use prepared statements to avoid SQL errors
    $tables = ['event_fines', 'attendance_schedule', 'attendance'];
    
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows > 0) {
            $conn->query("DELETE FROM $table WHERE event_id = $event_id");
        }
    }
    
    $conn->query("DELETE FROM events WHERE event_id = $event_id");
    header("Location: manage_event.php");
    exit();
}

// Handle Edit Action - Check if AJAX request (for editing only)
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Only handle edit (event_id must be > 0) because create is now separate
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if ($event_id <= 0) {
        // If no event_id, redirect to create page
        header("Location: create_event.php");
        exit();
    }
    
    $event_name = $conn->real_escape_string($_POST['event_name']);
    $event_date = $conn->real_escape_string($_POST['event_date']);
    $event_type = $conn->real_escape_string($_POST['event_type']);
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $location = $conn->real_escape_string($_POST['location'] ?? '');
    
    // Validate event_type
    $valid_types = ['whole_day', 'half_day_am', 'half_day_pm'];
    if (!in_array($event_type, $valid_types)) {
        $event_type = 'whole_day';
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update event
        $conn->query("UPDATE events SET 
            event_name = '$event_name',
            event_date = '$event_date',
            event_type = '$event_type',
            description = '$description',
            location = '$location'
            WHERE event_id = $event_id");
        
        // --- Attendance Schedule ---
        $schedule_exists = $conn->query("SELECT schedule_id FROM attendance_schedule WHERE event_id = $event_id")->num_rows > 0;
        
        $am_login_start   = !empty($_POST['am_login_start']) ? "'" . $conn->real_escape_string($_POST['am_login_start']) . "'" : "NULL";
        $am_login_end     = !empty($_POST['am_login_end']) ? "'" . $conn->real_escape_string($_POST['am_login_end']) . "'" : "NULL";
        $am_logout_start  = !empty($_POST['am_logout_start']) ? "'" . $conn->real_escape_string($_POST['am_logout_start']) . "'" : "NULL";
        $am_logout_end    = !empty($_POST['am_logout_end']) ? "'" . $conn->real_escape_string($_POST['am_logout_end']) . "'" : "NULL";
        $pm_login_start   = !empty($_POST['pm_login_start']) ? "'" . $conn->real_escape_string($_POST['pm_login_start']) . "'" : "NULL";
        $pm_login_end     = !empty($_POST['pm_login_end']) ? "'" . $conn->real_escape_string($_POST['pm_login_end']) . "'" : "NULL";
        $pm_logout_start  = !empty($_POST['pm_logout_start']) ? "'" . $conn->real_escape_string($_POST['pm_logout_start']) . "'" : "NULL";
        $pm_logout_end    = !empty($_POST['pm_logout_end']) ? "'" . $conn->real_escape_string($_POST['pm_logout_end']) . "'" : "NULL";
        
        if ($schedule_exists) {
            $conn->query("UPDATE attendance_schedule SET 
                am_login_start = $am_login_start,
                am_login_end = $am_login_end,
                am_logout_start = $am_logout_start,
                am_logout_end = $am_logout_end,
                pm_login_start = $pm_login_start,
                pm_login_end = $pm_login_end,
                pm_logout_start = $pm_logout_start,
                pm_logout_end = $pm_logout_end
                WHERE event_id = $event_id");
        } else {
            $conn->query("INSERT INTO attendance_schedule (
                event_id, am_login_start, am_login_end, am_logout_start, am_logout_end,
                pm_login_start, pm_login_end, pm_logout_start, pm_logout_end
            ) VALUES (
                $event_id, $am_login_start, $am_login_end, $am_logout_start, $am_logout_end,
                $pm_login_start, $pm_login_end, $pm_logout_start, $pm_logout_end
            )");
        }
        
        // --- Fine Settings ---
        $fines_exists = $conn->query("SELECT fine_setting_id FROM event_fines WHERE event_id = $event_id")->num_rows > 0;
        
        $miss_am_login   = isset($_POST['miss_am_login']) ? floatval($_POST['miss_am_login']) : 0.00;
        $miss_am_logout  = isset($_POST['miss_am_logout']) ? floatval($_POST['miss_am_logout']) : 0.00;
        $miss_pm_login   = isset($_POST['miss_pm_login']) ? floatval($_POST['miss_pm_login']) : 0.00;
        $miss_pm_logout  = isset($_POST['miss_pm_logout']) ? floatval($_POST['miss_pm_logout']) : 0.00;
        
        if ($fines_exists) {
            $conn->query("UPDATE event_fines SET 
                miss_am_login = $miss_am_login,
                miss_am_logout = $miss_am_logout,
                miss_pm_login = $miss_pm_login,
                miss_pm_logout = $miss_pm_logout
                WHERE event_id = $event_id");
        } else {
            $conn->query("INSERT INTO event_fines (event_id, miss_am_login, miss_am_logout, miss_pm_login, miss_pm_logout)
                VALUES ($event_id, $miss_am_login, $miss_am_logout, $miss_pm_login, $miss_pm_logout)");
        }
        
        $conn->commit();
        
        // Return JSON for AJAX, otherwise redirect
        if ($isAjax) {
            echo json_encode(['success' => true, 'message' => 'Event updated successfully!']);
            exit();
        } else {
            header("Location: manage_event.php");
            exit();
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        if ($isAjax) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        } else {
            die("Error updating event: " . $e->getMessage());
        }
    }
}

// Fetch data for display (non-AJAX)
$events = $conn->query("
    SELECT e.*, 
           COUNT(DISTINCT a.student_id) as attendance_count,
           (SELECT COUNT(*) FROM students) as total_students
    FROM events e
    LEFT JOIN attendance a ON e.event_id = a.event_id
    GROUP BY e.event_id
    ORDER BY e.event_date DESC
");

// NOW START OUTPUT
include "../sidebar/officer_sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Manager | BEAMS Officer Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
    :root {
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --secondary: #ec4899;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --info: #0ea5e9;
        --dark: #1e293b;
        --light: #f8fafc;
        --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --card-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: #f1f5f9;
        color: #334155;
        min-height: 100vh;
    }

    /* Header Section */
    .page-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 500px;
        height: 500px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .header-content {
        position: relative;
        z-index: 1;
    }

    .breadcrumb {
        background: transparent;
        padding: 0;
        margin-bottom: 0.5rem;
    }

    .breadcrumb-item a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
    }

    .breadcrumb-item.active {
        color: white;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .page-subtitle {
        opacity: 0.9;
        font-size: 1rem;
    }

    /* Stats Cards */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        padding: 2rem;
        margin-top: -3rem;
        position: relative;
        z-index: 10;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .stat-card.primary .stat-icon {
        background: #eef2ff;
        color: var(--primary);
    }

    .stat-card.success .stat-icon {
        background: #d1fae5;
        color: var(--success);
    }

    .stat-card.warning .stat-icon {
        background: #fef3c7;
        color: var(--warning);
    }

    .stat-card.danger .stat-icon {
        background: #fee2e2;
        color: var(--danger);
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 800;
        color: var(--dark);
        margin-bottom: 0.25rem;
    }

    .stat-label {
        color: #64748b;
        font-size: 0.875rem;
        font-weight: 500;
    }

    /* Main content area */
    .main-contents {
        margin-left: var(--sidebar-width, 250px);
        transition: margin-left 0.3s ease;
    }

    .main-contents .modal {
        left: var(--sidebar-width, 250px);
        /* Offset by sidebar width */
        width: calc(100% - var(--sidebar-width, 250px));
    }

    .main-contents .modal-dialog {
        margin: 1.75rem auto;
        /* Keep it centered within the reduced width */
    }

    /* Optional: adjust backdrop to cover only main content */
    .main-contents .modal-backdrop {
        left: var(--sidebar-width, 250px);
        width: calc(100% - var(--sidebar-width, 250px));
    }

    .content-card {
        background: white;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin: 2rem;
    }

    .card-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafafa;
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Event Grid */
    .events-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        padding: 1.5rem;
    }

    .event-card {
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }

    .event-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover);
        border-color: var(--primary);
    }

    .event-header {
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
        position: relative;
    }

    .event-type-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        padding: 0.375rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-whole {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-half-am {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-half-pm {
        background: #e0f2fe;
        color: #0369a1;
    }

    .event-date {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .date-box {
        background: white;
        border-radius: 12px;
        padding: 0.75rem 1rem;
        text-align: center;
        min-width: 60px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .date-day {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--primary);
        line-height: 1;
    }

    .date-month {
        font-size: 0.75rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
    }

    .event-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }

    .event-location {
        color: #64748b;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .event-body {
        padding: 1.5rem;
    }

    .event-description {
        color: #64748b;
        font-size: 0.875rem;
        line-height: 1.6;
        margin-bottom: 1.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .event-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .event-stat {
        text-align: center;
        padding: 0.75rem;
        background: #f8fafc;
        border-radius: 8px;
    }

    .event-stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--dark);
    }

    .event-stat-label {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .progress {
        height: 6px;
        background: #e2e8f0;
        border-radius: 3px;
        margin-bottom: 1.5rem;
    }

    .progress-bar {
        background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 3px;
    }

    .event-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .event-actions a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        font-size: 0.875rem;
        transition: color 0.2s;
    }

    .event-actions a:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }

    .event-actions a.delete-link {
        color: var(--danger);
    }

    .event-actions a.delete-link:hover {
        color: #b91c1c;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-icon {
        width: 120px;
        height: 120px;
        background: #f1f5f9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 3rem;
        color: #cbd5e1;
    }

    .empty-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.5rem;
    }

    .empty-text {
        color: #64748b;
        margin-bottom: 1.5rem;
    }

    /* Floating Action Button */
    .fab {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.5);
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        z-index: 1000;
    }

    .fab:hover {
        transform: scale(1.1) rotate(90deg);
        box-shadow: 0 20px 35px -5px rgba(99, 102, 241, 0.6);
    }

    .create-link {
        color: white;
        background: var(--primary);
        padding: 0.5rem 1rem;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: background 0.2s;
    }

    .create-link:hover {
        background: var(--primary-dark);
        color: white;
    }

    /* Modal improvements */
    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--dark);
        margin: 1.5rem 0 1rem 0;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid var(--primary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .time-range {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .time-range .form-control {
        flex: 1;
    }

    .range-separator {
        color: #64748b;
        font-weight: 600;
    }

    .event-type-selector {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 0.5rem;
    }

    .event-type-option {
        flex: 1 1 150px;
        cursor: pointer;
    }

    .event-type-option input[type="radio"] {
        display: none;
    }

    .event-type-card {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
        transition: all 0.2s;
    }

    .event-type-option input[type="radio"]:checked+.event-type-card {
        border-color: var(--primary);
        background: #eef2ff;
    }

    .event-type-icon {
        font-size: 2rem;
        color: var(--primary);
        margin-bottom: 0.5rem;
    }

    .event-type-label {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .event-type-desc {
        font-size: 0.75rem;
        color: #64748b;
    }

    /* Toast container */
    .toast-container {
        position: fixed;
        top: 1rem;
        right: 1rem;
        z-index: 1100;
    }

    .toast {
        background: white;
        border-left: 4px solid var(--success);
        box-shadow: var(--card-shadow);
    }

    .toast.error {
        border-left-color: var(--danger);
    }

    @media (max-width: 991px) {
        .stats-row {
            grid-template-columns: 1fr;
            margin-top: 0;
        }

        .events-grid {
            grid-template-columns: 1fr;
        }

        .event-type-selector {
            flex-direction: column;
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-in {
        animation: fadeInUp 0.5s ease forwards;
    }

    .delay-1 {
        animation-delay: 0.1s;
    }

    .delay-2 {
        animation-delay: 0.2s;
    }

    .delay-3 {
        animation-delay: 0.3s;
    }

    .delay-4 {
        animation-delay: 0.4s;
    }
    </style>
</head>

<body>
    <!-- Toast container for AJAX feedback -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Sidebar already included -->

    <div class="main-contents">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="officer_dashboard.php"><i class="fas fa-home"></i>
                                Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Event Manager</li>
                    </ol>
                </nav>
                <h1 class="page-title"><i class="fas fa-calendar-alt me-3"></i>Event Manager</h1>
                <p class="page-subtitle">Create, manage, and track all your events in one place</p>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card primary animate-in">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-value"><?php echo $events->num_rows; ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card success animate-in delay-1">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-value">
                    <?php 
                    $upcoming = $conn->query("SELECT COUNT(*) as count FROM events WHERE event_date >= CURDATE()")->fetch_assoc()['count'];
                    echo $upcoming;
                    ?>
                </div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <div class="stat-card warning animate-in delay-2">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value">
                    <?php
                    $total_attendance = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM attendance")->fetch_assoc()['count'];
                    echo $total_attendance;
                    ?>
                </div>
                <div class="stat-label">Total Attendance</div>
            </div>
            <div class="stat-card danger animate-in delay-3">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-value">
                    <?php
                    $past = $conn->query("SELECT COUNT(*) as count FROM events WHERE event_date < CURDATE()")->fetch_assoc()['count'];
                    echo $past;
                    ?>
                </div>
                <div class="stat-label">Past Events</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-card animate-in delay-2">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list-ul text-primary"></i> All Events</h3>
                <!-- Create New Event now links to separate page -->
                <a href="create_event.php" class="create-link">
                    <i class="fas fa-plus me-2"></i>Create New Event
                </a>
            </div>

            <div class="events-grid">
                <?php if ($events->num_rows > 0): ?>
                <?php while($event = $events->fetch_assoc()): 
                        $date = strtotime($event['event_date']);
                        $day = date('d', $date);
                        $month = date('M', $date);
                        $attendance_rate = $event['total_students'] > 0 ? 
                            round(($event['attendance_count'] / $event['total_students']) * 100) : 0;
                        
                        $badge_class = '';
                        $badge_label = '';
                        switch($event['event_type']) {
                            case 'whole_day':
                                $badge_class = 'badge-whole';
                                $badge_label = 'Whole Day';
                                break;
                            case 'half_day_am':
                                $badge_class = 'badge-half-am';
                                $badge_label = 'Half Day - AM';
                                break;
                            case 'half_day_pm':
                                $badge_class = 'badge-half-pm';
                                $badge_label = 'Half Day - PM';
                                break;
                            default:
                                $badge_class = 'badge-whole';
                                $badge_label = 'Whole Day';
                        }
                    ?>
                <div class="event-card">
                    <div class="event-header">
                        <span class="event-type-badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                        <div class="event-date">
                            <div class="date-box">
                                <div class="date-day"><?php echo $day; ?></div>
                                <div class="date-month"><?php echo $month; ?></div>
                            </div>
                            <div>
                                <h4 class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></h4>
                                <div class="event-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($event['location'] ?: 'No location set'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="event-body">
                        <p class="event-description">
                            <?php echo htmlspecialchars($event['description'] ?: 'No description available.'); ?></p>
                        <div class="event-stats">
                            <div class="event-stat">
                                <div class="event-stat-value"><?php echo $event['attendance_count']; ?></div>
                                <div class="event-stat-label">Attended</div>
                            </div>
                            <div class="event-stat">
                                <div class="event-stat-value"><?php echo $attendance_rate; ?>%</div>
                                <div class="event-stat-label">Rate</div>
                            </div>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo $attendance_rate; ?>%"></div>
                        </div>
                        <div class="event-actions">
                            <!-- View link opens modal -->
                            <a href="#"
                                onclick='viewEvent(<?php echo json_encode($event, JSON_HEX_APOS); ?>); return false;'><i
                                    class="fas fa-eye"></i> View</a>
                            <!-- Edit link opens edit modal -->
                            <a href="#"
                                onclick='editEvent(<?php echo json_encode($event, JSON_HEX_APOS); ?>); return false;'><i
                                    class="fas fa-edit"></i> Edit</a>
                            <a href="?delete=<?php echo $event['event_id']; ?>" class="delete-link"
                                onclick="return confirm('Are you sure you want to delete this event?')"><i
                                    class="fas fa-trash"></i> Delete</a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-calendar-plus"></i></div>
                    <h3 class="empty-title">No Events Yet</h3>
                    <p class="empty-text">Start by creating your first event to manage attendance and track
                        participation.</p>
                    <a href="create_event.php" class="create-link"><i class="fas fa-plus me-2"></i>Create Your First
                        Event</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Floating Action Button (Mobile) now links to create page -->
        <a href="create_event.php" class="fab d-lg-none">
            <i class="fas fa-plus"></i>
        </a>

        <!-- View Event Modal (Read-only) -->
        <div class="modal fade" id="viewEventModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title">
                            <i class="fas fa-calendar-alt text-primary me-2"></i>Event Details
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Event basic info -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1 text-muted small">EVENT NAME</p>
                                <p class="fw-bold" id="view_event_name">—</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted small">EVENT DATE</p>
                                <p class="fw-bold" id="view_event_date">—</p>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1 text-muted small">EVENT TYPE</p>
                                <p class="fw-bold" id="view_event_type">—</p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1 text-muted small">LOCATION</p>
                                <p class="fw-bold" id="view_location">—</p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <p class="mb-1 text-muted small">DESCRIPTION</p>
                            <p class="fw-bold" id="view_description">—</p>
                        </div>

                        <!-- Attendance Schedule -->
                        <div class="section-title mt-4">
                            <i class="fas fa-clock me-2"></i>Attendance Schedule
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold"><i class="fas fa-sun text-warning me-1"></i> AM Session</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th>Login Period:</th>
                                        <td id="view_am_login">—</td>
                                    </tr>
                                    <tr>
                                        <th>Logout Period:</th>
                                        <td id="view_am_logout">—</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold"><i class="fas fa-moon text-primary me-1"></i> PM Session</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th>Login Period:</th>
                                        <td id="view_pm_login">—</td>
                                    </tr>
                                    <tr>
                                        <th>Logout Period:</th>
                                        <td id="view_pm_logout">—</td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Fine Amounts -->
                        <div class="section-title mt-3">
                            <i class="fas fa-coins me-2"></i>Fine Amounts (₱)
                        </div>
                        <div class="row">
                            <div class="col-md-3"><span class="text-muted small">Miss AM Login:</span> <span
                                    class="fw-bold" id="view_miss_am_login">0.00</span></div>
                            <div class="col-md-3"><span class="text-muted small">Miss AM Logout:</span> <span
                                    class="fw-bold" id="view_miss_am_logout">0.00</span></div>
                            <div class="col-md-3"><span class="text-muted small">Miss PM Login:</span> <span
                                    class="fw-bold" id="view_miss_pm_login">0.00</span></div>
                            <div class="col-md-3"><span class="text-muted small">Miss PM Logout:</span> <span
                                    class="fw-bold" id="view_miss_pm_logout">0.00</span></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-outline-primary" id="viewEditBtn"
                            onclick="editCurrentEvent()">Edit Event</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Event Modal (only for editing) -->
        <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle"><i class="fas fa-edit me-2"></i>Edit Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="eventForm" method="POST" action="">
                        <div class="modal-body">
                            <input type="hidden" name="event_id" id="event_id" value="0">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Event Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="event_name" id="event_name"
                                            required placeholder="Enter event name">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Event Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="event_date" id="event_date"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Event Type</label>
                                <div class="event-type-selector">
                                    <label class="event-type-option">
                                        <input type="radio" name="event_type" value="whole_day" checked>
                                        <div class="event-type-card">
                                            <div class="event-type-icon"><i class="fas fa-sun"></i></div>
                                            <div class="event-type-label">Whole Day</div>
                                            <div class="event-type-desc">Full day event</div>
                                        </div>
                                    </label>
                                    <label class="event-type-option">
                                        <input type="radio" name="event_type" value="half_day_am">
                                        <div class="event-type-card">
                                            <div class="event-type-icon"><i class="fas fa-cloud-sun"></i></div>
                                            <div class="event-type-label">Half Day - AM</div>
                                            <div class="event-type-desc">Morning only</div>
                                        </div>
                                    </label>
                                    <label class="event-type-option">
                                        <input type="radio" name="event_type" value="half_day_pm">
                                        <div class="event-type-card">
                                            <div class="event-type-icon"><i class="fas fa-moon"></i></div>
                                            <div class="event-type-label">Half Day - PM</div>
                                            <div class="event-type-desc">Afternoon only</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Location</label>
                                        <input type="text" class="form-control" name="location" id="location"
                                            placeholder="Enter location (optional)">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" id="description" rows="3"
                                            placeholder="Enter event description (optional)"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance Schedule Section -->
                            <div class="section-title">
                                <i class="fas fa-clock me-2"></i>Attendance Schedule
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-sun text-warning me-1"></i> AM Session
                                    </h6>
                                    <div class="mb-3">
                                        <label class="form-label small">Login Period</label>
                                        <div class="time-range">
                                            <input type="time" class="form-control" name="am_login_start"
                                                id="am_login_start" placeholder="Start">
                                            <span class="range-separator">—</span>
                                            <input type="time" class="form-control" name="am_login_end"
                                                id="am_login_end" placeholder="End">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">Logout Period</label>
                                        <div class="time-range">
                                            <input type="time" class="form-control" name="am_logout_start"
                                                id="am_logout_start" placeholder="Start">
                                            <span class="range-separator">—</span>
                                            <input type="time" class="form-control" name="am_logout_end"
                                                id="am_logout_end" placeholder="End">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3"><i class="fas fa-moon text-primary me-1"></i> PM Session
                                    </h6>
                                    <div class="mb-3">
                                        <label class="form-label small">Login Period</label>
                                        <div class="time-range">
                                            <input type="time" class="form-control" name="pm_login_start"
                                                id="pm_login_start" placeholder="Start">
                                            <span class="range-separator">—</span>
                                            <input type="time" class="form-control" name="pm_login_end"
                                                id="pm_login_end" placeholder="End">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">Logout Period</label>
                                        <div class="time-range">
                                            <input type="time" class="form-control" name="pm_logout_start"
                                                id="pm_logout_start" placeholder="Start">
                                            <span class="range-separator">—</span>
                                            <input type="time" class="form-control" name="pm_logout_end"
                                                id="pm_logout_end" placeholder="End">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fine Settings Section -->
                            <div class="section-title">
                                <i class="fas fa-coins me-2"></i>Fine Amounts (₱)
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <label class="form-label small">Miss AM Login</label>
                                        <input type="number" step="0.01" min="0" class="form-control"
                                            name="miss_am_login" id="miss_am_login" value="0.00">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <label class="form-label small">Miss AM Logout</label>
                                        <input type="number" step="0.01" min="0" class="form-control"
                                            name="miss_am_logout" id="miss_am_logout" value="0.00">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <label class="form-label small">Miss PM Login</label>
                                        <input type="number" step="0.01" min="0" class="form-control"
                                            name="miss_pm_login" id="miss_pm_login" value="0.00">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-2">
                                        <label class="form-label small">Miss PM Logout</label>
                                        <input type="number" step="0.01" min="0" class="form-control"
                                            name="miss_pm_logout" id="miss_pm_logout" value="0.00">
                                    </div>
                                </div>
                            </div>
                            <small class="text-muted">Leave as 0.00 if no fine applies.</small>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="saveBtn">
                                <i class="fas fa-save me-2"></i>Update Event
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toast helper
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toastContainer');
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${type} border-0 show`;
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
        toastContainer.appendChild(toastEl);
        setTimeout(() => toastEl.remove(), 5000);
    }

    // Reset form for editing (clears previous data, but not used for create)
    function resetForm() {
        document.getElementById('eventForm').reset();
        document.getElementById('event_id').value = '0';
        document.querySelector('input[name="event_type"][value="whole_day"]').checked = true;
        // Clear time and fine fields
        document.querySelectorAll('#eventForm input[type="time"], #eventForm input[type="number"]').forEach(input => {
            if (input.type === 'number') input.value = '0.00';
            else input.value = '';
        });
        // Set min date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('event_date').min = today;
    }

    // Edit event: populate form and fetch schedule/fines
    function editEvent(event) {
        document.getElementById('event_id').value = event.event_id;
        document.getElementById('event_name').value = event.event_name;
        document.getElementById('event_date').value = event.event_date;
        document.getElementById('location').value = event.location || '';
        document.getElementById('description').value = event.description || '';

        const eventType = event.event_type || 'whole_day';
        const radio = document.querySelector(`input[name="event_type"][value="${eventType}"]`);
        if (radio) radio.checked = true;

        // Fetch schedule and fines via AJAX
        fetch(`get_event_details.php?event_id=${event.event_id}`)
            .then(response => response.json())
            .then(data => {
                // Populate schedule
                if (data.schedule) {
                    document.getElementById('am_login_start').value = data.schedule.am_login_start || '';
                    document.getElementById('am_login_end').value = data.schedule.am_login_end || '';
                    document.getElementById('am_logout_start').value = data.schedule.am_logout_start || '';
                    document.getElementById('am_logout_end').value = data.schedule.am_logout_end || '';
                    document.getElementById('pm_login_start').value = data.schedule.pm_login_start || '';
                    document.getElementById('pm_login_end').value = data.schedule.pm_login_end || '';
                    document.getElementById('pm_logout_start').value = data.schedule.pm_logout_start || '';
                    document.getElementById('pm_logout_end').value = data.schedule.pm_logout_end || '';
                }
                // Populate fines
                if (data.fines) {
                    document.getElementById('miss_am_login').value = data.fines.miss_am_login || '0.00';
                    document.getElementById('miss_am_logout').value = data.fines.miss_am_logout || '0.00';
                    document.getElementById('miss_pm_login').value = data.fines.miss_pm_login || '0.00';
                    document.getElementById('miss_pm_logout').value = data.fines.miss_pm_logout || '0.00';
                }
            })
            .catch(error => console.error('Error fetching details:', error));

        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Event';
        const modal = new bootstrap.Modal(document.getElementById('eventModal'));
        modal.show();
    }

    // AJAX form submission for editing
    document.getElementById('eventForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';

        fetch('manage_event.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Event updated successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.error || 'Error updating event', 'danger');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save me-2"></i>Update Event';
                }
            })
            .catch(error => {
                showToast('Network error. Please try again.', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-2"></i>Update Event';
            });
    });

    // Reset modal on close
    document.getElementById('eventModal').addEventListener('hidden.bs.modal', function() {
        resetForm();
    });

    // Set min date on page load
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('event_date').min = today;
    });

    // Optional: Client-side validation for time ranges
    document.getElementById('eventForm').addEventListener('submit', function(e) {
        const amLoginStart = document.getElementById('am_login_start').value;
        const amLoginEnd = document.getElementById('am_login_end').value;
        if (amLoginStart && amLoginEnd && amLoginStart > amLoginEnd) {
            e.preventDefault();
            alert('AM Login start must be before end time.');
            return false;
        }
    });

    // ========== VIEW MODAL FUNCTIONS ==========
    let currentViewedEvent = null;

    function viewEvent(event) {
        currentViewedEvent = event;

        document.getElementById('view_event_name').innerText = event.event_name || '—';
        if (event.event_date) {
            const d = new Date(event.event_date);
            const options = {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            document.getElementById('view_event_date').innerText = d.toLocaleDateString(undefined, options);
        } else {
            document.getElementById('view_event_date').innerText = '—';
        }
        let typeLabel = 'Whole Day';
        if (event.event_type === 'half_day_am') typeLabel = 'Half Day - AM';
        else if (event.event_type === 'half_day_pm') typeLabel = 'Half Day - PM';
        document.getElementById('view_event_type').innerText = typeLabel;
        document.getElementById('view_location').innerText = event.location || '—';
        document.getElementById('view_description').innerText = event.description || '—';

        // Fetch schedule and fines via AJAX
        fetch(`get_event_details.php?event_id=${event.event_id}`)
            .then(response => response.json())
            .then(data => {
                const s = data.schedule || {};
                document.getElementById('view_am_login').innerText = (s.am_login_start && s.am_login_end) ?
                    `${s.am_login_start} — ${s.am_login_end}` : 'Not set';
                document.getElementById('view_am_logout').innerText = (s.am_logout_start && s.am_logout_end) ?
                    `${s.am_logout_start} — ${s.am_logout_end}` : 'Not set';
                document.getElementById('view_pm_login').innerText = (s.pm_login_start && s.pm_login_end) ?
                    `${s.pm_login_start} — ${s.pm_login_end}` : 'Not set';
                document.getElementById('view_pm_logout').innerText = (s.pm_logout_start && s.pm_logout_end) ?
                    `${s.pm_logout_start} — ${s.pm_logout_end}` : 'Not set';

                const f = data.fines || {};
                document.getElementById('view_miss_am_login').innerText = parseFloat(f.miss_am_login || 0).toFixed(
                    2);
                document.getElementById('view_miss_am_logout').innerText = parseFloat(f.miss_am_logout || 0)
                    .toFixed(2);
                document.getElementById('view_miss_pm_login').innerText = parseFloat(f.miss_pm_login || 0).toFixed(
                    2);
                document.getElementById('view_miss_pm_logout').innerText = parseFloat(f.miss_pm_logout || 0)
                    .toFixed(2);
            })
            .catch(error => console.error('Error fetching details:', error));

        const modal = new bootstrap.Modal(document.getElementById('viewEventModal'));
        modal.show();
    }

    function editCurrentEvent() {
        const viewModalEl = document.getElementById('viewEventModal');
        const viewModal = bootstrap.Modal.getInstance(viewModalEl);
        if (viewModal) viewModal.hide();

        if (currentViewedEvent) {
            editEvent(currentViewedEvent);
        } else {
            alert('No event selected for editing.');
        }
    }
    </script>
</body>

</html>
<?php
session_start();
require "../sidebar/officer_sidebar.php";
require "../../Connection/connection.php";

if (!isset($_SESSION['officer_id'])) {
    header("Location: ../../Login.php");
    exit();
}

$officer_id = $_SESSION['officer_id'];

/* TOTAL STUDENTS */
$students = $conn->query("SELECT COUNT(*) as total FROM students");
$total_students = $students->fetch_assoc()['total'];

/* TOTAL EVENTS */
$events = $conn->query("SELECT COUNT(*) as total FROM events");
$total_events = $events->fetch_assoc()['total'];

/* TOTAL ATTENDANCE */
$attendance = $conn->query("SELECT COUNT(*) as total FROM attendance");
$total_attendance = $attendance->fetch_assoc()['total'];

/* FINES */
$pending = $conn->query("SELECT SUM(amount) as total FROM student_fines WHERE status='unpaid'");
$pending_fines = $pending->fetch_assoc()['total'] ?? 0;

$paid = $conn->query("SELECT SUM(amount) as total FROM student_fines WHERE status='paid'");
$paid_fines = $paid->fetch_assoc()['total'] ?? 0;

/* UPCOMING EVENTS */
$upcoming = $conn->query("
    SELECT e.event_id, e.event_name, e.event_date, e.event_type, 
           e.description, e.location, e.created_by, e.created_at,
           (SELECT full_name FROM officers WHERE officer_id = e.created_by) as full_name,
           COUNT(a.attendance_id) as attendance_count
    FROM events e
    LEFT JOIN attendance a ON e.event_id = a.event_id
    WHERE e.event_date >= CURDATE() 
    GROUP BY e.event_id
    ORDER BY e.event_date ASC 
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard | Event Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        --card-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.08);
        --hover-shadow: 0 8px 30px -5px rgba(0, 0, 0, 0.12);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html {
        font-size: 14px;
        /* Base font size reduction for 100% zoom match */
    }

    body {
        font-family: 'Inter', sans-serif;
        background: #f0f2f5;
        color: #2d3748;
        line-height: 1.5;
        overflow-x: hidden;
    }

    .main-content {
        margin-left: 240px;
        /* Sidebar width adjustment */
        min-height: 100vh;
        padding: 0;
    }

    .dashboard-header {
        background: white;
        padding: 20px 30px;
        margin-bottom: 20px;
        border-bottom: 1px solid #e2e8f0;
        position: relative;
        overflow: hidden;
    }

    .dashboard-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .welcome-text h1 {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 4px;
    }

    .welcome-text p {
        color: #718096;
        font-size: 0.9rem;
        margin: 0;
    }

    .date-badge {
        background: #f7fafc;
        padding: 10px 16px;
        border-radius: 10px;
        font-weight: 600;
        color: #4a5568;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        border: 1px solid #e2e8f0;
    }

    .container {
        max-width: 1400px;
        padding: 0 25px;
    }

    /* Quick Actions - Compact horizontal layout */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    .action-btn {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 12px;
        text-align: center;
        text-decoration: none;
        color: #4a5568;
        transition: all 0.25s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }

    .action-btn:hover {
        border-color: #667eea;
        color: #667eea;
        transform: translateY(-2px);
        box-shadow: var(--card-shadow);
        text-decoration: none;
    }

    .action-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: #f7fafc;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        transition: all 0.25s ease;
    }

    .action-btn:hover .action-icon {
        background: #667eea;
        color: white;
    }

    .action-label {
        font-weight: 600;
        font-size: 0.85rem;
    }

    /* Stats Grid - Compact cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 18px;
        position: relative;
        overflow: hidden;
        transition: all 0.25s ease;
        border: 1px solid #e2e8f0;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--hover-shadow);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
    }

    .stat-card.primary::before {
        background: var(--primary-gradient);
    }

    .stat-card.success::before {
        background: var(--success-gradient);
    }

    .stat-card.warning::before {
        background: var(--warning-gradient);
    }

    .stat-card.danger::before {
        background: var(--danger-gradient);
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 12px;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .stat-card.primary .stat-icon {
        background: #eef2ff;
        color: #667eea;
    }

    .stat-card.success .stat-icon {
        background: #e6fffa;
        color: #11998e;
    }

    .stat-card.warning .stat-icon {
        background: #fef3f2;
        color: #f5576c;
    }

    .stat-card.danger .stat-icon {
        background: #fff5f5;
        color: #fa709a;
    }

    .stat-trend {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 12px;
        background: #f7fafc;
        color: #48bb78;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 4px;
        line-height: 1;
    }

    .stat-label {
        font-size: 0.8rem;
        color: #718096;
        font-weight: 500;
    }

    /* Content Grid */
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .content-card {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .card-header-custom {
        padding: 18px 20px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .card-title {
        font-size: 1rem;
        font-weight: 700;
        color: #1a202c;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-action {
        color: #667eea;
        font-weight: 600;
        font-size: 0.8rem;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 4px;
        transition: gap 0.2s ease;
    }

    .card-action:hover {
        gap: 8px;
        color: #764ba2;
    }

    /* Events List - Compact items */
    .events-list {
        padding: 0 20px 20px;
    }

    .event-item {
        display: flex;
        align-items: center;
        padding: 12px;
        border-radius: 10px;
        transition: all 0.2s ease;
        margin-bottom: 10px;
        border: 1px solid #e2e8f0;
        background: #fafbfc;
    }

    .event-item:hover {
        background: #f7fafc;
        border-color: #cbd5e0;
        transform: translateX(3px);
    }

    .event-date {
        text-align: center;
        min-width: 45px;
        margin-right: 12px;
        padding: 6px;
        background: white;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .event-day {
        font-size: 1.25rem;
        font-weight: 700;
        color: #667eea;
        line-height: 1;
    }

    .event-month {
        font-size: 0.65rem;
        font-weight: 700;
        color: #a0aec0;
        text-transform: uppercase;
        margin-top: 2px;
    }

    .event-details {
        flex: 1;
        min-width: 0;
    }

    .event-name {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 3px;
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .event-meta {
        display: flex;
        gap: 8px;
        font-size: 0.75rem;
        color: #718096;
        align-items: center;
    }

    .event-badge {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .badge-whole {
        background: #d1fae5;
        color: #047857;
    }

    .badge-half {
        background: #fef3c7;
        color: #d97706;
    }

    /* View Button */
    .btn-view-details {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 4px;
        cursor: pointer;
        white-space: nowrap;
    }

    .btn-view-details:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        color: white;
    }

    /* Chart Container */
    .chart-container {
        padding: 0 20px 20px;
    }

    .chart-wrapper {
        position: relative;
        height: 200px;
    }

    /* Modal Styles - Compact */
    .modal-content {
        border: none;
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
    }

    .modal-header {
        background: var(--primary-gradient);
        color: white;
        padding: 18px 24px;
        border-bottom: none;
    }

    .modal-title {
        font-weight: 700;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-close-white {
        filter: invert(1) grayscale(100%) brightness(200%);
        opacity: 0.8;
    }

    .modal-body {
        padding: 20px;
        background: #fafbfc;
    }

    .event-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 16px;
    }

    .event-detail-item {
        display: flex;
        align-items: flex-start;
        padding: 12px;
        background: white;
        border-radius: 10px;
        border-left: 3px solid #667eea;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }

    .event-detail-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 10px;
        color: #667eea;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .event-detail-content {
        flex: 1;
        min-width: 0;
    }

    .event-detail-label {
        font-size: 0.7rem;
        color: #718096;
        font-weight: 700;
        margin-bottom: 2px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .event-detail-value {
        font-size: 0.85rem;
        color: #1a202c;
        font-weight: 600;
        word-wrap: break-word;
    }

    .event-description-section {
        background: white;
        border-radius: 10px;
        padding: 16px;
        border-top: 3px solid #764ba2;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
    }

    .event-description-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        color: #764ba2;
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .event-description-text {
        color: #4a5568;
        line-height: 1.6;
        font-size: 0.85rem;
        white-space: pre-wrap;
    }

    .modal-footer {
        border-top: 1px solid #e2e8f0;
        padding: 16px 20px;
        background: white;
    }

    .btn-modal-close {
        background: #e2e8f0;
        color: #4a5568;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s ease;
    }

    .btn-modal-close:hover {
        background: #cbd5e0;
        color: #2d3748;
    }

    .btn-modal-edit {
        background: var(--primary-gradient);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-modal-edit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        color: white;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 30px;
        color: #a0aec0;
    }

    .empty-state i {
        font-size: 2.5rem;
        margin-bottom: 12px;
        display: block;
    }

    .empty-state p {
        font-size: 0.9rem;
        margin-bottom: 12px;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .quick-actions {
            grid-template-columns: repeat(2, 1fr);
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 992px) {
        .main-content {
            margin-left: 0;
        }

        .content-grid {
            grid-template-columns: 1fr;
        }

        .event-detail-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {

        .quick-actions,
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .header-content {
            flex-direction: column;
            gap: 12px;
            text-align: center;
        }

        .welcome-text h1 {
            font-size: 1.4rem;
        }

        .event-item {
            flex-direction: column;
            text-align: center;
            gap: 10px;
        }

        .event-date {
            margin-right: 0;
        }

        .event-meta {
            justify-content: center;
            flex-wrap: wrap;
        }
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(15px);
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
    <div class="main-content">

        <!-- Dashboard Header with Date -->
        <div class="dashboard-header">
            <div class="container">
                <div class="header-content">
                    <div class="welcome-text">
                        <h1>Welcome Back, Officer!</h1>
                        <p>Here's what's happening with your events today</p>
                    </div>
                    <div class="date-badge">
                        <i class="bi bi-calendar3"></i>
                        <span id="currentDate"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="quick-actions animate-in">
                <a href="create_event.php" class="action-btn">
                    <div class="action-icon"><i class="bi bi-calendar-plus"></i></div>
                    <span class="action-label">Create Event</span>
                </a>
                <a href="manage_students.php" class="action-btn">
                    <div class="action-icon"><i class="bi bi-people"></i></div>
                    <span class="action-label">Manage Students</span>
                </a>
                <a href="attendance_report.php" class="action-btn">
                    <div class="action-icon"><i class="bi bi-clipboard-data"></i></div>
                    <span class="action-label">View Reports</span>
                </a>
                <a href="fine_management.php" class="action-btn">
                    <div class="action-icon"><i class="bi bi-cash-stack"></i></div>
                    <span class="action-label">Manage Fines</span>
                </a>
            </div>

            <div class="stats-grid">
                <div class="stat-card primary animate-in delay-1">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                        <span class="stat-trend">+12% <i class="bi bi-arrow-up-short"></i></span>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_students); ?></div>
                    <div class="stat-label">Total Students Enrolled</div>
                </div>

                <div class="stat-card success animate-in delay-2">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="bi bi-calendar-event-fill"></i></div>
                        <span class="stat-trend">Active</span>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_events); ?></div>
                    <div class="stat-label">Events Organized</div>
                </div>

                <div class="stat-card warning animate-in delay-3">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <span class="stat-trend">+85% <i class="bi bi-arrow-up-short"></i></span>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_attendance); ?></div>
                    <div class="stat-label">Attendance Records</div>
                </div>

                <div class="stat-card danger animate-in delay-4">
                    <div class="stat-header">
                        <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <span class="stat-trend">Pending</span>
                    </div>
                    <div class="stat-value">Rs <?php echo number_format($pending_fines, 2); ?></div>
                    <div class="stat-label">Unpaid Fines</div>
                </div>
            </div>

            <div class="content-grid">
                <div class="content-card animate-in delay-2">
                    <div class="card-header-custom">
                        <h3 class="card-title">
                            <i class="bi bi-calendar-week text-primary"></i>
                            Upcoming Events
                        </h3>
                        <a href="events_list.php" class="card-action">
                            View All <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="events-list">
                        <?php if ($upcoming->num_rows > 0): ?>
                        <?php while ($row = $upcoming->fetch_assoc()): 
                            $date = strtotime($row['event_date']);
                            $day = date('d', $date);
                            $month = date('M', $date);
                            $is_whole = $row['event_type'] == 'whole_day';
                            
                            $eventData = [
                                'id' => $row['event_id'],
                                'name' => $row['event_name'],
                                'date' => date('l, F d, Y', strtotime($row['event_date'])),
                                'raw_date' => $row['event_date'],
                                'type' => $row['event_type'],
                                'description' => $row['description'] ?? 'No description available for this event.',
                                'location' => $row['location'] ?? 'Location not specified',
                                'created_by' => !empty($row['full_name']) ? $row['full_name'] : ('Officer #' . $row['created_by']),
                                'created_at' => date('M d, Y \\a\\t h:i A', strtotime($row['created_at'])),
                                'attendance' => $row['attendance_count']
                            ];
                        ?>
                        <div class="event-item">
                            <div class="event-date">
                                <div class="event-day"><?php echo $day; ?></div>
                                <div class="event-month"><?php echo $month; ?></div>
                            </div>
                            <div class="event-details">
                                <div class="event-name"><?php echo htmlspecialchars($row['event_name']); ?></div>
                                <div class="event-meta">
                                    <span><i class="bi bi-people me-1"></i> <?php echo $row['attendance_count']; ?>
                                        attended</span>
                                    <span class="event-badge <?php echo $is_whole ? 'badge-whole' : 'badge-half'; ?>">
                                        <?php echo $is_whole ? 'Whole Day' : 'Half Day'; ?>
                                    </span>
                                </div>
                            </div>
                            <button type="button" class="btn-view-details" data-bs-toggle="modal"
                                data-bs-target="#eventViewModal"
                                data-event='<?php echo htmlspecialchars(json_encode($eventData), ENT_QUOTES, 'UTF-8'); ?>'>
                                <i class="bi bi-eye-fill"></i> View
                            </button>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-calendar-x"></i>
                            <p>No upcoming events scheduled</p>
                            <a href="create_event.php" class="btn btn-primary btn-sm mt-2">Create Event</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-card animate-in delay-3">
                    <div class="card-header-custom">
                        <h3 class="card-title">
                            <i class="bi bi-pie-chart-fill text-success"></i>
                            Fine Collection Status
                        </h3>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                                data-bs-toggle="dropdown" style="font-size: 0.8rem; padding: 4px 12px;">
                                This Month
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">This Month</a></li>
                                <li><a class="dropdown-item" href="#">Last 3 Months</a></li>
                                <li><a class="dropdown-item" href="#">This Year</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="chart-container">
                        <div class="chart-wrapper">
                            <canvas id="fineChart"></canvas>
                        </div>
                        <div class="row mt-3 text-center">
                            <div class="col-6">
                                <div class="text-success fw-bold" style="font-size: 1.1rem;">Rs
                                    <?php echo number_format($paid_fines, 2); ?></div>
                                <div class="text-muted small" style="font-size: 0.75rem;">Collected</div>
                            </div>
                            <div class="col-6">
                                <div class="text-danger fw-bold" style="font-size: 1.1rem;">Rs
                                    <?php echo number_format($pending_fines, 2); ?></div>
                                <div class="text-muted small" style="font-size: 0.75rem;">Pending</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EVENT VIEW MODAL -->
        <div class="modal fade" id="eventViewModal" tabindex="-1" aria-labelledby="eventViewModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="eventViewModalLabel">
                            <i class="bi bi-calendar-event-fill"></i>
                            <span id="modalEventTitle">Event Details</span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="event-detail-grid">
                            <div class="event-detail-item">
                                <div class="event-detail-icon">
                                    <i class="bi bi-calendar3"></i>
                                </div>
                                <div class="event-detail-content">
                                    <div class="event-detail-label">Event Date</div>
                                    <div class="event-detail-value" id="modalEventDate">-</div>
                                </div>
                            </div>

                            <div class="event-detail-item">
                                <div class="event-detail-icon">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div class="event-detail-content">
                                    <div class="event-detail-label">Event Type</div>
                                    <div class="event-detail-value" id="modalEventType">-</div>
                                </div>
                            </div>

                            <div class="event-detail-item">
                                <div class="event-detail-icon">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </div>
                                <div class="event-detail-content">
                                    <div class="event-detail-label">Location</div>
                                    <div class="event-detail-value" id="modalEventLocation">-</div>
                                </div>
                            </div>

                            <div class="event-detail-item">
                                <div class="event-detail-icon">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div class="event-detail-content">
                                    <div class="event-detail-label">Attendance Count</div>
                                    <div class="event-detail-value" id="modalEventAttendance">-</div>
                                </div>
                            </div>

                            <div class="event-detail-item">
                                <div class="event-detail-icon">
                                    <i class="bi bi-person-badge-fill"></i>
                                </div>
                                <div class="event-detail-content">
                                    <div class="event-detail-label">Created By</div>
                                    <div class="event-detail-value" id="modalEventCreator">-</div>
                                </div>
                            </div>

                            <div class="event-detail-item">
                                <div class="event-detail-icon">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div class="event-detail-content">
                                    <div class="event-detail-label">Created At</div>
                                    <div class="event-detail-value" id="modalEventCreated">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="event-description-section">
                            <div class="event-description-header">
                                <i class="bi bi-text-paragraph"></i>
                                Event Description
                            </div>
                            <div class="event-description-text" id="modalEventDescription">
                                -
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-close" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg"></i> Close
                        </button>
                        <a href="#" id="modalEditLink" class="btn-modal-edit">
                            <i class="bi bi-pencil-square"></i> Edit Event
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script>
        // Set current date in header
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Event Modal Handler
        const eventViewModal = document.getElementById('eventViewModal');
        eventViewModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const eventData = JSON.parse(button.getAttribute('data-event'));

            document.getElementById('modalEventTitle').textContent = eventData.name;
            document.getElementById('modalEventDate').textContent = eventData.date;

            const typeMap = {
                'whole_day': 'Whole Day Event',
                'half_day_am': 'Half Day (Morning)',
                'half_day_pm': 'Half Day (Afternoon)'
            };
            document.getElementById('modalEventType').textContent = typeMap[eventData.type] || eventData.type
                .replace(/_/g, ' ').replace(/\\b\\w/g, l => l.toUpperCase());

            document.getElementById('modalEventLocation').textContent = eventData.location;
            document.getElementById('modalEventAttendance').textContent = eventData.attendance + ' students';
            document.getElementById('modalEventCreator').textContent = eventData.created_by;
            document.getElementById('modalEventCreated').textContent = eventData.created_at;
            document.getElementById('modalEventDescription').textContent = eventData.description;

            document.getElementById('modalEditLink').href = 'edit_event.php?id=' + eventData.id;
        });

        // Fine Chart - Compact sizing
        new Chart(document.getElementById('fineChart'), {
            type: 'doughnut',
            data: {
                labels: ['Paid Fines', 'Pending Fines'],
                datasets: [{
                    data: [<?php echo $paid_fines; ?>, <?php echo $pending_fines; ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: '#1a202c',
                        padding: 10,
                        cornerRadius: 6,
                        titleFont: {
                            size: 12
                        },
                        bodyFont: {
                            size: 12
                        },
                        callbacks: {
                            label: function(context) {
                                return ' Rs ' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        </script>
</body>

</html>'''
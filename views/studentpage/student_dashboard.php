<?php
session_start();
require "../../Connection/connection.php";

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../../Login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// --- AJAX Handlers ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    switch ($_GET['action']) {
        case 'get_stats':
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $totalEvents = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM student_fines WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $totalFines = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM student_fines WHERE student_id = ? AND status = 'unpaid'");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $unpaidAmount = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            echo json_encode([
                'success' => true,
                'totalEvents' => $totalEvents,
                'totalFines' => $totalFines,
                'unpaidAmount' => $unpaidAmount
            ]);
            exit;

        case 'get_upcoming':
            $query = "
                SELECT e.event_id, e.event_name, e.event_date, e.event_type, e.half_day_period,
                       s.am_login_start, s.am_login_end, s.am_logout_start, s.am_logout_end,
                       s.pm_login_start, s.pm_login_end, s.pm_logout_start, s.pm_logout_end
                FROM events e
                LEFT JOIN attendance_schedule s ON e.event_id = s.event_id
                WHERE e.event_date >= CURDATE()
                ORDER BY e.event_date ASC
                LIMIT 5
            ";
            $result = $conn->query($query);
            $events = [];
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            echo json_encode(['success' => true, 'events' => $events]);
            exit;

        case 'get_recent_attendance':
            $stmt = $conn->prepare("
                SELECT a.event_id, e.event_name, a.am_login_time, a.am_logout_time, a.pm_login_time, a.pm_logout_time, a.created_at
                FROM attendance a
                JOIN events e ON a.event_id = e.event_id
                WHERE a.student_id = ?
                ORDER BY a.created_at DESC
                LIMIT 5
            ");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $attendance = [];
            while ($row = $result->fetch_assoc()) {
                $attendance[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'attendance' => $attendance]);
            exit;

        case 'get_attendance_history':
            $stmt = $conn->prepare("
                SELECT a.event_id, e.event_name, e.event_date, a.am_login_time, a.am_logout_time, a.pm_login_time, a.pm_logout_time, a.created_at
                FROM attendance a
                JOIN events e ON a.event_id = e.event_id
                WHERE a.student_id = ?
                ORDER BY a.created_at DESC
                LIMIT 10
            ");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'history' => $history]);
            exit;

        case 'get_fines':
            $stmt = $conn->prepare("
                SELECT f.fine_id, f.event_id, e.event_name, f.fine_reason, f.amount, f.status, f.recorded_at
                FROM student_fines f
                JOIN events e ON f.event_id = e.event_id
                WHERE f.student_id = ?
                ORDER BY f.recorded_at DESC
                LIMIT 5
            ");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $fines = [];
            while ($row = $result->fetch_assoc()) {
                $fines[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'fines' => $fines]);
            exit;
    }
}

// --- Initial page load data ---
$stmt = $conn->prepare("SELECT student_id, full_name, year_level, section, created_at FROM students WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$totalEvents = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM student_fines WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$totalFines = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM student_fines WHERE student_id = ? AND status = 'unpaid'");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$unpaidAmount = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$upcomingQuery = "
    SELECT e.event_id, e.event_name, e.event_date, e.event_type, e.half_day_period,
           s.am_login_start, s.am_login_end, s.am_logout_start, s.am_logout_end,
           s.pm_login_start, s.pm_login_end, s.pm_logout_start, s.pm_logout_end
    FROM events e
    LEFT JOIN attendance_schedule s ON e.event_id = s.event_id
    WHERE e.event_date >= CURDATE()
    ORDER BY e.event_date ASC
    LIMIT 5
";
$upcomingEvents = $conn->query($upcomingQuery);

$recentAttendanceQuery = "
    SELECT a.event_id, e.event_name, a.am_login_time, a.am_logout_time, a.pm_login_time, a.pm_logout_time, a.created_at
    FROM attendance a
    JOIN events e ON a.event_id = e.event_id
    WHERE a.student_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
";
$stmt = $conn->prepare($recentAttendanceQuery);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$recentAttendance = $stmt->get_result();
$stmt->close();

$attendanceHistoryQuery = "
    SELECT a.event_id, e.event_name, e.event_date, a.am_login_time, a.am_logout_time, a.pm_login_time, a.pm_logout_time, a.created_at
    FROM attendance a
    JOIN events e ON a.event_id = e.event_id
    WHERE a.student_id = ?
    ORDER BY a.created_at DESC
    LIMIT 10
";
$stmt = $conn->prepare($attendanceHistoryQuery);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$attendanceHistory = $stmt->get_result();
$stmt->close();

// Keep connection open for AJAX
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | BEAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary: #33A1E0;
        --primary-dark: #1e6f9f;
        --primary-light: #e6f2ff;
        --success: #28a745;
        --danger: #dc3545;
        --warning: #ffc107;
        --bg-light: #f4f7fc;
        --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
        --card-hover-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.15);
        --transition: all 0.2s ease;
    }

    body {
        background-color: var(--bg-light);
        font-family: 'Inter', sans-serif;
        color: #1e293b;
    }

    .main-content {
        margin-left: 260px;
        padding: 30px;
        transition: margin-left 0.3s ease;
    }

    @media (max-width: 991.98px) {
        .main-content {
            margin-left: 0;
            padding: 20px;
        }
    }

    .page-header {
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .page-header h2 {
        font-weight: 700;
        color: #0f172a;
        font-size: 2rem;
    }

    .page-header h2 i {
        color: var(--primary);
        margin-right: 0.5rem;
    }

    /* Profile card */
    .profile-card {
        background: linear-gradient(145deg, #ffffff 0%, #f9fbfd 100%);
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    }

    .profile-avatar {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 600;
        color: white;
        box-shadow: 0 8px 16px rgba(51, 161, 224, 0.2);
    }

    .stat-badge {
        background: #e9ecef;
        border-radius: 30px;
        padding: 0.4rem 1rem;
        font-size: 0.9rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #495057;
    }

    .stat-badge i {
        color: var(--primary);
    }

    /* Stats cards */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid #edf2f7;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .stat-icon.primary {
        background: var(--primary-light);
        color: var(--primary);
    }

    .stat-icon.success {
        background: #d1fae5;
        color: var(--success);
    }

    .stat-icon.warning {
        background: #fff3cd;
        color: var(--warning);
    }

    .stat-info h3 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 0.2rem;
    }

    .stat-info p {
        color: #64748b;
        font-size: 0.9rem;
        font-weight: 500;
        margin: 0;
    }

    /* Action buttons */
    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1rem;
        margin: 1.5rem 0;
    }

    .action-btn {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        padding: 1rem 0.5rem;
        text-align: center;
        color: #1e293b;
        text-decoration: none;
        transition: var(--transition);
        font-weight: 500;
    }

    .action-btn i {
        display: block;
        font-size: 1.6rem;
        margin-bottom: 0.5rem;
        color: var(--primary);
    }

    .action-btn:hover {
        background: #f1f5f9;
        border-color: var(--primary);
        transform: translateY(-2px);
        color: #0f172a;
    }

    /* Dashboard cards */
    .dashboard-card {
        background: white;
        border-radius: 20px;
        box-shadow: var(--card-shadow);
        border: 1px solid #edf2f7;
        transition: var(--transition);
        height: 100%;
    }

    .dashboard-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--card-hover-shadow);
    }

    .card-header-custom {
        padding: 1.25rem 1.5rem 0.5rem;
        background: transparent;
        border-bottom: 0;
        font-weight: 600;
        font-size: 1.1rem;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .card-header-custom i {
        color: var(--primary);
        font-size: 1.2rem;
    }

    .card-body-custom {
        padding: 0.5rem 1.5rem 1.5rem;
    }

    .compact-table {
        width: 100%;
        font-size: 0.9rem;
    }

    .compact-table td {
        padding: 0.6rem 0;
        border-bottom: 1px solid #edf2f7;
    }

    .compact-table tr:last-child td {
        border-bottom: 0;
    }

    .badge-status {
        padding: 0.25rem 0.6rem;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .badge-upcoming {
        background: #e6f2ff;
        color: var(--primary);
    }

    .badge-paid {
        background: #d1fae5;
        color: #0d9488;
    }

    .badge-unpaid {
        background: #fee2e2;
        color: #b91c1c;
    }

    /* Attendance history table */
    .history-table th {
        font-size: 0.8rem;
        color: #64748b;
        font-weight: 600;
        border-bottom: 1px solid #edf2f7;
        padding: 0.5rem 0;
    }

    .history-table td {
        padding: 0.5rem 0;
        border-bottom: 1px solid #edf2f7;
    }

    /* WebSocket status */
    .ws-status {
        position: fixed;
        bottom: 20px;
        left: 300px;
        padding: 0.5rem 1rem;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        z-index: 1000;
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        border: 1px solid transparent;
    }

    @media (max-width: 991.98px) {
        .ws-status {
            left: 20px;
        }
    }

    .ws-status.connected {
        color: var(--success);
        border-color: var(--success);
    }

    .ws-status.disconnected {
        color: var(--danger);
        border-color: var(--danger);
    }

    .ws-status.connecting {
        color: var(--warning);
        border-color: var(--warning);
    }

    .ws-status i {
        font-size: 0.5rem;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }
    </style>
</head>

<body>

    <?php include "../sidebar/student_sidebar.php"; ?>

    <div class="ws-status disconnected" id="wsStatus">
        <i class="fas fa-circle"></i>
        <span>Offline</span>
    </div>

    <div class="main-content">
        <div class="container-fluid px-0">
            <div class="page-header">
                <h2><i class="fas fa-chart-pie"></i>Dashboard</h2>
                <span class="text-muted small"><i class="far fa-calendar-alt me-1"></i><?= date('l, F j, Y') ?></span>
            </div>

            <!-- Student profile card -->
            <?php if ($student): ?>
            <div class="profile-card mb-4">
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                    </div>
                    <div class="flex-grow-1">
                        <h4 class="fw-bold mb-1"><?= htmlspecialchars($student['full_name']) ?></h4>
                        <div class="d-flex flex-wrap gap-3 align-items-center">
                            <span class="stat-badge"><i class="fas fa-id-card"></i>
                                <?= htmlspecialchars($student['student_id']) ?></span>
                            <span class="stat-badge"><i class="fas fa-graduation-cap"></i> Year
                                <?= htmlspecialchars($student['year_level']) ?></span>
                            <span class="stat-badge"><i class="fas fa-users"></i> Section
                                <?= htmlspecialchars($student['section'] ?: 'N/A') ?></span>
                            <span class="stat-badge"><i class="fas fa-calendar-plus"></i> Joined
                                <?= date('M Y', strtotime($student['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats row -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-info">
                        <h3 id="totalEvents"><?= $totalEvents ?></h3>
                        <p>Events Attended</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-info">
                        <h3 id="totalFines"><?= $totalFines ?></h3>
                        <p>Total Fines</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-coins"></i></div>
                    <div class="stat-info">
                        <h3 id="unpaidAmount">₱<?= number_format($unpaidAmount, 2) ?></h3>
                        <p>Unpaid Amount</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="action-grid">
                <a href="student_event.php" class="action-btn"><i class="fas fa-calendar-alt"></i>Events</a>
                <a href="student_attendance.php" class="action-btn"><i class="fas fa-clock"></i>Attendance</a>
                <a href="student_fines.php" class="action-btn"><i class="fas fa-coins"></i>Fines</a>
                <a href="student_profile.php" class="action-btn"><i class="fas fa-user-cog"></i>Profile</a>
            </div>

            <!-- Two column layout: Upcoming Events & Recent Attendance -->
            <div class="row g-4">
                <!-- Upcoming Events -->
                <div class="col-lg-6">
                    <div class="dashboard-card">
                        <div class="card-header-custom">
                            <i class="fas fa-calendar-alt"></i> Upcoming Events
                        </div>
                        <div class="card-body-custom" id="upcomingEventsContainer">
                            <?php if ($upcomingEvents->num_rows > 0): ?>
                            <table class="compact-table" id="upcomingEventsTable">
                                <?php while ($event = $upcomingEvents->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($event['event_name']) ?></div>
                                        <small class="text-secondary">
                                            <?= date('M d, Y', strtotime($event['event_date'])) ?>
                                            · <?= str_replace('_', ' ', $event['event_type']) ?>
                                            <?php if ($event['half_day_period']): ?>
                                            (<?= strtoupper($event['half_day_period']) ?>)<?php endif; ?>
                                        </small>
                                        <?php if ($event['am_login_start']): ?>
                                        <br><small class="text-muted">AM:
                                            <?= substr($event['am_login_start'],0,5) ?>-<?= substr($event['am_logout_end'],0,5) ?></small>
                                        <?php endif; ?>
                                        <?php if ($event['pm_login_start']): ?>
                                        <br><small class="text-muted">PM:
                                            <?= substr($event['pm_login_start'],0,5) ?>-<?= substr($event['pm_logout_end'],0,5) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge-status badge-upcoming">Upcoming</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </table>
                            <?php else: ?>
                            <p class="text-muted mb-0">No upcoming events.</p>
                            <?php endif; ?>
                            <div class="mt-3 text-end">
                                <a href="student_event.php" class="small fw-semibold text-decoration-none">View all <i
                                        class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance -->
                <div class="col-lg-6">
                    <div class="dashboard-card">
                        <div class="card-header-custom">
                            <i class="fas fa-history"></i> Recent Attendance
                        </div>
                        <div class="card-body-custom" id="recentAttendanceContainer">
                            <?php if ($recentAttendance->num_rows > 0): ?>
                            <table class="compact-table" id="recentAttendanceTable">
                                <?php while ($att = $recentAttendance->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($att['event_name']) ?></div>
                                        <small class="text-secondary">
                                            <?php 
                                                $times = [];
                                                if ($att['am_login_time']) $times[] = 'AM in '.date('H:i', strtotime($att['am_login_time']));
                                                if ($att['am_logout_time']) $times[] = 'AM out';
                                                if ($att['pm_login_time']) $times[] = 'PM in '.date('H:i', strtotime($att['pm_login_time']));
                                                if ($att['pm_logout_time']) $times[] = 'PM out';
                                                echo $times ? implode(' · ', $times) : 'No times recorded';
                                            ?>
                                        </small>
                                    </td>
                                    <td class="text-end">
                                        <span
                                            class="badge-status"><?= date('M d', strtotime($att['created_at'])) ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </table>
                            <?php else: ?>
                            <p class="text-muted mb-0">No attendance records yet.</p>
                            <?php endif; ?>
                            <div class="mt-3 text-end">
                                <a href="student_attendance.php" class="small fw-semibold text-decoration-none">View all
                                    <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance History (replaces Recent Fines) -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="dashboard-card">
                        <div class="card-header-custom">
                            <i class="fas fa-clock"></i> Attendance History
                        </div>
                        <div class="card-body-custom" id="attendanceHistoryContainer">
                            <?php if ($attendanceHistory->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle history-table">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>AM Login</th>
                                            <th>AM Logout</th>
                                            <th>PM Login</th>
                                            <th>PM Logout</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($rec = $attendanceHistory->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($rec['event_name']) ?></td>
                                            <td><?= date('M d, Y', strtotime($rec['event_date'])) ?></td>
                                            <td><?= $rec['am_login_time'] ? date('g:i A', strtotime($rec['am_login_time'])) : '—' ?>
                                            </td>
                                            <td><?= $rec['am_logout_time'] ? date('g:i A', strtotime($rec['am_logout_time'])) : '—' ?>
                                            </td>
                                            <td><?= $rec['pm_login_time'] ? date('g:i A', strtotime($rec['pm_login_time'])) : '—' ?>
                                            </td>
                                            <td><?= $rec['pm_logout_time'] ? date('g:i A', strtotime($rec['pm_logout_time'])) : '—' ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <p class="text-muted mb-0">No attendance history.</p>
                            <?php endif; ?>
                            <div class="mt-3 text-end">
                                <a href="student_attendance.php" class="small fw-semibold text-decoration-none">View
                                    full history <i class="fas fa-arrow-right ms-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // --- State ---
    const WS_CONFIG = {
        host: '<?php echo $_SERVER['HTTP_HOST']; ?>',
        port: 8080,
        protocol: window.location.protocol === 'https:' ? 'wss:' : 'ws:',
        reconnectInterval: 3000,
        maxReconnectAttempts: 5
    };
    let ws = null;
    let reconnectAttempts = 0;
    let reconnectTimer = null;
    const studentId = '<?php echo $student_id; ?>';

    let refreshInterval = setInterval(() => {
        refreshAll();
    }, 5000);

    document.addEventListener('DOMContentLoaded', function() {
        initWebSocket();
        setTimeout(refreshAll, 1000);
    });

    function refreshAll() {
        refreshStats();
        refreshUpcomingEvents();
        refreshRecentAttendance();
        refreshAttendanceHistory();
    }

    // --- AJAX refresh functions (unchanged) ---
    async function refreshStats() {
        /* ... same as before ... */ }
    async function refreshUpcomingEvents() {
        /* ... same ... */ }
    async function refreshRecentAttendance() {
        /* ... same ... */ }
    async function refreshAttendanceHistory() {
        /* ... same ... */ }

    function escapeHtml(text) {
        /* ... same ... */ }

    function formatDate(dateStr) {
        /* ... same ... */ }

    function formatTime(datetimeStr) {
        /* ... same ... */ }

    // --- WebSocket with subscription and dual message handling ---
    function updateWSStatus(status, text) {
        const el = document.getElementById('wsStatus');
        if (el) {
            el.className = `ws-status ${status}`;
            el.innerHTML = `<i class="fas fa-circle"></i><span>${text}</span>`;
        }
    }

    function initWebSocket() {
        if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;
        const wsUrl = `${WS_CONFIG.protocol}//${WS_CONFIG.host}:${WS_CONFIG.port}`;
        updateWSStatus('connecting', 'Connecting...');
        try {
            ws = new WebSocket(wsUrl);
            ws.onopen = () => {
                updateWSStatus('connected', 'Live');
                reconnectAttempts = 0;
                // Send subscription
                ws.send(JSON.stringify({
                    type: 'subscribe',
                    student_id: studentId
                }));
            };
            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                // Ignore messages intended for other students (if any)
                if (data.student_id && data.student_id !== studentId) return;

                // Handle student updates (from direct broadcast or internal socket)
                if (data.type === 'STUDENT_UPDATED' || data.type === 'student_updated') {
                    refreshAll();
                }
                // You can also handle attendance/events/fines updates here if needed
                if (data.type === 'attendance_updated' || data.type === 'fines_updated' || data.type ===
                    'events_updated') {
                    refreshAll();
                }
            };
            ws.onclose = () => {
                updateWSStatus('disconnected', 'Offline');
                if (reconnectAttempts < WS_CONFIG.maxReconnectAttempts) {
                    reconnectAttempts++;
                    updateWSStatus('connecting', `Reconnecting (${reconnectAttempts})...`);
                    reconnectTimer = setTimeout(initWebSocket, WS_CONFIG.reconnectInterval);
                }
            };
            ws.onerror = (err) => {
                console.error(err);
                updateWSStatus('disconnected', 'Error');
            };
        } catch (e) {
            console.error(e);
            updateWSStatus('disconnected', 'Failed');
        }
    }

    window.addEventListener('beforeunload', () => {
        if (reconnectTimer) clearTimeout(reconnectTimer);
        if (ws) ws.close();
        if (refreshInterval) clearInterval(refreshInterval);
    });
    </script>
</body>

</html>
<?php
// Connection stays open for AJAX
?>
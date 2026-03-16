<?php
session_start();
date_default_timezone_set('Asia/Manila'); // <-- ADD THIS LINE

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
        case 'get_current_event':
            $today = date('Y-m-d');
            $query = "
                SELECT e.event_id, e.event_name, e.event_date, e.event_type, e.half_day_period,
                       s.am_login_start, s.am_login_end, s.am_logout_start, s.am_logout_end,
                       s.pm_login_start, s.pm_login_end, s.pm_logout_start, s.pm_logout_end
                FROM events e
                LEFT JOIN attendance_schedule s ON e.event_id = s.event_id
                WHERE e.event_date = ?
                LIMIT 1
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $event = $result->fetch_assoc();
            $stmt->close();

            if (!$event) {
                echo json_encode(['success' => false, 'message' => 'No event today']);
                exit;
            }

            // Get student's existing attendance for this event
            $stmt = $conn->prepare("SELECT am_login_time, am_logout_time, pm_login_time, pm_logout_time FROM attendance WHERE student_id = ? AND event_id = ?");
            $stmt->bind_param("si", $student_id, $event['event_id']);
            $stmt->execute();
            $att = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $event['attendance'] = $att ?: [
                'am_login_time' => null,
                'am_logout_time' => null,
                'pm_login_time' => null,
                'pm_logout_time' => null
            ];

            echo json_encode(['success' => true, 'event' => $event]);
            exit;

        case 'record_attendance':
            $data = json_decode(file_get_contents('php://input'), true);
            $event_id = $data['event_id'];
            $field = $data['field'];

            $allowed = ['am_login_time', 'am_logout_time', 'pm_login_time', 'pm_logout_time'];
            if (!in_array($field, $allowed)) {
                echo json_encode(['success' => false, 'message' => 'Invalid field']);
                exit;
            }

            // Check if an attendance record exists
            $stmt = $conn->prepare("SELECT attendance_id FROM attendance WHERE student_id = ? AND event_id = ?");
            $stmt->bind_param("si", $student_id, $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->fetch_assoc();
            $stmt->close();

            $now = date('Y-m-d H:i:s'); // now in local time

            if ($exists) {
                $sql = "UPDATE attendance SET $field = ? WHERE student_id = ? AND event_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $now, $student_id, $event_id);
            } else {
                $sql = "INSERT INTO attendance (student_id, event_id, $field, created_at) VALUES (?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sis", $student_id, $event_id, $now);
            }

            if ($stmt->execute()) {
                broadcastWebSocket([
                    'type' => 'attendance_updated',
                    'student_id' => $student_id,
                    'event_id' => $event_id,
                    'field' => $field,
                    'timestamp' => time()
                ]);
                echo json_encode(['success' => true, 'message' => 'Recorded successfully', 'time' => $now]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            }
            $stmt->close();
            exit;

        case 'get_attendance':
            $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // 'all' or 'missed'

            // Attended events
            $attendedQuery = "
                SELECT a.attendance_id, e.event_id, e.event_name, e.event_date, e.event_type, e.half_day_period,
                       a.am_login_time, a.am_logout_time, a.pm_login_time, a.pm_logout_time, a.created_at
                FROM attendance a
                JOIN events e ON a.event_id = e.event_id
                WHERE a.student_id = ?
                ORDER BY e.event_date DESC
            ";
            $stmt = $conn->prepare($attendedQuery);
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $attendedResult = $stmt->get_result();
            $attended = [];
            while ($row = $attendedResult->fetch_assoc()) {
                $attended[] = $row;
            }
            $stmt->close();

            // Missed events (past events with no attendance)
            $missedQuery = "
                SELECT e.event_id, e.event_name, e.event_date, e.event_type, e.half_day_period,
                       s.am_login_start, s.am_login_end, s.am_logout_start, s.am_logout_end,
                       s.pm_login_start, s.pm_login_end, s.pm_logout_start, s.pm_logout_end
                FROM events e
                LEFT JOIN attendance_schedule s ON e.event_id = s.event_id
                WHERE e.event_date < CURDATE()
                  AND e.event_id NOT IN (
                      SELECT event_id FROM attendance WHERE student_id = ?
                  )
                ORDER BY e.event_date DESC
                LIMIT 50
            ";
            $stmt = $conn->prepare($missedQuery);
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $missedResult = $stmt->get_result();
            $missed = [];
            while ($row = $missedResult->fetch_assoc()) {
                $missed[] = $row;
            }
            $stmt->close();

            if ($filter === 'missed') {
                echo json_encode(['success' => true, 'attendance' => [], 'missed' => $missed]);
            } else {
                echo json_encode(['success' => true, 'attendance' => $attended, 'missed' => []]);
            }
            exit;
    }
}

function broadcastWebSocket($data) {
    $socket = @fsockopen('tcp://127.0.0.1', 8081, $errno, $errstr, 1);
    if ($socket) {
        fwrite($socket, json_encode($data) . "\n");
        fclose($socket);
    }
}

// Stats for initial page load
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM attendance WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$totalAttended = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM events e
    WHERE e.event_date < CURDATE()
      AND e.event_id NOT IN (SELECT event_id FROM attendance WHERE student_id = ?)
");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$totalMissed = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalEvents = $totalAttended + $totalMissed;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History | BEAMS Student</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary: #33A1E0;
        --primary-light: #e6f2ff;
        --primary-dark: #1e6f9f;
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

    .stat-icon.danger {
        background: #fee2e2;
        color: var(--danger);
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

    .current-event-card {
        background: linear-gradient(145deg, #ffffff, #f8fafc);
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        border: 1px solid #edf2f7;
        padding: 1.5rem;
        margin-bottom: 2rem;
        transition: var(--transition);
    }

    .current-event-card:hover {
        box-shadow: var(--card-hover-shadow);
        border-color: var(--primary-light);
    }

    .current-event-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary-dark);
        margin-bottom: 0.5rem;
    }

    .current-event-meta {
        color: #64748b;
        margin-bottom: 1.5rem;
        font-size: 0.95rem;
    }

    .attendance-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .btn-attendance {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 40px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        color: #334155;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-attendance i {
        color: var(--primary);
    }

    .btn-attendance:hover:not(:disabled) {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .btn-attendance:hover:not(:disabled) i {
        color: white;
    }

    .btn-attendance:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-attendance.recorded {
        background: #d1fae5;
        border-color: #0d9488;
        color: #0d9488;
    }

    .btn-attendance.recorded i {
        color: #0d9488;
    }

    .attendance-tabs {
        display: flex;
        gap: 1rem;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 2rem;
    }

    .tab-btn {
        background: none;
        border: none;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        position: relative;
        transition: var(--transition);
    }

    .tab-btn:hover {
        color: var(--primary);
    }

    .tab-btn.active {
        color: var(--primary);
    }

    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--primary);
    }

    .search-wrapper {
        margin-bottom: 1.5rem;
        position: relative;
        max-width: 300px;
    }

    .search-wrapper i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    .search-wrapper input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 30px;
    }

    .search-wrapper input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(51, 161, 224, 0.15);
    }

    .attendance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1.5rem;
        margin-top: 1rem;
    }

    .skeleton-card {
        background: white;
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        animation: pulse 1.5s infinite;
    }

    .skeleton-line {
        height: 1.2rem;
        background: #e2e8f0;
        border-radius: 8px;
        margin-bottom: 0.8rem;
    }

    .skeleton-line.short {
        width: 60%;
    }

    .skeleton-line.medium {
        width: 80%;
    }

    .skeleton-line.long {
        width: 100%;
    }

    @keyframes pulse {
        0% {
            opacity: 0.6;
        }

        50% {
            opacity: 1;
        }

        100% {
            opacity: 0.6;
        }
    }

    .attendance-card {
        background: white;
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        border: 1px solid #edf2f7;
        transition: var(--transition);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .attendance-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--card-hover-shadow);
        border-color: var(--primary-light);
    }

    .card-header {
        padding: 1.5rem 1.5rem 1rem;
        background: linear-gradient(145deg, #ffffff, #fafcfc);
        border-bottom: 1px solid #edf2f7;
    }

    .event-name {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }

    .event-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem 1rem;
        font-size: 0.85rem;
        color: #64748b;
    }

    .event-meta span {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .event-meta i {
        color: var(--primary);
        width: 16px;
    }

    .card-body {
        padding: 1rem 1.5rem;
        flex: 1;
    }

    .time-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .time-icon {
        width: 24px;
        color: var(--primary);
    }

    .card-footer {
        padding: 1rem 1.5rem 1.5rem;
        border-top: 1px solid #edf2f7;
        background: #fafcfc;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .badge {
        padding: 0.35rem 1rem;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-attended {
        background: #d1fae5;
        color: #0d9488;
    }

    .badge-missed {
        background: #fee2e2;
        color: #b91c1c;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 30px;
        box-shadow: var(--card-shadow);
    }

    .empty-icon {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 1.5rem;
    }

    .empty-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }

    .empty-text {
        color: #64748b;
        margin-bottom: 2rem;
    }

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
                <h2><i class="fas fa-history"></i>Attendance History</h2>
                <span class="text-muted small"><i class="far fa-calendar-alt me-1"></i><?= date('l, F j, Y') ?></span>
            </div>

            <div id="currentEventContainer"></div>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-info">
                        <h3 id="totalEvents"><?= $totalEvents ?></h3>
                        <p>Total Events</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3 id="totalAttended"><?= $totalAttended ?></h3>
                        <p>Attended</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-info">
                        <h3 id="totalMissed"><?= $totalMissed ?></h3>
                        <p>Missed</p>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div class="attendance-tabs">
                    <button class="tab-btn active" id="tabAll">All Attendance</button>
                    <button class="tab-btn" id="tabMissed">Missed Only</button>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search events...">
                </div>
            </div>

            <div id="attendanceContainer">
                <div class="attendance-grid" id="skeletonGrid">
                    <?php for ($i=0; $i<6; $i++): ?>
                    <div class="skeleton-card">
                        <div class="skeleton-line short"></div>
                        <div class="skeleton-line medium"></div>
                        <div class="skeleton-line long"></div>
                        <div style="margin-top:1rem;">
                            <div class="skeleton-line short"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let currentFilter = 'all';
    let attendanceData = [];
    let missedData = [];
    let filteredItems = [];
    let searchTerm = '';
    let currentEvent = null;

    const container = document.getElementById('attendanceContainer');
    const currentEventContainer = document.getElementById('currentEventContainer');
    const tabAll = document.getElementById('tabAll');
    const tabMissed = document.getElementById('tabMissed');
    const searchInput = document.getElementById('searchInput');

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
        fetchCurrentEvent();
        fetchAttendance();
    }, 5000);

    document.addEventListener('DOMContentLoaded', function() {
        initWebSocket();
        fetchCurrentEvent();
        fetchAttendance();

        tabAll.addEventListener('click', () => switchFilter('all'));
        tabMissed.addEventListener('click', () => switchFilter('missed'));
        searchInput.addEventListener('input', (e) => {
            searchTerm = e.target.value.toLowerCase();
            filterAndRender();
        });
    });

    async function fetchCurrentEvent() {
        try {
            const response = await fetch('?action=get_current_event');
            const data = await response.json();
            if (data.success) {
                currentEvent = data.event;
                renderCurrentEvent(currentEvent);
            } else {
                currentEventContainer.innerHTML = '';
            }
        } catch (e) {
            console.error('Failed to fetch current event:', e);
        }
    }

    function renderCurrentEvent(event) {
        const att = event.attendance;
        const now = new Date();
        const currentTime = now.getHours() * 60 + now.getMinutes();

        function isEnabled(startTime, endTime, recorded) {
            if (recorded) return false;
            if (!startTime || !endTime) return false;
            const start = parseInt(startTime.split(':')[0]) * 60 + parseInt(startTime.split(':')[1]);
            const end = parseInt(endTime.split(':')[0]) * 60 + parseInt(endTime.split(':')[1]);
            return currentTime >= start && currentTime <= end;
        }

        const amLoginEnabled = isEnabled(event.am_login_start, event.am_login_end, att.am_login_time);
        const amLogoutEnabled = isEnabled(event.am_logout_start, event.am_logout_end, att.am_logout_time);
        const pmLoginEnabled = isEnabled(event.pm_login_start, event.pm_login_end, att.pm_login_time);
        const pmLogoutEnabled = isEnabled(event.pm_logout_start, event.pm_logout_end, att.pm_logout_time);

        const html = `
                <div class="current-event-card">
                    <div class="current-event-title">${escapeHtml(event.event_name)}</div>
                    <div class="current-event-meta">
                        <i class="fas fa-calendar-day me-1"></i>${formatDate(event.event_date)} ·
                        <i class="fas fa-tag me-1"></i>${event.event_type.replace(/_/g, ' ')}
                        ${event.half_day_period ? ' (' + event.half_day_period.toUpperCase() + ')' : ''}
                    </div>
                    <div class="attendance-buttons">
                        ${event.am_login_start ? `
                            <button class="btn-attendance ${att.am_login_time ? 'recorded' : ''}" 
                                onclick="recordAttendance(${event.event_id}, 'am_login_time')"
                                ${amLoginEnabled ? '' : 'disabled'}>
                                <i class="fas fa-sun"></i> AM Login
                                ${att.am_login_time ? ' ✓' : ''}
                            </button>
                        ` : ''}
                        ${event.am_logout_start ? `
                            <button class="btn-attendance ${att.am_logout_time ? 'recorded' : ''}" 
                                onclick="recordAttendance(${event.event_id}, 'am_logout_time')"
                                ${amLogoutEnabled ? '' : 'disabled'}>
                                <i class="fas fa-sun"></i> AM Logout
                                ${att.am_logout_time ? ' ✓' : ''}
                            </button>
                        ` : ''}
                        ${event.pm_login_start ? `
                            <button class="btn-attendance ${att.pm_login_time ? 'recorded' : ''}" 
                                onclick="recordAttendance(${event.event_id}, 'pm_login_time')"
                                ${pmLoginEnabled ? '' : 'disabled'}>
                                <i class="fas fa-moon"></i> PM Login
                                ${att.pm_login_time ? ' ✓' : ''}
                            </button>
                        ` : ''}
                        ${event.pm_logout_start ? `
                            <button class="btn-attendance ${att.pm_logout_time ? 'recorded' : ''}" 
                                onclick="recordAttendance(${event.event_id}, 'pm_logout_time')"
                                ${pmLogoutEnabled ? '' : 'disabled'}>
                                <i class="fas fa-moon"></i> PM Logout
                                ${att.pm_logout_time ? ' ✓' : ''}
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        currentEventContainer.innerHTML = html;
    }

    async function recordAttendance(eventId, field) {
        try {
            const response = await fetch('?action=record_attendance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    event_id: eventId,
                    field: field
                })
            });
            const result = await response.json();
            if (result.success) {
                if (currentEvent && currentEvent.event_id == eventId) {
                    currentEvent.attendance[field] = result.time;
                    renderCurrentEvent(currentEvent);
                }
                fetchAttendance();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (e) {
            console.error('Record error:', e);
            alert('Network error');
        }
    }

    async function fetchAttendance() {
        try {
            const response = await fetch('?action=get_attendance&filter=' + currentFilter);
            const data = await response.json();
            if (data.success) {
                attendanceData = data.attendance || [];
                missedData = data.missed || [];
                filterAndRender();
            } else {
                showError();
            }
        } catch (e) {
            console.error('Failed to fetch attendance:', e);
            showError();
        }
    }

    function filterAndRender() {
        let source = currentFilter === 'all' ? attendanceData : missedData;
        filteredItems = source.filter(item =>
            (item.event_name || '').toLowerCase().includes(searchTerm)
        );
        render();
    }

    function render() {
        if (filteredItems.length === 0) {
            container.innerHTML = getEmptyState();
        } else {
            let html = '<div class="attendance-grid">';
            filteredItems.forEach(item => {
                if (currentFilter === 'all') {
                    html += renderAttendedCard(item);
                } else {
                    html += renderMissedCard(item);
                }
            });
            html += '</div>';
            container.innerHTML = html;
        }
    }

    function renderAttendedCard(a) {
        return `
                <div class="attendance-card" data-event-id="${a.event_id}">
                    <div class="card-header">
                        <div class="event-name">${escapeHtml(a.event_name)}</div>
                        <div class="event-meta">
                            <span><i class="fas fa-calendar-day"></i> ${formatDate(a.event_date)}</span>
                            <span><i class="fas fa-tag"></i> ${a.event_type.replace(/_/g, ' ')}</span>
                            ${a.half_day_period ? `<span><i class="fas fa-clock"></i> ${a.half_day_period.toUpperCase()}</span>` : ''}
                        </div>
                    </div>
                    <div class="card-body">
                        ${a.am_login_time ? `<div class="time-row"><i class="fas fa-sun time-icon"></i><span><span class="time-value">AM Login:</span> ${formatDateTime(a.am_login_time)}</span></div>` : ''}
                        ${a.am_logout_time ? `<div class="time-row"><i class="fas fa-sun time-icon"></i><span><span class="time-value">AM Logout:</span> ${formatDateTime(a.am_logout_time)}</span></div>` : ''}
                        ${a.pm_login_time ? `<div class="time-row"><i class="fas fa-moon time-icon"></i><span><span class="time-value">PM Login:</span> ${formatDateTime(a.pm_login_time)}</span></div>` : ''}
                        ${a.pm_logout_time ? `<div class="time-row"><i class="fas fa-moon time-icon"></i><span><span class="time-value">PM Logout:</span> ${formatDateTime(a.pm_logout_time)}</span></div>` : ''}
                    </div>
                    <div class="card-footer">
                        <span class="badge badge-attended"><i class="fas fa-check-circle me-1"></i>Attended</span>
                        <span class="text-muted small">Recorded: ${formatDateShort(a.created_at)}</span>
                    </div>
                </div>`;
    }

    function renderMissedCard(m) {
        return `
                <div class="attendance-card" data-event-id="${m.event_id}">
                    <div class="card-header">
                        <div class="event-name">${escapeHtml(m.event_name)}</div>
                        <div class="event-meta">
                            <span><i class="fas fa-calendar-day"></i> ${formatDate(m.event_date)}</span>
                            <span><i class="fas fa-tag"></i> ${m.event_type.replace(/_/g, ' ')}</span>
                            ${m.half_day_period ? `<span><i class="fas fa-clock"></i> ${m.half_day_period.toUpperCase()}</span>` : ''}
                        </div>
                    </div>
                    <div class="card-body">
                        ${m.am_login_start ? `<div class="time-row"><i class="fas fa-sun time-icon"></i><span>AM Schedule: ${formatTime(m.am_login_start)} – ${formatTime(m.am_logout_end)}</span></div>` : ''}
                        ${m.pm_login_start ? `<div class="time-row"><i class="fas fa-moon time-icon"></i><span>PM Schedule: ${formatTime(m.pm_login_start)} – ${formatTime(m.pm_logout_end)}</span></div>` : ''}
                    </div>
                    <div class="card-footer">
                        <span class="badge badge-missed"><i class="fas fa-times-circle me-1"></i>Missed</span>
                    </div>
                </div>`;
    }

    function getEmptyState() {
        return `
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
                    <h3 class="empty-title">No ${currentFilter === 'all' ? 'attendance' : 'missed'} records</h3>
                    <p class="empty-text">${currentFilter === 'all' ? 'You haven\'t attended any events yet.' : 'Good job! You haven\'t missed any events.'}</p>
                </div>`;
    }

    function formatDate(dateStr) {
        return new Date(dateStr).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function formatDateShort(dateStr) {
        return new Date(dateStr).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    }

    function formatDateTime(datetimeStr) {
        return new Date(datetimeStr).toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit'
        });
    }

    function formatTime(timeStr) {
        return timeStr ? timeStr.substr(0, 5) : '';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function switchFilter(filter) {
        currentFilter = filter;
        tabAll.classList.toggle('active', filter === 'all');
        tabMissed.classList.toggle('active', filter === 'missed');
        fetchAttendance();
    }

    function showError() {
        container.innerHTML = '<div class="alert alert-danger">Failed to load attendance. Please refresh.</div>';
    }

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
                ws.send(JSON.stringify({
                    type: 'subscribe',
                    student_id: studentId
                }));
            };
            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                if (data.student_id && data.student_id !== studentId) return;
                if (data.type === 'attendance_updated') {
                    fetchCurrentEvent();
                    fetchAttendance();
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
// Connection closes automatically
?>
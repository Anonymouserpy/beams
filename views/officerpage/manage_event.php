<?php
/**
 * manage_event.php
 * Officer portal for managing events.
 *
 * Features:
 * - List events with attendance statistics
 * - View event details (read-only)
 * - Edit event information, schedule, and fines
 * - Delete events with confirmation
 * - Real-time WebSocket notifications
 */

// -----------------------------------------------------------------------------
// Configuration & Initialization
// -----------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);   // Turn off in production
session_start();

require "../../Connection/connection.php";

// Constants
define('WEBSOCKET_HOST', '127.0.0.1');
define('WEBSOCKET_PORT', 8081);
define('CSRF_TOKEN_LENGTH', 32);

// Authentication check
if (!isset($_SESSION['officer_id'])) {
    header("Location: ../../officer_Login.php");
    exit();
}

// -----------------------------------------------------------------------------
// Helper Functions
// -----------------------------------------------------------------------------

/**
 * Sends a WebSocket message to the internal server.
 * @param array $data Payload to send.
 * @return bool True on success, false on failure.
 */
function sendWebSocketMessage(array $data): bool {
    $socket = @fsockopen(WEBSOCKET_HOST, WEBSOCKET_PORT, $errno, $errstr, 1);
    if (!$socket) {
        error_log("WebSocket connection failed: $errstr ($errno)");
        return false;
    }
    fwrite($socket, json_encode($data));
    fclose($socket);
    return true;
}

/**
 * Fetches full event details including schedule and fines.
 * @param mysqli $conn Database connection.
 * @param int $eventId Event ID.
 * @return array|null Associative array or null if not found.
 */
function getEventDetails(mysqli $conn, int $eventId): ?array {
    $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    if (!$event) {
        return null;
    }

    // Get schedule
    $stmt2 = $conn->prepare("SELECT * FROM attendance_schedule WHERE event_id = ?");
    $stmt2->bind_param("i", $eventId);
    $stmt2->execute();
    $event['schedule'] = $stmt2->get_result()->fetch_assoc();

    // Get fines
    $stmt3 = $conn->prepare("SELECT * FROM event_fines WHERE event_id = ?");
    $stmt3->bind_param("i", $eventId);
    $stmt3->execute();
    $event['fines'] = $stmt3->get_result()->fetch_assoc();

    return $event;
}

/**
 * Validates and processes the event update from POST.
 * @param mysqli $conn Database connection.
 * @param array $post $_POST data.
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function processEventUpdate(mysqli $conn, array $post): array {
    $eventId = (int)($post['event_id'] ?? 0);
    if ($eventId <= 0) {
        return ['success' => false, 'message' => 'Invalid event ID.'];
    }

    // Sanitize inputs
    $eventName = trim($post['event_name'] ?? '');
    $eventDate = trim($post['event_date'] ?? '');
    $eventTypeRadio = trim($post['event_type'] ?? 'whole_day');
    $description = trim($post['description'] ?? '');
    $location = trim($post['location'] ?? '');

    // Validate required fields
    if (empty($eventName)) {
        return ['success' => false, 'message' => 'Event name is required.'];
    }
    if (empty($eventDate) || !strtotime($eventDate)) {
        return ['success' => false, 'message' => 'Valid event date is required.'];
    }

    // Convert radio value to database fields
    $eventType = 'whole_day';
    $halfDayPeriod = null;
    if ($eventTypeRadio === 'half_day_am') {
        $eventType = 'half_day';
        $halfDayPeriod = 'am';
    } elseif ($eventTypeRadio === 'half_day_pm') {
        $eventType = 'half_day';
        $halfDayPeriod = 'pm';
    }

    // Start transaction
    $conn->begin_transaction();
    try {
        // Update events table
        $stmt = $conn->prepare("UPDATE events SET
            event_name = ?,
            event_date = ?,
            event_type = ?,
            half_day_period = ?,
            description = ?,
            location = ?
            WHERE event_id = ?");
        $stmt->bind_param("ssssssi", $eventName, $eventDate, $eventType, $halfDayPeriod, $description, $location, $eventId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update event: " . $stmt->error);
        }

        // Attendance schedule
        $amLoginStart = !empty($post['am_login_start']) ? $post['am_login_start'] : null;
        $amLoginEnd   = !empty($post['am_login_end'])   ? $post['am_login_end']   : null;
        $amLogoutStart= !empty($post['am_logout_start'])? $post['am_logout_start']: null;
        $amLogoutEnd  = !empty($post['am_logout_end'])  ? $post['am_logout_end']  : null;
        $pmLoginStart = !empty($post['pm_login_start']) ? $post['pm_login_start'] : null;
        $pmLoginEnd   = !empty($post['pm_login_end'])   ? $post['pm_login_end']   : null;
        $pmLogoutStart= !empty($post['pm_logout_start'])? $post['pm_logout_start']: null;
        $pmLogoutEnd  = !empty($post['pm_logout_end'])  ? $post['pm_logout_end']  : null;

        // Validate time ranges
        $timeErrors = [];
        if ($amLoginStart && $amLoginEnd && $amLoginStart >= $amLoginEnd) $timeErrors[] = "AM Login start must be before end.";
        if ($amLogoutStart && $amLogoutEnd && $amLogoutStart >= $amLogoutEnd) $timeErrors[] = "AM Logout start must be before end.";
        if ($pmLoginStart && $pmLoginEnd && $pmLoginStart >= $pmLoginEnd) $timeErrors[] = "PM Login start must be before end.";
        if ($pmLogoutStart && $pmLogoutEnd && $pmLogoutStart >= $pmLogoutEnd) $timeErrors[] = "PM Logout start must be before end.";
        if (!empty($timeErrors)) {
            throw new Exception(implode(' ', $timeErrors));
        }

        $stmt2 = $conn->prepare("REPLACE INTO attendance_schedule
            (event_id, am_login_start, am_login_end, am_logout_start, am_logout_end,
             pm_login_start, pm_login_end, pm_logout_start, pm_logout_end)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("issssssss", $eventId, $amLoginStart, $amLoginEnd, $amLogoutStart, $amLogoutEnd,
                                        $pmLoginStart, $pmLoginEnd, $pmLogoutStart, $pmLogoutEnd);
        if (!$stmt2->execute()) {
            throw new Exception("Failed to update attendance schedule: " . $stmt2->error);
        }

        // Fines
        $missAmLogin   = (float)($post['miss_am_login']   ?? 0);
        $missAmLogout  = (float)($post['miss_am_logout']  ?? 0);
        $missPmLogin   = (float)($post['miss_pm_login']   ?? 0);
        $missPmLogout  = (float)($post['miss_pm_logout']  ?? 0);

        $stmt3 = $conn->prepare("REPLACE INTO event_fines
            (event_id, miss_am_login, miss_am_logout, miss_pm_login, miss_pm_logout)
            VALUES (?, ?, ?, ?, ?)");
        $stmt3->bind_param("idddd", $eventId, $missAmLogin, $missAmLogout, $missPmLogin, $missPmLogout);
        if (!$stmt3->execute()) {
            throw new Exception("Failed to update fines: " . $stmt3->error);
        }

        $conn->commit();

        // WebSocket broadcast
        $wsData = [
            'type' => 'EVENT_UPDATED',
            'payload' => [
                'event_id'          => $eventId,
                'event_name'        => $eventName,
                'event_date'        => $eventDate,
                'event_type'        => $eventType,
                'half_day_period'   => $halfDayPeriod,
                'location'          => $location,
                'description'       => $description,
                'am_login_start'    => $amLoginStart,
                'am_login_end'      => $amLoginEnd,
                'am_logout_start'   => $amLogoutStart,
                'am_logout_end'     => $amLogoutEnd,
                'pm_login_start'    => $pmLoginStart,
                'pm_login_end'      => $pmLoginEnd,
                'pm_logout_start'   => $pmLogoutStart,
                'pm_logout_end'     => $pmLogoutEnd,
                'miss_am_login'     => $missAmLogin,
                'miss_am_logout'    => $missAmLogout,
                'miss_pm_login'     => $missPmLogin,
                'miss_pm_logout'    => $missPmLogout
            ]
        ];
        sendWebSocketMessage($wsData);

        return ['success' => true, 'message' => 'Event updated successfully.', 'data' => $wsData['payload']];
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Event update error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Deletes an event and its related records.
 * @param mysqli $conn Database connection.
 * @param int $eventId Event ID.
 * @return bool True on success.
 */
function deleteEvent(mysqli $conn, int $eventId): bool {
    $conn->begin_transaction();
    try {
        $event = getEventDetails($conn, $eventId); // for broadcast

        $tables = ['event_fines', 'attendance_schedule', 'attendance'];
        foreach ($tables as $table) {
            $check = $conn->query("SHOW TABLES LIKE '$table'");
            if ($check && $check->num_rows > 0) {
                $stmt = $conn->prepare("DELETE FROM $table WHERE event_id = ?");
                $stmt->bind_param("i", $eventId);
                $stmt->execute();
            }
        }

        $stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();

        $conn->commit();

        if ($event) {
            sendWebSocketMessage([
                'type' => 'EVENT_DELETED',
                'payload' => [
                    'event_id'   => $eventId,
                    'event_name' => $event['event_name'],
                    'event_date' => $event['event_date'],
                    'event_type' => $event['event_type'],
                    'half_day_period' => $event['half_day_period'] ?? null
                ]
            ]);
        } else {
            sendWebSocketMessage([
                'type' => 'EVENT_DELETED',
                'payload' => ['event_id' => $eventId]
            ]);
        }
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Event deletion error: " . $e->getMessage());
        return false;
    }
}

// -----------------------------------------------------------------------------
// CSRF Token
// -----------------------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
}

// -----------------------------------------------------------------------------
// Handle Actions
// -----------------------------------------------------------------------------
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// DELETE action (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit();
    }

    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid event ID.']);
        exit();
    }

    $success = deleteEvent($conn, $eventId);
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Event deleted successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete event.']);
    }
    exit();
}

// EDIT action (POST with event_id)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id']) && (int)$_POST['event_id'] > 0) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        if ($isAjax) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        } else {
            die("Invalid CSRF token.");
        }
        exit();
    }

    $result = processEventUpdate($conn, $_POST);
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    } else {
        if ($result['success']) {
            header("Location: manage_event.php");
            exit();
        } else {
            die("Error: " . $result['message']);
        }
    }
}

// -----------------------------------------------------------------------------
// Fetch Events for Display
// -----------------------------------------------------------------------------
$events = $conn->query("
    SELECT e.*,
           COUNT(DISTINCT a.student_id) as attendance_count,
           (SELECT COUNT(*) FROM students) as total_students
    FROM events e
    LEFT JOIN attendance a ON e.event_id = a.event_id
    GROUP BY e.event_id
    ORDER BY e.event_date DESC
");

// Include sidebar after all logic
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
    /* Modern CSS - all styles kept for completeness */
    :root {
        --primary: #4361ee;
        --primary-dark: #3a56d4;
        --secondary: #3f37c9;
        --success: #4cc9f0;
        --danger: #f72585;
        --warning: #f8961e;
        --info: #4895ef;
        --light: #f8f9fa;
        --dark: #212529;
        --gray: #6c757d;
        --border-radius: 12px;
        --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        --transition: all 0.3s ease;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: #f5f7fb;
        color: var(--dark);
        overflow-x: hidden;
    }

    .main-contents {
        margin-left: 220px;
        padding: 30px;
        transition: var(--transition);
    }

    @media (max-width: 992px) {
        .main-contents {
            margin-left: 0;
            padding: 20px;
        }
    }

    /* Page Header */
    .page-header {
        margin-bottom: 30px;
    }

    .breadcrumb {
        background: transparent;
        padding: 0;
        margin-bottom: 10px;
    }

    .breadcrumb-item a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--dark);
    }

    .page-subtitle {
        color: var(--gray);
        font-size: 0.95rem;
    }

    /* Stats Row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: var(--border-radius);
        padding: 20px;
        display: flex;
        align-items: center;
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--primary);
    }

    .stat-card.primary::before {
        background: var(--primary);
    }

    .stat-card.success::before {
        background: var(--success);
    }

    .stat-card.warning::before {
        background: var(--warning);
    }

    .stat-card.danger::before {
        background: var(--danger);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        background: rgba(67, 97, 238, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
    }

    .stat-icon i {
        font-size: 28px;
        color: var(--primary);
    }

    .stat-card.primary .stat-icon i {
        color: var(--primary);
    }

    .stat-card.success .stat-icon i {
        color: var(--success);
    }

    .stat-card.warning .stat-icon i {
        color: var(--warning);
    }

    .stat-card.danger .stat-icon i {
        color: var(--danger);
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        line-height: 1.2;
    }

    .stat-label {
        font-size: 14px;
        color: var(--gray);
    }

    /* Content Card */
    .content-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        overflow: hidden;
    }

    .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid #eef2f6;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .card-title {
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .create-link {
        background: var(--primary);
        color: white;
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .create-link:hover {
        background: var(--primary-dark);
        color: white;
    }

    /* Events Grid */
    .events-grid {
        padding: 20px;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }

    .event-card {
        background: white;
        border-radius: var(--border-radius);
        border: 1px solid #eef2f6;
        transition: var(--transition);
        overflow: hidden;
    }

    .event-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    }

    .event-header {
        padding: 15px;
        border-bottom: 1px solid #eef2f6;
        background: #fafbfc;
    }

    .event-type-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .badge-whole {
        background: #e3f2fd;
        color: #1976d2;
    }

    .badge-half-am {
        background: #fff3e0;
        color: #f57c00;
    }

    .badge-half-pm {
        background: #e8eaf6;
        color: #3f51b5;
    }

    .event-date {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .date-box {
        background: white;
        border-radius: 8px;
        padding: 6px;
        text-align: center;
        min-width: 60px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .date-day {
        font-size: 24px;
        font-weight: 700;
        line-height: 1;
    }

    .date-month {
        font-size: 12px;
        color: var(--gray);
        text-transform: uppercase;
    }

    .event-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 5px;
    }

    .event-location {
        font-size: 12px;
        color: var(--gray);
    }

    .event-body {
        padding: 15px;
    }

    .event-description {
        font-size: 0.9rem;
        color: var(--gray);
        margin-bottom: 15px;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .event-stats {
        display: flex;
        gap: 20px;
        margin-bottom: 12px;
    }

    .event-stat {
        text-align: center;
    }

    .event-stat-value {
        font-size: 1.25rem;
        font-weight: 700;
    }

    .event-stat-label {
        font-size: 11px;
        color: var(--gray);
    }

    .progress {
        height: 6px;
        border-radius: 10px;
        margin-bottom: 15px;
    }

    .progress-bar {
        background: var(--primary);
        border-radius: 10px;
    }

    .event-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    .event-actions a {
        font-size: 13px;
        color: var(--gray);
        text-decoration: none;
        transition: var(--transition);
    }

    .event-actions a:hover {
        color: var(--primary);
    }

    .event-actions .delete-link:hover {
        color: var(--danger);
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }

    .empty-icon {
        font-size: 70px;
        color: #dee2e6;
        margin-bottom: 20px;
    }

    .empty-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .empty-text {
        color: var(--gray);
        margin-bottom: 25px;
    }

    /* Modal Styles */
    .section-title {
        font-size: 1rem;
        font-weight: 600;
        padding-bottom: 8px;
        margin-bottom: 15px;
        border-bottom: 2px solid #eef2f6;
    }

    .time-range {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .time-range .form-control {
        flex: 1;
    }

    .range-separator {
        color: var(--gray);
    }

    .event-type-selector {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .event-type-option input {
        display: none;
    }

    .event-type-card {
        border: 2px solid #eef2f6;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        width: 130px;
    }

    .event-type-option input:checked+.event-type-card {
        border-color: var(--primary);
        background: rgba(67, 97, 238, 0.05);
    }

    .event-type-icon {
        font-size: 28px;
        margin-bottom: 8px;
    }

    .event-type-label {
        font-weight: 600;
        margin-bottom: 4px;
    }

    .event-type-desc {
        font-size: 11px;
        color: var(--gray);
    }

    /* Toast */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1055;
    }

    /* FAB for mobile */
    .fab {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 56px;
        height: 56px;
        background: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        transition: var(--transition);
        z-index: 1000;
    }

    .fab:hover {
        background: var(--primary-dark);
        color: white;
        transform: scale(1.05);
    }

    /* Animations */
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
    </style>
</head>

<body>
    <div class="toast-container" id="toastContainer"></div>

    <div class="main-contents">
        <!-- Page Header -->
        <div class="page-header">
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
                        
                        // Badge based on event type and period
                        $badge_class = '';
                        $badge_label = '';
                        if ($event['event_type'] === 'whole_day') {
                            $badge_class = 'badge-whole';
                            $badge_label = 'Whole Day';
                        } elseif ($event['event_type'] === 'half_day') {
                            if ($event['half_day_period'] === 'am') {
                                $badge_class = 'badge-half-am';
                                $badge_label = 'Half Day - AM';
                            } elseif ($event['half_day_period'] === 'pm') {
                                $badge_class = 'badge-half-pm';
                                $badge_label = 'Half Day - PM';
                            } else {
                                $badge_class = 'badge-whole';
                                $badge_label = 'Half Day';
                            }
                        } else {
                            $badge_class = 'badge-whole';
                            $badge_label = 'Whole Day';
                        }
                    ?>
                <div class="event-card" data-event-id="<?php echo $event['event_id']; ?>">
                    <div class="event-header">
                        <span
                            class="event-type-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($badge_label); ?></span>
                        <div class="event-date">
                            <div class="date-box">
                                <div class="date-day"><?php echo htmlspecialchars($day); ?></div>
                                <div class="date-month"><?php echo htmlspecialchars($month); ?></div>
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
                            <?php echo htmlspecialchars($event['description'] ?: 'No description available.'); ?>
                        </p>
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
                            <a href="#" class="view-event" data-id="<?php echo $event['event_id']; ?>"><i
                                    class="fas fa-eye"></i> View</a>
                            <a href="#" class="edit-event" data-id="<?php echo $event['event_id']; ?>"><i
                                    class="fas fa-edit"></i> Edit</a>
                            <a href="#" class="delete-event" data-id="<?php echo $event['event_id']; ?>"><i
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

        <!-- Floating Action Button (Mobile) -->
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
                        <button type="button" class="btn btn-outline-primary" id="viewEditBtn">Edit Event</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Event Modal -->
        <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="eventForm" method="POST" action="">
                        <div class="modal-body">
                            <input type="hidden" name="event_id" id="event_id" value="0">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Event Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="event_name" id="event_name"
                                            required>
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
                                        <input type="text" class="form-control" name="location" id="location">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" id="description"
                                            rows="3"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance Schedule -->
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
                                                id="am_login_start">
                                            <span class="range-separator">—</span>
                                            <input type="time" class="form-control" name="am_login_end"
                                                id="am_login_end">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">Logout Period</label>
                                        <div class="time-range">
                                            <input type="time" class="form-control" name="am_logout_start"
                                                id="am_logout_start">
                                            <span class="range-separator">—</span>
                                            <input type="time" class="form-control" name="am_logout_end"
                                                id="am_logout_end">
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
                                                id="pm_login_start">
                                            <span class="range-separator">—</span>
                                            <input type="time" class="form-control" name="pm_login_end"
                                                id="pm_login_end">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small">Logout Period</label>
                                        <div class="time-range">
                                            <input type="time" class="form-control" name="pm_logout_start"
                                                id="pm_logout_start">
                                            <span class="range-separator">—</span>
                                            <input type="time" class="form-control" name="pm_logout_end"
                                                id="pm_logout_end">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fine Settings -->
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
    // -------------------------------------------------------------------------
    // Utility Functions
    // -------------------------------------------------------------------------
    function showToast(message, type = 'success') {
        const toastContainer = document.getElementById('toastContainer');
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${type} border-0 show`;
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
        toastContainer.appendChild(toastEl);
        setTimeout(() => toastEl.remove(), 5000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    function resetEditForm() {
        document.getElementById('eventForm').reset();
        document.getElementById('event_id').value = '0';
        document.querySelector('input[name="event_type"][value="whole_day"]').checked = true;
        document.querySelectorAll('#eventForm input[type="time"], #eventForm input[type="number"]').forEach(input => {
            if (input.type === 'number') input.value = '0.00';
            else input.value = '';
        });
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('event_date').min = today;
    }

    // -------------------------------------------------------------------------
    // API Calls
    // -------------------------------------------------------------------------
    function loadEventForEdit(eventId) {
        fetch(`get_event_details.php?event_id=${eventId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showToast('Error loading event details: ' + data.error, 'danger');
                    return;
                }
                document.getElementById('event_id').value = eventId;
                document.getElementById('event_name').value = data.event_name || '';
                document.getElementById('event_date').value = data.event_date || '';
                document.getElementById('location').value = data.location || '';
                document.getElementById('description').value = data.description || '';

                let radioValue = 'whole_day';
                if (data.event_type === 'half_day') {
                    if (data.half_day_period === 'am') radioValue = 'half_day_am';
                    else if (data.half_day_period === 'pm') radioValue = 'half_day_pm';
                }
                const typeRadio = document.querySelector(`input[name="event_type"][value="${radioValue}"]`);
                if (typeRadio) typeRadio.checked = true;

                const s = data.schedule || {};
                document.getElementById('am_login_start').value = s.am_login_start || '';
                document.getElementById('am_login_end').value = s.am_login_end || '';
                document.getElementById('am_logout_start').value = s.am_logout_start || '';
                document.getElementById('am_logout_end').value = s.am_logout_end || '';
                document.getElementById('pm_login_start').value = s.pm_login_start || '';
                document.getElementById('pm_login_end').value = s.pm_login_end || '';
                document.getElementById('pm_logout_start').value = s.pm_logout_start || '';
                document.getElementById('pm_logout_end').value = s.pm_logout_end || '';

                const f = data.fines || {};
                document.getElementById('miss_am_login').value = f.miss_am_login || '0.00';
                document.getElementById('miss_am_logout').value = f.miss_am_logout || '0.00';
                document.getElementById('miss_pm_login').value = f.miss_pm_login || '0.00';
                document.getElementById('miss_pm_logout').value = f.miss_pm_logout || '0.00';
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error while loading event details.', 'danger');
            });
    }

    function viewEvent(eventId) {
        fetch(`get_event_details.php?event_id=${eventId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showToast('Error loading event: ' + data.error, 'danger');
                    return;
                }
                document.getElementById('view_event_name').innerText = data.event_name || '—';
                if (data.event_date) {
                    const d = new Date(data.event_date);
                    document.getElementById('view_event_date').innerText = d.toLocaleDateString(undefined, {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                } else {
                    document.getElementById('view_event_date').innerText = '—';
                }
                let typeLabel = 'Whole Day';
                if (data.event_type === 'half_day') {
                    if (data.half_day_period === 'am') typeLabel = 'Half Day - AM';
                    else if (data.half_day_period === 'pm') typeLabel = 'Half Day - PM';
                }
                document.getElementById('view_event_type').innerText = typeLabel;
                document.getElementById('view_location').innerText = data.location || '—';
                document.getElementById('view_description').innerText = data.description || '—';

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

                const viewModal = new bootstrap.Modal(document.getElementById('viewEventModal'));
                viewModal.show();
                document.getElementById('viewEditBtn').setAttribute('data-event-id', eventId);
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error while loading event details.', 'danger');
            });
    }

    function deleteEvent(eventId, eventName) {
        if (confirm(
                `Are you sure you want to delete the event "${escapeHtml(eventName)}"? This action cannot be undone.`
            )) {
            fetch('manage_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        action: 'delete',
                        event_id: eventId,
                        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message || 'Error deleting event.', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Network error while deleting event.', 'danger');
                });
        }
    }

    // -------------------------------------------------------------------------
    // Event Listeners
    // -------------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('event_date').min = today;

        // View events
        document.querySelectorAll('.view-event').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const eventId = link.getAttribute('data-id');
                if (eventId) viewEvent(eventId);
            });
        });

        // Edit events
        document.querySelectorAll('.edit-event').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const eventId = link.getAttribute('data-id');
                if (eventId) {
                    loadEventForEdit(eventId);
                    const editModal = new bootstrap.Modal(document.getElementById(
                        'eventModal'));
                    editModal.show();
                }
            });
        });

        // Delete events
        document.querySelectorAll('.delete-event').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const eventId = link.getAttribute('data-id');
                const eventCard = link.closest('.event-card');
                const eventName = eventCard ? eventCard.querySelector('.event-title')
                    ?.innerText : 'this event';
                if (eventId) deleteEvent(eventId, eventName);
            });
        });

        // Edit button in view modal
        document.getElementById('viewEditBtn').addEventListener('click', function() {
            const eventId = this.getAttribute('data-event-id');
            if (eventId) {
                const viewModal = bootstrap.Modal.getInstance(document.getElementById(
                    'viewEventModal'));
                if (viewModal) viewModal.hide();
                loadEventForEdit(eventId);
                const editModal = new bootstrap.Modal(document.getElementById('eventModal'));
                editModal.show();
            }
        });

        // Form submit via AJAX
        const form = document.getElementById('eventForm');
        const saveBtn = document.getElementById('saveBtn');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            saveBtn.disabled = true;
            saveBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';

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
                        showToast(data.message || 'Error updating event', 'danger');
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Update Event';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Network error. Please try again.', 'danger');
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Update Event';
                });
        });

        // Reset edit modal when hidden
        document.getElementById('eventModal').addEventListener('hidden.bs.modal', resetEditForm);

        // Time range validation
        function validateTimeRange(startId, endId, fieldName) {
            const start = document.getElementById(startId).value;
            const end = document.getElementById(endId).value;
            if (start && end && start >= end) {
                showToast(`${fieldName} start must be before end.`, 'warning');
                return false;
            }
            return true;
        }

        form.addEventListener('submit', function(e) {
            let valid = true;
            valid = validateTimeRange('am_login_start', 'am_login_end', 'AM Login') && valid;
            valid = validateTimeRange('am_logout_start', 'am_logout_end', 'AM Logout') && valid;
            valid = validateTimeRange('pm_login_start', 'pm_login_end', 'PM Login') && valid;
            valid = validateTimeRange('pm_logout_start', 'pm_logout_end', 'PM Logout') && valid;
            if (!valid) {
                e.preventDefault();
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save me-2"></i>Update Event';
            }
        });
    });
    </script>
</body>

</html>
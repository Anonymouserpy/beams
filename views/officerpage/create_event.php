<?php
/**
 * create_event.php
 * Officer portal for creating new events.
 */

session_start();

require "../../Connection/connection.php";

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
    $socket = @fsockopen('127.0.0.1', 8081, $errno, $errstr, 1);
    if (!$socket) {
        error_log("WebSocket internal socket error: $errstr ($errno)");
        return false;
    }
    fwrite($socket, json_encode($data));
    fclose($socket);
    return true;
}

/**
 * Generates a CSRF token if not already set.
 */
function generateCsrfToken(): void {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

// -----------------------------------------------------------------------------
// CSRF Token
// -----------------------------------------------------------------------------
generateCsrfToken();

// -----------------------------------------------------------------------------
// Handle Form Submission
// -----------------------------------------------------------------------------
$msg = '';
$msg_type = '';
$form_data = []; // To retain values on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $msg = 'Invalid security token. Please refresh the page and try again.';
        $msg_type = 'danger';
    } else {
        // Collect and sanitize input
        $event_name   = trim($_POST['event_name'] ?? '');
        $event_date   = $_POST['event_date'] ?? '';
        $event_type   = $_POST['event_type'] ?? 'whole_day';
        $half_day_period = $_POST['half_day_period'] ?? null;
        $location     = trim($_POST['location'] ?? '');
        $description  = trim($_POST['description'] ?? '');

        // Basic validation
        if (empty($event_name) || empty($event_date)) {
            $msg = 'Event name and date are required.';
            $msg_type = 'danger';
        } elseif (!strtotime($event_date)) {
            $msg = 'Please provide a valid event date.';
            $msg_type = 'danger';
        } else {
            // Validate event type
            $valid_types = ['whole_day', 'half_day'];
            if (!in_array($event_type, $valid_types)) {
                $event_type = 'whole_day';
            }

            // For half-day, period must be selected
            if ($event_type === 'half_day' && !in_array($half_day_period, ['am', 'pm'])) {
                $msg = 'Please select a period (AM or PM) for half-day event.';
                $msg_type = 'danger';
            }

            if (empty($msg)) {
                // Determine which sessions are active
                $is_am_active = ($event_type === 'whole_day') ||
                                ($event_type === 'half_day' && $half_day_period === 'am');
                $is_pm_active = ($event_type === 'whole_day') ||
                                ($event_type === 'half_day' && $half_day_period === 'pm');

                // AM times (only if active)
                $am_login_start  = $is_am_active ? ($_POST['am_login_start'] ?? null) : null;
                $am_login_end    = $is_am_active ? ($_POST['am_login_end'] ?? null) : null;
                $am_logout_start = $is_am_active ? ($_POST['am_logout_start'] ?? null) : null;
                $am_logout_end   = $is_am_active ? ($_POST['am_logout_end'] ?? null) : null;

                // PM times (only if active)
                $pm_login_start  = $is_pm_active ? ($_POST['pm_login_start'] ?? null) : null;
                $pm_login_end    = $is_pm_active ? ($_POST['pm_login_end'] ?? null) : null;
                $pm_logout_start = $is_pm_active ? ($_POST['pm_logout_start'] ?? null) : null;
                $pm_logout_end   = $is_pm_active ? ($_POST['pm_logout_end'] ?? null) : null;

                // Validate time ranges if present
                $time_errors = [];
                if ($am_login_start && $am_login_end && $am_login_start >= $am_login_end) {
                    $time_errors[] = 'AM Login start must be before end.';
                }
                if ($am_logout_start && $am_logout_end && $am_logout_start >= $am_logout_end) {
                    $time_errors[] = 'AM Logout start must be before end.';
                }
                if ($pm_login_start && $pm_login_end && $pm_login_start >= $pm_login_end) {
                    $time_errors[] = 'PM Login start must be before end.';
                }
                if ($pm_logout_start && $pm_logout_end && $pm_logout_start >= $pm_logout_end) {
                    $time_errors[] = 'PM Logout start must be before end.';
                }

                if (!empty($time_errors)) {
                    $msg = implode(' ', $time_errors);
                    $msg_type = 'danger';
                } else {
                    // Fine amounts
                    $miss_am_login   = $is_am_active ? (float)($_POST['miss_am_login'] ?? 0) : 0;
                    $miss_am_logout  = $is_am_active ? (float)($_POST['miss_am_logout'] ?? 0) : 0;
                    $miss_pm_login   = $is_pm_active ? (float)($_POST['miss_pm_login'] ?? 0) : 0;
                    $miss_pm_logout  = $is_pm_active ? (float)($_POST['miss_pm_logout'] ?? 0) : 0;

                    // Start transaction
                    $conn->begin_transaction();
                    try {
                        // Insert into events table
                        $stmt = $conn->prepare("INSERT INTO events
                            (event_name, event_date, event_type, half_day_period, location, description, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->bind_param("ssssssi", $event_name, $event_date, $event_type, $half_day_period, $location, $description, $_SESSION['officer_id']);
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to create event: " . $stmt->error);
                        }
                        $event_id = $stmt->insert_id;

                        // Insert attendance schedule
                        $stmt2 = $conn->prepare("INSERT INTO attendance_schedule
                            (event_id, am_login_start, am_login_end, am_logout_start, am_logout_end,
                             pm_login_start, pm_login_end, pm_logout_start, pm_logout_end)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt2->bind_param("issssssss", $event_id, $am_login_start, $am_login_end, $am_logout_start, $am_logout_end,
                                                            $pm_login_start, $pm_login_end, $pm_logout_start, $pm_logout_end);
                        if (!$stmt2->execute()) {
                            throw new Exception("Failed to insert attendance schedule: " . $stmt2->error);
                        }

                        // Insert fine settings
                        $stmt3 = $conn->prepare("INSERT INTO event_fines
                            (event_id, miss_am_login, miss_am_logout, miss_pm_login, miss_pm_logout)
                            VALUES (?, ?, ?, ?, ?)");
                        $stmt3->bind_param("idddd", $event_id, $miss_am_login, $miss_am_logout, $miss_pm_login, $miss_pm_logout);
                        if (!$stmt3->execute()) {
                            throw new Exception("Failed to insert fine settings: " . $stmt3->error);
                        }

                        $conn->commit();

                        // WebSocket broadcast
                        $wsData = [
                            'type' => 'EVENT_CREATED',
                            'payload' => [
                                'event_id'          => $event_id,
                                'event_name'        => $event_name,
                                'event_date'        => $event_date,
                                'event_type'        => $event_type,
                                'half_day_period'   => $half_day_period,
                                'description'       => $description,
                                'location'          => $location,
                                'created_by'        => $_SESSION['officer_id'],
                                'am_login_start'    => $am_login_start,
                                'am_login_end'      => $am_login_end,
                                'am_logout_start'   => $am_logout_start,
                                'am_logout_end'     => $am_logout_end,
                                'pm_login_start'    => $pm_login_start,
                                'pm_login_end'      => $pm_login_end,
                                'pm_logout_start'   => $pm_logout_start,
                                'pm_logout_end'     => $pm_logout_end,
                                'miss_am_login'     => $miss_am_login,
                                'miss_am_logout'    => $miss_am_logout,
                                'miss_pm_login'     => $miss_pm_login,
                                'miss_pm_logout'    => $miss_pm_logout
                            ]
                        ];
                        sendWebSocketMessage($wsData);

                        // Success - redirect after a short delay
                        $msg = "Event created successfully! Redirecting...";
                        $msg_type = "success";
                        header("Refresh: 2; URL=manage_event.php");
                        exit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        error_log("Event creation error: " . $e->getMessage());
                        $msg = "An error occurred while creating the event: " . $e->getMessage();
                        $msg_type = "danger";
                        // Retain submitted data for repopulation
                        $form_data = $_POST;
                    }
                }
            }
        }
    }
}

// -----------------------------------------------------------------------------
// Prepare default values for form (if not set from POST)
// -----------------------------------------------------------------------------
$defaults = [
    'event_name'        => $form_data['event_name'] ?? '',
    'event_date'        => $form_data['event_date'] ?? '',
    'event_type'        => $form_data['event_type'] ?? 'whole_day',
    'half_day_period'   => $form_data['half_day_period'] ?? '',
    'location'          => $form_data['location'] ?? '',
    'description'       => $form_data['description'] ?? '',
    'am_login_start'    => $form_data['am_login_start'] ?? '08:00',
    'am_login_end'      => $form_data['am_login_end'] ?? '09:00',
    'am_logout_start'   => $form_data['am_logout_start'] ?? '12:00',
    'am_logout_end'     => $form_data['am_logout_end'] ?? '13:00',
    'pm_login_start'    => $form_data['pm_login_start'] ?? '13:00',
    'pm_login_end'      => $form_data['pm_login_end'] ?? '14:00',
    'pm_logout_start'   => $form_data['pm_logout_start'] ?? '17:00',
    'pm_logout_end'     => $form_data['pm_logout_end'] ?? '18:00',
    'miss_am_login'     => $form_data['miss_am_login'] ?? '5.00',
    'miss_am_logout'    => $form_data['miss_am_logout'] ?? '5.00',
    'miss_pm_login'     => $form_data['miss_pm_login'] ?? '5.00',
    'miss_pm_logout'    => $form_data['miss_pm_logout'] ?? '5.00',
];

// -----------------------------------------------------------------------------
// Include sidebar (only after all processing is done)
// -----------------------------------------------------------------------------
require "../sidebar/officer_sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Event | BEAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
    /* Modern styling – keep your existing CSS but ensure it's consistent */
    :root {
        --primary: #4361ee;
        --primary-dark: #3a56d4;
        --secondary: #3f37c9;
        --success: #4cc9f0;
        --danger: #f72585;
        --warning: #f8961e;
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

    .main-card {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .card-header-custom {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px 25px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .header-icon {
        width: 50px;
        height: 50px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .custom-alert {
        padding: 15px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 25px;
    }

    .alert-success {
        background: #d4edda;
        border-left: 4px solid #28a745;
        color: #155724;
    }

    .alert-danger {
        background: #f8d7da;
        border-left: 4px solid #dc3545;
        color: #721c24;
    }

    .form-section {
        background: white;
        border-radius: var(--border-radius);
        padding: 20px;
        margin-bottom: 25px;
        border: 1px solid #eef2f6;
        transition: var(--transition);
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid #eef2f6;
    }

    .section-icon {
        width: 40px;
        height: 40px;
        background: rgba(67, 97, 238, 0.1);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .section-icon.info {
        background: rgba(72, 149, 239, 0.1);
        color: #4895ef;
    }

    .section-icon.am {
        background: rgba(248, 150, 30, 0.1);
        color: #f8961e;
    }

    .section-icon.pm {
        background: rgba(114, 9, 183, 0.1);
        color: #7209b7;
    }

    .section-title {
        font-size: 1.2rem;
        font-weight: 600;
        margin: 0;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .status-badge.active {
        background: #d4edda;
        color: #155724;
    }

    .status-badge.inactive {
        background: #f8d7da;
        color: #721c24;
    }

    .time-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .period-grid {
        display: flex;
        gap: 15px;
        margin-top: 10px;
    }

    .period-option {
        flex: 1;
        cursor: pointer;
        transition: var(--transition);
    }

    .period-option .period-icon {
        font-size: 30px;
        margin-bottom: 10px;
    }

    .period-option .period-title {
        font-weight: 600;
    }

    .period-option .period-time {
        font-size: 12px;
        color: var(--gray);
    }

    .period-option.active .period-icon,
    .period-option.active .period-title,
    .period-option.active .period-time {
        color: var(--primary);
    }

    .period-option input {
        display: none;
    }

    .attendance-section.disabled {
        opacity: 0.6;
        background: #f8f9fa;
        pointer-events: none;
    }

    .btn-create {
        background: var(--primary);
        color: white;
        border: none;
        padding: 12px;
        border-radius: 10px;
        font-weight: 600;
        transition: var(--transition);
    }

    .btn-create:hover {
        background: var(--primary-dark);
        color: white;
    }

    .btn-cancel {
        background: #f1f3f5;
        color: var(--dark);
        border: none;
        padding: 12px 20px;
        border-radius: 10px;
        font-weight: 600;
        transition: var(--transition);
    }

    .btn-cancel:hover {
        background: #e9ecef;
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
    </style>
</head>

<body>

    <div class="main-contents">
        <div class="container mb-5">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="main-card animate-in">
                        <!-- Card Header -->
                        <div class="card-header-custom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="header-icon">
                                    <i class="bi bi-calendar-event"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-bold">Event Configuration</h5>
                                    <small class="text-white-50">Configure attendance schedules and fine
                                        policies</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <small class="text-white-50 d-block">Created by</small>
                                <span
                                    class="fw-semibold"><?php echo htmlspecialchars($_SESSION['officer_name'] ?? 'Officer'); ?></span>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <?php if ($msg !== ''): ?>
                            <div class="custom-alert alert-<?php echo $msg_type; ?> mb-4 animate-in">
                                <i
                                    class="bi bi-<?php echo $msg_type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> fs-4"></i>
                                <div>
                                    <strong><?php echo $msg_type === 'success' ? 'Success!' : 'Error!'; ?></strong>
                                    <span class="d-block small"><?php echo htmlspecialchars($msg); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <form method="POST" id="eventForm" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <!-- Event Details Section -->
                                <div class="form-section animate-in delay-1">
                                    <div class="section-header">
                                        <div class="section-icon info">
                                            <i class="bi bi-info-circle"></i>
                                        </div>
                                        <h4 class="section-title">Event Information</h4>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label">Event Name <span class="text-danger">*</span></label>
                                        <input type="text" name="event_name" class="form-control form-control-lg"
                                            placeholder="e.g., Annual Sports Day 2024" required
                                            value="<?php echo htmlspecialchars($defaults['event_name']); ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Event Date <span
                                                    class="text-danger">*</span></label>
                                            <input type="date" name="event_date" class="form-control" required
                                                value="<?php echo htmlspecialchars($defaults['event_date']); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Event Type <span
                                                    class="text-danger">*</span></label>
                                            <select name="event_type" id="eventType" class="form-select" required>
                                                <option value="whole_day"
                                                    <?php echo $defaults['event_type'] === 'whole_day' ? 'selected' : ''; ?>>
                                                    Whole Day</option>
                                                <option value="half_day"
                                                    <?php echo $defaults['event_type'] === 'half_day' ? 'selected' : ''; ?>>
                                                    Half Day</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Location Field -->
                                    <div class="mb-3">
                                        <label class="form-label">Location</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                            <input type="text" name="location" class="form-control"
                                                placeholder="e.g., Main Auditorium, Building A"
                                                value="<?php echo htmlspecialchars($defaults['location']); ?>">
                                        </div>
                                    </div>

                                    <!-- Description Field -->
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="3"
                                            placeholder="Enter event description, agenda, or additional notes..."><?php echo htmlspecialchars($defaults['description']); ?></textarea>
                                    </div>

                                    <!-- Half Day Period Selection -->
                                    <div id="periodContainer" class="mt-3"
                                        style="<?php echo $defaults['event_type'] === 'half_day' ? 'display: block;' : 'display: none;'; ?>">
                                        <label class="form-label">Select Period <span
                                                class="text-danger">*</span></label>
                                        <div class="period-grid">
                                            <label
                                                class="period-option am <?php echo $defaults['half_day_period'] === 'am' ? 'active' : ''; ?>">
                                                <input type="radio" name="half_day_period" value="am"
                                                    <?php echo $defaults['half_day_period'] === 'am' ? 'checked' : ''; ?>>
                                                <div class="period-icon"><i class="bi bi-sunrise"></i></div>
                                                <div class="period-title">Morning Session</div>
                                                <div class="period-time">6:00 AM - 12:00 PM</div>
                                            </label>
                                            <label
                                                class="period-option pm <?php echo $defaults['half_day_period'] === 'pm' ? 'active' : ''; ?>">
                                                <input type="radio" name="half_day_period" value="pm"
                                                    <?php echo $defaults['half_day_period'] === 'pm' ? 'checked' : ''; ?>>
                                                <div class="period-icon"><i class="bi bi-sunset"></i></div>
                                                <div class="period-title">Afternoon Session</div>
                                                <div class="period-time">12:00 PM - 6:00 PM</div>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <!-- AM Section -->
                                <div id="amSection"
                                    class="form-section attendance-section animate-in delay-2 position-relative">
                                    <div class="section-header">
                                        <div class="section-icon am">
                                            <i class="bi bi-sunrise"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h4 class="section-title">Morning (AM) Schedule</h4>
                                        </div>
                                        <span id="amStatus" class="status-badge active">Active</span>
                                    </div>

                                    <div class="time-grid mb-3">
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Login Start</label>
                                            <input type="time" name="am_login_start" class="form-control am-input"
                                                value="<?php echo htmlspecialchars($defaults['am_login_start']); ?>"
                                                required>
                                        </div>
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Login End</label>
                                            <input type="time" name="am_login_end" class="form-control am-input"
                                                value="<?php echo htmlspecialchars($defaults['am_login_end']); ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="time-grid mb-4">
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Logout Start</label>
                                            <input type="time" name="am_logout_start" class="form-control am-input"
                                                value="<?php echo htmlspecialchars($defaults['am_logout_start']); ?>"
                                                required>
                                        </div>
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Logout End</label>
                                            <input type="time" name="am_logout_end" class="form-control am-input"
                                                value="<?php echo htmlspecialchars($defaults['am_logout_end']); ?>"
                                                required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Miss Login Fine ($)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" name="miss_am_login"
                                                    class="form-control am-input"
                                                    value="<?php echo htmlspecialchars($defaults['miss_am_login']); ?>"
                                                    required>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Miss Logout Fine ($)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" name="miss_am_logout"
                                                    class="form-control am-input"
                                                    value="<?php echo htmlspecialchars($defaults['miss_am_logout']); ?>"
                                                    required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- PM Section -->
                                <div id="pmSection"
                                    class="form-section attendance-section animate-in delay-3 position-relative">
                                    <div class="section-header">
                                        <div class="section-icon pm">
                                            <i class="bi bi-sunset"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h4 class="section-title">Afternoon (PM) Schedule</h4>
                                        </div>
                                        <span id="pmStatus" class="status-badge active">Active</span>
                                    </div>

                                    <div class="time-grid mb-3">
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Login Start</label>
                                            <input type="time" name="pm_login_start" class="form-control pm-input"
                                                value="<?php echo htmlspecialchars($defaults['pm_login_start']); ?>">
                                        </div>
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Login End</label>
                                            <input type="time" name="pm_login_end" class="form-control pm-input"
                                                value="<?php echo htmlspecialchars($defaults['pm_login_end']); ?>">
                                        </div>
                                    </div>

                                    <div class="time-grid mb-4">
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Logout Start</label>
                                            <input type="time" name="pm_logout_start" class="form-control pm-input"
                                                value="<?php echo htmlspecialchars($defaults['pm_logout_start']); ?>">
                                        </div>
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Logout End</label>
                                            <input type="time" name="pm_logout_end" class="form-control pm-input"
                                                value="<?php echo htmlspecialchars($defaults['pm_logout_end']); ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Miss Login Fine ($)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" name="miss_pm_login"
                                                    class="form-control pm-input"
                                                    value="<?php echo htmlspecialchars($defaults['miss_pm_login']); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Miss Logout Fine ($)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" name="miss_pm_logout"
                                                    class="form-control pm-input"
                                                    value="<?php echo htmlspecialchars($defaults['miss_pm_logout']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-flex gap-3 mt-4">
                                    <button type="submit" class="btn btn-create flex-grow-1">
                                        <i class="bi bi-check-lg me-2"></i>Create Event
                                    </button>
                                    <a href="manage_event.php" class="btn btn-cancel">
                                        <i class="bi bi-x-lg me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Client-side logic to toggle sections based on event type and period
    (function() {
        const eventTypeSelect = document.getElementById('eventType');
        const periodContainer = document.getElementById('periodContainer');
        const amSection = document.getElementById('amSection');
        const pmSection = document.getElementById('pmSection');
        const amStatus = document.getElementById('amStatus');
        const pmStatus = document.getElementById('pmStatus');

        function toggleSections() {
            const eventType = eventTypeSelect.value;
            const isHalfDay = eventType === 'half_day';
            periodContainer.style.display = isHalfDay ? 'block' : 'none';

            if (isHalfDay) {
                const selectedPeriod = document.querySelector('input[name="half_day_period"]:checked')?.value;
                if (selectedPeriod === 'am') {
                    setSectionActive(amSection, amStatus, true);
                    setSectionActive(pmSection, pmStatus, false);
                } else if (selectedPeriod === 'pm') {
                    setSectionActive(amSection, amStatus, false);
                    setSectionActive(pmSection, pmStatus, true);
                } else {
                    // No period selected yet – default both inactive
                    setSectionActive(amSection, amStatus, false);
                    setSectionActive(pmSection, pmStatus, false);
                }
            } else {
                // Whole day – both sections active
                setSectionActive(amSection, amStatus, true);
                setSectionActive(pmSection, pmStatus, true);
            }
        }

        function setSectionActive(section, statusSpan, active) {
            if (active) {
                section.classList.remove('disabled');
                statusSpan.textContent = 'Active';
                statusSpan.className = 'status-badge active';
                // Enable all inputs with class matching section's prefix (am-input or pm-input)
                const inputs = section.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.disabled = false;
                    if (input.hasAttribute('required') && !input.value && input.type !== 'radio') {
                        input.setAttribute('required', 'required');
                    }
                });
            } else {
                section.classList.add('disabled');
                statusSpan.textContent = 'Inactive';
                statusSpan.className = 'status-badge inactive';
                const inputs = section.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.disabled = true;
                    input.removeAttribute('required');
                });
            }
        }

        // Period radio change listener
        document.querySelectorAll('input[name="half_day_period"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Update active visual for period options
                document.querySelectorAll('.period-option').forEach(opt => opt.classList.remove(
                    'active'));
                this.closest('.period-option').classList.add('active');
                toggleSections();
            });
        });

        // Event type change listener
        eventTypeSelect.addEventListener('change', toggleSections);

        // Initial setup
        toggleSections();

        // Additional validation: ensure time ranges are logical before submit
        const form = document.getElementById('eventForm');
        form.addEventListener('submit', function(e) {
            const eventType = eventTypeSelect.value;
            const isHalfDay = eventType === 'half_day';
            if (isHalfDay && !document.querySelector('input[name="half_day_period"]:checked')) {
                e.preventDefault();
                alert('Please select AM or PM period for half-day event.');
                return false;
            }

            // Validate time ranges if the section is active
            const sections = [{
                    section: amSection,
                    prefix: 'am',
                    name: 'AM'
                },
                {
                    section: pmSection,
                    prefix: 'pm',
                    name: 'PM'
                }
            ];
            for (const s of sections) {
                if (!s.section.classList.contains('disabled')) {
                    const startLogin = s.section.querySelector(`input[name="${s.prefix}_login_start"]`);
                    const endLogin = s.section.querySelector(`input[name="${s.prefix}_login_end"]`);
                    const startLogout = s.section.querySelector(`input[name="${s.prefix}_logout_start"]`);
                    const endLogout = s.section.querySelector(`input[name="${s.prefix}_logout_end"]`);

                    if (startLogin && endLogin && startLogin.value && endLogin.value && startLogin.value >=
                        endLogin.value) {
                        e.preventDefault();
                        alert(`${s.name} Login start must be before end.`);
                        return false;
                    }
                    if (startLogout && endLogout && startLogout.value && endLogout.value && startLogout
                        .value >= endLogout.value) {
                        e.preventDefault();
                        alert(`${s.name} Logout start must be before end.`);
                        return false;
                    }
                }
            }

            // Ensure event date is not in the past
            const eventDate = form.querySelector('input[name="event_date"]').value;
            const today = new Date().toISOString().split('T')[0];
            if (eventDate && eventDate < today) {
                e.preventDefault();
                alert('Event date cannot be in the past.');
                return false;
            }
        });

        // Set min date for event date
        const dateInput = document.querySelector('input[name="event_date"]');
        if (dateInput) {
            const today = new Date().toISOString().split('T')[0];
            dateInput.setAttribute('min', today);
        }
    })();
    </script>
</body>

</html>
<?php
session_start();
require "../sidebar/officer_sidebar.php";
require "../../Connection/connection.php";

if (!isset($_SESSION['officer_id'])) {
    header("Location: ../../officer_Login.php");
    exit();
}

$officer_id = $_SESSION['officer_id'];
$msg = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name = trim($_POST['event_name']);
    $event_date = $_POST['event_date'];
    $event_type = $_POST['event_type'];
    $half_day_period = $_POST['half_day_period'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validation
    if (empty($event_name) || empty($event_date)) {
        $msg = "Please fill in all required fields.";
        $msg_type = "danger";
    } else {
        $is_am_active = ($event_type === 'whole_day') || ($event_type === 'half_day' && $half_day_period === 'am');
        $is_pm_active = ($event_type === 'whole_day') || ($event_type === 'half_day' && $half_day_period === 'pm');

        // AM times
        $am_login_start = $is_am_active ? $_POST['am_login_start'] : NULL;
        $am_login_end   = $is_am_active ? $_POST['am_login_end'] : NULL;
        $am_logout_start = $is_am_active ? $_POST['am_logout_start'] : NULL;
        $am_logout_end   = $is_am_active ? $_POST['am_logout_end'] : NULL;

        // PM times
        $pm_login_start = $is_pm_active ? $_POST['pm_login_start'] : NULL;
        $pm_login_end   = $is_pm_active ? $_POST['pm_login_end'] : NULL;
        $pm_logout_start = $is_pm_active ? $_POST['pm_logout_start'] : NULL;
        $pm_logout_end   = $is_pm_active ? $_POST['pm_logout_end'] : NULL;

        // Fines
        $miss_am_login = $is_am_active ? floatval($_POST['miss_am_login']) : 0;
        $miss_am_logout = $is_am_active ? floatval($_POST['miss_am_logout']) : 0;
        $miss_pm_login = $is_pm_active ? floatval($_POST['miss_pm_login']) : 0;
        $miss_pm_logout = $is_pm_active ? floatval($_POST['miss_pm_logout']) : 0;

        // Database insertion - NOW WITH location AND description
        $stmt = $conn->prepare("INSERT INTO events (event_name, event_date, event_type, half_day_period, location, description, created_by, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
        $stmt->bind_param("ssssssi", $event_name, $event_date, $event_type, $half_day_period, $location, $description, $officer_id);
        
        if($stmt->execute()){
            $event_id = $stmt->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO attendance_schedule 
                (event_id, am_login_start, am_login_end, am_logout_start, am_logout_end, pm_login_start, pm_login_end, pm_logout_start, pm_logout_end) 
                VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt2->bind_param("issssssss", $event_id, $am_login_start, $am_login_end, $am_logout_start, $am_logout_end, $pm_login_start, $pm_login_end, $pm_logout_start, $pm_logout_end);
            $stmt2->execute();

            $stmt3 = $conn->prepare("INSERT INTO event_fines 
                (event_id, miss_am_login, miss_am_logout, miss_pm_login, miss_pm_logout) 
                VALUES (?,?,?,?,?)");
            $stmt3->bind_param("idddd", $event_id, $miss_am_login, $miss_am_logout, $miss_pm_login, $miss_pm_logout);
            $stmt3->execute();

            $msg = "Event created successfully! Redirecting...";
            $msg_type = "success";
            
            // Optional: Redirect after success
            // header("Refresh: 2; URL=events_list.php");
        } else {
            $msg = "Error creating event: " . $stmt->error;
            $msg_type = "danger";
        }
    }
}

// Get officer name for display
$officer_name = isset($_SESSION['officer_name']) ? $_SESSION['officer_name'] : 'Officer';
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
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        --glass-bg: rgba(255, 255, 255, 0.95);
        --shadow-soft: 0 10px 40px -10px rgba(0, 0, 0, 0.1);
        --shadow-hover: 0 20px 60px -15px rgba(0, 0, 0, 0.15);
        --radius-lg: 20px;
        --radius-md: 12px;
        --radius-sm: 8px;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
        color: #2d3748;
    }

    /* Page Header */
    .page-header {
        background: white;
        padding: 2rem 0;
        margin-bottom: 2rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--primary-gradient);
    }

    .breadcrumb-nav {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #718096;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .breadcrumb-nav a {
        color: #667eea;
        text-decoration: none;
        font-weight: 500;
    }

    .breadcrumb-nav a:hover {
        text-decoration: underline;
    }

    .page-title {
        font-size: 1.75rem;
        font-weight: 800;
        color: #1a202c;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    /* Main Card */
    .main-card {
        background: var(--glass-bg);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-soft);
        border: 1px solid rgba(255, 255, 255, 0.5);
        overflow: hidden;
        backdrop-filter: blur(10px);
    }

    .card-header-custom {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header-icon {
        width: 50px;
        height: 50px;
        background: var(--primary-gradient);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    /* Form Sections */
    .form-section {
        background: white;
        border-radius: var(--radius-md);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }

    .form-section:hover {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        border-color: #cbd5e0;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #f1f5f9;
    }

    .section-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .section-icon.info {
        background: #eef2ff;
        color: #667eea;
    }

    .section-icon.am {
        background: #fff7ed;
        color: #f97316;
    }

    .section-icon.pm {
        background: #eff6ff;
        color: #3b82f6;
    }

    .section-icon.fine {
        background: #f0fdf4;
        color: #22c55e;
    }

    .section-icon.location {
        background: #fef3c7;
        color: #d97706;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    /* Form Controls */
    .form-label {
        font-weight: 600;
        color: #475569;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .form-control,
    .form-select {
        border: 2px solid #e2e8f0;
        border-radius: var(--radius-sm);
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: all 0.2s ease;
        background: #fafafa;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }

    .input-group-text {
        background: #f1f5f9;
        border: 2px solid #e2e8f0;
        border-right: none;
        color: #64748b;
        font-weight: 600;
    }

    .input-group .form-control {
        border-left: none;
    }

    /* Event Type Selector */
    .event-type-card {
        border: 2px solid #e2e8f0;
        border-radius: var(--radius-md);
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        background: white;
    }

    .event-type-card:hover {
        border-color: #cbd5e0;
        transform: translateY(-2px);
    }

    .event-type-card.active {
        border-color: #667eea;
        background: #eef2ff;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
    }

    .event-type-card.selected {
        border-color: #667eea;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .event-type-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 0.75rem;
        font-size: 1.5rem;
        background: #f1f5f9;
        transition: all 0.3s ease;
    }

    .event-type-card.selected .event-type-icon {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .event-type-card:not(.selected) .event-type-icon.am {
        background: #fff7ed;
        color: #f97316;
    }

    .event-type-card:not(.selected) .event-type-icon.pm {
        background: #eff6ff;
        color: #3b82f6;
    }

    .event-type-card:not(.selected) .event-type-icon.whole {
        background: #f0fdf4;
        color: #22c55e;
    }

    /* Period Selector */
    .period-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-top: 1rem;
    }

    .period-option {
        position: relative;
        border: 2px solid #e2e8f0;
        border-radius: var(--radius-md);
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        background: white;
    }

    .period-option:hover {
        border-color: #cbd5e0;
    }

    .period-option.active {
        border-color: #667eea;
        background: #eef2ff;
    }

    .period-option input {
        position: absolute;
        opacity: 0;
    }

    .period-icon {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.75rem;
        transition: all 0.3s ease;
    }

    .period-option.am .period-icon {
        background: #fff7ed;
        color: #f97316;
    }

    .period-option.pm .period-icon {
        background: #eff6ff;
        color: #3b82f6;
    }

    .period-option.active .period-icon {
        background: #667eea;
        color: white;
    }

    .period-title {
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }

    .period-time {
        font-size: 0.875rem;
        color: #64748b;
    }

    .period-option.active .period-time {
        color: #667eea;
    }

    /* Attendance Section States */
    .attendance-section {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 1;
        transform: scale(1);
    }

    .attendance-section.disabled {
        opacity: 0.4;
        transform: scale(0.98);
        pointer-events: none;
        filter: grayscale(0.8);
    }

    .attendance-section.disabled::after {
        content: 'Not Required for This Event Type';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #1e293b;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 30px;
        font-size: 0.875rem;
        font-weight: 600;
        white-space: nowrap;
        z-index: 10;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.875rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge.active {
        background: #dcfce7;
        color: #166534;
    }

    .status-badge.inactive {
        background: #f1f5f9;
        color: #64748b;
    }

    /* Time Input Grid */
    .time-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .time-input-wrapper {
        background: #f8fafc;
        border-radius: var(--radius-sm);
        padding: 1rem;
        border: 1px solid #e2e8f0;
    }

    .time-input-wrapper:focus-within {
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    /* Buttons */
    .btn-create {
        background: var(--primary-gradient);
        border: none;
        color: white;
        padding: 1rem 2rem;
        font-size: 1rem;
        font-weight: 600;
        border-radius: var(--radius-md);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        transition: all 0.3s ease;
    }

    .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        color: white;
    }

    .btn-cancel {
        border: 2px solid #e2e8f0;
        color: #64748b;
        padding: 1rem 2rem;
        font-weight: 600;
        border-radius: var(--radius-md);
        transition: all 0.3s ease;
    }

    .btn-cancel:hover {
        border-color: #cbd5e0;
        background: #f8fafc;
        color: #475569;
    }

    /* Alert */
    .custom-alert {
        border-radius: var(--radius-md);
        border: none;
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .custom-alert.alert-success {
        background: #dcfce7;
        color: #166534;
    }

    .custom-alert.alert-danger {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Animations */
    @keyframes slideIn {
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
        animation: slideIn 0.5s ease forwards;
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

    /* Responsive */
    @media (max-width: 768px) {
        .time-grid {
            grid-template-columns: 1fr;
        }

        .period-grid {
            grid-template-columns: 1fr;
        }

        .page-title {
            font-size: 1.25rem;
        }
    }
    </style>
</head>

<body>

    <div class="main-content">


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
                                    <small class="text-muted">Configure attendance schedules and fine policies</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted d-block">Created by</small>
                                <span class="fw-semibold"><?php echo htmlspecialchars($officer_name); ?></span>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <?php if($msg != ""): ?>
                            <div class="custom-alert alert-<?php echo $msg_type; ?> mb-4 animate-in">
                                <i
                                    class="bi bi-<?php echo $msg_type == 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill'; ?> fs-4"></i>
                                <div>
                                    <strong><?php echo $msg_type == 'success' ? 'Success!' : 'Error!'; ?></strong>
                                    <span class="d-block small"><?php echo $msg; ?></span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <form method="POST" id="eventForm" novalidate>

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
                                            value="<?php echo isset($_POST['event_name']) ? htmlspecialchars($_POST['event_name']) : ''; ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Event Date <span
                                                    class="text-danger">*</span></label>
                                            <input type="date" name="event_date" class="form-control" required
                                                value="<?php echo isset($_POST['event_date']) ? $_POST['event_date'] : ''; ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Event Type <span
                                                    class="text-danger">*</span></label>
                                            <select name="event_type" id="eventType" class="form-select" required
                                                onchange="handleEventTypeChange()">
                                                <option value="whole_day"
                                                    <?php echo (isset($_POST['event_type']) && $_POST['event_type'] == 'whole_day') ? 'selected' : ''; ?>>
                                                    Whole Day</option>
                                                <option value="half_day"
                                                    <?php echo (isset($_POST['event_type']) && $_POST['event_type'] == 'half_day') ? 'selected' : ''; ?>>
                                                    Half Day</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- NEW: Location Field -->
                                    <div class="mb-3">
                                        <label class="form-label">Location</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                            <input type="text" name="location" class="form-control"
                                                placeholder="e.g., Main Auditorium, Building A"
                                                value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                                        </div>
                                    </div>

                                    <!-- NEW: Description Field -->
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="3"
                                            placeholder="Enter event description, agenda, or additional notes..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                    </div>

                                    <!-- Half Day Period Selection -->
                                    <div id="periodContainer" class="mt-3" style="display: none;">
                                        <label class="form-label">Select Period <span
                                                class="text-danger">*</span></label>
                                        <div class="period-grid">
                                            <label
                                                class="period-option am <?php echo (isset($_POST['half_day_period']) && $_POST['half_day_period'] == 'am') ? 'active' : ''; ?>"
                                                onclick="selectPeriod('am')">
                                                <input type="radio" name="half_day_period" value="am" id="period-am"
                                                    <?php echo (isset($_POST['half_day_period']) && $_POST['half_day_period'] == 'am') ? 'checked' : ''; ?>
                                                    onchange="updateSections()">
                                                <div class="period-icon">
                                                    <i class="bi bi-sunrise"></i>
                                                </div>
                                                <div class="period-title">Morning Session</div>
                                                <div class="period-time">6:00 AM - 12:00 PM</div>
                                            </label>

                                            <label
                                                class="period-option pm <?php echo (isset($_POST['half_day_period']) && $_POST['half_day_period'] == 'pm') ? 'active' : ''; ?>"
                                                onclick="selectPeriod('pm')">
                                                <input type="radio" name="half_day_period" value="pm" id="period-pm"
                                                    <?php echo (isset($_POST['half_day_period']) && $_POST['half_day_period'] == 'pm') ? 'checked' : ''; ?>
                                                    onchange="updateSections()">
                                                <div class="period-icon">
                                                    <i class="bi bi-sunset"></i>
                                                </div>
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
                                                required
                                                value="<?php echo isset($_POST['am_login_start']) ? $_POST['am_login_start'] : '08:00'; ?>">
                                        </div>
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Login End</label>
                                            <input type="time" name="am_login_end" class="form-control am-input"
                                                required
                                                value="<?php echo isset($_POST['am_login_end']) ? $_POST['am_login_end'] : '09:00'; ?>">
                                        </div>
                                    </div>

                                    <div class="time-grid mb-4">
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Logout Start</label>
                                            <input type="time" name="am_logout_start" class="form-control am-input"
                                                required
                                                value="<?php echo isset($_POST['am_logout_start']) ? $_POST['am_logout_start'] : '12:00'; ?>">
                                        </div>
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Logout End</label>
                                            <input type="time" name="am_logout_end" class="form-control am-input"
                                                required
                                                value="<?php echo isset($_POST['am_logout_end']) ? $_POST['am_logout_end'] : '13:00'; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Miss Login Fine ($)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" name="miss_am_login"
                                                    class="form-control am-input" placeholder="0.00" required
                                                    value="<?php echo isset($_POST['miss_am_login']) ? $_POST['miss_am_login'] : '5.00'; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Miss Logout Fine ($)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" name="miss_am_logout"
                                                    class="form-control am-input" placeholder="0.00" required
                                                    value="<?php echo isset($_POST['miss_am_logout']) ? $_POST['miss_am_logout'] : '5.00'; ?>">
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
                                                value="<?php echo isset($_POST['pm_login_start']) ? $_POST['pm_login_start'] : '13:00'; ?>">
                                        </div>
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Login End</label>
                                            <input type="time" name="pm_login_end" class="form-control pm-input"
                                                value="<?php echo isset($_POST['pm_login_end']) ? $_POST['pm_login_end'] : '14:00'; ?>">
                                        </div>
                                    </div>

                                    <div class="time-grid mb-4">
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Logout Start</label>
                                            <input type="time" name="pm_logout_start" class="form-control pm-input"
                                                value="<?php echo isset($_POST['pm_logout_start']) ? $_POST['pm_logout_start'] : '17:00'; ?>">
                                        </div>
                                        <div class="time-input-wrapper">
                                            <label class="form-label small text-muted mb-2">Logout End</label>
                                            <input type="time" name="pm_logout_end" class="form-control pm-input"
                                                value="<?php echo isset($_POST['pm_logout_end']) ? $_POST['pm_logout_end'] : '18:00'; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Miss Login Fine ($)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" name="miss_pm_login"
                                                    class="form-control pm-input" placeholder="0.00"
                                                    value="<?php echo isset($_POST['miss_pm_login']) ? $_POST['miss_pm_login'] : '5.00'; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Miss Logout Fine ($)</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" name="miss_pm_logout"
                                                    class="form-control pm-input" placeholder="0.00"
                                                    value="<?php echo isset($_POST['miss_pm_logout']) ? $_POST['miss_pm_logout'] : '5.00'; ?>">
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script>
        function handleEventTypeChange() {
            const eventType = document.getElementById('eventType').value;
            const periodContainer = document.getElementById('periodContainer');

            if (eventType === 'half_day') {
                periodContainer.style.display = 'block';
                // Auto-select AM if nothing selected
                if (!document.querySelector('input[name="half_day_period"]:checked')) {
                    selectPeriod('am');
                }
            } else {
                periodContainer.style.display = 'none';
                // Enable both sections for whole day
                toggleSection('am', true);
                toggleSection('pm', true);
            }
        }

        function selectPeriod(period) {
            // Update visual selection
            document.querySelectorAll('.period-option').forEach(opt => opt.classList.remove('active'));
            document.querySelector('.period-option.' + period).classList.add('active');

            // Update radio button
            document.getElementById('period-' + period).checked = true;

            updateSections();
        }

        function updateSections() {
            const eventType = document.getElementById('eventType').value;
            const selectedPeriod = document.querySelector('input[name="half_day_period"]:checked')?.value;

            if (eventType === 'half_day') {
                if (selectedPeriod === 'am') {
                    toggleSection('am', true);
                    toggleSection('pm', false);
                } else if (selectedPeriod === 'pm') {
                    toggleSection('am', false);
                    toggleSection('pm', true);
                }
            }
        }

        function toggleSection(section, isActive) {
            const sectionEl = document.getElementById(section + 'Section');
            const inputs = document.querySelectorAll('.' + section + '-input');
            const statusBadge = document.getElementById(section + 'Status');

            if (isActive) {
                sectionEl.classList.remove('disabled');
                statusBadge.textContent = 'Active';
                statusBadge.className = 'status-badge active';
                inputs.forEach(input => {
                    input.disabled = false;
                    input.setAttribute('required', 'required');
                });
            } else {
                sectionEl.classList.add('disabled');
                statusBadge.textContent = 'Inactive';
                statusBadge.className = 'status-badge inactive';
                inputs.forEach(input => {
                    input.disabled = true;
                    input.removeAttribute('required');
                    input.value = '';
                });
            }
        }

        // Form validation
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            const eventType = document.getElementById('eventType').value;

            if (eventType === 'half_day' && !document.querySelector('input[name="half_day_period"]:checked')) {
                e.preventDefault();
                alert('Please select AM or PM period for half-day event.');
                return false;
            }
        });

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            handleEventTypeChange();

            // Set min date to today for date picker
            const dateInput = document.querySelector('input[name="event_date"]');
            if (dateInput) {
                const today = new Date().toISOString().split('T')[0];
                dateInput.setAttribute('min', today);
            }
        });
        </script>
</body>

</html>
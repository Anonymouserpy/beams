<?php
// MUST BE FIRST - NO SPACES BEFORE <?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
// REMOVED the early sidebar require from here
require "../../Connection/connection.php";

// Auth check
if (!isset($_SESSION['officer_id'])) {
    header("Location: ../../officer_Login.php");
    exit();
}

// Function to run fines if needed
function runFinesIfNeeded() {
    global $conn;
    $lastRunFile = __DIR__ . '/last_fine_run.txt';
    $currentTime = time();

    // Always run the procedure
    $query = "CALL generate_event_fines()";
    if (mysqli_query($conn, $query)) {
        error_log("Fines generated successfully at " . date('Y-m-d H:i:s'));
    } else {
        error_log("Error generating fines: " . mysqli_error($conn));
    }

    // Update the last run time (optional, for tracking)
    file_put_contents($lastRunFile, date('Y-m-d H:i:s', $currentTime));
}
runFinesIfNeeded();

// Handle AJAX Delete Action
if (isset($_GET['ajax_delete']) && !empty($_GET['ajax_delete'])) {
    header('Content-Type: application/json');
    $student_id = $conn->real_escape_string($_GET['ajax_delete']);

    // Delete related records first
    $tables = ['student_fines', 'attendance'];

    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows > 0) {
            $conn->query("DELETE FROM $table WHERE student_id = '$student_id'");
        }
    }

    // Delete student
    $result = $conn->query("DELETE FROM students WHERE student_id = '$student_id'");

    if ($result) {
        // Broadcast WebSocket notification
        broadcastWebSocket([
            'type' => 'student_deleted',
            'student_id' => $student_id,
            'timestamp' => time()
        ]);
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting student: ' . $conn->error]);
    }
    exit();
}
// Handle AJAX Refresh (every 5 seconds polling)
if (isset($_GET['ajax_refresh']) && $_GET['ajax_refresh'] == '1') {
    header('Content-Type: application/json');

    // Fetch all students with aggregated data
    $students_result = $conn->query("
        SELECT s.*, 
               COUNT(DISTINCT a.attendance_id) as attendance_count,
               COUNT(DISTINCT f.fine_id) as total_fines,
               SUM(CASE WHEN f.status = 'unpaid' THEN f.amount ELSE 0 END) as unpaid_amount
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id
        LEFT JOIN student_fines f ON s.student_id = f.student_id
        GROUP BY s.student_id
        ORDER BY s.year_level ASC, s.section ASC, s.full_name ASC
    ");

    $students = [];
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }

    // Get statistics
    $total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
    $total_sections = $conn->query("SELECT COUNT(DISTINCT section) as count FROM students")->fetch_assoc()['count'];
    $total_unpaid = $conn->query("SELECT SUM(amount) as total FROM student_fines WHERE status = 'unpaid'")->fetch_assoc()['total'] ?: 0;
    $total_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'];

    echo json_encode([
        'success' => true,
        'students' => $students,
        'stats' => [
            'total_students' => $total_students,
            'total_sections' => $total_sections,
            'total_unpaid' => $total_unpaid,
            'total_attendance' => $total_attendance
        ]
    ]);
    exit();
}

// Handle AJAX Create/Edit Action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    $student_id = isset($_POST['student_id']) ? $conn->real_escape_string($_POST['student_id']) : '';
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $year_level = intval($_POST['year_level']);
    $section = $conn->real_escape_string($_POST['section']);
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Validate year level (1-5 for college)
    if ($year_level < 1 || $year_level > 4) {
        $year_level = 1;
    }

    if (!empty($student_id) && isset($_POST['existing_id']) && !empty($_POST['existing_id'])) {
        // Update existing student
        $existing_id = $conn->real_escape_string($_POST['existing_id']);

        if (!empty($password)) {
            // Update with new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE students SET 
                student_id = '$student_id',
                full_name = '$full_name',
                year_level = $year_level,
                section = '$section',
                password = '$hashed_password'
                WHERE student_id = '$existing_id'";
        } else {
            // Update without changing password
            $sql = "UPDATE students SET 
                student_id = '$student_id',
                full_name = '$full_name',
                year_level = $year_level,
                section = '$section'
                WHERE student_id = '$existing_id'";
        }

        if ($conn->query($sql)) {
            // Broadcast WebSocket notification
            broadcastWebSocket([
                'type' => 'student_updated',
                'student' => [
                    'student_id' => $student_id,
                    'full_name' => $full_name,
                    'year_level' => $year_level,
                    'section' => $section
                ],
                'timestamp' => time()
            ]);
            echo json_encode(['success' => true, 'message' => 'Student updated successfully', 'action' => 'update']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating student: ' . $conn->error]);
        }
    } else {
        // Create new student
        if (empty($password)) {
            $password = 'student123'; // Default password
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO students (student_id, full_name, year_level, section, password, created_at) 
            VALUES ('$student_id', '$full_name', $year_level, '$section', '$hashed_password', NOW())";

        if ($conn->query($sql)) {
            // Broadcast WebSocket notification
            broadcastWebSocket([
                'type' => 'student_created',
                'student' => [
                    'student_id' => $student_id,
                    'full_name' => $full_name,
                    'year_level' => $year_level,
                    'section' => $section
                ],
                'timestamp' => time()
            ]);
            echo json_encode(['success' => true, 'message' => 'Student created successfully', 'action' => 'create']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error creating student: ' . $conn->error]);
        }
    }
    exit();
}

// Handle Get Single Student (for edit) - MODIFIED to include aggregated data
if (isset($_GET['get_student']) && !empty($_GET['get_student'])) {
    header('Content-Type: application/json');
    $student_id = $conn->real_escape_string($_GET['get_student']);
    $result = $conn->query("
        SELECT s.*, 
               COUNT(DISTINCT a.attendance_id) as attendance_count,
               COUNT(DISTINCT f.fine_id) as total_fines,
               SUM(CASE WHEN f.status = 'unpaid' THEN f.amount ELSE 0 END) as unpaid_amount
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id
        LEFT JOIN student_fines f ON s.student_id = f.student_id
        WHERE s.student_id = '$student_id'
        GROUP BY s.student_id
    ");

    if ($result->num_rows > 0) {
        echo json_encode(['success' => true, 'student' => $result->fetch_assoc()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
    exit();
}

// Function to broadcast WebSocket messages (using file-based approach for simplicity)
function broadcastWebSocket($data) {
    // Connect to the internal TCP socket (port 8081)
    $socket = @fsockopen('tcp://127.0.0.1', 8081, $errno, $errstr, 1);
    if ($socket) {
        fwrite($socket, json_encode($data) . "\n");
        fclose($socket);
    }
    // Also store in database for persistence (optional)
    global $conn;
    $message = $conn->real_escape_string(json_encode($data));
    $conn->query("INSERT INTO websocket_messages (message, created_at) VALUES ('$message', NOW())");
}

// Fetch all students with aggregated data
$students = $conn->query("
    SELECT s.*, 
           COUNT(DISTINCT a.attendance_id) as attendance_count,
           COUNT(DISTINCT f.fine_id) as total_fines,
           SUM(CASE WHEN f.status = 'unpaid' THEN f.amount ELSE 0 END) as unpaid_amount
    FROM students s
    LEFT JOIN attendance a ON s.student_id = a.student_id
    LEFT JOIN student_fines f ON s.student_id = f.student_id
    GROUP BY s.student_id
    ORDER BY s.year_level ASC, s.section ASC, s.full_name ASC
");

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_sections = $conn->query("SELECT COUNT(DISTINCT section) as count FROM students")->fetch_assoc()['count'];
$total_unpaid = $conn->query("SELECT SUM(amount) as total FROM student_fines WHERE status = 'unpaid'")->fetch_assoc()['total'] ?: 0;
$total_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'];

// Check for WebSocket table and create if not exists
$conn->query("CREATE TABLE IF NOT EXISTS websocket_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (created_at)
)");

// INCLUDE THE SIDEBAR (this will output the sidebar HTML)
require "../sidebar/officer_sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College Student Manager | BEAMS Officer Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
    /* Only page-specific styles - no sidebar styles here since they're in the sidebar file */
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



    /* Main content wrapper - adjust based on your sidebar's layout */
    .main-wrapper {
        margin-left: 280px;
        /* Adjust this value to match your sidebar width */
        padding: 20px;
        transition: margin-left 0.3s ease;
    }

    @media (max-width: 991px) {
        .main-wrapper {
            margin-left: 0;
        }
    }

    /* Page Header */
    .page-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        padding: 2rem;
        color: white;
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        margin-bottom: 2rem;
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

    /* Stats Row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        border: 1px solid #e2e8f0;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover);
    }

    .stat-card.updating::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        animation: updatePulse 1s ease;
    }

    @keyframes updatePulse {
        0% {
            opacity: 0;
            transform: scaleX(0);
        }

        50% {
            opacity: 1;
            transform: scaleX(1);
        }

        100% {
            opacity: 0;
            transform: scaleX(1);
        }
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
        transition: all 0.3s ease;
    }

    .stat-value.changed {
        color: var(--primary);
        transform: scale(1.1);
    }

    .stat-label {
        color: #64748b;
        font-size: 0.875rem;
        font-weight: 500;
    }

    /* Content Card */
    .content-card {
        background: white;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .card-header {
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafafa;
        flex-wrap: wrap;
        gap: 1rem;
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

    .search-box {
        position: relative;
        min-width: 300px;
    }

    .search-box input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .search-box input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    /* Student Grid */
    .students-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        padding: 1.5rem;
    }

    .student-card {
        background: white;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        transition: all 0.3s ease;
        position: relative;
    }

    .student-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--card-hover);
        border-color: var(--primary);
    }

    .student-card.deleting {
        animation: fadeOut 0.5s ease forwards;
    }

    .student-card.adding {
        animation: fadeInUp 0.5s ease forwards;
    }

    .student-card.updating {
        animation: pulseUpdate 0.5s ease;
    }

    @keyframes pulseUpdate {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.02);
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.3);
        }
    }

    @keyframes fadeOut {
        to {
            opacity: 0;
            transform: scale(0.9);
        }
    }

    .student-header {
        padding: 1.5rem;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .student-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .student-info h4 {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.25rem;
    }

    .student-id {
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        color: var(--primary);
        font-weight: 600;
        background: #eef2ff;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        display: inline-block;
    }

    .student-body {
        padding: 1.5rem;
    }

    .student-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .meta-item {
        text-align: center;
        padding: 0.75rem;
        background: #f8fafc;
        border-radius: 8px;
    }

    .meta-value {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--dark);
    }

    .meta-label {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .student-stats {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.875rem;
    }

    .stat-row span:first-child {
        color: #64748b;
    }

    .stat-row span:last-child {
        font-weight: 600;
        color: var(--dark);
    }

    .fines-warning {
        color: var(--danger) !important;
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

    .student-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-student {
        flex: 1;
        padding: 0.625rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        text-decoration: none;
        text-align: center;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-view {
        background: #eef2ff;
        color: var(--primary);
    }

    .btn-view:hover {
        background: var(--primary);
        color: white;
    }

    .btn-edit {
        background: #fef3c7;
        color: #d97706;
    }

    .btn-edit:hover {
        background: #f59e0b;
        color: white;
    }

    .btn-delete {
        background: #fee2e2;
        color: #dc2626;
    }

    .btn-delete:hover {
        background: #ef4444;
        color: white;
    }

    /* Modal Styles */
    .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .modal-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        border-radius: 16px 16px 0 0;
        padding: 1.5rem;
        border: none;
    }

    .modal-header.bg-info {
        background: var(--info) !important;
    }

    .modal-title {
        font-weight: 700;
        font-size: 1.25rem;
    }

    .btn-close {
        filter: brightness(0) invert(1);
    }

    .modal-body {
        padding: 1.5rem;
    }

    .form-label {
        font-weight: 600;
        color: var(--dark);
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .form-control,
    .form-select {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .form-control:focus,
    .form-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);
    }

    .btn-primary:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    /* Year Level Selector - College Style */
    .year-selector {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .year-option {
        position: relative;
        cursor: pointer;
    }

    .year-option input[type="radio"] {
        position: absolute;
        opacity: 0;
    }

    .year-card {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.75rem;
        text-align: center;
        transition: all 0.3s ease;
        background: white;
    }

    .year-option input[type="radio"]:checked+.year-card {
        border-color: var(--primary);
        background: #eef2ff;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
    }

    .year-number {
        font-weight: 700;
        font-size: 1.125rem;
        color: var(--dark);
    }

    .year-label {
        font-size: 0.625rem;
        color: #64748b;
        text-transform: uppercase;
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

    /* Filter Tabs */
    .filter-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        padding: 0 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        overflow-x: auto;
    }

    .filter-tab {
        padding: 0.75rem 1.25rem;
        border: none;
        background: transparent;
        color: #64748b;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .filter-tab:hover {
        color: var(--primary);
    }

    .filter-tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    /* Toast Notifications */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }

    .toast {
        background: white;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 300px;
        animation: slideInRight 0.3s ease;
        border-left: 4px solid var(--success);
        margin-bottom: 10px;
    }

    .toast.error {
        border-left-color: var(--danger);
    }

    .toast.warning {
        border-left-color: var(--warning);
    }

    .toast.info {
        border-left-color: var(--info);
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .toast-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .toast.success .toast-icon {
        background: #d1fae5;
        color: var(--success);
    }

    .toast.error .toast-icon {
        background: #fee2e2;
        color: var(--danger);
    }

    .toast.warning .toast-icon {
        background: #fef3c7;
        color: var(--warning);
    }

    .toast.info .toast-icon {
        background: #e0f2fe;
        color: var(--info);
    }

    .toast-content {
        flex: 1;
    }

    .toast-title {
        font-weight: 700;
        color: var(--dark);
        font-size: 0.95rem;
    }

    .toast-message {
        color: #64748b;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    .toast-close {
        background: none;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        font-size: 1.25rem;
        padding: 0.25rem;
        transition: color 0.2s;
    }

    .toast-close:hover {
        color: var(--dark);
    }

    /* Loading Spinner */
    .spinner {
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        display: inline-block;
        margin-right: 0.5rem;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* WebSocket Connection Status */
    .ws-status {
        position: fixed;
        bottom: 20px;
        left: 300px;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        z-index: 1000;
        transition: all 0.3s ease;
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 991px) {
        .ws-status {
            left: 20px;
        }
    }

    .ws-status.connected {
        color: var(--success);
        border: 1px solid var(--success);
    }

    .ws-status.disconnected {
        color: var(--danger);
        border: 1px solid var(--danger);
    }

    .ws-status.connecting {
        color: var(--warning);
        border: 1px solid var(--warning);
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

    /* Real-time indicator */
    .live-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        background: #fee2e2;
        color: var(--danger);
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 1rem;
        animation: livePulse 2s infinite;
    }

    @keyframes livePulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.6;
        }
    }

    .live-indicator::before {
        content: '';
        width: 6px;
        height: 6px;
        background: var(--danger);
        border-radius: 50%;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }

        .year-selector {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 991px) {
        .stats-row {
            grid-template-columns: 1fr;
        }

        .students-grid {
            grid-template-columns: 1fr;
        }

        .year-selector {
            grid-template-columns: repeat(2, 1fr);
        }

        .search-box {
            width: 100%;
            min-width: unset;
        }

        .card-header {
            flex-direction: column;
            align-items: stretch;
        }
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

    .delay-4 {
        animation-delay: 0.4s;
    }

    /* Password toggle */
    .password-wrapper {
        position: relative;
    }

    .password-toggle {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #94a3b8;
        transition: color 0.2s;
    }

    .password-toggle:hover {
        color: var(--primary);
    }

    /* Sync notification */
    .sync-notification {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: var(--dark);
        color: white;
        padding: 1rem 2rem;
        border-radius: 50px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        z-index: 10000;
        display: none;
        align-items: center;
        gap: 0.75rem;
        font-weight: 600;
    }

    .sync-notification.show {
        display: flex;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            transform: translate(-50%, -100%);
            opacity: 0;
        }

        to {
            transform: translate(-50%, 0);
            opacity: 1;
        }
    }
    </style>
</head>

<body>
    <!-- The sidebar is already included above via require and will appear here -->
    <div class="main-contents">


        <!-- WebSocket Connection Status -->
        <div class="ws-status disconnected" id="wsStatus">
            <i class="fas fa-circle"></i>
            <span>Offline</span>
        </div>

        <!-- Sync Notification -->
        <div class="sync-notification" id="syncNotification">
            <i class="fas fa-sync-alt fa-spin"></i>
            <span>Syncing real-time updates...</span>
        </div>

        <!-- Main Content Wrapper - Adjust margin based on your sidebar -->
        <!-- Toast Container -->
        <div class="toast-container" id="toastContainer"></div>

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="officer_dashboard.php"><i class="fas fa-home"></i>
                                Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">College Student Manager</li>
                    </ol>
                </nav>
                <h1 class="page-title">
                    <i class="fas fa-user-graduate me-3"></i>College Student Manager
                    <span class="live-indicator">LIVE</span>
                </h1>
                <p class="page-subtitle">Manage college student records, track attendance, and monitor fines in
                    real-time</p>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row" id="statsRow">
            <div class="stat-card primary animate-in" id="statStudents">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value" id="totalStudents"><?php echo $total_students; ?></div>
                <div class="stat-label">Total College Students</div>
            </div>
            <div class="stat-card success animate-in delay-1" id="statSections">
                <div class="stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-value" id="totalSections"><?php echo $total_sections; ?></div>
                <div class="stat-label">Sections</div>
            </div>
            <div class="stat-card warning animate-in delay-2" id="statFines">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value" id="totalUnpaid">₱<?php echo number_format($total_unpaid, 2); ?></div>
                <div class="stat-label">Unpaid Fines</div>
            </div>
            <div class="stat-card danger animate-in delay-3" id="statAttendance">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-value" id="totalAttendance"><?php echo $total_attendance; ?></div>
                <div class="stat-label">Total Records</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-card animate-in delay-2">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-list-ul text-primary"></i>
                    All College Students
                </h3>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search students..." onkeyup="filterStudents()">
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal"
                        onclick="resetForm()">
                        <i class="fas fa-plus me-2"></i>Add Student
                    </button>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab" onclick="filterByYear(event, 'all')">All Students</button>
                <button class="filter-tab" onclick="filterByYear(event, 1)">1st Year</button>
                <button class="filter-tab" onclick="filterByYear(event, 2)">2nd Year</button>
                <button class="filter-tab" onclick="filterByYear(event, 3)">3rd Year</button>
                <button class="filter-tab" onclick="filterByYear(event, 4)">4th Year</button>
            </div>

            <div class="students-grid" id="studentsGrid">
                <?php if ($students->num_rows > 0): ?>
                <?php while($student = $students->fetch_assoc()): 
                            $initials = implode('', array_map(function($word) { 
                                return strtoupper(substr($word, 0, 1)); 
                            }, explode(' ', $student['full_name'])));
                            $initials = substr($initials, 0, 2);

                            $attendance_rate = $total_attendance > 0 ? 
                                round(($student['attendance_count'] / $total_attendance) * 100) : 0;

                            // Year level suffix
                            $year_suffix = ['st', 'nd', 'rd', 'th', 'th'];
                            $year_display = $student['year_level'] . $year_suffix[$student['year_level'] - 1] . ' Year';
                        ?>
                <div class="student-card" id="student-<?php echo htmlspecialchars($student['student_id']); ?>"
                    data-year="<?php echo $student['year_level']; ?>"
                    data-name="<?php echo strtolower($student['full_name']); ?>"
                    data-id="<?php echo strtolower($student['student_id']); ?>">
                    <div class="student-header">
                        <div class="student-avatar">
                            <?php echo $initials; ?>
                        </div>
                        <div class="student-info">
                            <h4><?php echo htmlspecialchars($student['full_name']); ?></h4>
                            <span class="student-id"><?php echo htmlspecialchars($student['student_id']); ?></span>
                        </div>
                    </div>
                    <div class="student-body">
                        <div class="student-meta">
                            <div class="meta-item">
                                <div class="meta-value"><?php echo $year_display; ?></div>
                                <div class="meta-label">Year Level</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-value"><?php echo htmlspecialchars($student['section']); ?></div>
                                <div class="meta-label">Section</div>
                            </div>
                        </div>

                        <div class="student-stats">
                            <div class="stat-row">
                                <span><i class="fas fa-clipboard-check me-2 text-primary"></i>Attendance
                                    Records</span>
                                <span class="attendance-count"><?php echo $student['attendance_count']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span><i class="fas fa-file-invoice-dollar me-2 text-warning"></i>Total Fines</span>
                                <span class="fines-count"><?php echo $student['total_fines']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span><i class="fas fa-exclamation-circle me-2 text-danger"></i>Unpaid Amount</span>
                                <span
                                    class="unpaid-amount <?php echo $student['unpaid_amount'] > 0 ? 'fines-warning' : ''; ?>">
                                    ₱<?php echo number_format($student['unpaid_amount'] ?: 0, 2); ?>
                                </span>
                            </div>
                        </div>

                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo min($attendance_rate, 100); ?>%">
                            </div>
                        </div>

                        <div class="student-actions">
                            <!-- View button now triggers modal -->
                            <button class="btn-student btn-view"
                                onclick="viewStudent('<?php echo htmlspecialchars($student['student_id']); ?>')">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn-student btn-edit"
                                onclick="editStudent('<?php echo htmlspecialchars($student['student_id']); ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-student btn-delete"
                                onclick="deleteStudent('<?php echo htmlspecialchars($student['student_id']); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="empty-state" id="emptyState">
                    <div class="empty-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3 class="empty-title">No Students Yet</h3>
                    <p class="empty-text">Start by adding your first college student to the system.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal"
                        onclick="resetForm()">
                        <i class="fas fa-plus me-2"></i>Add First Student
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

    <!-- Floating Action Button (Mobile) -->
    <button class="fab d-lg-none" data-bs-toggle="modal" data-bs-target="#studentModal" onclick="resetForm()">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Student Modal (Add/Edit) -->
    <div class="modal fade" id="studentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="fas fa-user-plus me-2"></i>Add New College Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="studentForm" onsubmit="return saveStudent(event)">
                    <div class="modal-body">
                        <input type="hidden" name="existing_id" id="existing_id" value="">
                        <input type="hidden" name="ajax_action" value="1">

                        <div class="mb-3">
                            <label class="form-label">Student ID</label>
                            <input type="text" class="form-control" name="student_id" id="student_id" required
                                placeholder="e.g., 11">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" id="full_name" required
                                placeholder="Enter full name">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Year Level</label>
                            <div class="year-selector">
                                <label class="year-option">
                                    <input type="radio" name="year_level" value="1" checked>
                                    <div class="year-card">
                                        <div class="year-number">1</div>
                                        <div class="year-label">1st Year</div>
                                    </div>
                                </label>
                                <label class="year-option">
                                    <input type="radio" name="year_level" value="2">
                                    <div class="year-card">
                                        <div class="year-number">2</div>
                                        <div class="year-label">2nd Year</div>
                                    </div>
                                </label>
                                <label class="year-option">
                                    <input type="radio" name="year_level" value="3">
                                    <div class="year-card">
                                        <div class="year-number">3</div>
                                        <div class="year-label">3rd Year</div>
                                    </div>
                                </label>
                                <label class="year-option">
                                    <input type="radio" name="year_level" value="4">
                                    <div class="year-card">
                                        <div class="year-number">4</div>
                                        <div class="year-label">4th Year</div>
                                    </div>
                                </label>

                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Section/Block</label>
                            <input type="text" class="form-control" name="section" id="section" required
                                placeholder="e.g., A, B, C or Block 1, Block 2">
                        </div>

                        <div class="mb-3" id="passwordField">
                            <label class="form-label">Password <small class="text-muted" id="passwordHint">(Leave blank
                                    for default: student123)</small></label>
                            <div class="password-wrapper">
                                <input type="password" class="form-control" name="password" id="password"
                                    placeholder="Enter password">
                                <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveBtn">
                            <i class="fas fa-save me-2"></i>Save Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Student Modal (NEW) -->
    <div class="modal fade" id="viewStudentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-graduate me-2"></i>Student Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <div class="student-avatar mx-auto" style="width: 80px; height: 80px; font-size: 2rem;"
                            id="viewAvatar">JD</div>
                        <h4 id="viewFullName" class="mt-2"></h4>
                        <span class="student-id" id="viewStudentId"></span>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Year Level</label>
                            <p class="form-control-plaintext" id="viewYearLevel"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Section</label>
                            <p class="form-control-plaintext" id="viewSection"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Attendance Records</label>
                            <p class="form-control-plaintext" id="viewAttendanceCount">0</p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Total Fines</label>
                            <p class="form-control-plaintext" id="viewTotalFines">0</p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Unpaid Amount</label>
                            <p class="form-control-plaintext" id="viewUnpaidAmount">₱0.00</p>
                        </div>
                    </div>
                    <hr>
                    <p class="text-muted small">Created at: <span id="viewCreatedAt"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="editStudentFromView()"
                        id="viewEditBtn">Edit</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // WebSocket Configuration
    const WS_CONFIG = {
        host: '<?php echo $_SERVER['HTTP_HOST']; ?>',
        port: 8080,
        protocol: window.location.protocol === 'https:' ? 'wss:' : 'ws:',
        reconnectInterval: 3000,
        maxReconnectAttempts: 5
    };

    // Global WebSocket instance
    let ws = null;
    let reconnectAttempts = 0;
    let reconnectTimer = null;

    // Filter state
    let currentFilterYear = 'all';

    // Initialize WebSocket connection
    function initWebSocket() {
        // Prevent duplicate connections
        if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) {
            console.log('WebSocket already connecting or open');
            return;
        }

        const wsUrl = `${WS_CONFIG.protocol}//${WS_CONFIG.host}:${WS_CONFIG.port}`;
        updateWSStatus('connecting', 'Connecting...');

        try {
            ws = new WebSocket(wsUrl); // assign to global ws

            ws.onopen = function(event) {
                console.log('WebSocket Connected');
                updateWSStatus('connected', 'Live');
                reconnectAttempts = 0;
                showToast('Connected', 'Real-time updates enabled', 'success');
                this.send(JSON.stringify({
                    type: 'subscribe',
                    channel: 'student_updates'
                }));
            };

            ws.onmessage = function(event) {
                const data = JSON.parse(event.data);
                handleWebSocketMessage(data);
            };

            ws.onclose = function(event) {
                console.log('WebSocket Disconnected');
                updateWSStatus('disconnected', 'Offline');
                // Reconnect logic
                if (reconnectAttempts < WS_CONFIG.maxReconnectAttempts) {
                    reconnectAttempts++;
                    updateWSStatus('connecting', `Reconnecting (${reconnectAttempts})...`);
                    reconnectTimer = setTimeout(initWebSocket, WS_CONFIG.reconnectInterval);
                } else {
                    showToast('Connection Lost', 'Real-time updates unavailable. Please refresh.', 'error');
                }
            };

            ws.onerror = function(error) {
                console.error('WebSocket Error:', error);
                updateWSStatus('disconnected', 'Error');
            };
        } catch (error) {
            console.error('WebSocket Init Error:', error);
            updateWSStatus('disconnected', 'Failed');
        }
    }

    // Update WebSocket status indicator
    function updateWSStatus(status, text) {
        const indicator = document.getElementById('wsStatus');
        indicator.className = `ws-status ${status}`;
        indicator.innerHTML = `<i class="fas fa-circle"></i><span>${text}</span>`;
    }

    // Handle incoming WebSocket messages
    function handleWebSocketMessage(data) {
        console.log('WebSocket Message:', data);

        switch (data.type) {
            case 'student_created':
                handleStudentCreated(data.student);
                break;
            case 'student_updated':
                handleStudentUpdated(data.student);
                break;
            case 'student_deleted':
                handleStudentDeleted(data.student_id);
                break;
            case 'stats_update':
                updateStats(data.stats);
                break;
            case 'notification':
                showToast(data.title, data.message, data.level || 'info');
                break;
            default:
                console.log('Unknown message type:', data.type);
        }
    }

    // Handle student created via WebSocket
    function handleStudentCreated(student) {
        // Check if student already exists (avoid duplicates if we created it)
        const existingCard = document.getElementById(`student-${student.student_id}`);
        if (existingCard) return;

        // Create new student card HTML
        const yearSuffix = ['st', 'nd', 'rd', 'th', 'th'];
        const yearDisplay = student.year_level + yearSuffix[student.year_level - 1] + ' Year';
        const initials = student.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

        const cardHTML = `
            <div class="student-card adding" id="student-${student.student_id}"
                data-year="${student.year_level}"
                data-name="${student.full_name.toLowerCase()}"
                data-id="${student.student_id.toLowerCase()}">
                <div class="student-header">
                    <div class="student-avatar">${initials}</div>
                    <div class="student-info">
                        <h4>${escapeHtml(student.full_name)}</h4>
                        <span class="student-id">${escapeHtml(student.student_id)}</span>
                    </div>
                </div>
                <div class="student-body">
                    <div class="student-meta">
                        <div class="meta-item">
                            <div class="meta-value">${yearDisplay}</div>
                            <div class="meta-label">Year Level</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-value">${escapeHtml(student.section)}</div>
                            <div class="meta-label">Section</div>
                        </div>
                    </div>
                    <div class="student-stats">
                        <div class="stat-row">
                            <span><i class="fas fa-clipboard-check me-2 text-primary"></i>Attendance Records</span>
                            <span class="attendance-count">0</span>
                        </div>
                        <div class="stat-row">
                            <span><i class="fas fa-file-invoice-dollar me-2 text-warning"></i>Total Fines</span>
                            <span class="fines-count">0</span>
                        </div>
                        <div class="stat-row">
                            <span><i class="fas fa-exclamation-circle me-2 text-danger"></i>Unpaid Amount</span>
                            <span class="unpaid-amount">₱0.00</span>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: 0%"></div>
                    </div>
                    <div class="student-actions">
                        <button class="btn-student btn-view" onclick="viewStudent('${escapeHtml(student.student_id)}')">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn-student btn-edit" onclick="editStudent('${escapeHtml(student.student_id)}')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-student btn-delete" onclick="deleteStudent('${escapeHtml(student.student_id)}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;

        const grid = document.getElementById('studentsGrid');

        // Remove empty state if exists
        const emptyState = document.getElementById('emptyState');
        if (emptyState) {
            emptyState.remove();
        }

        // Insert new card
        grid.insertAdjacentHTML('afterbegin', cardHTML);

        // Show notification
        showToast('New Student Added', `${student.full_name} was added by another officer`, 'info');

        // Update stats
        incrementStat('totalStudents');

        // Reapply filter
        applyFilter();
    }

    // Handle student updated via WebSocket
    function handleStudentUpdated(student) {
        const card = document.getElementById(`student-${student.student_id}`);
        if (!card) return;

        // Add updating animation
        card.classList.add('updating');
        setTimeout(() => card.classList.remove('updating'), 500);

        // Update card content
        const yearSuffix = ['st', 'nd', 'rd', 'th', 'th'];
        const yearDisplay = student.year_level + yearSuffix[student.year_level - 1] + ' Year';
        const initials = student.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();

        card.querySelector('.student-avatar').textContent = initials;
        card.querySelector('.student-info h4').textContent = student.full_name;
        card.querySelector('.student-id').textContent = student.student_id;
        card.querySelector('.meta-item:first-child .meta-value').textContent = yearDisplay;
        card.querySelector('.meta-item:last-child .meta-value').textContent = student.section;

        // Update data attributes
        card.setAttribute('data-name', student.full_name.toLowerCase());
        card.setAttribute('data-id', student.student_id.toLowerCase());
        card.setAttribute('data-year', student.year_level);

        showToast('Student Updated', `${student.full_name}'s information was updated`, 'info');

        // Reapply filter (in case year changed)
        applyFilter();
    }

    // Render student cards from JSON data
    function renderStudents(students) {
        const grid = document.getElementById('studentsGrid');
        const emptyStateHtml = `
        <div class="empty-state" id="emptyState">
            <div class="empty-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h3 class="empty-title">No Students Yet</h3>
            <p class="empty-text">Start by adding your first college student to the system.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal" onclick="resetForm()">
                <i class="fas fa-plus me-2"></i>Add First Student
            </button>
        </div>
    `;

        if (!students || students.length === 0) {
            grid.innerHTML = emptyStateHtml;
            // No cards to filter, but we still need to update active tab class
            updateActiveTabClass();
            return;
        }

        let html = '';
        const yearSuffix = ['st', 'nd', 'rd', 'th', 'th'];

        students.forEach(student => {
            const yearDisplay = student.year_level + yearSuffix[student.year_level - 1] + ' Year';
            const initials = student.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            const attendanceRate = <?php echo $total_attendance; ?> > 0 ?
                Math.round((student.attendance_count / <?php echo $total_attendance; ?>) * 100) :
                0;

            html += `
            <div class="student-card" id="student-${escapeHtml(student.student_id)}"
                data-year="${student.year_level}"
                data-name="${student.full_name.toLowerCase()}"
                data-id="${student.student_id.toLowerCase()}">
                <div class="student-header">
                    <div class="student-avatar">${initials}</div>
                    <div class="student-info">
                        <h4>${escapeHtml(student.full_name)}</h4>
                        <span class="student-id">${escapeHtml(student.student_id)}</span>
                    </div>
                </div>
                <div class="student-body">
                    <div class="student-meta">
                        <div class="meta-item">
                            <div class="meta-value">${yearDisplay}</div>
                            <div class="meta-label">Year Level</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-value">${escapeHtml(student.section)}</div>
                            <div class="meta-label">Section</div>
                        </div>
                    </div>
                    <div class="student-stats">
                        <div class="stat-row">
                            <span><i class="fas fa-clipboard-check me-2 text-primary"></i>Attendance Records</span>
                            <span class="attendance-count">${student.attendance_count || 0}</span>
                        </div>
                        <div class="stat-row">
                            <span><i class="fas fa-file-invoice-dollar me-2 text-warning"></i>Total Fines</span>
                            <span class="fines-count">${student.total_fines || 0}</span>
                        </div>
                        <div class="stat-row">
                            <span><i class="fas fa-exclamation-circle me-2 text-danger"></i>Unpaid Amount</span>
                            <span class="unpaid-amount ${student.unpaid_amount > 0 ? 'fines-warning' : ''}">
                                ₱${parseFloat(student.unpaid_amount || 0).toFixed(2)}
                            </span>
                        </div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: ${Math.min(attendanceRate, 100)}%"></div>
                    </div>
                    <div class="student-actions">
                        <button class="btn-student btn-view" onclick="viewStudent('${escapeHtml(student.student_id)}')">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn-student btn-edit" onclick="editStudent('${escapeHtml(student.student_id)}')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-student btn-delete" onclick="deleteStudent('${escapeHtml(student.student_id)}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        });

        grid.innerHTML = html;
        // Apply current filter after rendering
        applyFilter();
        // Update active tab class (in case tabs were recreated)
        updateActiveTabClass();
    }

    // Update active tab class based on currentFilterYear
    function updateActiveTabClass() {
        const tabs = document.querySelectorAll('.filter-tab');
        tabs.forEach(tab => {
            const year = tab.getAttribute('onclick') ? tab.getAttribute('onclick').match(/'([^']+)'/) : null;
            // The onclick attribute contains something like "filterByYear(event, 'all')"
            // We can parse the year from it, but simpler: just rely on the stored currentFilterYear
            // We'll add a data-year attribute to each tab for easy matching
        });

        // Add data-year attributes to tabs for easier matching (done in HTML but we can set here too)
        const tabButtons = document.querySelectorAll('.filter-tab');
        tabButtons.forEach(btn => {
            const onclick = btn.getAttribute('onclick');
            if (onclick) {
                const match = onclick.match(/'([^']+)'/);
                if (match) {
                    btn.setAttribute('data-year', match[1]);
                }
            }
        });

        // Now set active based on currentFilterYear
        tabButtons.forEach(btn => {
            if (btn.getAttribute('data-year') == currentFilterYear) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    // Apply current filter to student cards
    function applyFilter() {
        const cards = document.querySelectorAll('.student-card');
        cards.forEach(card => {
            if (currentFilterYear === 'all' || card.getAttribute('data-year') == currentFilterYear) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Fetch latest data and refresh UI
    async function refreshData() {
        try {
            const response = await fetch('?ajax_refresh=1');
            const data = await response.json();

            if (data.success) {
                renderStudents(data.students);
                updateStats(data.stats); // reuse existing updateStats function
            }
        } catch (error) {
            console.error('Auto-refresh failed:', error);
        }
    }

    // Start auto-refresh every 5 seconds when page loads
    document.addEventListener('DOMContentLoaded', function() {
        initWebSocket(); // keep WebSocket
        setInterval(refreshData, 5000); // add AJAX polling every 5 seconds

        // Initialize filter state: set active tab for 'all'
        currentFilterYear = 'all';
        updateActiveTabClass();
    });

    // Handle student deleted via WebSocket
    function handleStudentDeleted(studentId) {
        const card = document.getElementById(`student-${studentId}`);
        if (!card) return;

        card.classList.add('deleting');
        setTimeout(() => {
            card.remove();
            decrementStat('totalStudents');

            // Check if grid is empty
            const grid = document.getElementById('studentsGrid');
            if (grid.children.length === 0) {
                location.reload();
            } else {
                // Reapply filter after removal
                applyFilter();
            }
        }, 500);

        showToast('Student Deleted', 'A student record was removed', 'warning');
    }

    // Update statistics
    function updateStats(stats) {
        if (stats.total_students !== undefined) {
            animateValue('totalStudents', parseInt(document.getElementById('totalStudents').textContent), stats
                .total_students);
        }
        if (stats.total_sections !== undefined) {
            animateValue('totalSections', parseInt(document.getElementById('totalSections').textContent), stats
                .total_sections);
        }
        if (stats.total_unpaid !== undefined) {
            document.getElementById('totalUnpaid').textContent = '₱' + parseFloat(stats.total_unpaid).toFixed(2);
        }
        if (stats.total_attendance !== undefined) {
            animateValue('totalAttendance', parseInt(document.getElementById('totalAttendance').textContent), stats
                .total_attendance);
        }

        // Highlight updated stats
        ['statStudents', 'statSections', 'statFines', 'statAttendance'].forEach(id => {
            const card = document.getElementById(id);
            card.classList.add('updating');
            setTimeout(() => card.classList.remove('updating'), 1000);
        });
    }

    // Animate number changes
    function animateValue(id, start, end) {
        const obj = document.getElementById(id);
        const range = end - start;
        const duration = 500;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const current = Math.floor(start + (range * progress));
            obj.textContent = current;

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                obj.classList.add('changed');
                setTimeout(() => obj.classList.remove('changed'), 300);
            }
        }

        requestAnimationFrame(update);
    }

    // Increment/decrement helpers
    function incrementStat(id) {
        const el = document.getElementById(id);
        const current = parseInt(el.textContent) || 0;
        animateValue(id, current, current + 1);
    }

    function decrementStat(id) {
        const el = document.getElementById(id);
        const current = parseInt(el.textContent) || 0;
        animateValue(id, current, Math.max(0, current - 1));
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Toast Notification System
    function showToast(title, message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        const icon = type === 'success' ? 'check-circle' :
            type === 'error' ? 'times-circle' :
            type === 'warning' ? 'exclamation-triangle' : 'info-circle';

        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${icon}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

        container.appendChild(toast);

        // Auto remove after 4 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    // Save Student (Create or Update)
    async function saveStudent(event) {
        event.preventDefault();

        const form = document.getElementById('studentForm');
        const formData = new FormData(form);
        const saveBtn = document.getElementById('saveBtn');
        const originalBtnContent = saveBtn.innerHTML;

        // Show loading state
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner"></span>Saving...';

        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showToast('Success!', result.message, 'success');

                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('studentModal'));
                modal.hide();

                // If WebSocket is connected, the update will come through there
                // Otherwise, refresh the page
                if (!ws || ws.readyState !== WebSocket.OPEN) {
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                showToast('Error', result.message, 'error');
            }
        } catch (error) {
            showToast('Error', 'Something went wrong. Please try again.', 'error');
            console.error('Error:', error);
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnContent;
        }

        return false;
    }

    // Edit Student - Load data via AJAX
    async function editStudent(studentId) {
        try {
            const response = await fetch(`?get_student=${encodeURIComponent(studentId)}`);
            const result = await response.json();

            if (result.success) {
                const student = result.student;

                document.getElementById('existing_id').value = student.student_id;
                document.getElementById('student_id').value = student.student_id;
                document.getElementById('full_name').value = student.full_name;
                document.getElementById('section').value = student.section;

                // Set year level radio button
                const yearLevel = student.year_level || 1;
                const radio = document.querySelector(`input[name="year_level"][value="${yearLevel}"]`);
                if (radio) {
                    radio.checked = true;
                }

                // Clear password field and update hint
                document.getElementById('password').value = '';
                document.getElementById('passwordHint').textContent = 'Leave blank to keep current password';

                document.getElementById('modalTitle').innerHTML =
                    '<i class="fas fa-edit me-2"></i>Edit College Student';

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('studentModal'));
                modal.show();
            } else {
                showToast('Error', result.message || 'Student not found', 'error');
            }
        } catch (error) {
            showToast('Error', 'Failed to load student data', 'error');
            console.error('Error:', error);
        }
    }

    // Delete Student via AJAX
    async function deleteStudent(studentId) {
        if (!confirm(
                'Are you sure you want to delete this student? This will also remove all their attendance and fine records.'
            )) {
            return;
        }

        try {
            const response = await fetch(`?ajax_delete=${encodeURIComponent(studentId)}`);
            const result = await response.json();

            if (result.success) {
                showToast('Deleted!', result.message, 'success');

                // Animate and remove the card
                const card = document.getElementById(`student-${studentId}`);
                if (card) {
                    card.classList.add('deleting');
                    setTimeout(() => {
                        card.remove();

                        // Check if grid is empty
                        const grid = document.getElementById('studentsGrid');
                        if (grid.children.length === 0) {
                            location.reload();
                        } else {
                            // Reapply filter after removal
                            applyFilter();
                        }
                    }, 500);
                }

                // Update stats
                decrementStat('totalStudents');
            } else {
                showToast('Error', result.message, 'error');
            }
        } catch (error) {
            showToast('Error', 'Failed to delete student', 'error');
            console.error('Error:', error);
        }
    }

    // Reset form when creating new student
    function resetForm() {
        document.getElementById('studentForm').reset();
        document.getElementById('existing_id').value = '';
        document.getElementById('passwordHint').textContent = 'Leave blank for default: student123';
        document.getElementById('modalTitle').innerHTML =
            '<i class="fas fa-user-plus me-2"></i>Add New College Student';
        // Set default year level
        document.querySelector('input[name="year_level"][value="1"]').checked = true;
    }

    // Reset form when modal is closed
    document.getElementById('studentModal').addEventListener('hidden.bs.modal', function() {
        resetForm();
    });

    // Toggle password visibility
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.querySelector('.password-toggle');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // Filter students by search
    function filterStudents() {
        const searchTerm = document.getElementById('searchInput').value.toLowerCase();
        const cards = document.querySelectorAll('.student-card');

        cards.forEach(card => {
            const name = card.getAttribute('data-name');
            const id = card.getAttribute('data-id');

            if (name.includes(searchTerm) || id.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // Filter students by year level
    function filterByYear(event, year) {
        currentFilterYear = year;
        // Update active tab using the clicked button (event.currentTarget)
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        event.currentTarget.classList.add('active');

        // Filter cards
        applyFilter();
    }

    // View student details in modal
    async function viewStudent(studentId) {
        try {
            const response = await fetch(`?get_student=${encodeURIComponent(studentId)}`);
            const result = await response.json();

            if (result.success) {
                const student = result.student;

                // Set avatar initials
                const initials = student.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                document.getElementById('viewAvatar').textContent = initials;

                // Populate fields
                document.getElementById('viewFullName').textContent = student.full_name;
                document.getElementById('viewStudentId').textContent = student.student_id;

                const yearSuffix = ['st', 'nd', 'rd', 'th', 'th'];
                const yearDisplay = student.year_level + yearSuffix[student.year_level - 1] + ' Year';
                document.getElementById('viewYearLevel').textContent = yearDisplay;
                document.getElementById('viewSection').textContent = student.section;
                document.getElementById('viewAttendanceCount').textContent = student.attendance_count || 0;
                document.getElementById('viewTotalFines').textContent = student.total_fines || 0;
                document.getElementById('viewUnpaidAmount').textContent = '₱' + parseFloat(student.unpaid_amount ||
                    0).toFixed(2);

                // Format created_at if available
                if (student.created_at) {
                    const date = new Date(student.created_at);
                    document.getElementById('viewCreatedAt').textContent = date.toLocaleString();
                } else {
                    document.getElementById('viewCreatedAt').textContent = 'N/A';
                }

                // Store student ID for edit button
                document.getElementById('viewEditBtn').setAttribute('data-student-id', student.student_id);

                // Show modal
                const viewModal = new bootstrap.Modal(document.getElementById('viewStudentModal'));
                viewModal.show();
            } else {
                showToast('Error', result.message || 'Student not found', 'error');
            }
        } catch (error) {
            showToast('Error', 'Failed to load student details', 'error');
            console.error('Error:', error);
        }
    }

    // Edit from view modal
    function editStudentFromView() {
        const studentId = document.getElementById('viewEditBtn').getAttribute('data-student-id');
        if (studentId) {
            // Hide view modal
            const viewModalEl = document.getElementById('viewStudentModal');
            const viewModal = bootstrap.Modal.getInstance(viewModalEl);
            if (viewModal) viewModal.hide();

            // Open edit modal
            editStudent(studentId);
        }
    }

    // Initialize WebSocket on page load
    document.addEventListener('DOMContentLoaded', function() {
        initWebSocket();

        // Setup periodic sync check (fallback if WebSocket fails)
        setInterval(async () => {
            if (!ws || ws.readyState !== WebSocket.OPEN) {
                // Silently check for updates via AJAX if WebSocket is down
                checkForUpdates();
            }
        }, 30000); // Check every 30 seconds
    });

    // Fallback: Check for updates via AJAX polling
    async function checkForUpdates() {
        try {
            const response = await fetch('?check_updates=1&last_check=' + (window.lastCheck || 0));
            const data = await response.json();

            if (data.updates && data.updates.length > 0) {
                document.getElementById('syncNotification').classList.add('show');

                data.updates.forEach(update => {
                    handleWebSocketMessage(update);
                });

                setTimeout(() => {
                    document.getElementById('syncNotification').classList.remove('show');
                }, 2000);
            }

            window.lastCheck = Math.floor(Date.now() / 1000);
        } catch (error) {
            console.log('Sync check failed:', error);
        }
    }

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
        }
        if (ws) {
            ws.close();
        }
    });
    </script>
</body>

</html>
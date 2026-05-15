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

// ========== AUDIT LOG FUNCTION ==========
function logAudit($conn, $officer_id, $action, $table_name, $record_id = null, $old_data = null, $new_data = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    if (is_array($old_data) || is_object($old_data)) {
        $old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);
    }
    if (is_array($new_data) || is_object($new_data)) {
        $new_data = json_encode($new_data, JSON_UNESCAPED_UNICODE);
    }
    
    // Check for null before using strlen
    if ($old_data !== null && strlen($old_data) > 60000) {
        $old_data = substr($old_data, 0, 60000) . '...[TRUNCATED]';
    }
    if ($new_data !== null && strlen($new_data) > 60000) {
        $new_data = substr($new_data, 0, 60000) . '...[TRUNCATED]';
    }
    
    $query = "INSERT INTO audit_logs (officer_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssss", 
            $officer_id, 
            $action, 
            $table_name, 
            $record_id, 
            $old_data, 
            $new_data, 
            $ip_address, 
            $user_agent
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            error_log("Audit log failed: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
        return true;
    } else {
        error_log("Failed to prepare audit log statement: " . mysqli_error($conn));
        return false;
    }
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
}
runFinesIfNeeded();

// Handle AJAX Delete Action
if (isset($_GET['ajax_delete']) && !empty($_GET['ajax_delete'])) {
    header('Content-Type: application/json');
    $student_id = $conn->real_escape_string($_GET['ajax_delete']);

    // Get student data before deletion for audit
    $old_stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $old_stmt->bind_param("s", $student_id);
    $old_stmt->execute();
    $deleted_student = $old_stmt->get_result()->fetch_assoc();
    $old_stmt->close();

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
        // AUDIT: Log student deletion
        logAudit($conn, $_SESSION['officer_id'], 'DELETE', 'students', $student_id, 
            json_encode($deleted_student), 
            json_encode(['action' => 'deleted', 'deleted_by' => $_SESSION['officer_id'], 'deleted_at' => date('Y-m-d H:i:s')]));
        
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

// Handle AJAX Refresh with Pagination support
if (isset($_GET['ajax_refresh']) && $_GET['ajax_refresh'] == '1') {
    header('Content-Type: application/json');
    
    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 12;
    $offset = ($page - 1) * $per_page;
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $year_filter = isset($_GET['year']) ? intval($_GET['year']) : 0;
    
    // Build WHERE clause for search and filter
    $where_clauses = [];
    if (!empty($search)) {
        $where_clauses[] = "(s.full_name LIKE '%$search%' OR s.student_id LIKE '%$search%')";
    }
    if ($year_filter > 0 && $year_filter <= 4) {
        $where_clauses[] = "s.year_level = $year_filter";
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(DISTINCT s.student_id) as total FROM students s $where_sql";
    $count_result = $conn->query($count_query);
    $total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
    $total_pages = ceil($total_records / $per_page);
    
    // Fetch students with pagination
    $query = "
        SELECT s.*, 
               COUNT(DISTINCT a.attendance_id) as attendance_count,
               COUNT(DISTINCT f.fine_id) as total_fines,
               SUM(CASE WHEN f.status = 'unpaid' THEN f.amount ELSE 0 END) as unpaid_amount
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id
        LEFT JOIN student_fines f ON s.student_id = f.student_id
        $where_sql
        GROUP BY s.student_id
        ORDER BY s.year_level ASC, s.section ASC, s.full_name ASC
        LIMIT $offset, $per_page
    ";
    
    $students_result = $conn->query($query);
    $students = [];
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
    
    // Get statistics (unfiltered)
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
        ],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
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

    // Validate year level (1-4 for college)
    if ($year_level < 1 || $year_level > 4) {
        $year_level = 1;
    }

    if (!empty($student_id) && isset($_POST['existing_id']) && !empty($_POST['existing_id'])) {
        // Update existing student
        $existing_id = $conn->real_escape_string($_POST['existing_id']);
        
        // Get old student data before update for audit
        $old_stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
        $old_stmt->bind_param("s", $existing_id);
        $old_stmt->execute();
        $old_student = $old_stmt->get_result()->fetch_assoc();
        $old_stmt->close();

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
            // Get new student data for audit
            $new_stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
            $new_stmt->bind_param("s", $student_id);
            $new_stmt->execute();
            $new_student = $new_stmt->get_result()->fetch_assoc();
            $new_stmt->close();
            
            // Build changes for audit
            $changes = [];
            if ($old_student['full_name'] != $full_name) {
                $changes['full_name'] = ['old' => $old_student['full_name'], 'new' => $full_name];
            }
            if ($old_student['student_id'] != $student_id) {
                $changes['student_id'] = ['old' => $old_student['student_id'], 'new' => $student_id];
            }
            if ($old_student['year_level'] != $year_level) {
                $changes['year_level'] = ['old' => $old_student['year_level'], 'new' => $year_level];
            }
            if ($old_student['section'] != $section) {
                $changes['section'] = ['old' => $old_student['section'], 'new' => $section];
            }
            if (!empty($password)) {
                $changes['password'] = ['old' => '[HIDDEN]', 'new' => '[CHANGED]'];
            }
            
            // AUDIT: Log student update
            logAudit($conn, $_SESSION['officer_id'], 'UPDATE', 'students', $student_id, 
                json_encode(['old' => $old_student, 'changes' => $changes]), 
                json_encode($new_student));
            
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
            // Get created student data for audit
            $new_stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
            $new_stmt->bind_param("s", $student_id);
            $new_stmt->execute();
            $new_student = $new_stmt->get_result()->fetch_assoc();
            $new_stmt->close();
            
            // AUDIT: Log student creation
            logAudit($conn, $_SESSION['officer_id'], 'CREATE', 'students', $student_id, null, json_encode($new_student));
            
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
    
    // AUDIT: Log view student action
    logAudit($conn, $_SESSION['officer_id'], 'VIEW', 'students', $student_id, null, 
        json_encode(['action' => 'view_student_details', 'timestamp' => date('Y-m-d H:i:s')]));
    
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

// Function to broadcast WebSocket messages
function broadcastWebSocket($data) {
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

// --- Log page access ---
logAudit($conn, $_SESSION['officer_id'], 'VIEW', 'manage_students_page', null, null, 
    json_encode(['action' => 'page_access', 'timestamp' => date('Y-m-d H:i:s'), 'page' => 'Manage Students']));

// --- PAGINATION SETUP FOR INITIAL PAGE LOAD ---
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 12;
$per_page_options = [12, 24, 48, 96];
if (!in_array($per_page, $per_page_options)) {
    $per_page = 12;
}
$offset = ($current_page - 1) * $per_page;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : 0;

// Build WHERE clause for initial load
$where_clauses = [];
if (!empty($search_query)) {
    $search_escaped = $conn->real_escape_string($search_query);
    $where_clauses[] = "(s.full_name LIKE '%$search_escaped%' OR s.student_id LIKE '%$search_escaped%')";
}
if ($year_filter > 0 && $year_filter <= 4) {
    $where_clauses[] = "s.year_level = $year_filter";
}
$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT s.student_id) as total FROM students s $where_sql";
$count_result = $conn->query($count_query);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $per_page);

// Fetch students with pagination for initial load
$students = $conn->query("
    SELECT s.*, 
           COUNT(DISTINCT a.attendance_id) as attendance_count,
           COUNT(DISTINCT f.fine_id) as total_fines,
           SUM(CASE WHEN f.status = 'unpaid' THEN f.amount ELSE 0 END) as unpaid_amount
    FROM students s
    LEFT JOIN attendance a ON s.student_id = a.student_id
    LEFT JOIN student_fines f ON s.student_id = f.student_id
    $where_sql
    GROUP BY s.student_id
    ORDER BY s.year_level ASC, s.section ASC, s.full_name ASC
    LIMIT $offset, $per_page
");

// Get statistics (unfiltered for stats cards)
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_sections = $conn->query("SELECT COUNT(DISTINCT section) as count FROM students")->fetch_assoc()['count'];
$total_unpaid = $conn->query("SELECT SUM(amount) as total FROM student_fines WHERE status = 'unpaid'")->fetch_assoc()['total'] ?: 0;
$total_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'];

// Helper function to build pagination URL
function buildPaginationUrl($params = []) {
    $current_params = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($current_params[$key]);
        } else {
            $current_params[$key] = $value;
        }
    }
    return '?' . http_build_query($current_params);
}

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

    body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
    .main-contents {
        margin-left: 190px;
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
    
    /* Search loading indicator */
    .search-box .search-spinner {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        border: 2px solid #e2e8f0;
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
        display: none;
    }
    
    .search-box.searching .search-spinner {
        display: block;
    }
    
    .search-box.searching i {
        opacity: 0.5;
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
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.02); box-shadow: 0 0 20px rgba(99, 102, 241, 0.3); }
    }

    @keyframes fadeOut {
        to { opacity: 0; transform: scale(0.9); }
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
        to { transform: rotate(360deg); }
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
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    /* Live indicator */
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
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }

    .live-indicator::before {
        content: '';
        width: 6px;
        height: 6px;
        background: var(--danger);
        border-radius: 50%;
    }

    /* Pagination Styles */
    .pagination-container {
        padding: 1rem 1.5rem;
        border-top: 1px solid #e2e8f0;
        background: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .pagination-controls {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .pagination-btn {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: white;
        color: var(--dark);
        font-weight: 500;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .pagination-btn:hover:not(:disabled) {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination-btn.active-page {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .page-numbers {
        display: flex;
        gap: 0.25rem;
        flex-wrap: wrap;
    }

    .page-number {
        min-width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: white;
        color: var(--dark);
        font-weight: 500;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }

    .page-number:hover:not(.active-page) {
        background: #f1f5f9;
        border-color: var(--primary);
    }

    .page-number.active-page {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
        cursor: default;
    }

    .page-ellipsis {
        min-width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #94a3b8;
    }

    .per-page-selector {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .per-page-selector label {
        font-size: 0.875rem;
        color: #64748b;
    }

    .per-page-select {
        padding: 0.5rem;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: white;
        font-size: 0.875rem;
        cursor: pointer;
    }

    .pagination-info {
        font-size: 0.875rem;
        color: #64748b;
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
        .pagination-container {
            flex-direction: column;
            align-items: stretch;
            text-align: center;
        }
        .pagination-controls {
            justify-content: center;
        }
        .per-page-selector {
            justify-content: center;
        }
        .pagination-info {
            text-align: center;
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

    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }

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
    <!-- The sidebar is already included above via require -->
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

        <!-- Toast Container -->
        <div class="toast-container" id="toastContainer"></div>

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="officer_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">College Student Manager</li>
                    </ol>
                </nav>
                <h1 class="page-title">
                    <i class="fas fa-user-graduate me-3"></i>College Student Manager
                    <span class="live-indicator">LIVE</span>
                </h1>
                <p class="page-subtitle">Manage college student records, track attendance, and monitor fines in real-time</p>
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
                    <div class="search-box" id="searchBox">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search students..." 
                               value="<?php echo htmlspecialchars($search_query); ?>">
                        <div class="search-spinner"></div>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal" onclick="resetForm()">
                        <i class="fas fa-plus me-2"></i>Add Student
                    </button>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab <?php echo $year_filter == 0 ? 'active' : ''; ?>" data-year="0">All Students</button>
                <button class="filter-tab <?php echo $year_filter == 1 ? 'active' : ''; ?>" data-year="1">1st Year</button>
                <button class="filter-tab <?php echo $year_filter == 2 ? 'active' : ''; ?>" data-year="2">2nd Year</button>
                <button class="filter-tab <?php echo $year_filter == 3 ? 'active' : ''; ?>" data-year="3">3rd Year</button>
                <button class="filter-tab <?php echo $year_filter == 4 ? 'active' : ''; ?>" data-year="4">4th Year</button>
            </div>

            <div class="students-grid" id="studentsGrid">
                <?php if ($students && $students->num_rows > 0): ?>
                <?php while($student = $students->fetch_assoc()): 
                            $initials = implode('', array_map(function($word) { 
                                return strtoupper(substr($word, 0, 1)); 
                            }, explode(' ', $student['full_name'])));
                            $initials = substr($initials, 0, 2);

                            $attendance_rate = $total_attendance > 0 ? 
                                round(($student['attendance_count'] / max($total_attendance, 1)) * 100) : 0;

                            $year_suffix = ['st', 'nd', 'rd', 'th'];
                            $year_display = $student['year_level'] . ($year_suffix[$student['year_level'] - 1] ?? 'th') . ' Year';
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
                                <span><i class="fas fa-clipboard-check me-2 text-primary"></i>Attendance Records</span>
                                <span class="attendance-count"><?php echo $student['attendance_count']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span><i class="fas fa-file-invoice-dollar me-2 text-warning"></i>Total Fines</span>
                                <span class="fines-count"><?php echo $student['total_fines']; ?></span>
                            </div>
                            <div class="stat-row">
                                <span><i class="fas fa-exclamation-circle me-2 text-danger"></i>Unpaid Amount</span>
                                <span class="unpaid-amount <?php echo ($student['unpaid_amount'] ?? 0) > 0 ? 'fines-warning' : ''; ?>">
                                    ₱<?php echo number_format($student['unpaid_amount'] ?? 0, 2); ?>
                                </span>
                            </div>
                        </div>

                        <div class="progress">
                            <div class="progress-bar" style="width: <?php echo min($attendance_rate, 100); ?>%">
                            </div>
                        </div>

                        <div class="student-actions">
                            <button class="btn-student btn-view" onclick="viewStudent('<?php echo htmlspecialchars($student['student_id']); ?>')">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn-student btn-edit" onclick="editStudent('<?php echo htmlspecialchars($student['student_id']); ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-student btn-delete" onclick="deleteStudent('<?php echo htmlspecialchars($student['student_id']); ?>')">
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
                    <h3 class="empty-title">No Students Found</h3>
                    <p class="empty-text"><?php echo !empty($search_query) ? 'No students match your search criteria.' : 'Start by adding your first college student to the system.'; ?></p>
                    <?php if (!empty($search_query) || $year_filter > 0): ?>
                    <button class="btn btn-primary" onclick="clearFilters()">
                        <i class="fas fa-undo me-2"></i>Clear Filters
                    </button>
                    <?php else: ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal" onclick="resetForm()">
                        <i class="fas fa-plus me-2"></i>Add First Student
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> students
                </div>
                <div class="pagination-controls">
                    <a href="<?php echo buildPaginationUrl(['page' => $current_page - 1]); ?>" 
                       class="pagination-btn <?php echo $current_page <= 1 ? 'disabled' : ''; ?>"
                       <?php echo $current_page <= 1 ? 'aria-disabled="true"' : ''; ?>>
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <div class="page-numbers">
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="<?php echo buildPaginationUrl(['page' => 1]); ?>" class="page-number">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="page-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="<?php echo buildPaginationUrl(['page' => $i]); ?>" 
                               class="page-number <?php echo $i == $current_page ? 'active-page' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="page-ellipsis">...</span>
                            <?php endif; ?>
                            <a href="<?php echo buildPaginationUrl(['page' => $total_pages]); ?>" class="page-number"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo buildPaginationUrl(['page' => $current_page + 1]); ?>" 
                       class="pagination-btn <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>"
                       <?php echo $current_page >= $total_pages ? 'aria-disabled="true"' : ''; ?>>
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
                <div class="per-page-selector">
                    <label>Show:</label>
                    <select class="per-page-select" id="perPageSelect" onchange="changePerPage(this.value)">
                        <option value="12" <?php echo $per_page == 12 ? 'selected' : ''; ?>>12 per page</option>
                        <option value="24" <?php echo $per_page == 24 ? 'selected' : ''; ?>>24 per page</option>
                        <option value="48" <?php echo $per_page == 48 ? 'selected' : ''; ?>>48 per page</option>
                        <option value="96" <?php echo $per_page == 96 ? 'selected' : ''; ?>>96 per page</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>
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
                                placeholder="e.g., 11-1111-111">
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
                            <label class="form-label">Password <small class="text-muted" id="passwordHint">(Leave blank for default: student123)</small></label>
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

    <!-- View Student Modal -->
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
                        <div class="student-avatar mx-auto" style="width: 80px; height: 80px; font-size: 2rem;" id="viewAvatar">JD</div>
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
                    <button type="button" class="btn btn-primary" onclick="editStudentFromView()" id="viewEditBtn">Edit</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Pagination state
    let currentPage = <?php echo $current_page; ?>;
    let currentPerPage = <?php echo $per_page; ?>;
    let currentSearch = '<?php echo addslashes($search_query); ?>';
    let currentYear = <?php echo $year_filter; ?>;
    let isRefreshing = false;
    let searchDebounceTimer = null; // For debouncing instant search

    // WebSocket Configuration
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

    // Initialize WebSocket connection
    function initWebSocket() {
        if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) {
            return;
        }

        const wsUrl = `${WS_CONFIG.protocol}//${WS_CONFIG.host}:${WS_CONFIG.port}`;
        updateWSStatus('connecting', 'Connecting...');

        try {
            ws = new WebSocket(wsUrl);

            ws.onopen = function() {
                updateWSStatus('connected', 'Live');
                reconnectAttempts = 0;
                showToast('Connected', 'Real-time updates enabled', 'success');
                ws.send(JSON.stringify({ type: 'subscribe', channel: 'student_updates' }));
            };

            ws.onmessage = function(event) {
                const data = JSON.parse(event.data);
                handleWebSocketMessage(data);
            };

            ws.onclose = function() {
                updateWSStatus('disconnected', 'Offline');
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

    function updateWSStatus(status, text) {
        const indicator = document.getElementById('wsStatus');
        indicator.className = `ws-status ${status}`;
        indicator.innerHTML = `<i class="fas fa-circle"></i><span>${text}</span>`;
    }

    function handleWebSocketMessage(data) {
        console.log('WebSocket Message:', data);
        switch (data.type) {
            case 'student_created':
            case 'student_updated':
            case 'student_deleted':
                refreshData();
                break;
            case 'stats_update':
                updateStats(data.stats);
                break;
            case 'notification':
                showToast(data.title, data.message, data.level || 'info');
                break;
        }
    }

    // Refresh data with current pagination/filter state
    async function refreshData() {
        if (isRefreshing) return;
        isRefreshing = true;
        
        const syncNotification = document.getElementById('syncNotification');
        syncNotification.classList.add('show');
        
        try {
            const url = `?ajax_refresh=1&page=${currentPage}&per_page=${currentPerPage}&search=${encodeURIComponent(currentSearch)}&year=${currentYear}`;
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                renderStudents(data.students);
                updateStats(data.stats);
                if (data.pagination) {
                    updatePaginationUI(data.pagination);
                    // Update URL without reload
                    const newUrl = `?page=${data.pagination.current_page}&per_page=${data.pagination.per_page}&search=${encodeURIComponent(currentSearch)}&year=${currentYear}`;
                    window.history.pushState({}, '', newUrl);
                }
            }
        } catch (error) {
            console.error('Refresh failed:', error);
        } finally {
            // Remove searching indicator
            const searchBox = document.getElementById('searchBox');
            if (searchBox) searchBox.classList.remove('searching');
            
            setTimeout(() => {
                syncNotification.classList.remove('show');
                isRefreshing = false;
            }, 500);
        }
    }

    function renderStudents(students) {
        const grid = document.getElementById('studentsGrid');
        
        if (!students || students.length === 0) {
            grid.innerHTML = `
                <div class="empty-state" id="emptyState">
                    <div class="empty-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3 class="empty-title">No Students Found</h3>
                    <p class="empty-text">${currentSearch ? 'No students match your search criteria.' : 'Start by adding your first college student to the system.'}</p>
                    ${currentSearch || currentYear ? 
                        '<button class="btn btn-primary" onclick="clearFilters()"><i class="fas fa-undo me-2"></i>Clear Filters</button>' : 
                        '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#studentModal" onclick="resetForm()"><i class="fas fa-plus me-2"></i>Add First Student</button>'
                    }
                </div>
            `;
            return;
        }

        let html = '';
        const yearSuffix = ['st', 'nd', 'rd', 'th'];
        const totalAttendance = parseInt(document.getElementById('totalAttendance')?.textContent) || 0;
        
        students.forEach(student => {
            const yearDisplay = student.year_level + (yearSuffix[student.year_level - 1] || 'th') + ' Year';
            const initials = student.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
            const attendanceRate = totalAttendance > 0 ? Math.round((student.attendance_count / Math.max(totalAttendance, 1)) * 100) : 0;
            
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
                                <span class="unpaid-amount ${(student.unpaid_amount || 0) > 0 ? 'fines-warning' : ''}">
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
    }

    function updatePaginationUI(pagination) {
        const paginationContainer = document.querySelector('.pagination-container');
        if (!pagination || pagination.total_pages <= 1) {
            if (paginationContainer) paginationContainer.style.display = 'none';
            return;
        }
        
        if (paginationContainer) paginationContainer.style.display = 'flex';
        
        const infoEl = paginationContainer?.querySelector('.pagination-info');
        if (infoEl) {
            const start = (pagination.current_page - 1) * pagination.per_page + 1;
            const end = Math.min(pagination.current_page * pagination.per_page, pagination.total_records);
            infoEl.textContent = `Showing ${start} to ${end} of ${pagination.total_records} students`;
        }
        
        const prevBtn = paginationContainer?.querySelector('.pagination-btn:first-child');
        if (prevBtn) {
            if (!pagination.has_prev) {
                prevBtn.classList.add('disabled');
                prevBtn.removeAttribute('href');
                prevBtn.setAttribute('aria-disabled', 'true');
            } else {
                prevBtn.classList.remove('disabled');
                prevBtn.setAttribute('href', `?page=${pagination.current_page - 1}&per_page=${pagination.per_page}&search=${encodeURIComponent(currentSearch)}&year=${currentYear}`);
                prevBtn.removeAttribute('aria-disabled');
            }
        }
        
        const nextBtn = paginationContainer?.querySelector('.pagination-btn:last-child');
        if (nextBtn) {
            if (!pagination.has_next) {
                nextBtn.classList.add('disabled');
                nextBtn.removeAttribute('href');
                nextBtn.setAttribute('aria-disabled', 'true');
            } else {
                nextBtn.classList.remove('disabled');
                nextBtn.setAttribute('href', `?page=${pagination.current_page + 1}&per_page=${pagination.per_page}&search=${encodeURIComponent(currentSearch)}&year=${currentYear}`);
                nextBtn.removeAttribute('aria-disabled');
            }
        }
        
        const pageNumbers = paginationContainer?.querySelector('.page-numbers');
        if (pageNumbers) {
            let pagesHtml = '';
            const startPage = Math.max(1, pagination.current_page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
            
            if (startPage > 1) {
                pagesHtml += `<a href="?page=1&per_page=${pagination.per_page}&search=${encodeURIComponent(currentSearch)}&year=${currentYear}" class="page-number">1</a>`;
                if (startPage > 2) pagesHtml += `<span class="page-ellipsis">...</span>`;
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === pagination.current_page ? 'active-page' : '';
                pagesHtml += `<a href="?page=${i}&per_page=${pagination.per_page}&search=${encodeURIComponent(currentSearch)}&year=${currentYear}" class="page-number ${activeClass}">${i}</a>`;
            }
            
            if (endPage < pagination.total_pages) {
                if (endPage < pagination.total_pages - 1) pagesHtml += `<span class="page-ellipsis">...</span>`;
                pagesHtml += `<a href="?page=${pagination.total_pages}&per_page=${pagination.per_page}&search=${encodeURIComponent(currentSearch)}&year=${currentYear}" class="page-number">${pagination.total_pages}</a>`;
            }
            
            pageNumbers.innerHTML = pagesHtml;
        }
    }

    function updateStats(stats) {
        if (stats.total_students !== undefined) {
            animateValue('totalStudents', parseInt(document.getElementById('totalStudents').textContent), stats.total_students);
        }
        if (stats.total_sections !== undefined) {
            animateValue('totalSections', parseInt(document.getElementById('totalSections').textContent), stats.total_sections);
        }
        if (stats.total_unpaid !== undefined) {
            document.getElementById('totalUnpaid').textContent = '₱' + parseFloat(stats.total_unpaid).toFixed(2);
        }
        if (stats.total_attendance !== undefined) {
            animateValue('totalAttendance', parseInt(document.getElementById('totalAttendance').textContent), stats.total_attendance);
        }
        
        ['statStudents', 'statSections', 'statFines', 'statAttendance'].forEach(id => {
            const card = document.getElementById(id);
            if (card) {
                card.classList.add('updating');
                setTimeout(() => card.classList.remove('updating'), 1000);
            }
        });
    }

    function animateValue(id, start, end) {
        const obj = document.getElementById(id);
        if (!obj) return;
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

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(title, message, type = 'success') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const iconMap = { success: 'check-circle', error: 'times-circle', warning: 'exclamation-triangle', info: 'info-circle' };
        const icon = iconMap[type] || 'info-circle';
        
        toast.innerHTML = `
            <div class="toast-icon"><i class="fas fa-${icon}"></i></div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        `;
        
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    async function saveStudent(event) {
        event.preventDefault();
        
        const form = document.getElementById('studentForm');
        const formData = new FormData(form);
        const saveBtn = document.getElementById('saveBtn');
        const originalContent = saveBtn.innerHTML;
        
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner"></span>Saving...';
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                showToast('Success!', result.message, 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('studentModal'));
                modal.hide();
                refreshData();
                resetForm();
            } else {
                showToast('Error', result.message, 'error');
            }
        } catch (error) {
            showToast('Error', 'Something went wrong. Please try again.', 'error');
            console.error('Error:', error);
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalContent;
        }
        return false;
    }

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
                
                const radio = document.querySelector(`input[name="year_level"][value="${student.year_level || 1}"]`);
                if (radio) radio.checked = true;
                
                document.getElementById('password').value = '';
                document.getElementById('passwordHint').textContent = 'Leave blank to keep current password';
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit College Student';
                
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

    async function deleteStudent(studentId) {
        if (!confirm('Are you sure you want to delete this student? This will also remove all their attendance and fine records.')) return;
        
        try {
            const response = await fetch(`?ajax_delete=${encodeURIComponent(studentId)}`);
            const result = await response.json();
            
            if (result.success) {
                showToast('Deleted!', result.message, 'success');
                refreshData();
            } else {
                showToast('Error', result.message, 'error');
            }
        } catch (error) {
            showToast('Error', 'Failed to delete student', 'error');
            console.error('Error:', error);
        }
    }

    async function viewStudent(studentId) {
        try {
            const response = await fetch(`?get_student=${encodeURIComponent(studentId)}`);
            const result = await response.json();
            
            if (result.success) {
                const student = result.student;
                const initials = student.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                document.getElementById('viewAvatar').textContent = initials;
                document.getElementById('viewFullName').textContent = student.full_name;
                document.getElementById('viewStudentId').textContent = student.student_id;
                
                const yearSuffix = ['st', 'nd', 'rd', 'th'];
                const yearDisplay = student.year_level + (yearSuffix[student.year_level - 1] || 'th') + ' Year';
                document.getElementById('viewYearLevel').textContent = yearDisplay;
                document.getElementById('viewSection').textContent = student.section;
                document.getElementById('viewAttendanceCount').textContent = student.attendance_count || 0;
                document.getElementById('viewTotalFines').textContent = student.total_fines || 0;
                document.getElementById('viewUnpaidAmount').textContent = '₱' + parseFloat(student.unpaid_amount || 0).toFixed(2);
                
                if (student.created_at) {
                    document.getElementById('viewCreatedAt').textContent = new Date(student.created_at).toLocaleString();
                } else {
                    document.getElementById('viewCreatedAt').textContent = 'N/A';
                }
                
                document.getElementById('viewEditBtn').setAttribute('data-student-id', student.student_id);
                
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

    function editStudentFromView() {
        const studentId = document.getElementById('viewEditBtn').getAttribute('data-student-id');
        if (studentId) {
            const viewModalEl = document.getElementById('viewStudentModal');
            const viewModal = bootstrap.Modal.getInstance(viewModalEl);
            if (viewModal) viewModal.hide();
            editStudent(studentId);
        }
    }

    function resetForm() {
        document.getElementById('studentForm').reset();
        document.getElementById('existing_id').value = '';
        document.getElementById('passwordHint').textContent = 'Leave blank for default: student123';
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Add New College Student';
        const defaultRadio = document.querySelector('input[name="year_level"][value="1"]');
        if (defaultRadio) defaultRadio.checked = true;
    }

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

    // === INSTANT SEARCH IMPLEMENTATION ===
    function setupInstantSearch() {
        const searchInput = document.getElementById('searchInput');
        const searchBox = document.getElementById('searchBox');
        
        if (!searchInput) return;
        
        searchInput.removeAttribute('onkeypress');
        
        searchInput.addEventListener('input', function() {
            searchBox.classList.add('searching');
            
            if (searchDebounceTimer) {
                clearTimeout(searchDebounceTimer);
            }
            
            const newSearchValue = this.value;
            
            searchDebounceTimer = setTimeout(function() {
                currentSearch = newSearchValue;
                currentPage = 1;
                refreshData();
            }, 300);
        });
    }
    
    function filterByYear(year) {
        currentYear = year;
        currentPage = 1;
        
        document.querySelectorAll('.filter-tab').forEach(tab => {
            const tabYear = parseInt(tab.getAttribute('data-year'));
            if (tabYear === year) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        refreshData();
    }

    function changePerPage(perPage) {
        currentPerPage = parseInt(perPage);
        currentPage = 1;
        refreshData();
    }

    function clearFilters() {
        currentSearch = '';
        currentYear = 0;
        currentPage = 1;
        
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = '';
        
        document.querySelectorAll('.filter-tab').forEach(tab => {
            const tabYear = parseInt(tab.getAttribute('data-year'));
            if (tabYear === 0) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });
        
        refreshData();
    }

    document.addEventListener('DOMContentLoaded', function() {
        initWebSocket();
        setupInstantSearch();
        
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const year = parseInt(this.getAttribute('data-year'));
                filterByYear(year);
            });
        });
        
        const perPageSelect = document.getElementById('perPageSelect');
        if (perPageSelect) {
            perPageSelect.addEventListener('change', function() {
                changePerPage(this.value);
            });
        }
        
        setInterval(refreshData, 10000);
    });
    
    window.addEventListener('beforeunload', function() {
        if (reconnectTimer) clearTimeout(reconnectTimer);
        if (ws) ws.close();
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    });
    </script>
</body>

</html>
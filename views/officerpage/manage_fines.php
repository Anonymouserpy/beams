<?php
session_start();
require "../../Connection/connection.php";

// Configuration
$config = [
    'app_name' => 'Student Fines Management',
    'currency' => '₱',
    'date_format' => 'Y-m-d',
    'datetime_format' => 'Y-m-d H:i',
];

if (!isset($_SESSION['officer_id'])) {
    header("Location: ../../officer_Login.php");
    exit();
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ========== AUDIT LOG FUNCTION ==========
function logAudit($conn, $officer_id, $action, $table_name, $record_id = null, $old_data = null, $new_data = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Convert arrays/objects to JSON if needed
    if (is_array($old_data) || is_object($old_data)) {
        $old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);
    }
    if (is_array($new_data) || is_object($new_data)) {
        $new_data = json_encode($new_data, JSON_UNESCAPED_UNICODE);
    }
    
    // Truncate if too long
    if (strlen($old_data) > 60000) {
        $old_data = substr($old_data, 0, 60000) . '...[TRUNCATED]';
    }
    if (strlen($new_data) > 60000) {
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

// Helper function to return JSON responses
function jsonResponse($status, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit();
}

// Safe logging function – only inserts if the table exists
function logWebsocketMessage($conn, $action, $details) {
    static $tableExists = null;
    if ($tableExists === null) {
        $result = $conn->query("SHOW TABLES LIKE 'websocket_messages'");
        $tableExists = $result && $result->num_rows > 0;
    }
    if (!$tableExists) return;

    $stmt = $conn->prepare("INSERT INTO websocket_messages (message, created_at) VALUES (?, NOW())");
    if ($stmt === false) return;
    $message = json_encode(['action' => $action, 'details' => $details, 'officer_id' => $_SESSION['officer_id'] ?? null]);
    $stmt->bind_param("s", $message);
    @$stmt->execute();
    $stmt->close();
}

// --- AJAX Handlers ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        jsonResponse('error', 'Invalid security token. Please refresh the page.');
    }

    $action = $_POST['action'] ?? '';

    // Add fine
    if ($action === 'add') {
        $student_id = trim($_POST['student_id'] ?? '');
        $event_id   = intval($_POST['event_id'] ?? 0);
        $reason     = trim($_POST['fine_reason'] ?? '');
        $amount     = floatval($_POST['amount'] ?? 0);
        $status     = $_POST['status'] ?? 'unpaid';

        $errors = [];
        if (empty($student_id)) $errors[] = 'Student is required.';
        if ($event_id <= 0) $errors[] = 'Event is required.';
        if (empty($reason)) $errors[] = 'Fine reason is required.';
        if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';

        if (empty($errors)) {
            // Get student and event info for audit
            $student_name = '';
            $stu_stmt = $conn->prepare("SELECT full_name FROM students WHERE student_id = ?");
            $stu_stmt->bind_param("s", $student_id);
            $stu_stmt->execute();
            $stu_result = $stu_stmt->get_result();
            if ($stu_result->num_rows > 0) {
                $student_name = $stu_result->fetch_assoc()['full_name'];
            }
            $stu_stmt->close();
            
            $event_name = '';
            $ev_stmt = $conn->prepare("SELECT event_name FROM events WHERE event_id = ?");
            $ev_stmt->bind_param("i", $event_id);
            $ev_stmt->execute();
            $ev_result = $ev_stmt->get_result();
            if ($ev_result->num_rows > 0) {
                $event_name = $ev_result->fetch_assoc()['event_name'];
            }
            $ev_stmt->close();
            
            $stmt = $conn->prepare("INSERT INTO student_fines (student_id, event_id, fine_reason, amount, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sids", $student_id, $event_id, $reason, $amount, $status);
            if ($stmt->execute()) {
                $fine_id = $stmt->insert_id;
                
                // AUDIT: Log the creation
                logAudit($conn, $_SESSION['officer_id'], 'CREATE', 'student_fines', $fine_id, null, json_encode([
                    'student_id' => $student_id,
                    'student_name' => $student_name,
                    'event_id' => $event_id,
                    'event_name' => $event_name,
                    'fine_reason' => $reason,
                    'amount' => $amount,
                    'status' => $status
                ]));
                
                logWebsocketMessage($conn, 'add', ['fine_id' => $fine_id, 'student_id' => $student_id, 'amount' => $amount]);
                jsonResponse('success', 'Fine added successfully.', ['fine_id' => $fine_id]);
            } else {
                jsonResponse('error', 'Database error: ' . $conn->error);
            }
            $stmt->close();
        } else {
            jsonResponse('error', implode(' ', $errors));
        }
    }

    // Edit fine
    if ($action === 'edit') {
        $fine_id    = intval($_POST['fine_id'] ?? 0);
        $student_id = trim($_POST['student_id'] ?? '');
        $event_id   = intval($_POST['event_id'] ?? 0);
        $reason     = trim($_POST['fine_reason'] ?? '');
        $amount     = floatval($_POST['amount'] ?? 0);
        $status     = $_POST['status'] ?? 'unpaid';

        $errors = [];
        if ($fine_id <= 0) $errors[] = 'Invalid fine ID.';
        if (empty($student_id)) $errors[] = 'Student is required.';
        if ($event_id <= 0) $errors[] = 'Event is required.';
        if (empty($reason)) $errors[] = 'Fine reason is required.';
        if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';

        if (empty($errors)) {
            // Get old data before update for audit
            $old_stmt = $conn->prepare("SELECT sf.*, s.full_name as student_name, e.event_name 
                                        FROM student_fines sf 
                                        LEFT JOIN students s ON sf.student_id = s.student_id 
                                        LEFT JOIN events e ON sf.event_id = e.event_id 
                                        WHERE sf.fine_id = ?");
            $old_stmt->bind_param("i", $fine_id);
            $old_stmt->execute();
            $old_data = $old_stmt->get_result()->fetch_assoc();
            $old_stmt->close();
            
            $stmt = $conn->prepare("UPDATE student_fines SET student_id=?, event_id=?, fine_reason=?, amount=?, status=? WHERE fine_id=?");
            $stmt->bind_param("sidsi", $student_id, $event_id, $reason, $amount, $status, $fine_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    // Get new data for audit
                    $new_stmt = $conn->prepare("SELECT s.full_name as student_name, e.event_name 
                                                FROM student_fines sf 
                                                LEFT JOIN students s ON sf.student_id = s.student_id 
                                                LEFT JOIN events e ON sf.event_id = e.event_id 
                                                WHERE sf.fine_id = ?");
                    $new_stmt->bind_param("i", $fine_id);
                    $new_stmt->execute();
                    $new_data = $new_stmt->get_result()->fetch_assoc();
                    $new_stmt->close();
                    
                    // AUDIT: Log the edit with changes
                    $changes = [];
                    if ($old_data['student_id'] != $student_id) {
                        $changes['student'] = ['old' => $old_data['student_name'], 'new' => $new_data['student_name']];
                    }
                    if ($old_data['event_id'] != $event_id) {
                        $changes['event'] = ['old' => $old_data['event_name'], 'new' => $new_data['event_name']];
                    }
                    if ($old_data['fine_reason'] != $reason) {
                        $changes['fine_reason'] = ['old' => $old_data['fine_reason'], 'new' => $reason];
                    }
                    if ($old_data['amount'] != $amount) {
                        $changes['amount'] = ['old' => $old_data['amount'], 'new' => $amount];
                    }
                    if ($old_data['status'] != $status) {
                        $changes['status'] = ['old' => $old_data['status'], 'new' => $status];
                    }
                    
                    logAudit($conn, $_SESSION['officer_id'], 'UPDATE', 'student_fines', $fine_id, 
                        json_encode(['old' => $old_data, 'changes' => $changes]), 
                        json_encode($new_data));
                    
                    logWebsocketMessage($conn, 'edit', ['fine_id' => $fine_id, 'student_id' => $student_id, 'amount' => $amount, 'status' => $status]);
                    jsonResponse('success', 'Fine updated successfully.');
                } else {
                    jsonResponse('error', 'No changes made or fine not found.');
                }
            } else {
                jsonResponse('error', 'Database error: ' . $conn->error);
            }
            $stmt->close();
        } else {
            jsonResponse('error', implode(' ', $errors));
        }
    }

    // Delete fine
    if ($action === 'delete') {
        $fine_id = intval($_POST['fine_id'] ?? 0);
        if ($fine_id <= 0) {
            jsonResponse('error', 'Invalid fine ID.');
        }

        // Get fine data before deletion for audit
        $old_stmt = $conn->prepare("SELECT sf.*, s.full_name as student_name, e.event_name 
                                    FROM student_fines sf 
                                    LEFT JOIN students s ON sf.student_id = s.student_id 
                                    LEFT JOIN events e ON sf.event_id = e.event_id 
                                    WHERE sf.fine_id = ?");
        $old_stmt->bind_param("i", $fine_id);
        $old_stmt->execute();
        $deleted_fine = $old_stmt->get_result()->fetch_assoc();
        $old_stmt->close();

        $stmt = $conn->prepare("DELETE FROM student_fines WHERE fine_id = ?");
        $stmt->bind_param("i", $fine_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // AUDIT: Log the deletion
                logAudit($conn, $_SESSION['officer_id'], 'DELETE', 'student_fines', $fine_id, 
                    json_encode($deleted_fine), 
                    json_encode(['action' => 'deleted', 'deleted_by' => $_SESSION['officer_id']]));
                
                logWebsocketMessage($conn, 'delete', ['fine_id' => $fine_id]);
                jsonResponse('success', 'Fine deleted successfully.');
            } else {
                jsonResponse('error', 'Fine not found.');
            }
        } else {
            jsonResponse('error', 'Database error: ' . $conn->error);
        }
        $stmt->close();
    }

    // Toggle status (Pay/Unpay)
    if ($action === 'toggle_status') {
        $fine_id = intval($_POST['fine_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';

        if ($fine_id <= 0 || !in_array($new_status, ['paid', 'unpaid'])) {
            jsonResponse('error', 'Invalid request.');
        }

        // Get old status and fine details for audit
        $old_stmt = $conn->prepare("SELECT sf.*, s.full_name as student_name, e.event_name 
                                    FROM student_fines sf 
                                    LEFT JOIN students s ON sf.student_id = s.student_id 
                                    LEFT JOIN events e ON sf.event_id = e.event_id 
                                    WHERE sf.fine_id = ?");
        $old_stmt->bind_param("i", $fine_id);
        $old_stmt->execute();
        $fine_data = $old_stmt->get_result()->fetch_assoc();
        $old_stmt->close();

        $stmt = $conn->prepare("UPDATE student_fines SET status = ? WHERE fine_id = ?");
        $stmt->bind_param("si", $new_status, $fine_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                // AUDIT: Log status change
                logAudit($conn, $_SESSION['officer_id'], 'UPDATE', 'student_fines', $fine_id, 
                    json_encode(['old_status' => $fine_data['status'], 'new_status' => $new_status, 'fine_details' => $fine_data]), 
                    json_encode(['status_changed_to' => $new_status, 'changed_by' => $_SESSION['officer_id']]));
                
                logWebsocketMessage($conn, 'toggle_status', ['fine_id' => $fine_id, 'new_status' => $new_status]);
                jsonResponse('success', "Fine marked as $new_status.");
            } else {
                jsonResponse('error', 'Fine not found or status unchanged.');
            }
        } else {
            jsonResponse('error', 'Database error: ' . $conn->error);
        }
        $stmt->close();
    }

    // Pay all unpaid for a student
    if ($action === 'pay_all_unpaid') {
        $student_id = trim($_POST['student_id'] ?? '');
        if (empty($student_id)) {
            jsonResponse('error', 'Student ID required.');
        }

        // Get student info and list of unpaid fines before updating
        $student_name = '';
        $stu_stmt = $conn->prepare("SELECT full_name FROM students WHERE student_id = ?");
        $stu_stmt->bind_param("s", $student_id);
        $stu_stmt->execute();
        $stu_result = $stu_stmt->get_result();
        if ($stu_result->num_rows > 0) {
            $student_name = $stu_result->fetch_assoc()['full_name'];
        }
        $stu_stmt->close();
        
        // Get list of unpaid fines
        $fines_stmt = $conn->prepare("SELECT fine_id, amount, fine_reason FROM student_fines WHERE student_id = ? AND status = 'unpaid'");
        $fines_stmt->bind_param("s", $student_id);
        $fines_stmt->execute();
        $unpaid_fines = $fines_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $fines_stmt->close();

        $stmt = $conn->prepare("UPDATE student_fines SET status = 'paid' WHERE student_id = ? AND status = 'unpaid'");
        $stmt->bind_param("s", $student_id);
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            if ($affected > 0) {
                // AUDIT: Log bulk payment
                logAudit($conn, $_SESSION['officer_id'], 'UPDATE', 'student_fines', $student_id, 
                    json_encode(['action' => 'pay_all_unpaid', 'student_name' => $student_name, 'unpaid_fines' => $unpaid_fines]), 
                    json_encode(['action' => 'paid_all_unpaid', 'count' => $affected, 'paid_by' => $_SESSION['officer_id']]));
                
                logWebsocketMessage($conn, 'pay_all_unpaid', ['student_id' => $student_id, 'count' => $affected]);
            }
            jsonResponse('success', "$affected unpaid fine(s) marked as paid.");
        } else {
            jsonResponse('error', 'Database error: ' . $conn->error);
        }
        $stmt->close();
    }

    jsonResponse('error', 'Invalid action.');
}

// --- GET endpoint: fetch fines for a student (for the Manage Fines modal) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_student_fines') {
    $student_id = trim($_GET['student_id'] ?? '');
    if (empty($student_id)) {
        jsonResponse('error', 'Student ID required.');
    }

    $stmt = $conn->prepare("
        SELECT f.fine_id, f.fine_reason, f.amount, f.status, e.event_name
        FROM student_fines f
        LEFT JOIN events e ON f.event_id = e.event_id
        WHERE f.student_id = ?
        ORDER BY f.fine_id DESC
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $fines = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // AUDIT: Log page view for manage fines modal
    logAudit($conn, $_SESSION['officer_id'], 'VIEW', 'student_fines', $student_id, null, 
        json_encode(['action' => 'view_student_fines', 'student_id' => $student_id, 'fines_count' => count($fines)]));

    jsonResponse('success', '', ['fines' => $fines]);
}

// --- Log page access ---
logAudit($conn, $_SESSION['officer_id'], 'VIEW', 'student_fines_page', null, null, 
    json_encode(['action' => 'page_access', 'timestamp' => date('Y-m-d H:i:s'), 'page' => 'Student Fines Management']));

// --- For GET requests, include sidebar and display the page ---
include "../sidebar/officer_sidebar.php";

// --- Filter, Pagination, and Data Fetching ---
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Get per_page from GET parameter, session, or default to 10
$per_page = 10;
if (isset($_GET['per_page']) && in_array($_GET['per_page'], [10, 25, 50, 100])) {
    $per_page = intval($_GET['per_page']);
    $_SESSION['fines_per_page'] = $per_page;
} elseif (isset($_SESSION['fines_per_page'])) {
    $per_page = $_SESSION['fines_per_page'];
}

$offset = ($current_page - 1) * $per_page;

// Build the base query for counting total distinct students with fines
$count_query = "
    SELECT COUNT(DISTINCT sf.student_id) as total
    FROM student_fines sf
";
// Apply status filter to count query
if ($filter_status === 'unpaid') {
    $count_query .= " WHERE sf.status = 'unpaid'";
} elseif ($filter_status === 'paid') {
    $count_query .= " WHERE sf.status = 'paid'";
}
$count_result = $conn->query($count_query);
$total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($total_records / $per_page);

// Build the main query with pagination
$query = "
    SELECT 
        sf.student_id,
        s.full_name AS student_name,
        SUM(sf.amount) AS total_amount,
        COUNT(*) AS total_fines,
        SUM(CASE WHEN sf.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
        SUM(CASE WHEN sf.status = 'unpaid' THEN 1 ELSE 0 END) AS unpaid_count,
        SUM(CASE WHEN sf.status = 'unpaid' THEN sf.amount ELSE 0 END) AS unpaid_total
    FROM student_fines sf
    LEFT JOIN students s ON sf.student_id = s.student_id
";

// Apply status filter to main query
if ($filter_status === 'unpaid') {
    $query .= " WHERE sf.status = 'unpaid'";
} elseif ($filter_status === 'paid') {
    $query .= " WHERE sf.status = 'paid'";
}

$query .= " GROUP BY sf.student_id";
$query .= " ORDER BY s.full_name ASC";
$query .= " LIMIT $offset, $per_page";

$result = $conn->query($query);
$students_agg = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Calculate totals for stats cards (unfiltered)
$stats_query = "
    SELECT 
        COUNT(*) AS total_fines,
        SUM(amount) AS total_amount,
        SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) AS unpaid_count,
        SUM(CASE WHEN status = 'unpaid' THEN amount ELSE 0 END) AS unpaid_amount
    FROM student_fines
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result ? $stats_result->fetch_assoc() : [];
$total_fines = $stats['total_fines'] ?? 0;
$total_amount = $stats['total_amount'] ?? 0;
$unpaid_count = $stats['unpaid_count'] ?? 0;
$unpaid_amount = $stats['unpaid_amount'] ?? 0;

// Fetch students for dropdown
$students = [];
$stu_res = $conn->query("SELECT student_id, full_name AS name FROM students ORDER BY full_name");
if ($stu_res) {
    $students = $stu_res->fetch_all(MYSQLI_ASSOC);
}

// Fetch events for dropdown
$events = [];
$ev_res = $conn->query("SELECT event_id, event_name FROM events ORDER BY event_name");
if ($ev_res) {
    $events = $ev_res->fetch_all(MYSQLI_ASSOC);
}

// Helper function to build query string with pagination parameters
function buildQueryString($params = []) {
    $currentParams = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($currentParams[$key]);
        } else {
            $currentParams[$key] = $value;
        }
    }
    return http_build_query($currentParams);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $config['app_name'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --success: #16a34a;
        --success-dark: #15803d;
        --danger: #dc2626;
        --warning: #ca8a04;
        --dark: #1e293b;
        --light: #f8fafc;
        --gray-100: #f1f5f9;
        --gray-200: #e2e8f0;
        --gray-600: #475569;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: var(--dark);
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

    .stats-card {
        background: white;
        border-radius: 1.5rem;
        padding: 1.5rem;
        box-shadow: 0 8px 20px -6px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s;
        border: 1px solid rgba(0, 0, 0, 0.02);
    }

    .stats-card:hover {
        transform: translateY(-3px);
    }

    .stats-icon {
        width: 54px;
        height: 54px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }

    .badge-paid {
        background: #dcfce7;
        color: #166534;
        padding: 0.35rem 1rem;
        border-radius: 100px;
        font-weight: 500;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .badge-unpaid {
        background: #fee2e2;
        color: #991b1b;
        padding: 0.35rem 1rem;
        border-radius: 100px;
        font-weight: 500;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .filter-btn {
        border-radius: 100px;
        padding: 0.5rem 1.8rem;
        font-weight: 500;
        border: 1px solid var(--gray-200);
        background: white;
        color: var(--gray-600);
        transition: all 0.15s;
        text-decoration: none;
        display: inline-block;
    }

    .filter-btn:hover {
        background: var(--gray-100);
        border-color: var(--gray-600);
        color: var(--dark);
    }

    .filter-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .pay-all-btn {
        background: linear-gradient(145deg, #16a34a, #15803d);
        border: none;
        color: white;
        padding: 0.45rem 1.2rem;
        border-radius: 2rem;
        font-weight: 500;
        font-size: 0.9rem;
        box-shadow: 0 6px 12px -6px #15803d80;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .pay-all-btn:hover {
        transform: scale(1.02);
        box-shadow: 0 10px 18px -8px #15803d;
        color: white;
    }

    .manage-btn {
        background: var(--primary);
        border: none;
        color: white;
        padding: 0.45rem 1.2rem;
        border-radius: 2rem;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .manage-btn:hover {
        background: var(--primary-dark);
        transform: scale(1.02);
        color: white;
    }

    .fines-table {
        background: white;
        border-radius: 1.5rem;
        overflow: hidden;
        box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.08);
        border: 1px solid var(--gray-200);
    }

    .fines-table table {
        margin-bottom: 0;
    }

    .fines-table th {
        background: #f9fafc;
        font-weight: 600;
        color: var(--gray-600);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        padding: 1rem 1rem;
        border-bottom: 1px solid var(--gray-200);
    }

    .fines-table td {
        padding: 1rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--gray-200);
    }

    .fines-table tr:last-child td {
        border-bottom: none;
    }

    .modal-content {
        border-radius: 2rem;
        border: none;
        box-shadow: 0 30px 60px -20px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
        background: #fafcff;
        border-radius: 2rem 2rem 0 0;
        padding: 1.75rem 2rem;
        border-bottom: 1px solid var(--gray-200);
    }

    .modal-body {
        padding: 2rem;
    }

    .modal-footer {
        padding: 1.5rem 2rem;
        border-top: 1px solid var(--gray-200);
    }

    .form-control,
    .form-select {
        border-radius: 1rem;
        border: 1px solid var(--gray-200);
        padding: 0.7rem 1.2rem;
        font-size: 0.95rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    .alert {
        border-radius: 1.2rem;
        border: none;
        padding: 1.2rem 2rem;
        font-weight: 500;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
    }

    .spinner-border {
        width: 1.2rem;
        height: 1.2rem;
        display: none;
    }

    .btn-loading .spinner-border {
        display: inline-block;
    }

    .btn-loading .btn-text {
        display: none;
    }

    @media (max-width: 992px) {
        .fines-table {
            overflow-x: auto;
        }
    }

    .search-group {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .search-input {
        border-radius: 2rem;
        padding: 0.6rem 1.2rem;
        border: 1px solid var(--gray-200);
        width: 260px;
        font-size: 0.9rem;
    }

    .search-input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .search-btn {
        border-radius: 2rem;
        padding: 0.6rem 1.2rem;
        background: var(--primary);
        border: none;
        color: white;
        transition: 0.2s;
    }

    .search-btn:hover {
        background: var(--primary-dark);
        transform: scale(0.98);
    }

    .delete-fine-btn {
        background: #fee2e2;
        border: none;
        color: #991b1b;
        border-radius: 2rem;
        padding: 0.3rem 0.8rem;
        transition: 0.2s;
    }

    .delete-fine-btn:hover {
        background: #dc2626;
        color: white;
        transform: scale(1.02);
    }

    .no-results {
        display: none;
        text-align: center;
        padding: 1rem;
        color: var(--gray-600);
    }
    
    /* Pagination Styles */
    .pagination-container {
        background: white;
        border-radius: 1rem;
        padding: 1rem;
        margin-top: 1.5rem;
        border: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .pagination {
        margin: 0;
        gap: 0.3rem;
    }
    
    .pagination .page-link {
        border-radius: 0.5rem;
        border: 1px solid var(--gray-200);
        color: var(--gray-600);
        padding: 0.5rem 1rem;
        font-weight: 500;
        transition: all 0.2s;
    }
    
    .pagination .page-link:hover {
        background-color: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    
    .pagination .page-item.active .page-link {
        background-color: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    
    .pagination .page-item.disabled .page-link {
        background-color: var(--gray-100);
        color: var(--gray-600);
        cursor: not-allowed;
    }
    
    .pagination-info {
        color: var(--gray-600);
        font-size: 0.9rem;
    }
    
    .per-page-select {
        padding: 0.4rem 0.8rem;
        border-radius: 0.5rem;
        border: 1px solid var(--gray-200);
        background: white;
        color: var(--gray-600);
        font-size: 0.9rem;
        cursor: pointer;
    }
    
    .per-page-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    @media (max-width: 768px) {
        .pagination-container {
            flex-direction: column;
            text-align: center;
        }
        
        .pagination .page-link {
            padding: 0.3rem 0.7rem;
            font-size: 0.85rem;
        }
    }
    </style>
</head>

<body>
    <div class="main-contents">
        <div class="container-fluid px-0">
            <!-- Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-5">
                <h2 class="fw-semibold mb-3 mb-md-0" style="color: var(--dark);">
                    <i class="bi bi-receipt me-2" style="color: var(--primary);"></i>
                    <?= $config['app_name'] ?>
                </h2>
                <button class="btn btn-primary px-4 py-2 rounded-pill shadow-sm" data-bs-toggle="modal"
                    data-bs-target="#addFineModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New Fine
                </button>
            </div>

            <!-- Alert container -->
            <div class="alert alert-success" id="successAlert" role="alert" style="display: none;"></div>
            <div class="alert alert-danger" id="errorAlert" role="alert" style="display: none;"></div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-5">
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card d-flex align-items-center">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <span class="text-secondary-emphasis small text-uppercase">Total Fines</span>
                            <h3 class="mb-0 fw-bold"><?= number_format($total_fines) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card d-flex align-items-center">
                        <div class="stats-icon bg-success bg-opacity-10 text-success me-3">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div>
                            <span class="text-secondary-emphasis small text-uppercase">Total Amount</span>
                            <h3 class="mb-0 fw-bold"><?= $config['currency'] ?><?= number_format($total_amount, 2) ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card d-flex align-items-center">
                        <div class="stats-icon bg-warning bg-opacity-10 text-warning me-3">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>
                        <div>
                            <span class="text-secondary-emphasis small text-uppercase">Unpaid</span>
                            <h3 class="mb-0 fw-bold"><?= number_format($unpaid_count) ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card d-flex align-items-center">
                        <div class="stats-icon bg-danger bg-opacity-10 text-danger me-3">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                        <div>
                            <span class="text-secondary-emphasis small text-uppercase">Unpaid Amount</span>
                            <h3 class="mb-0 fw-bold"><?= $config['currency'] ?><?= number_format($unpaid_amount, 2) ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar + Search -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div class="d-flex flex-wrap gap-2">
                    <a href="?<?= buildQueryString(['status' => null, 'page' => 1]) ?>" 
                       class="filter-btn <?= $filter_status == '' ? 'active' : '' ?>">
                        <i class="bi bi-list-ul me-2"></i>All
                    </a>
                    <a href="?<?= buildQueryString(['status' => 'unpaid', 'page' => 1]) ?>" 
                       class="filter-btn <?= $filter_status == 'unpaid' ? 'active' : '' ?>">
                        <i class="bi bi-exclamation-triangle me-2"></i>Unpaid
                    </a>
                    <a href="?<?= buildQueryString(['status' => 'paid', 'page' => 1]) ?>" 
                       class="filter-btn <?= $filter_status == 'paid' ? 'active' : '' ?>">
                        <i class="bi bi-check-circle me-2"></i>Paid
                    </a>
                    <?php if ($filter_status): ?>
                    <a href="?<?= buildQueryString(['status' => null, 'page' => 1]) ?>" class="filter-btn">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </a>
                    <?php endif; ?>
                </div>
                <div class="search-group">
                    <input type="text" id="searchStudent" class="search-input" placeholder="Search by student name...">
                    <button id="searchBtn" class="search-btn"><i class="bi bi-search"></i> Search</button>
                </div>
            </div>

            <!-- Main Table -->
            <?php if (empty($students_agg)): ?>
            <div class="alert alert-info py-4 text-center">No fines found.</div>
            <?php else: ?>
            <div class="fines-table">
                <div class="table-responsive">
                    <table class="table align-middle" id="finesTable">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Reason</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_agg as $student): 
                                $student_id_esc = htmlspecialchars($student['student_id']);
                                $student_name_esc = htmlspecialchars($student['student_name']);
                                $status = ($student['unpaid_count'] == 0) ? 'paid' : 'unpaid';
                                $status_badge = ($status == 'paid') 
                                    ? '<span class="badge-paid"><i class="bi bi-check-circle-fill"></i> Paid</span>'
                                    : '<span class="badge-unpaid"><i class="bi bi-exclamation-circle-fill"></i> Unpaid</span>';
                            ?>
                            <tr data-student-id="<?= $student_id_esc ?>"
                                data-student-name="<?= strtolower($student_name_esc) ?>">
                                <td class="student-name"><?= $student_name_esc ?></td>
                                <td>Absent Event</td>
                                <td class="fw-semibold">
                                    <?= $config['currency'] ?><?= number_format($student['total_amount'], 2) ?>
                                 </td>
                                <td><?= $status_badge ?></td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if ($student['unpaid_count'] > 0): ?>
                                        <button class="btn pay-all-btn" data-student="<?= $student_id_esc ?>">
                                            <i class="bi bi-cash me-1"></i> Pay All
                                            <?= $config['currency'] ?><?= number_format($student['unpaid_total'], 2) ?>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn manage-btn" data-student-id="<?= $student_id_esc ?>"
                                            data-student-name="<?= $student_name_esc ?>" data-bs-toggle="modal"
                                            data-bs-target="#finesModal">
                                            <i class="bi bi-eye me-1"></i> Manage Fines
                                        </button>
                                    </div>
                                  </td>
                              </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div id="noSearchResults" class="no-results">No matching students found.</div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_records) ?> of <?= $total_records ?> students
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildQueryString(['page' => $current_page - 1]) ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= buildQueryString(['page' => 1]) ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= buildQueryString(['page' => $i]) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= buildQueryString(['page' => $total_pages]) ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildQueryString(['page' => $current_page + 1]) ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <div>
                    <form method="GET" id="perPageForm" style="display: inline;">
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'per_page' && $key !== 'page'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <input type="hidden" name="page" value="1">
                        <select name="per_page" id="perPageSelect" class="per-page-select" onchange="this.form.submit()">
                            <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10 per page</option>
                            <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25 per page</option>
                            <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50 per page</option>
                            <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100 per page</option>
                        </select>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Fine Modal -->
    <div class="modal fade" id="addFineModal" tabindex="-1" aria-labelledby="addFineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFineModalLabel"><i class="bi bi-plus-circle me-2"></i>Add New Fine
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addFineForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label for="add_student" class="form-label">Student <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="add_student" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $s): ?>
                                <option value="<?= htmlspecialchars($s['student_id']) ?>">
                                    <?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="add_event" class="form-label">Event <span class="text-danger">*</span></label>
                            <select class="form-select" id="add_event" name="event_id" required>
                                <option value="">Select Event</option>
                                <?php foreach ($events as $e): ?>
                                <option value="<?= $e['event_id'] ?>"><?= htmlspecialchars($e['event_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="add_reason" class="form-label">Fine Reason <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_reason" name="fine_reason" required
                                maxlength="100" value="Absent Event">
                        </div>
                        <div class="mb-3">
                            <label for="add_amount" class="form-label">Amount (<?= $config['currency'] ?>) <span
                                    class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="add_amount"
                                name="amount" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_status" class="form-label">Initial Status</label>
                            <select class="form-select" id="add_status" name="status">
                                <option value="unpaid" selected>Unpaid</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary rounded-pill px-5" id="saveFineBtn">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        <span class="btn-text">Save Fine</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Fines Modal -->
    <div class="modal fade" id="finesModal" tabindex="-1" aria-labelledby="finesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="finesModalLabel">
                        <i class="bi bi-receipt me-2"></i>Fines for <span id="modalStudentName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="finesLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2">Loading fines...</p>
                    </div>
                    <div id="finesList" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Reason</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="finesTableBody"></tbody>
                            </table>
                        </div>
                        <div class="text-muted mt-2 small"><i class="bi bi-info-circle"></i> Click "Delete" to remove a fine.</div>
                    </div>
                    <div id="noFinesMsg" class="alert alert-info text-center" style="display: none;">No fines recorded for this student.</div>
                    <div id="errorMsg" class="alert alert-danger text-center" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function() {
        // ---------- SEARCH FUNCTIONALITY ----------
        function filterTable() {
            let searchTerm = $('#searchStudent').val().toLowerCase().trim();
            let hasVisible = false;
            $('#finesTable tbody tr').each(function() {
                let studentName = $(this).attr('data-student-name');
                if (studentName === undefined || studentName === null) {
                    studentName = $(this).find('.student-name').text();
                }
                studentName = String(studentName).toLowerCase();
                if (studentName.indexOf(searchTerm) !== -1) {
                    $(this).show();
                    hasVisible = true;
                } else {
                    $(this).hide();
                }
            });
            if (searchTerm !== '' && !hasVisible) {
                $('#noSearchResults').show();
            } else {
                $('#noSearchResults').hide();
            }
        }

        $('#searchStudent').on('keyup', filterTable);
        $('#searchBtn').on('click', filterTable);

        // ---------- ADD FINE ----------
        $('#saveFineBtn').click(function() {
            const btn = $(this);
            const form = $('#addFineForm')[0];
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            const formData = new FormData(form);
            formData.append('action', 'add');
            btn.addClass('btn-loading').prop('disabled', true);
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    btn.removeClass('btn-loading').prop('disabled', false);
                    if (res.status === 'success') {
                        showAlert('success', res.message);
                        $('#addFineModal').modal('hide');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('error', res.message);
                    }
                },
                error: function() {
                    btn.removeClass('btn-loading').prop('disabled', false);
                    showAlert('error', 'Network error. Please try again.');
                }
            });
        });

        // ---------- PAY ALL ----------
        $('.pay-all-btn').click(function() {
            const studentId = $(this).data('student');
            if (!confirm('Mark ALL unpaid fines for this student as paid?')) return;
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'pay_all_unpaid',
                    student_id: studentId,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        showAlert('success', res.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert('error', res.message);
                    }
                },
                error: function() {
                    showAlert('error', 'Network error. Please try again.');
                }
            });
        });

        // ---------- MANAGE FINES MODAL ----------
        $('#finesModal').on('show.bs.modal', function(event) {
            const button = $(event.relatedTarget);
            const studentId = button.data('student-id');
            const studentName = button.data('student-name');

            $('#modalStudentName').text(studentName);
            $('#finesLoading').show();
            $('#finesList').hide();
            $('#noFinesMsg').hide();
            $('#errorMsg').hide();

            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: {
                    action: 'get_student_fines',
                    student_id: studentId
                },
                dataType: 'json',
                success: function(res) {
                    $('#finesLoading').hide();
                    let fines = res.fines || (res.data && res.data.fines) || [];

                    if (res.status === 'success' && fines.length > 0) {
                        let tbody = $('#finesTableBody');
                        tbody.empty();
                        $.each(fines, function(idx, fine) {
                            let statusBadge = fine.status === 'paid' ?
                                '<span class="badge-paid"><i class="bi bi-check-circle-fill"></i> Paid</span>' :
                                '<span class="badge-unpaid"><i class="bi bi-exclamation-circle-fill"></i> Unpaid</span>';
                            let eventName = fine.event_name ? escapeHtml(fine.event_name) : '—';
                            let row = `
                                <tr data-fine-id="${fine.fine_id}">
                                    <td>${eventName}</td>
                                    <td>${escapeHtml(fine.fine_reason)}</td>
                                    <td><?= $config['currency'] ?>${parseFloat(fine.amount).toFixed(2)}</td>
                                    <td>${statusBadge}</td>
                                    <td>
                                        <button class="btn delete-fine-btn" data-fine-id="${fine.fine_id}">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            `;
                            tbody.append(row);
                        });
                        $('#finesList').show();
                    } else if (res.status === 'success' && fines.length === 0) {
                        $('#noFinesMsg').show();
                    } else {
                        let errMsg = res.message || 'Failed to load fines.';
                        $('#errorMsg').text(errMsg).show();
                    }
                },
                error: function(xhr, status, error) {
                    $('#finesLoading').hide();
                    $('#errorMsg').text('Network error: ' + status).show();
                }
            });
        });

        // ---------- DELETE FINE ----------
        $(document).on('click', '.delete-fine-btn', function() {
            const fineId = $(this).data('fine-id');
            if (!confirm('Delete this fine permanently?')) return;
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'delete',
                    fine_id: fineId,
                    csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        showAlert('success', res.message);
                        $('#finesModal').modal('hide');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showAlert('error', res.message);
                    }
                },
                error: function() {
                    showAlert('error', 'Could not delete fine.');
                }
            });
        });

        // Helper functions
        function showAlert(type, message) {
            let alertBox = type === 'success' ? $('#successAlert') : $('#errorAlert');
            alertBox.text(message).fadeIn().delay(4000).fadeOut();
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
        
        $('#addFineModal, #finesModal').on('show.bs.modal', function() {
            $('.alert').fadeOut();
        });
    });
    </script>
</body>

</html>
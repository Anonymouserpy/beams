<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['officer_id'])) {
    header("Location: login.php");
    exit();
}
require "../../Connection/connection.php";

// Audit Log Function
function log_audit($conn, $officer_id, $action, $table_name, $record_id = null, $old_data = null, $new_data = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    if (is_array($old_data) || is_object($old_data)) {
        $old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);
    }
    if (is_array($new_data) || is_object($new_data)) {
        $new_data = json_encode($new_data, JSON_UNESCAPED_UNICODE);
    }
    
    $query = "INSERT INTO audit_logs (officer_id, action, table_name, record_id, old_data, new_data, ip_address, user_agent) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssss", 
            $officer_id, $action, $table_name, $record_id, $old_data, $new_data, $ip_address, $user_agent
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

// ========== AJAX HANDLER FOR REFRESH ==========
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    // Get filters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $action_filter = isset($_GET['action_filter']) ? trim($_GET['action_filter']) : '';
    $table_filter = isset($_GET['table_filter']) ? trim($_GET['table_filter']) : '';
    $date_filter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $records_per_page = 50;
    $offset = ($page - 1) * $records_per_page;
    
    // Build WHERE clause for filters
    $where_clauses = [];
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $where_clauses[] = "(o.full_name LIKE ? OR al.officer_id LIKE ? OR al.table_name LIKE ? OR al.action LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "ssss";
    }
    if (!empty($action_filter)) {
        $where_clauses[] = "al.action = ?";
        $params[] = $action_filter;
        $types .= "s";
    }
    if (!empty($table_filter)) {
        $where_clauses[] = "al.table_name = ?";
        $params[] = $table_filter;
        $types .= "s";
    }
    if (!empty($date_filter)) {
        $where_clauses[] = "DATE(al.created_at) = ?";
        $params[] = $date_filter;
        $types .= "s";
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Get total count with filters
    $count_query = "SELECT COUNT(*) as total FROM audit_logs al LEFT JOIN officers o ON al.officer_id = o.officer_id $where_sql";
    $count_stmt = mysqli_prepare($conn, $count_query);
    if (!empty($params)) {
        mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    }
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_records = mysqli_fetch_assoc($count_result)['total'];
    $total_pages = ceil($total_records / $records_per_page);
    
    // Fetch filtered data
    $query = "SELECT al.*, o.full_name as officer_name 
              FROM audit_logs al 
              LEFT JOIN officers o ON al.officer_id = o.officer_id 
              $where_sql
              ORDER BY al.created_at DESC 
              LIMIT ? OFFSET ?";
    
    $params[] = $records_per_page;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    
    // Get unique tables for stats
    $tables_query = mysqli_query($conn, "SELECT COUNT(DISTINCT table_name) as count FROM audit_logs");
    $tables_count = $tables_query ? mysqli_fetch_assoc($tables_query)['count'] : 0;
    
    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'stats' => [
            'total_records' => $total_records,
            'tables_tracked' => $tables_count,
            'last_updated' => date('M d, Y H:i:s')
        ],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_records' => $total_records,
            'records_per_page' => $records_per_page,
            'start_record' => $offset + 1,
            'end_record' => min($offset + $records_per_page, $total_records),
            'has_prev' => $page > 1,
            'has_next' => $page < $total_pages
        ]
    ]);
    exit();
}

// ========== NORMAL PAGE LOAD ==========
// Pagination variables
$records_per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM audit_logs";
$count_result = mysqli_query($conn, $count_query);
if (!$count_result) {
    die("Error counting audit logs: " . mysqli_error($conn));
}
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch audit logs with officer details
$query = "SELECT al.*, o.full_name as officer_name 
          FROM audit_logs al 
          LEFT JOIN officers o ON al.officer_id = o.officer_id 
          ORDER BY al.created_at DESC 
          LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $records_per_page, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    die("Error fetching audit logs: " . mysqli_error($conn));
}

// Get unique tables for filter
$tables_query = mysqli_query($conn, "SELECT DISTINCT table_name, COUNT(*) as count FROM audit_logs GROUP BY table_name ORDER BY table_name");
$unique_tables = [];
if ($tables_query) {
    while($t = mysqli_fetch_assoc($tables_query)) {
        $unique_tables[] = $t;
    }
}

// Handle export request
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    $export_query = "SELECT al.*, o.full_name as officer_name 
                     FROM audit_logs al 
                     LEFT JOIN officers o ON al.officer_id = o.officer_id 
                     ORDER BY al.created_at DESC";
    $export_result = mysqli_query($conn, $export_query);
    
    if ($export_result) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'Officer Name', 'Officer ID', 'Action', 'Table Name', 'Record ID', 'Old Data', 'New Data', 'IP Address', 'User Agent', 'Created At']);
        
        while($row = mysqli_fetch_assoc($export_result)) {
            fputcsv($output, [
                $row['id'],
                $row['officer_name'] ?? 'Unknown',
                $row['officer_id'],
                $row['action'],
                $row['table_name'],
                $row['record_id'] ?? '',
                strip_tags($row['old_data'] ?? ''),
                strip_tags($row['new_data'] ?? ''),
                $row['ip_address'] ?? '',
                $row['user_agent'] ?? '',
                $row['created_at']
            ]);
        }
        fclose($output);
        exit();
    }
}

require "../sidebar/officer_sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Audit Logs | BEAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .wrapper {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 20px 30px;
            margin-left: 190px;
            transition: all 0.3s ease;
            width: calc(100% - 190px);
        }

        /* Responsive Sidebar */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }
        }

        /* WebSocket Status Indicator */
        .ws-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border-radius: 30px;
            padding: 8px 16px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1050;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid transparent;
            cursor: pointer;
        }

        .ws-status.connected {
            color: #28a745;
            border-color: #28a745;
            background: white;
        }

        .ws-status.disconnected {
            color: #dc3545;
            border-color: #dc3545;
            background: white;
        }

        .ws-status.connecting {
            color: #ffc107;
            border-color: #ffc107;
            background: white;
        }

        .ws-status i:first-child {
            font-size: 0.5rem;
        }

        /* Auto-refresh indicator */
        .auto-refresh-badge {
            position: fixed;
            bottom: 20px;
            left: 300px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 0.7rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1040;
            backdrop-filter: blur(5px);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .auto-refresh-badge:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: scale(1.02);
        }

        @media (max-width: 992px) {
            .auto-refresh-badge {
                left: 20px;
                bottom: 20px;
            }
            .ws-status {
                bottom: 20px;
                right: 20px;
            }
        }

        @media (max-width: 576px) {
            .auto-refresh-badge {
                font-size: 0.6rem;
                padding: 4px 10px;
            }
            .ws-status {
                font-size: 0.65rem;
                padding: 6px 12px;
            }
        }

        /* Header Section */
        .page-header {
            margin-bottom: 24px;
        }

        .page-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: white;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .page-header h2 i {
            font-size: 28px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-export, .btn-refresh {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-export:hover, .btn-refresh:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: white;
        }

        .btn-refresh.refreshing {
            animation: spin 0.5s ease;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @media (max-width: 576px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .page-header h2 {
                font-size: 22px;
            }
            .btn-export, .btn-refresh {
                padding: 8px 15px;
                font-size: 13px;
            }
        }

        /* Stats Cards - Responsive Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .stat-card.updating {
            animation: pulse 0.5s ease;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .stat-icon i {
            font-size: 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .stat-info h4 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
            transition: all 0.3s ease;
        }

        .stat-info h4.updated {
            animation: numberPop 0.3s ease;
        }

        @keyframes numberPop {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); color: #667eea; }
            100% { transform: scale(1); }
        }

        .stat-info p {
            font-size: 13px;
            color: #6c757d;
            margin: 0;
            font-weight: 500;
        }

        @media (max-width: 480px) {
            .stat-card {
                padding: 15px;
            }
            .stat-info h4 {
                font-size: 22px;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
            }
            .stat-icon i {
                font-size: 20px;
            }
        }

        /* Filter Bar - Responsive */
        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .filter-bar .row {
            margin: 0 -8px;
        }

        .filter-bar [class*="col-"] {
            padding: 0 8px;
        }

        .filter-bar .form-control,
        .filter-bar .form-select {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 10px 16px;
            font-size: 14px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .filter-bar .form-control:focus,
        .filter-bar .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-clear {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-clear:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .filter-bar {
                padding: 15px;
            }
            .filter-bar .form-control,
            .filter-bar .form-select,
            .btn-clear {
                font-size: 13px;
                padding: 8px 12px;
            }
        }

        /* Main Card */
        .main-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .card-header {
            background: white;
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-header h5 {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fee2e2;
            color: #dc2626;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .live-indicator::before {
            content: '';
            width: 6px;
            height: 6px;
            background: #dc2626;
            border-radius: 50%;
            animation: livePulse 2s infinite;
        }

        @keyframes livePulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        @media (max-width: 576px) {
            .card-header {
                padding: 15px;
                flex-direction: column;
                align-items: flex-start;
            }
            .card-header h5 {
                font-size: 16px;
            }
            .live-indicator {
                font-size: 10px;
                padding: 3px 8px;
            }
        }

        /* Table Styles - Responsive */
        .table-container {
            overflow-x: auto;
            position: relative;
            -webkit-overflow-scrolling: touch;
        }

        .audit-table {
            width: 100%;
            min-width: 900px;
            border-collapse: separate;
            border-spacing: 0;
        }

        .audit-table thead th {
            background: #f8f9fa;
            padding: 14px 12px;
            font-size: 12px;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
        }

        .audit-table tbody td {
            padding: 12px;
            font-size: 13px;
            color: #495057;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            word-break: break-word;
        }

        .audit-table tbody tr {
            transition: background 0.3s ease;
        }

        .audit-table tbody tr:hover {
            background: #f8f9fa;
        }

        .audit-table tbody tr.new-row {
            animation: highlightNew 1s ease;
        }

        @keyframes highlightNew {
            0% { background: rgba(102, 126, 234, 0.3); }
            100% { background: transparent; }
        }

        /* Badge Styles */
        .badge-custom {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .badge-CREATE { background: #d4edda; color: #155724; }
        .badge-UPDATE { background: #fff3cd; color: #856404; }
        .badge-DELETE { background: #f8d7da; color: #721c24; }
        .badge-LOGIN { background: #d1ecf1; color: #0c5460; }
        .badge-LOGOUT { background: #e2e3e5; color: #383d41; }
        .badge-VIEW { background: #cce5ff; color: #004085; }

        /* JSON Preview */
        .json-preview {
            max-width: 200px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            font-family: 'Monaco', monospace;
            font-size: 11px;
            cursor: pointer;
            color: #667eea;
            transition: all 0.3s ease;
        }

        .json-preview:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* IP Address */
        .ip-address {
            font-family: 'Monaco', monospace;
            font-size: 11px;
            background: #f8f9fa;
            padding: 3px 6px;
            border-radius: 6px;
            display: inline-block;
        }

        .info-icon {
            color: #17a2b8;
            cursor: pointer;
            margin-left: 6px;
            font-size: 11px;
        }

        /* Pagination - Responsive */
        .pagination-container {
            padding: 20px 24px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .data-count {
            font-size: 13px;
            color: #6c757d;
        }

        .pagination {
            margin: 0;
            gap: 5px;
            flex-wrap: wrap;
        }

        .page-link {
            border: none;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 500;
            color: #495057;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #f8f9fa;
            text-decoration: none;
            display: inline-block;
        }

        .page-link:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .page-item.disabled .page-link {
            color: #dee2e6;
            cursor: not-allowed;
            background: #f8f9fa;
        }

        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 15px;
            }
            .page-link {
                padding: 6px 10px;
                font-size: 12px;
            }
            .data-count {
                font-size: 12px;
            }
        }

        /* Modal */
        .modal-content {
            border: none;
            border-radius: 24px;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 20px 24px;
        }

        .modal-header .btn-close {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            opacity: 1;
        }

        .modal-body pre {
            background: #1e1e2e;
            color: #e0e0e0;
            padding: 20px;
            border-radius: 16px;
            font-size: 13px;
            font-family: 'Monaco', monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 500px;
            overflow-y: auto;
        }

        @media (max-width: 576px) {
            .modal-dialog {
                margin: 10px;
            }
            .modal-body pre {
                font-size: 11px;
                padding: 12px;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state p {
            color: #6c757d;
            font-size: 16px;
        }

        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            padding: 12px 20px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1100;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            border-left: 4px solid #28a745;
            max-width: 350px;
        }

        .toast-notification.show {
            transform: translateX(0);
        }

        .toast-notification i {
            font-size: 20px;
        }

        .toast-notification .message {
            font-size: 13px;
            font-weight: 500;
        }

        @media (max-width: 576px) {
            .toast-notification {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: calc(100% - 20px);
                padding: 10px 15px;
            }
            .toast-notification i {
                font-size: 16px;
            }
            .toast-notification .message {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="main-content">
        <!-- WebSocket Status -->
        <div class="ws-status disconnected" id="wsStatus">
            <i class="fas fa-circle"></i>
            <span>Offline</span>
        </div>

        <!-- Auto Refresh Badge -->
        <div class="auto-refresh-badge" id="autoRefreshBadge">
            <i class="fas fa-sync-alt fa-fw"></i>
            <span>Auto-refresh: <span id="countdownTimer">5</span>s</span>
        </div>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center page-header flex-wrap">
            <h2>
                <i class="fas fa-history"></i>
                Audit Logs
            </h2>
            <div class="header-actions">
                <a href="?export=csv" class="btn-export">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <button onclick="manualRefresh()" class="btn-refresh" id="refreshBtn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-info">
                    <h4 id="totalRecords"><?= number_format($total_records) ?></h4>
                    <p>Total Records</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-table"></i>
                </div>
                <div class="stat-info">
                    <h4 id="tablesTracked"><?= number_format(count($unique_tables)) ?></h4>
                    <p>Tables Tracked</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div class="stat-info">
                    <h4 id="lastUpdated"><?= date('M d, Y H:i:s') ?></h4>
                    <p>Last Updated</p>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="row g-2">
                <div class="col-12 col-md-4 mb-2">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by officer, table, action...">
                </div>
                <div class="col-6 col-md-2 mb-2">
                    <select id="actionFilter" class="form-select">
                        <option value="">All Actions</option>
                        <option value="CREATE">CREATE</option>
                        <option value="UPDATE">UPDATE</option>
                        <option value="DELETE">DELETE</option>
                        <option value="LOGIN">LOGIN</option>
                        <option value="LOGOUT">LOGOUT</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 mb-2">
                    <select id="tableFilter" class="form-select">
                        <option value="">All Tables</option>
                        <?php foreach($unique_tables as $table): ?>
                            <option value="<?= htmlspecialchars($table['table_name']) ?>">
                                <?= htmlspecialchars($table['table_name']) ?> (<?= $table['count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 mb-2">
                    <input type="date" id="dateFilter" class="form-control">
                </div>
                <div class="col-6 col-md-2 mb-2">
                    <button class="btn-clear w-100" onclick="clearFilters()">
                        <i class="fas fa-eraser"></i> Clear
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="main-card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list-ul"></i>
                    System Activity Log
                    <span class="live-indicator">LIVE</span>
                </h5>
                <small class="text-muted" id="lastSyncTime">Last sync: Just now</small>
            </div>
            
            <div class="table-container">
                <table class="audit-table" id="auditTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Officer</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Record ID</th>
                            <th>Old Data</th>
                            <th>New Data</th>
                            <th>IP Address</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr data-id="<?= $row['id'] ?>">
                                    <td><strong>#<?= $row['id'] ?></strong></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-placeholder" style="width: 32px; height: 32px; background: linear-gradient(135deg, rgba(102,126,234,0.1), rgba(118,75,162,0.1)); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-user" style="font-size: 12px; color: #667eea;"></i>
                                            </div>
                                            <span><?= htmlspecialchars($row['officer_name'] ?? $row['officer_id']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-custom badge-<?= $row['action'] ?>">
                                            <i class="fas <?= $row['action'] == 'CREATE' ? 'fa-plus' : ($row['action'] == 'UPDATE' ? 'fa-edit' : ($row['action'] == 'DELETE' ? 'fa-trash' : ($row['action'] == 'LOGIN' ? 'fa-sign-in-alt' : 'fa-sign-out-alt'))) ?>"></i>
                                            <?= $row['action'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 6px; font-size: 11px;">
                                            <?= htmlspecialchars($row['table_name']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <code style="font-size: 11px;"><?= htmlspecialchars($row['record_id'] ?? '-') ?></code>
                                    </td>
                                    <td class="json-preview" onclick='showDataModal(<?= json_encode($row['old_data']) ?>, "Old Data - Record #<?= $row['id'] ?>")'>
                                        <?php if($row['old_data']): ?>
                                            <i class="fas fa-code"></i> <?= htmlspecialchars(substr($row['old_data'], 0, 50)) ?>...
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="json-preview" onclick='showDataModal(<?= json_encode($row['new_data']) ?>, "New Data - Record #<?= $row['id'] ?>")'>
                                        <?php if($row['new_data']): ?>
                                            <i class="fas fa-code"></i> <?= htmlspecialchars(substr($row['new_data'], 0, 50)) ?>...
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code class="ip-address"><?= htmlspecialchars($row['ip_address'] ?? '-') ?></code>
                                        <?php if($row['user_agent']): ?>
                                            <i class="fas fa-info-circle info-icon" 
                                               onclick="alert('User Agent: <?= htmlspecialchars(addslashes($row['user_agent'])) ?>')"
                                               title="View User Agent"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span style="font-weight: 600;"><?= date('Y-m-d', strtotime($row['created_at'])) ?></span>
                                            <small class="text-muted"><?= date('H:i:s', strtotime($row['created_at'])) ?></small>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr id="emptyStateRow">
                                <td colspan="9" class="empty-state">
                                    <i class="fas fa-database"></i>
                                    <p>No audit logs found yet</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="data-count">
                    <i class="fas fa-chart-line"></i> Showing <span id="startRecord"><?= $offset + 1 ?></span> to <span id="endRecord"><?= min($offset + $records_per_page, $total_records) ?></span> of <span id="totalRecordsCount"><?= number_format($total_records) ?></span> records
                </div>
                <nav>
                    <ul class="pagination" id="pagination">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="#" data-page="<?= $page - 1 ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="#" data-page="<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="#" data-page="<?= $page + 1 ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Data Modal -->
<div class="modal fade" id="dataModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="fas fa-code"></i> Data Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="modalContent"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Close
                </button>
                <button type="button" class="btn btn-primary" onclick="copyToClipboard()">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ========== WebSocket Configuration ==========
const WS_CONFIG = {
    host: window.location.hostname,
    port: 8080,
    protocol: window.location.protocol === 'https:' ? 'wss:' : 'ws:',
    reconnectInterval: 3000,
    maxReconnectAttempts: 5
};

let ws = null;
let reconnectAttempts = 0;
let reconnectTimer = null;

// ========== Auto Refresh Configuration ==========
let countdownInterval = null;
let refreshIntervalSeconds = 5;
let countdownValue = refreshIntervalSeconds;
let isRefreshing = false;

// ========== Current State ==========
let currentPage = <?= $page ?>;
let currentSearch = '';
let currentAction = '';
let currentTable = '';
let currentDate = '';

// ========== WebSocket Functions ==========
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
            showToastNotification('Connected', 'Real-time updates enabled', 'success');
            ws.send(JSON.stringify({ type: 'subscribe', channel: 'audit_updates' }));
        };
        
        ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            handleWebSocketMessage(data);
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
            console.error('WebSocket error:', err);
            updateWSStatus('disconnected', 'Error');
        };
    } catch (e) {
        console.error(e);
        updateWSStatus('disconnected', 'Failed');
    }
}

function handleWebSocketMessage(data) {
    console.log('WebSocket message:', data);
    
    const auditEvents = ['AUDIT_NEW', 'AUDIT_CREATED', 'OFFICER_CREATED', 'OFFICER_UPDATED', 
                          'OFFICER_DELETED', 'EVENT_CREATED', 'EVENT_UPDATED', 'EVENT_DELETED'];
    
    if (auditEvents.includes(data.type)) {
        let message = '';
        switch(data.type) {
            case 'OFFICER_CREATED':
                message = `New officer: ${data.payload?.full_name || 'Unknown'}`;
                break;
            case 'OFFICER_UPDATED':
                message = `Officer updated: ${data.payload?.officer_id || 'Unknown'}`;
                break;
            case 'OFFICER_DELETED':
                message = `Officer deleted: ${data.payload?.officer_id || 'Unknown'}`;
                break;
            case 'EVENT_CREATED':
                message = `New event: ${data.payload?.event_name || 'Unknown'}`;
                break;
            default:
                message = `New activity detected`;
        }
        
        showToastNotification('Live Update', message, 'info');
        refreshData();
    }
}

// ========== Toast Notification ==========
function showToastNotification(title, message, type = 'info') {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    const iconColor = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#17a2b8');
    const icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
    
    toast.style.borderLeftColor = iconColor;
    toast.innerHTML = `
        <i class="fas ${icon}" style="color: ${iconColor}"></i>
        <div>
            <div style="font-weight: 600; font-size: 13px;">${title}</div>
            <div class="message" style="font-size: 12px;">${message}</div>
        </div>
    `;
    
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ========== Auto Refresh Functions ==========
function startAutoRefresh() {
    if (countdownInterval) clearInterval(countdownInterval);
    
    countdownValue = refreshIntervalSeconds;
    updateCountdownDisplay();
    
    countdownInterval = setInterval(() => {
        if (!isRefreshing) {
            countdownValue--;
            updateCountdownDisplay();
            
            if (countdownValue <= 0) {
                countdownValue = refreshIntervalSeconds;
                refreshData();
            }
        }
    }, 1000);
}

function updateCountdownDisplay() {
    const timerSpan = document.getElementById('countdownTimer');
    if (timerSpan) timerSpan.textContent = countdownValue;
}

// ========== Refresh Data Function ==========
async function manualRefresh() {
    const refreshBtn = document.getElementById('refreshBtn');
    refreshBtn.classList.add('refreshing');
    countdownValue = refreshIntervalSeconds;
    updateCountdownDisplay();
    await refreshData();
    setTimeout(() => refreshBtn.classList.remove('refreshing'), 500);
}

async function refreshData() {
    if (isRefreshing) return;
    isRefreshing = true;
    
    try {
        let url = window.location.pathname + '?ajax=1&page=' + currentPage;
        if (currentSearch) url += '&search=' + encodeURIComponent(currentSearch);
        if (currentAction) url += '&action_filter=' + encodeURIComponent(currentAction);
        if (currentTable) url += '&table_filter=' + encodeURIComponent(currentTable);
        if (currentDate) url += '&date_filter=' + encodeURIComponent(currentDate);
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            updateTableBody(data.rows);
            updateStats(data.stats);
            updatePagination(data.pagination);
            document.getElementById('lastSyncTime').innerHTML = `Last sync: ${new Date().toLocaleTimeString()}`;
            animateStatsCards();
        }
    } catch (error) {
        console.error('Refresh failed:', error);
    } finally {
        isRefreshing = false;
    }
}

function updateTableBody(rows) {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;
    
    if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="empty-state"><i class="fas fa-database"></i><p>No audit logs found</p></td></tr>`;
        return;
    }
    
    const existingIds = new Set();
    tbody.querySelectorAll('tr[data-id]').forEach(row => {
        existingIds.add(row.getAttribute('data-id'));
    });
    
    let html = '';
    rows.forEach(row => {
        const isNew = !existingIds.has(row.id.toString());
        const newRowClass = isNew ? 'new-row' : '';
        const oldDataPreview = row.old_data ? (row.old_data.substring(0, 50) + '...') : '';
        const newDataPreview = row.new_data ? (row.new_data.substring(0, 50) + '...') : '';
        
        html += `
            <tr data-id="${row.id}" class="${newRowClass}">
                <td><strong>#${escapeHtml(row.id)}</strong></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-placeholder" style="width: 32px; height: 32px; background: linear-gradient(135deg, rgba(102,126,234,0.1), rgba(118,75,162,0.1)); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user" style="font-size: 12px; color: #667eea;"></i>
                        </div>
                        <span>${escapeHtml(row.officer_name || row.officer_id)}</span>
                    </div>
                </td>
                <td>
                    <span class="badge-custom badge-${row.action}">
                        <i class="fas ${getActionIcon(row.action)}"></i>
                        ${row.action}
                    </span>
                </td>
                <td><code style="background: #f8f9fa; padding: 4px 8px; border-radius: 6px; font-size: 11px;">${escapeHtml(row.table_name)}</code></td>
                <td><code style="font-size: 11px;">${escapeHtml(row.record_id || '-')}</code></td>
                <td class="json-preview" onclick='showDataModal(${JSON.stringify(row.old_data)}, "Old Data - Record #${row.id}")'>
                    ${row.old_data ? '<i class="fas fa-code"></i> ' + escapeHtml(oldDataPreview) : '<span class="text-muted">—</span>'}
                </td>
                <td class="json-preview" onclick='showDataModal(${JSON.stringify(row.new_data)}, "New Data - Record #${row.id}")'>
                    ${row.new_data ? '<i class="fas fa-code"></i> ' + escapeHtml(newDataPreview) : '<span class="text-muted">—</span>'}
                </td>
                <td>
                    <code class="ip-address">${escapeHtml(row.ip_address || '-')}</code>
                    ${row.user_agent ? `<i class="fas fa-info-circle info-icon" onclick="alert('User Agent: ${escapeHtml(row.user_agent).replace(/'/g, "\\'")}')" title="View User Agent"></i>` : ''}
                </td>
                <td>
                    <div class="d-flex flex-column">
                        <span style="font-weight: 600;">${row.created_at?.split(' ')[0] || '-'}</span>
                        <small class="text-muted">${row.created_at?.split(' ')[1] || '-'}</small>
                    </div>
                </td>
             </tr>
        `;
    });
    
    tbody.innerHTML = html;
    
    setTimeout(() => {
        tbody.querySelectorAll('.new-row').forEach(row => row.classList.remove('new-row'));
    }, 1000);
}

function getActionIcon(action) {
    const icons = {
        'CREATE': 'fa-plus',
        'UPDATE': 'fa-edit',
        'DELETE': 'fa-trash',
        'LOGIN': 'fa-sign-in-alt',
        'LOGOUT': 'fa-sign-out-alt'
    };
    return icons[action] || 'fa-info-circle';
}

function updateStats(stats) {
    if (!stats) return;
    
    const totalRecordsEl = document.getElementById('totalRecords');
    if (totalRecordsEl && stats.total_records !== undefined) {
        totalRecordsEl.textContent = stats.total_records.toLocaleString();
        animateNumber(totalRecordsEl);
    }
    
    const tablesTrackedEl = document.getElementById('tablesTracked');
    if (tablesTrackedEl && stats.tables_tracked !== undefined) {
        tablesTrackedEl.textContent = stats.tables_tracked;
        animateNumber(tablesTrackedEl);
    }
    
    const lastUpdatedEl = document.getElementById('lastUpdated');
    if (lastUpdatedEl && stats.last_updated) {
        lastUpdatedEl.textContent = stats.last_updated;
    }
    
    const totalRecordsCountEl = document.getElementById('totalRecordsCount');
    if (totalRecordsCountEl && stats.total_records !== undefined) {
        totalRecordsCountEl.textContent = stats.total_records.toLocaleString();
    }
}

function animateNumber(element) {
    element.classList.add('updated');
    setTimeout(() => element.classList.remove('updated'), 300);
}

function animateStatsCards() {
    document.querySelectorAll('.stat-card').forEach(card => {
        card.classList.add('updating');
        setTimeout(() => card.classList.remove('updating'), 500);
    });
}

function updatePagination(pagination) {
    if (!pagination || pagination.total_pages <= 1) {
        const paginationContainer = document.querySelector('.pagination-container');
        if (paginationContainer) paginationContainer.style.display = 'none';
        return;
    }
    
    const paginationContainer = document.querySelector('.pagination-container');
    if (paginationContainer) paginationContainer.style.display = 'flex';
    
    const startRecordSpan = document.getElementById('startRecord');
    const endRecordSpan = document.getElementById('endRecord');
    if (startRecordSpan) startRecordSpan.textContent = pagination.start_record;
    if (endRecordSpan) endRecordSpan.textContent = pagination.end_record;
    
    const paginationUl = document.getElementById('pagination');
    if (paginationUl) {
        let pagesHtml = `
            <li class="page-item ${!pagination.has_prev ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page - 1}">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;
        
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            pagesHtml += `
                <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `;
        }
        
        pagesHtml += `
            <li class="page-item ${!pagination.has_next ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.current_page + 1}">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;
        
        paginationUl.innerHTML = pagesHtml;
        
        paginationUl.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const page = parseInt(this.getAttribute('data-page'));
                if (page && !isNaN(page) && page !== currentPage) {
                    currentPage = page;
                    refreshData();
                }
            });
        });
    }
}

// ========== Filter Functions ==========
function filterTable() {
    currentSearch = document.getElementById('searchInput').value;
    currentAction = document.getElementById('actionFilter').value;
    currentTable = document.getElementById('tableFilter').value;
    currentDate = document.getElementById('dateFilter').value;
    currentPage = 1;
    refreshData();
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('actionFilter').value = '';
    document.getElementById('tableFilter').value = '';
    document.getElementById('dateFilter').value = '';
    currentSearch = '';
    currentAction = '';
    currentTable = '';
    currentDate = '';
    currentPage = 1;
    refreshData();
}

// ========== Helper Functions ==========
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showDataModal(data, title) {
    if (!data || data === '-' || data === 'null' || data === 'NULL' || data === '') {
        alert('No data available to display');
        return;
    }
    
    let formattedData = data;
    try {
        const parsed = JSON.parse(data);
        formattedData = JSON.stringify(parsed, null, 2);
    } catch(e) {
        formattedData = data;
    }
    
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-code"></i> ' + title;
    document.getElementById('modalContent').innerText = formattedData;
    window.currentModalData = formattedData;
    
    const modal = new bootstrap.Modal(document.getElementById('dataModal'));
    modal.show();
}

function copyToClipboard() {
    if (window.currentModalData) {
        navigator.clipboard.writeText(window.currentModalData).then(function() {
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(() => {
                btn.innerHTML = originalText;
            }, 2000);
        }).catch(function() {
            alert('Failed to copy to clipboard');
        });
    }
}

// ========== Initialize ==========
document.addEventListener('DOMContentLoaded', function() {
    initWebSocket();
    startAutoRefresh();
    
    const searchInput = document.getElementById('searchInput');
    const actionFilter = document.getElementById('actionFilter');
    const tableFilter = document.getElementById('tableFilter');
    const dateFilter = document.getElementById('dateFilter');
    
    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (actionFilter) actionFilter.addEventListener('change', filterTable);
    if (tableFilter) tableFilter.addEventListener('change', filterTable);
    if (dateFilter) dateFilter.addEventListener('change', filterTable);
    
    document.querySelectorAll('.page-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = parseInt(this.getAttribute('data-page'));
            if (page && !isNaN(page)) {
                currentPage = page;
                refreshData();
            }
        });
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            manualRefresh();
        }
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.getElementById('searchInput').focus();
        }
    });
});

window.addEventListener('beforeunload', function() {
    if (reconnectTimer) clearTimeout(reconnectTimer);
    if (ws) ws.close();
    if (countdownInterval) clearInterval(countdownInterval);
});
</script>
</body>
</html>
<?php
session_start();

// ========== AJAX HANDLERS (run first, before any output) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require __DIR__ . '/../../Connection/connection.php';

    error_reporting(0);
    ini_set('display_errors', 0);
    ob_clean();
    header('Content-Type: application/json');

    $response = ['status' => 'error', 'message' => 'Invalid action'];

    if (!isset($_SESSION['officer_id']) || $_SESSION['position'] !== 'Admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit();
    }

    if (!isset($_SESSION['officer_id'])) {
    header("Location: ../../officer_Login.php");
    exit();
}

    function logAudit($conn, $officer_id, $action, $table_name, $record_id = null, $old_data = null, $new_data = null) {
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
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    $action = $_POST['action'];
    $officer_id = $_POST['officer_id'] ?? '';

    if (empty($officer_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing officer ID']);
        exit();
    }

    function notifyWebSocket($data) {
        try {
            $socket = @fsockopen('127.0.0.1', 8081, $errno, $errstr, 0.5);
            if ($socket) {
                fwrite($socket, json_encode($data));
                fclose($socket);
            }
        } catch (Exception $e) {
            error_log("WebSocket notification error: " . $e->getMessage());
        }
    }

    try {
        if ($action === 'edit') {
            $old_stmt = $conn->prepare("SELECT full_name, position FROM officers WHERE officer_id = ?");
            $old_stmt->bind_param("s", $officer_id);
            $old_stmt->execute();
            $old_result = $old_stmt->get_result();
            $old_data = $old_result->fetch_assoc();
            $old_stmt->close();
            
            $full_name = trim($_POST['full_name'] ?? '');
            $position = $_POST['position'] ?? '';
            
            if (empty($full_name) || !in_array($position, ['Admin', 'Officer'])) {
                $response['message'] = 'Invalid input.';
            } else {
                $stmt = $conn->prepare("UPDATE officers SET full_name = ?, position = ? WHERE officer_id = ?");
                $stmt->bind_param("sss", $full_name, $position, $officer_id);
                if ($stmt->execute()) {
                    $changes = [];
                    if ($old_data['full_name'] != $full_name) {
                        $changes['full_name'] = ['old' => $old_data['full_name'], 'new' => $full_name];
                    }
                    if ($old_data['position'] != $position) {
                        $changes['position'] = ['old' => $old_data['position'], 'new' => $position];
                    }
                    
                    $new_data = ['full_name' => $full_name, 'position' => $position];
                    
                    logAudit($conn, $_SESSION['officer_id'], 'UPDATE', 'officers', $officer_id, 
                        json_encode(['old' => $old_data, 'changes' => $changes]), 
                        json_encode($new_data));
                    
                    notifyWebSocket([
                        'type' => 'OFFICER_UPDATED',
                        'payload' => [
                            'officer_id' => $officer_id,
                            'field' => 'full_name_position',
                            'value' => ['full_name' => $full_name, 'position' => $position]
                        ]
                    ]);
                    $response = ['status' => 'success', 'message' => 'Officer updated successfully.'];
                } else {
                    $response['message'] = 'Database error: ' . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($action === 'change_password') {
            // Get officer info before password change
            $info_stmt = $conn->prepare("SELECT full_name FROM officers WHERE officer_id = ?");
            $info_stmt->bind_param("s", $officer_id);
            $info_stmt->execute();
            $info_result = $info_stmt->get_result();
            $officer_info = $info_result->fetch_assoc();
            $info_stmt->close();
            
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($new_password) || strlen($new_password) < 6) {
                $response['message'] = 'Password must be at least 6 characters long.';
            } elseif ($new_password !== $confirm_password) {
                $response['message'] = 'Passwords do not match.';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE officers SET password = ? WHERE officer_id = ?");
                $stmt->bind_param("ss", $hashed_password, $officer_id);
                if ($stmt->execute()) {
                    // Log password change
                    logAudit($conn, $_SESSION['officer_id'], 'UPDATE', 'officers', $officer_id, 
                        json_encode(['action' => 'password_change', 'officer_name' => $officer_info['full_name']]), 
                        json_encode(['action' => 'password_changed', 'changed_by' => $_SESSION['officer_id'], 'timestamp' => date('Y-m-d H:i:s')]));
                    
                    notifyWebSocket([
                        'type' => 'OFFICER_UPDATED',
                        'payload' => ['officer_id' => $officer_id, 'field' => 'password', 'value' => '[CHANGED]']
                    ]);
                    $response = ['status' => 'success', 'message' => 'Password changed successfully.'];
                } else {
                    $response['message'] = 'Database error: ' . $stmt->error;
                }
                $stmt->close();
            }
        } elseif ($action === 'delete') {
            if ($officer_id === $_SESSION['officer_id']) {
                $response['message'] = 'You cannot delete your own account.';
            } else {
                $old_stmt = $conn->prepare("SELECT full_name, position, created_at FROM officers WHERE officer_id = ?");
                $old_stmt->bind_param("s", $officer_id);
                $old_stmt->execute();
                $old_result = $old_stmt->get_result();
                $deleted_officer = $old_result->fetch_assoc();
                $old_stmt->close();
                
                $stmt = $conn->prepare("DELETE FROM officers WHERE officer_id = ?");
                $stmt->bind_param("s", $officer_id);
                if ($stmt->execute()) {
                    logAudit($conn, $_SESSION['officer_id'], 'DELETE', 'officers', $officer_id, 
                        json_encode($deleted_officer), 
                        json_encode(['action' => 'deleted', 'deleted_by' => $_SESSION['officer_id']]));
                    
                    notifyWebSocket(['type' => 'OFFICER_DELETED', 'payload' => ['officer_id' => $officer_id]]);
                    $response = ['status' => 'success', 'message' => 'Officer deleted successfully.'];
                } else {
                    $response['message'] = 'Database error: ' . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $response['message'] = 'Unknown action.';
        }
    } catch (Exception $e) {
        $response['message'] = 'Server error: ' . $e->getMessage();
    }

    echo json_encode($response);
    ob_end_flush();
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    require __DIR__ . '/../../Connection/connection.php';
    header('Content-Type: application/json');
    $total = $conn->query("SELECT COUNT(*) FROM officers")->fetch_row()[0];
    $admin = $conn->query("SELECT COUNT(*) FROM officers WHERE position='Admin'")->fetch_row()[0];
    echo json_encode([
        'success' => true,
        'totalOfficers' => $total,
        'adminCount' => $admin,
        'officerCount' => $total - $admin
    ]);
    exit();
}

require __DIR__ . '/../../Connection/connection.php';
include __DIR__ . '/../sidebar/officer_sidebar.php';

function logPageAccess($conn, $officer_id, $action, $details = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $query = "INSERT INTO audit_logs (officer_id, action, table_name, record_id, new_data, ip_address, user_agent) 
              VALUES (?, ?, 'manage_officers_page', NULL, ?, ?, ?)";
    
    $data = json_encode(['action' => $action, 'timestamp' => date('Y-m-d H:i:s'), 'details' => $details]);
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "sssss", $officer_id, $action, $data, $ip_address, $user_agent);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!isset($_SESSION['officer_id']) || $_SESSION['position'] !== 'Admin') {
    logPageAccess($conn, $_SESSION['officer_id'] ?? 'unknown', 'ACCESS_DENIED', 'Non-admin tried to access manage officers page');
    header('Location: officer_dashboard.php');
    exit();
}

logPageAccess($conn, $_SESSION['officer_id'], 'VIEW', 'Accessed Manage Officers page');

$result = $conn->query("SELECT officer_id, full_name, position, created_at FROM officers ORDER BY created_at DESC");
$officers = $result->fetch_all(MYSQLI_ASSOC);
$totalOfficers = count($officers);
$adminCount = count(array_filter($officers, fn($o) => $o['position'] === 'Admin'));
$officerCount = $totalOfficers - $adminCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Officers | BEAMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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

        .main-contents {
            margin-left: 190px;
            padding: 30px 40px;
            transition: all 0.3s ease;
        }

        @media (max-width: 992px) {
            .main-contents {
                margin-left: 0;
                padding: 20px;
            }
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
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
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .stat-icon i {
            font-size: 28px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1a1a2e;
        }

        .stat-info p {
            font-size: 14px;
            color: #6c757d;
            margin: 0;
            font-weight: 500;
        }

        /* Main Card */
        .main-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .card-header-custom {
            background: white;
            padding: 24px 32px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .card-header-custom h4 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header-custom h4 i {
            color: #667eea;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-wrapper {
            position: relative;
        }

        .search-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            font-size: 18px;
        }

        .search-wrapper input {
            padding: 10px 16px 10px 44px;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            font-size: 14px;
            width: 280px;
            transition: all 0.3s ease;
        }

        .search-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-register {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-refresh {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px 16px;
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .btn-refresh:hover {
            background: #e9ecef;
            transform: rotate(180deg);
        }

        /* Officer Cards Grid */
        .officers-grid {
            padding: 32px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
        }

        .officer-card {
            background: white;
            border-radius: 20px;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }

        .officer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .officer-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border-color: transparent;
        }

        .officer-card:hover::before {
            transform: scaleX(1);
        }

        .card-content {
            padding: 24px;
        }

        .officer-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .officer-avatar {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .officer-avatar i {
            font-size: 28px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-admin {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
            color: #667eea;
        }

        .badge-officer {
            background: #f8f9fa;
            color: #6c757d;
        }

        .officer-name {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            margin: 16px 0 8px 0;
        }

        .officer-id {
            font-family: 'Monaco', monospace;
            font-size: 12px;
            color: #6c757d;
            background: #f8f9fa;
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 16px;
        }

        .join-date {
            font-size: 13px;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            padding-top: 16px;
            border-top: 1px solid #e9ecef;
        }

        .btn-edit, .btn-delete {
            flex: 1;
            padding: 8px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #fff3e0;
            color: #f59e0b;
            border: none;
        }

        .btn-edit:hover {
            background: #f59e0b;
            color: white;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #fee;
            color: #dc3545;
            border: none;
        }

        .btn-delete:hover:not(:disabled) {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
        }

        .btn-delete:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Modal Styles */
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

        .modal-title {
            font-weight: 600;
        }

        .modal-body {
            padding: 24px;
        }

        .form-label {
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 10px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-cancel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 8px 20px;
        }

        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 12px;
            padding: 8px 24px;
            color: white;
        }

        /* Password Section */
        .password-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 2px solid #e9ecef;
        }

        .password-section h6 {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-section h6 i {
            color: #667eea;
        }

        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
        }

        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }

        .strength-weak { color: #dc3545; }
        .strength-medium { color: #f59e0b; }
        .strength-strong { color: #28a745; }

        /* Toast */
        .toast-container {
            z-index: 1060;
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid white;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spin 0.75s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px;
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

        @media (max-width: 768px) {
            .officers-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }
            
            .search-wrapper input {
                width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="main-contents">
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-info">
                    <h3 id="totalOfficers"><?= $totalOfficers ?></h3>
                    <p>Total Officers</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="stat-info">
                    <h3 id="adminCount"><?= $adminCount ?></h3>
                    <p>Administrators</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div class="stat-info">
                    <h3 id="officerCount"><?= $officerCount ?></h3>
                    <p>Officers</p>
                </div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="main-card">
            <div class="card-header-custom">
                <h4>
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    Manage Officers
                </h4>
                <div class="header-actions">
                    <div class="search-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by name or ID...">
                    </div>
                    <button class="btn-refresh" id="refreshBtn" title="Refresh">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <a href="officer_registration.php" class="btn-register">
                        <i class="bi bi-person-plus-fill"></i> Register
                    </a>
                </div>
            </div>
            
            <div class="officers-grid" id="officersContainer">
                <?php if (empty($officers)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>No officers found. Click "Register" to add your first officer.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($officers as $officer): ?>
                        <div class="officer-card" data-id="<?= htmlspecialchars($officer['officer_id']) ?>"
                             data-name="<?= htmlspecialchars($officer['full_name']) ?>"
                             data-position="<?= htmlspecialchars($officer['position']) ?>">
                            <div class="card-content">
                                <div class="officer-header">
                                    <div class="officer-avatar">
                                        <i class="bi bi-person-circle"></i>
                                    </div>
                                    <span class="badge-status <?= $officer['position'] === 'Admin' ? 'badge-admin' : 'badge-officer' ?>">
                                        <?= htmlspecialchars($officer['position']) ?>
                                    </span>
                                </div>
                                <h5 class="officer-name"><?= htmlspecialchars($officer['full_name']) ?></h5>
                                <div class="officer-id">
                                    <i class="bi bi-card-text"></i> <?= htmlspecialchars($officer['officer_id']) ?>
                                </div>
                                <div class="join-date">
                                    <i class="bi bi-calendar3"></i>
                                    Joined <?= date('M d, Y', strtotime($officer['created_at'])) ?>
                                </div>
                                <div class="action-buttons">
                                    <button class="btn-edit edit-btn"
                                            data-id="<?= htmlspecialchars($officer['officer_id']) ?>"
                                            data-name="<?= htmlspecialchars($officer['full_name']) ?>"
                                            data-position="<?= htmlspecialchars($officer['position']) ?>">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button class="btn-delete delete-btn"
                                            data-id="<?= htmlspecialchars($officer['officer_id']) ?>"
                                            data-name="<?= htmlspecialchars($officer['full_name']) ?>"
                                            <?= $officer['officer_id'] === $_SESSION['officer_id'] ? 'disabled' : '' ?>>
                                        <i class="bi bi-trash3"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Modal with Password Change -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square"></i> Edit Officer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="officer_id" id="edit_officer_id">
                        
                        <!-- Basic Information Section -->
                        <h6><i class="bi bi-person-badge"></i> Basic Information</h6>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required placeholder="Enter full name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <select class="form-select" id="edit_position" name="position" required>
                                <option value="Admin">Administrator</option>
                                <option value="Officer">Officer</option>
                            </select>
                        </div>
                        
                        <!-- Password Change Section -->
                        <div class="password-section">
                            <h6><i class="bi bi-key"></i> Change Password (Optional)</h6>
                            <div class="mb-3 password-toggle">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                                <i class="bi bi-eye-slash toggle-password" data-target="new_password"></i>
                                <div class="password-strength" id="passwordStrength"></div>
                            </div>
                            <div class="mb-3 password-toggle">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                                <i class="bi bi-eye-slash toggle-password" data-target="confirm_password"></i>
                                <div class="invalid-feedback" id="passwordMatchError">Passwords do not match</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-save" id="editSubmitBtn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle-fill"></i> Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteOfficerName"></strong>?</p>
                    <p class="text-muted small">This action cannot be undone. All data associated with this officer will be permanently removed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-delete" id="confirmDelete" style="background: #dc3545; color: white;">Delete Officer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Messages -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
        <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            if (password.length === 0) return '';
            if (strength <= 2) return '<span class="strength-weak">Weak password</span>';
            if (strength <= 4) return '<span class="strength-medium">Medium password</span>';
            return '<span class="strength-strong">Strong password</span>';
        }
        
        $('#new_password').on('keyup', function() {
            const password = $(this).val();
            $('#passwordStrength').html(checkPasswordStrength(password));
            
            // Check if passwords match when both fields have values
            if ($('#confirm_password').val().length > 0) {
                if ($(this).val() !== $('#confirm_password').val()) {
                    $('#confirm_password').addClass('is-invalid');
                } else {
                    $('#confirm_password').removeClass('is-invalid');
                }
            }
        });
        
        $('#confirm_password').on('keyup', function() {
            if ($(this).val() !== $('#new_password').val()) {
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        // Toggle password visibility
        $('.toggle-password').click(function() {
            const target = $(this).data('target');
            const input = $('#' + target);
            const type = input.attr('type') === 'password' ? 'text' : 'password';
            input.attr('type', type);
            $(this).toggleClass('bi-eye bi-eye-slash');
        });
        
        // Search filter
        let searchTimeout;
        $('#searchInput').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchText = $(this).val().toLowerCase();
                $('.officer-card').each(function() {
                    const name = $(this).data('name').toLowerCase();
                    const id = $(this).data('id').toLowerCase();
                    $(this).toggle(name.includes(searchText) || id.includes(searchText));
                });
            }, 300);
        });

        $('#refreshBtn').click(() => location.reload());

        function showToast(type, message) {
            const toast = type === 'success' ? $('#successToast') : $('#errorToast');
            toast.find('.toast-body').text(message);
            const bsToast = new bootstrap.Toast(toast[0], { delay: 3000 });
            bsToast.show();
        }

        function setLoading(btn, isLoading) {
            if (isLoading) {
                btn.data('original-text', btn.html());
                btn.prop('disabled', true);
                btn.html('<span class="loading-spinner"></span> Saving...');
            } else {
                btn.prop('disabled', false);
                btn.html(btn.data('original-text'));
            }
        }

        // Edit functionality with password change
        let currentEditCard = null;
        $(document).on('click', '.edit-btn', function() {
            currentEditCard = $(this).closest('.officer-card');
            $('#edit_officer_id').val($(this).data('id'));
            $('#edit_full_name').val($(this).data('name'));
            $('#edit_position').val($(this).data('position'));
            // Clear password fields
            $('#new_password').val('');
            $('#confirm_password').val('');
            $('#passwordStrength').html('');
            $('#confirm_password').removeClass('is-invalid');
            $('#editModal').modal('show');
        });

        $('#editForm').submit(function(e) {
            e.preventDefault();
            const submitBtn = $('#editSubmitBtn');
            setLoading(submitBtn, true);
            
            const formData = new FormData(this);
            const newPassword = $('#new_password').val();
            
            // If password is being changed, send separate request
            if (newPassword.length > 0) {
                // First update basic info
                const basicData = new FormData();
                basicData.append('action', 'edit');
                basicData.append('officer_id', $('#edit_officer_id').val());
                basicData.append('full_name', $('#edit_full_name').val());
                basicData.append('position', $('#edit_position').val());
                
                fetch('manage_officers.php', {
                    method: 'POST',
                    body: basicData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Then update password
                        const passwordData = new FormData();
                        passwordData.append('action', 'change_password');
                        passwordData.append('officer_id', $('#edit_officer_id').val());
                        passwordData.append('new_password', $('#new_password').val());
                        passwordData.append('confirm_password', $('#confirm_password').val());
                        
                        return fetch('manage_officers.php', {
                            method: 'POST',
                            body: passwordData
                        });
                    } else {
                        throw new Error(data.message);
                    }
                })
                .then(res => res.json())
                .then(data => {
                    setLoading(submitBtn, false);
                    if (data.status === 'success') {
                        $('#editModal').modal('hide');
                        showToast('success', 'Officer updated and password changed successfully!');
                        
                        // Update UI
                        const newName = $('#edit_full_name').val();
                        const newPosition = $('#edit_position').val();
                        currentEditCard.find('.officer-name').text(newName);
                        const badge = currentEditCard.find('.badge-status');
                        badge.text(newPosition);
                        badge.removeClass('badge-admin badge-officer');
                        badge.addClass(newPosition === 'Admin' ? 'badge-admin' : 'badge-officer');
                        currentEditCard.data('name', newName);
                        currentEditCard.data('position', newPosition);
                        
                        refreshStats();
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(err => {
                    setLoading(submitBtn, false);
                    showToast('error', err.message || 'Network error. Please try again.');
                });
            } else {
                // Just update basic info without password change
                fetch('manage_officers.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    setLoading(submitBtn, false);
                    if (data.status === 'success') {
                        $('#editModal').modal('hide');
                        showToast('success', data.message);
                        
                        const newName = $('#edit_full_name').val();
                        const newPosition = $('#edit_position').val();
                        currentEditCard.find('.officer-name').text(newName);
                        const badge = currentEditCard.find('.badge-status');
                        badge.text(newPosition);
                        badge.removeClass('badge-admin badge-officer');
                        badge.addClass(newPosition === 'Admin' ? 'badge-admin' : 'badge-officer');
                        currentEditCard.data('name', newName);
                        currentEditCard.data('position', newPosition);
                        
                        refreshStats();
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(err => {
                    setLoading(submitBtn, false);
                    showToast('error', 'Network error. Please try again.');
                });
            }
        });

        function refreshStats() {
            $.ajax({
                url: window.location.pathname,
                method: 'GET',
                data: { action: 'get_stats' },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $('#totalOfficers').text(res.totalOfficers);
                        $('#adminCount').text(res.adminCount);
                        $('#officerCount').text(res.officerCount);
                    }
                }
            });
        }

        // Delete functionality
        let deleteOfficerId = null;
        $(document).on('click', '.delete-btn', function() {
            if ($(this).is(':disabled')) return;
            deleteOfficerId = $(this).data('id');
            $('#deleteOfficerName').text($(this).data('name'));
            $('#deleteModal').modal('show');
        });

        $('#confirmDelete').click(function() {
            if (!deleteOfficerId) return;
            const btn = $(this);
            setLoading(btn, true);
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('officer_id', deleteOfficerId);
            
            fetch('manage_officers.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                setLoading(btn, false);
                $('#deleteModal').modal('hide');
                if (data.status === 'success') {
                    showToast('success', data.message);
                    $(`.officer-card[data-id="${deleteOfficerId}"]`).remove();
                    refreshStats();
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(err => {
                setLoading(btn, false);
                showToast('error', 'Network error. Please try again.');
            });
        });
    });
    </script>
</body>
</html>
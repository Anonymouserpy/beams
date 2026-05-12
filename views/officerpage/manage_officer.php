<?php
session_start();

// ========== AJAX HANDLERS (run first, before any output) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Only include database connection for AJAX
    require __DIR__ . '/../../Connection/connection.php';

    // Clean output buffers and disable error display to ensure clean JSON
    error_reporting(0);
    ini_set('display_errors', 0);
    ob_clean();
    header('Content-Type: application/json');

    $response = ['status' => 'error', 'message' => 'Invalid action'];

    // Verify admin session
    if (!isset($_SESSION['officer_id']) || $_SESSION['position'] !== 'Admin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit();
    }

    $action = $_POST['action'];
    $officer_id = $_POST['officer_id'] ?? '';

    if (empty($officer_id)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing officer ID']);
        exit();
    }

    // WebSocket helper
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
            $full_name = trim($_POST['full_name'] ?? '');
            $position = $_POST['position'] ?? '';
            if (empty($full_name) || !in_array($position, ['Admin', 'Officer'])) {
                $response['message'] = 'Invalid input.';
            } else {
                $stmt = $conn->prepare("UPDATE officers SET full_name = ?, position = ? WHERE officer_id = ?");
                $stmt->bind_param("sss", $full_name, $position, $officer_id);
                if ($stmt->execute()) {
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
        } elseif ($action === 'reset_password') {
            $newPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE officers SET password = ? WHERE officer_id = ?");
            $stmt->bind_param("ss", $hashed, $officer_id);
            if ($stmt->execute()) {
                notifyWebSocket([
                    'type' => 'OFFICER_UPDATED',
                    'payload' => ['officer_id' => $officer_id, 'field' => 'password', 'value' => '[RESET]']
                ]);
                $response = ['status' => 'success', 'message' => 'Password reset successfully.', 'new_password' => $newPassword];
            } else {
                $response['message'] = 'Database error: ' . $stmt->error;
            }
            $stmt->close();
        } elseif ($action === 'delete') {
            if ($officer_id === $_SESSION['officer_id']) {
                $response['message'] = 'You cannot delete your own account.';
            } else {
                $stmt = $conn->prepare("DELETE FROM officers WHERE officer_id = ?");
                $stmt->bind_param("s", $officer_id);
                if ($stmt->execute()) {
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

// Handle GET AJAX for stats
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

// ========== NORMAL PAGE LOAD ==========
require __DIR__ . '/../../Connection/connection.php';
include __DIR__ . '/../sidebar/officer_sidebar.php';

// Admin only (for page view)
if (!isset($_SESSION['officer_id']) || $_SESSION['position'] !== 'Admin') {
    header('Location: officer_dashboard.php');
    exit();
}

// Fetch all officers for initial load
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
    <style>
    /* ========== ALL ORIGINAL STYLES (KEEP) ========== */
    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #e9eef3 100%);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    .main-contents {
        margin-left: 220px;
        padding: 30px;
        transition: all 0.3s ease;
    }

    @media (max-width: 992px) {
        .main-contents {
            margin-left: 0;
            padding: 20px;
        }
    }

    .card {
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 0.75rem 1.5rem rgba(0, 0, 0, 0.05);
    }

    .stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 1rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .officer-card .card {
        height: 100%;
        transition: all 0.3s ease;
    }

    .officer-card:hover .card {
        transform: translateY(-5px);
        box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.12);
    }

    .card-header-custom {
        background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
        color: white;
        padding: 1rem 1.5rem;
        border-bottom: none;
    }

    .officer-id {
        font-family: monospace;
        font-size: 0.85rem;
        background: #f1f5f9;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.3rem 0.8rem;
        border-radius: 2rem;
        color: #2c3e50;
    }

    .badge-admin {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.4rem 1rem;
        border-radius: 2rem;
    }

    .badge-officer {
        background: #6c757d;
        color: white;
        padding: 0.4rem 1rem;
        border-radius: 2rem;
    }

    .action-buttons .btn {
        border-radius: 2rem;
        padding: 0.3rem 0.8rem;
        font-size: 0.8rem;
    }

    .search-box {
        max-width: 300px;
    }

    .search-box .form-control {
        border-radius: 2rem;
        padding-left: 2rem;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: 0.75rem center;
        background-size: 1rem;
    }

    .loading-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 0.2em solid currentColor;
        border-right-color: transparent;
        border-radius: 50%;
        animation: spinner-border 0.75s linear infinite;
        margin-right: 0.5rem;
    }

    @keyframes spinner-border {
        to {
            transform: rotate(360deg);
        }
    }

    .toast-container {
        z-index: 1060;
    }

    /* WebSocket status */
    .ws-status {
        position: fixed;
        bottom: 20px;
        left: 250px;
        background: white;
        border-radius: 30px;
        padding: 0.4rem 1rem;
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        z-index: 1050;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        border: 1px solid transparent;
    }

    @media (max-width: 992px) {
        .ws-status {
            left: 20px;
        }
    }

    .ws-status.connected {
        color: #28a745;
        border-color: #28a745;
    }

    .ws-status.disconnected {
        color: #dc3545;
        border-color: #dc3545;
    }

    .ws-status.connecting {
        color: #ffc107;
        border-color: #ffc107;
    }

    .ws-status i {
        font-size: 0.5rem;
    }
    </style>
</head>

<body>
    <!-- WebSocket Status -->
    <div class="ws-status disconnected" id="wsStatus">
        <i class="fas fa-circle"></i>
        <span>Offline</span>
    </div>

    <div class="main-contents">
        <!-- Stats row -->
        <div class="row mb-4" id="statsRow">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><i class="bi bi-people-fill fs-1"></i></div>
                        <div class="text-end">
                            <h3 class="mb-0" id="totalOfficers"><?= $totalOfficers ?></h3>
                            <small>Total Officers</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><i class="bi bi-shield-check fs-1"></i></div>
                        <div class="text-end">
                            <h3 class="mb-0" id="adminCount"><?= $adminCount ?></h3>
                            <small>Admins</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><i class="bi bi-person-badge fs-1"></i></div>
                        <div class="text-end">
                            <h3 class="mb-0" id="officerCount"><?= $officerCount ?></h3>
                            <small>Officers</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main card -->
        <div class="card">
            <div class="card-header-custom d-flex align-items-center justify-content-between flex-wrap">
                <div><i class="bi bi-grid-3x3-gap-fill me-2"></i> Manage Officers</div>
                <div class="d-flex gap-2 mt-2 mt-sm-0">
                    <div class="search-box">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by name or ID...">
                    </div>
                    <button class="btn btn-light btn-refresh" id="refreshBtn" title="Refresh">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                    <a href="officer_registration.php" class="btn btn-light">
                        <i class="bi bi-person-plus-fill"></i> Register
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row" id="officersContainer">
                    <?php if (empty($officers)): ?>
                    <div class="col-12">
                        <div class="empty-state text-center p-5">
                            <i class="bi bi-inbox fs-1"></i>
                            <p class="mt-2">No officers found.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($officers as $officer): ?>
                    <div class="col-lg-4 col-md-6 officer-card"
                        data-id="<?= htmlspecialchars($officer['officer_id']) ?>"
                        data-name="<?= htmlspecialchars($officer['full_name']) ?>"
                        data-position="<?= htmlspecialchars($officer['position']) ?>">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="officer-id">
                                    <i class="bi bi-person-badge"></i> <?= htmlspecialchars($officer['officer_id']) ?>
                                </div>
                                <h5 class="card-title"><?= htmlspecialchars($officer['full_name']) ?></h5>
                                <p class="card-text">
                                    <span
                                        class="<?= $officer['position'] === 'Admin' ? 'badge-admin' : 'badge-officer' ?>">
                                        <?= htmlspecialchars($officer['position']) ?>
                                    </span>
                                </p>
                                <p class="card-text text-muted small">
                                    <i class="bi bi-calendar3"></i> Joined:
                                    <?= date('M d, Y', strtotime($officer['created_at'])) ?>
                                </p>
                                <div class="action-buttons mt-3">
                                    <button class="btn btn-outline-warning btn-sm edit-btn"
                                        data-id="<?= htmlspecialchars($officer['officer_id']) ?>"
                                        data-name="<?= htmlspecialchars($officer['full_name']) ?>"
                                        data-position="<?= htmlspecialchars($officer['position']) ?>">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button class="btn btn-outline-info btn-sm reset-pwd-btn"
                                        data-id="<?= htmlspecialchars($officer['officer_id']) ?>"
                                        data-name="<?= htmlspecialchars($officer['full_name']) ?>">
                                        <i class="bi bi-key"></i> Reset
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm delete-btn"
                                        data-id="<?= htmlspecialchars($officer['officer_id']) ?>"
                                        data-name="<?= htmlspecialchars($officer['full_name']) ?>"
                                        <?= $officer['officer_id'] === $_SESSION['officer_id'] ? 'disabled' : '' ?>>
                                        <i class="bi bi-trash3"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Officer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="officer_id" id="edit_officer_id">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <select class="form-select" id="edit_position" name="position" required>
                                <option value="Admin">Admin</option>
                                <option value="Officer">Officer</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editSubmitBtn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetPwdModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reset the password for <strong id="resetOfficerName"></strong>?</p>
                    <p>A new random password will be generated. You'll be able to copy it after confirmation.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" id="confirmResetPwd">Confirm Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Officer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteOfficerName"></strong>? This action cannot be
                        undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1060">
        <div id="successToast" class="toast bg-success text-white" role="alert" data-bs-autohide="true"
            data-bs-delay="3000">
            <div class="toast-header bg-success text-white">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="successToastBody"></div>
        </div>
        <div id="errorToast" class="toast bg-danger text-white" role="alert" data-bs-autohide="true"
            data-bs-delay="5000">
            <div class="toast-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong class="me-auto">Error</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="errorToastBody"></div>
        </div>
        <div id="infoToast" class="toast bg-info text-white" role="alert" data-bs-autohide="true" data-bs-delay="8000">
            <div class="toast-header bg-info text-white">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong class="me-auto">New Password</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="infoToastBody">
                <span id="newPasswordText"></span>
                <button class="btn btn-sm btn-light mt-2" id="copyPasswordBtn">Copy to Clipboard</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // ========== WebSocket ==========
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
            if (data.type === 'OFFICER_UPDATED') {
                const payload = data.payload;
                const officerId = payload.officer_id;
                const card = $(`.officer-card[data-id="${officerId}"]`);
                if (card.length && payload.value && payload.value.full_name && payload.value.position) {
                    card.find('.card-title').text(payload.value.full_name);
                    const badge = card.find('.badge-admin, .badge-officer');
                    badge.text(payload.value.position);
                    badge.removeClass('badge-admin badge-officer');
                    badge.addClass(payload.value.position === 'Admin' ? 'badge-admin' : 'badge-officer');
                    card.data('name', payload.value.full_name);
                    card.data('position', payload.value.position);
                    card.find('.edit-btn').data('name', payload.value.full_name).data('position', payload.value
                        .position);
                    card.find('.reset-pwd-btn').data('name', payload.value.full_name);
                }
                refreshStats();
            } else if (data.type === 'OFFICER_DELETED') {
                const officerId = data.payload.officer_id;
                const card = $(`.officer-card[data-id="${officerId}"]`);
                if (card.length) {
                    card.remove();
                    if ($('.officer-card').length === 0) {
                        $('#officersContainer').html(`
                            <div class="col-12">
                                <div class="empty-state text-center p-5">
                                    <i class="bi bi-inbox fs-1"></i>
                                    <p class="mt-2">No officers found.</p>
                                </div>
                            </div>
                        `);
                    }
                    refreshStats();
                }
            }
        }

        function refreshStats() {
            $.ajax({
                url: window.location.pathname,
                method: 'GET',
                data: {
                    action: 'get_stats'
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        $('#totalOfficers').text(res.totalOfficers);
                        $('#adminCount').text(res.adminCount);
                        $('#officerCount').text(res.officerCount);
                    }
                },
                error: function() {
                    // Fallback: recalc from DOM
                    const total = $('.officer-card').length;
                    const admins = $('.officer-card .badge-admin').length;
                    $('#totalOfficers').text(total);
                    $('#adminCount').text(admins);
                    $('#officerCount').text(total - admins);
                }
            });
        }

        initWebSocket();

        // ========== Search filter ==========
        let searchTimeout;
        $('#searchInput').on('keyup', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchText = $(this).val().toLowerCase();
                $('.officer-card').each(function() {
                    const name = $(this).data('name').toLowerCase();
                    const id = $(this).data('id').toLowerCase();
                    $(this).toggle(name.includes(searchText) || id.includes(
                        searchText));
                });
                if ($('.officer-card:visible').length === 0 && $('#emptyMessage').length ===
                    0) {
                    $('#officersContainer').append(`
                        <div id="emptyMessage" class="col-12">
                            <div class="empty-state text-center p-5">
                                <i class="bi bi-search"></i>
                                <p class="mt-2">No officers match your search.</p>
                            </div>
                        </div>
                    `);
                } else if ($('.officer-card:visible').length > 0) {
                    $('#emptyMessage').remove();
                }
            }, 300);
        });

        $('#refreshBtn').click(() => location.reload());

        // ========== Toast helper ==========
        function showToast(type, message, extra = null) {
            let toastId = 'successToast';
            if (type === 'error') toastId = 'errorToast';
            else if (type === 'info') toastId = 'infoToast';

            if (type === 'info') {
                $('#newPasswordText').text(extra || message);
                $('#copyPasswordBtn').off('click').on('click', function() {
                    navigator.clipboard.writeText($('#newPasswordText').text()).then(() => {
                        showToast('success', 'Password copied to clipboard!');
                    });
                });
            } else {
                $(`#${toastId} .toast-body`).text(message);
            }
            new bootstrap.Toast(document.getElementById(toastId)).show();
        }

        function setLoading(btn, isLoading) {
            if (isLoading) {
                btn.data('original-text', btn.html());
                btn.prop('disabled', true);
                btn.html('<span class="loading-spinner"></span> Loading...');
            } else {
                btn.prop('disabled', false);
                btn.html(btn.data('original-text'));
            }
        }

        // ========== Edit modal ==========
        let currentEditCard = null;
        $(document).on('click', '.edit-btn', function() {
            currentEditCard = $(this).closest('.officer-card');
            $('#edit_officer_id').val($(this).data('id'));
            $('#edit_full_name').val($(this).data('name'));
            $('#edit_position').val($(this).data('position'));
            $('#editModal').modal('show');
        });

        $('#editForm').submit(function(e) {
            e.preventDefault();
            const submitBtn = $('#editSubmitBtn');
            setLoading(submitBtn, true);
            const formData = new FormData(this);
            fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                })
                .then(async res => {
                    if (!res.ok) {
                        const text = await res.text();
                        throw new Error(`HTTP ${res.status}: ${text}`);
                    }
                    return res.json();
                })
                .then(data => {
                    setLoading(submitBtn, false);
                    if (data.status === 'success') {
                        $('#editModal').modal('hide');
                        showToast('success', data.message);
                        // Update UI immediately (WebSocket will also update but this is faster)
                        const newName = $('#edit_full_name').val();
                        const newPosition = $('#edit_position').val();
                        currentEditCard.find('.card-title').text(newName);
                        const badge = currentEditCard.find('.badge-admin, .badge-officer');
                        badge.text(newPosition);
                        badge.removeClass('badge-admin badge-officer');
                        badge.addClass(newPosition === 'Admin' ? 'badge-admin' : 'badge-officer');
                        currentEditCard.data('name', newName);
                        currentEditCard.data('position', newPosition);
                        currentEditCard.find('.edit-btn').data('name', newName).data('position',
                            newPosition);
                        currentEditCard.find('.reset-pwd-btn').data('name', newName);
                        refreshStats();
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    setLoading(submitBtn, false);
                    showToast('error', 'Network error. Please try again.');
                });
        });

        // ========== Reset Password ==========
        let resetOfficerId = null;
        $(document).on('click', '.reset-pwd-btn', function() {
            resetOfficerId = $(this).data('id');
            $('#resetOfficerName').text($(this).data('name'));
            $('#resetPwdModal').modal('show');
        });

        $('#confirmResetPwd').click(function() {
            if (!resetOfficerId) return;
            const btn = $(this);
            setLoading(btn, true);
            const formData = new FormData();
            formData.append('action', 'reset_password');
            formData.append('officer_id', resetOfficerId);
            fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                })
                .then(async res => {
                    if (!res.ok) throw new Error(await res.text());
                    return res.json();
                })
                .then(data => {
                    setLoading(btn, false);
                    $('#resetPwdModal').modal('hide');
                    if (data.status === 'success') {
                        showToast('info', `New password generated`, data.new_password);
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(err => {
                    console.error('Reset error:', err);
                    setLoading(btn, false);
                    showToast('error', 'Network error. Please try again.');
                });
        });

        // ========== Delete ==========
        let deleteOfficerId = null;
        $(document).on('click', '.delete-btn', function() {
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
            fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                })
                .then(async res => {
                    if (!res.ok) throw new Error(await res.text());
                    return res.json();
                })
                .then(data => {
                    setLoading(btn, false);
                    $('#deleteModal').modal('hide');
                    if (data.status === 'success') {
                        showToast('success', data.message);
                        $(`.officer-card[data-id="${deleteOfficerId}"]`).remove();
                        if ($('.officer-card').length === 0) {
                            $('#officersContainer').html(`
                                <div class="col-12">
                                    <div class="empty-state text-center p-5">
                                        <i class="bi bi-inbox fs-1"></i>
                                        <p class="mt-2">No officers found.</p>
                                    </div>
                                </div>
                            `);
                        }
                        refreshStats();
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(err => {
                    console.error('Delete error:', err);
                    setLoading(btn, false);
                    showToast('error', 'Network error. Please try again.');
                });
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (reconnectTimer) clearTimeout(reconnectTimer);
            if (ws) ws.close();
        });
    });
    </script>
</body>

</html>
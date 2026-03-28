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
            $stmt = $conn->prepare("INSERT INTO student_fines (student_id, event_id, fine_reason, amount, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sids", $student_id, $event_id, $reason, $amount, $status);
            if ($stmt->execute()) {
                $fine_id = $stmt->insert_id;
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
            $stmt = $conn->prepare("UPDATE student_fines SET student_id=?, event_id=?, fine_reason=?, amount=?, status=? WHERE fine_id=?");
            $stmt->bind_param("sidsi", $student_id, $event_id, $reason, $amount, $status, $fine_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
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

        $stmt = $conn->prepare("DELETE FROM student_fines WHERE fine_id = ?");
        $stmt->bind_param("i", $fine_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
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

        $stmt = $conn->prepare("UPDATE student_fines SET status = ? WHERE fine_id = ?");
        $stmt->bind_param("si", $new_status, $fine_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
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

        $stmt = $conn->prepare("UPDATE student_fines SET status = 'paid' WHERE student_id = ? AND status = 'unpaid'");
        $stmt->bind_param("s", $student_id);
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            if ($affected > 0) {
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

// --- For GET requests, include sidebar and display the page ---
include "../sidebar/officer_sidebar.php";

// --- Filter and Data Fetching ---
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Fetch aggregated student data:
// total amount, total count, paid count, unpaid count, and unpaid total
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
if ($filter_status === 'unpaid') {
    // Show only students who have at least one unpaid fine
    $query .= " GROUP BY sf.student_id HAVING unpaid_count > 0";
} elseif ($filter_status === 'paid') {
    // Show only students who have no unpaid fines (all paid)
    $query .= " GROUP BY sf.student_id HAVING unpaid_count = 0";
} else {
    $query .= " GROUP BY sf.student_id";
}
$query .= " ORDER BY s.full_name ASC";
$result = $conn->query($query);
$students_agg = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Compute totals for the stats cards
$total_fines = array_sum(array_column($students_agg, 'total_fines'));
$total_amount = array_sum(array_column($students_agg, 'total_amount'));
$unpaid_count = array_sum(array_column($students_agg, 'unpaid_count'));
$unpaid_amount = array_sum(array_column($students_agg, 'unpaid_total'));

// Build unpaid summary array (used for the Pay All button)
$unpaid_summary = [];
foreach ($students_agg as $student) {
    if ($student['unpaid_total'] > 0) {
        $unpaid_summary[$student['student_id']] = [
            'unpaid_count' => $student['unpaid_count'],
            'unpaid_total' => $student['unpaid_total']
        ];
    }
}

// Fetch students for dropdown (for add/edit modals)
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $config['app_name'] ?></title>
    <!-- Bootstrap 5.3.2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Font (Inter) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
    <style>
    /* Your existing styles – unchanged */
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
        background-color: var(--gray-100);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
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


    /* Stats cards */
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

    /* Badges */
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

    /* Buttons */
    .btn-icon {
        padding: 0.35rem 0.7rem;
        border-radius: 0.75rem;
        font-size: 0.85rem;
        transition: all 0.15s;
        border: 1px solid transparent;
    }

    .btn-icon:hover {
        transform: translateY(-2px);
        filter: brightness(0.95);
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

    /* Pay-all button */
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

    /* Main table */
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

    /* Modals */
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

    /* Alerts */
    .alert {
        border-radius: 1.2rem;
        border: none;
        padding: 1.2rem 2rem;
        font-weight: 500;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
    }

    /* Loading spinner */
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

    /* Responsive table */
    @media (max-width: 992px) {
        .fines-table {
            overflow-x: auto;
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

            <!-- Stats Cards: Four cards as in your image -->
            <div class="row g-4 mb-5">
                <div class="col-sm-6 col-lg-3">
                    <div class="stats-card d-flex align-items-center">
                        <div class="stats-icon bg-primary bg-opacity-10 text-primary me-3">
                            <i class="bi bi-receipt"></i>
                        </div>
                        <div>
                            <span class="text-secondary-emphasis small text-uppercase">Total Fines</span>
                            <h3 class="mb-0 fw-bold"><?= $total_fines ?></h3>
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
                            <h3 class="mb-0 fw-bold"><?= $unpaid_count ?></h3>
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

            <!-- Filter Bar -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="?status=" class="filter-btn <?= $filter_status == '' ? 'active' : '' ?>">
                    <i class="bi bi-list-ul me-2"></i>All
                </a>
                <a href="?status=unpaid" class="filter-btn <?= $filter_status == 'unpaid' ? 'active' : '' ?>">
                    <i class="bi bi-exclamation-triangle me-2"></i>Unpaid
                </a>
                <a href="?status=paid" class="filter-btn <?= $filter_status == 'paid' ? 'active' : '' ?>">
                    <i class="bi bi-check-circle me-2"></i>Paid
                </a>
                <?php if ($filter_status): ?>
                <a href="?" class="filter-btn">
                    <i class="bi bi-x-circle me-2"></i>Clear Filters
                </a>
                <?php endif; ?>
            </div>

            <!-- Main Table -->
            <?php if (empty($students_agg)): ?>
            <div class="alert alert-info py-4 text-center">No fines found.</div>
            <?php else: ?>
            <div class="fines-table">
                <table class="table align-middle">
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
                        <tr id="student-row-<?= $student_id_esc ?>">
                            <td class="student-name align-middle"><?= $student_name_esc ?></td>
                            <td>Absent Event</td>
                            <td class="fw-semibold">
                                <?= $config['currency'] ?><?= number_format($student['total_amount'], 2) ?>
                            </td>
                            <td><?= $status_badge ?></td>
                            <td>
                                <?php if ($student['unpaid_count'] > 0): ?>
                                <button class="btn pay-all-btn" data-student="<?= $student_id_esc ?>"
                                    title="Pay all unpaid fines for this student">
                                    <i class="bi bi-cash me-1"></i> Pay All
                                    <?= $config['currency'] ?><?= number_format($student['unpaid_total'], 2) ?>
                                </button>
                                <?php else: ?>
                                <span class="text-muted small">All paid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Fine Modal (unchanged) -->
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function() {
        // Show alert function
        function showAlert(type, message) {
            let alertBox = type === 'success' ? $('#successAlert') : $('#errorAlert');
            alertBox.text(message).fadeIn().delay(4000).fadeOut();
        }

        // Add Fine
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

        // Pay All Unpaid for a student
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

        // Auto-hide alerts on modal open
        $('#addFineModal, #editFineModal').on('show.bs.modal', function() {
            $('.alert').fadeOut();
        });
    });
    </script>
</body>

</html>
<?php
session_start();
include "../sidebar/officer_sidebar.php";
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
                jsonResponse('success', 'Fine added successfully.');
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
            jsonResponse('success', "$affected unpaid fine(s) marked as paid.");
        } else {
            jsonResponse('error', 'Database error: ' . $conn->error);
        }
        $stmt->close();
    }

    jsonResponse('error', 'Invalid action.');
}

// --- Filter and Data Fetching ---
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Fetch fines with student and event details
$query = "
    SELECT 
        sf.fine_id,
        sf.student_id,
        -- 👇 Replace 'full_name' with the actual column name for student name
        s.full_name AS student_name,
        sf.event_id,
        -- 👇 Replace 'event_name' with the actual column name for event name
        e.event_name AS event_name,
        sf.fine_reason,
        sf.amount,
        sf.status,
        sf.recorded_at
    FROM student_fines sf
    LEFT JOIN students s ON sf.student_id = s.student_id
    LEFT JOIN events e ON sf.event_id = e.event_id
";
if ($filter_status) {
    $query .= " WHERE sf.status = '" . $conn->real_escape_string($filter_status) . "'";
}
$query .= " ORDER BY sf.recorded_at DESC";
$result = $conn->query($query);
$fines = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

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

// Calculate summary statistics
$total_fines = count($fines);
$total_amount = array_sum(array_column($fines, 'amount'));
$unpaid_count = count(array_filter($fines, fn($f) => $f['status'] === 'unpaid'));
$unpaid_amount = array_sum(array_column(array_filter($fines, fn($f) => $f['status'] === 'unpaid'), 'amount'));
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
    <!-- DataTables & Buttons -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <style>
    :root {
        --primary: #2563eb;
        --success: #16a34a;
        --danger: #dc2626;
        --warning: #ca8a04;
        --dark: #1e293b;
        --light: #f8fafc;
    }

    body {
        background-color: #f1f5f9;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .main-content {
        margin-left: 280px;
        padding: 2rem;
        transition: margin 0.3s;
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            padding: 1rem;
        }
    }

    .card {
        border: none;
        border-radius: 1.5rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    }

    .card-header {
        background: white;
        border-bottom: 1px solid #e2e8f0;
        padding: 1.5rem 1.5rem 0.5rem 1.5rem;
        font-weight: 600;
        font-size: 1.35rem;
        color: var(--dark);
        border-radius: 1.5rem 1.5rem 0 0 !important;
    }

    .stats-card {
        background: white;
        border-radius: 1.25rem;
        padding: 1.25rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s;
    }

    .stats-card:hover {
        transform: translateY(-2px);
    }

    .stats-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .badge-paid {
        background: #dcfce7;
        color: #166534;
        padding: 0.35rem 1rem;
        border-radius: 100px;
        font-weight: 500;
        font-size: 0.85rem;
    }

    .badge-unpaid {
        background: #fee2e2;
        color: #991b1b;
        padding: 0.35rem 1rem;
        border-radius: 100px;
        font-weight: 500;
        font-size: 0.85rem;
    }

    .btn-icon {
        padding: 0.4rem 0.8rem;
        border-radius: 0.75rem;
        font-size: 0.85rem;
        transition: all 0.15s;
    }

    .btn-icon:hover {
        transform: translateY(-1px);
    }

    .filter-btn {
        border-radius: 100px;
        padding: 0.4rem 1.5rem;
        font-weight: 500;
    }

    .filter-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .modal-content {
        border-radius: 1.5rem;
        border: none;
    }

    .modal-header {
        background: #f8fafc;
        border-radius: 1.5rem 1.5rem 0 0;
        padding: 1.5rem;
    }

    .form-control,
    .form-select {
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        padding: 0.6rem 1rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .alert {
        border-radius: 1rem;
        border: none;
        padding: 1rem 1.5rem;
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
    </style>
</head>

<body>
    <?php include "../sidebar/officer_sidebar.php"; ?>
    <div class="main-content">
        <div class="container-fluid px-0">
            <!-- Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
                <h2 class="fw-semibold mb-3 mb-md-0">
                    <i class="bi bi-receipt me-2" style="color: var(--primary);"></i>
                    <?= $config['app_name'] ?>
                </h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFineModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New Fine
                </button>
            </div>

            <!-- Alert container -->
            <div class="alert alert-success" id="successAlert" role="alert" style="display: none;"></div>
            <div class="alert alert-danger" id="errorAlert" role="alert" style="display: none;"></div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
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
                <a href="?status="
                    class="btn btn-outline-secondary filter-btn <?= $filter_status == '' ? 'active' : '' ?>">
                    <i class="bi bi-list-ul me-2"></i>All
                </a>
                <a href="?status=unpaid"
                    class="btn btn-outline-warning filter-btn <?= $filter_status == 'unpaid' ? 'active' : '' ?>">
                    <i class="bi bi-exclamation-triangle me-2"></i>Unpaid
                </a>
                <a href="?status=paid"
                    class="btn btn-outline-success filter-btn <?= $filter_status == 'paid' ? 'active' : '' ?>">
                    <i class="bi bi-check-circle me-2"></i>Paid
                </a>
                <?php if ($filter_status): ?>
                <a href="?" class="btn btn-outline-secondary filter-btn">
                    <i class="bi bi-x-circle me-2"></i>Clear Filters
                </a>
                <?php endif; ?>
            </div>

            <!-- Main Card with Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Fines List</span>
                    <small class="text-muted">Last updated: <?= date($config['datetime_format']) ?></small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="finesTable" class="table table-hover align-middle" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student</th>
                                    <th>Event</th>
                                    <th>Reason</th>
                                    <th>Amount</th>
                                    <th>Recorded</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                    <th>Bulk Pay</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fines as $fine): ?>
                                <tr id="fine-row-<?= $fine['fine_id'] ?>">
                                    <td><?= $fine['fine_id'] ?></td>
                                    <td><?= htmlspecialchars($fine['student_name'] ?? $fine['student_id']) ?></td>
                                    <td><?= htmlspecialchars($fine['event_name'] ?? 'Unknown') ?></td>
                                    <td><?= htmlspecialchars($fine['fine_reason']) ?></td>
                                    <td><?= $config['currency'] ?><?= number_format($fine['amount'], 2) ?></td>
                                    <td><?= date($config['date_format'], strtotime($fine['recorded_at'])) ?></td>
                                    <td>
                                        <?php if ($fine['status'] === 'paid'): ?>
                                        <span class="badge-paid"><i class="bi bi-check-circle-fill me-1"></i>Paid</span>
                                        <?php else: ?>
                                        <span class="badge-unpaid"><i
                                                class="bi bi-exclamation-circle-fill me-1"></i>Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Edit -->
                                        <button class="btn btn-sm btn-outline-primary btn-icon edit-btn"
                                            data-id="<?= $fine['fine_id'] ?>"
                                            data-student="<?= htmlspecialchars($fine['student_id']) ?>"
                                            data-event="<?= $fine['event_id'] ?>"
                                            data-reason="<?= htmlspecialchars($fine['fine_reason']) ?>"
                                            data-amount="<?= $fine['amount'] ?>" data-status="<?= $fine['status'] ?>"
                                            title="Edit Fine" aria-label="Edit fine <?= $fine['fine_id'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Pay/Unpay -->
                                        <?php if ($fine['status'] === 'unpaid'): ?>
                                        <button class="btn btn-sm btn-success btn-icon pay-btn"
                                            data-id="<?= $fine['fine_id'] ?>" title="Mark as Paid"
                                            aria-label="Pay fine <?= $fine['fine_id'] ?>">
                                            <i class="bi bi-cash-stack"></i> Pay
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-warning btn-icon unpay-btn"
                                            data-id="<?= $fine['fine_id'] ?>" title="Mark as Unpaid"
                                            aria-label="Unpay fine <?= $fine['fine_id'] ?>">
                                            <i class="bi bi-arrow-return-left"></i> Unpay
                                        </button>
                                        <?php endif; ?>

                                        <!-- Delete -->
                                        <button class="btn btn-sm btn-outline-danger btn-icon delete-btn"
                                            data-id="<?= $fine['fine_id'] ?>" title="Delete Fine"
                                            aria-label="Delete fine <?= $fine['fine_id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <?php
                                        static $seen_students = [];
                                        if (!in_array($fine['student_id'], $seen_students)):
                                            $seen_students[] = $fine['student_id'];
                                        ?>
                                        <button class="btn btn-sm btn-outline-success pay-all-btn"
                                            data-student="<?= htmlspecialchars($fine['student_id']) ?>"
                                            title="Pay all unpaid fines for this student"
                                            aria-label="Pay all unpaid fines for <?= htmlspecialchars($fine['student_name'] ?? $fine['student_id']) ?>">
                                            <i class="bi bi-cash"></i> Pay All
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
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
                                maxlength="100">
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveFineBtn">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        <span class="btn-text">Save Fine</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Fine Modal -->
    <div class="modal fade" id="editFineModal" tabindex="-1" aria-labelledby="editFineModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editFineModalLabel"><i class="bi bi-pencil-square me-2"></i>Edit Fine
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editFineForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" id="edit_fine_id" name="fine_id">
                        <div class="mb-3">
                            <label for="edit_student" class="form-label">Student <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" id="edit_student" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $s): ?>
                                <option value="<?= htmlspecialchars($s['student_id']) ?>">
                                    <?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_event" class="form-label">Event <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_event" name="event_id" required>
                                <option value="">Select Event</option>
                                <?php foreach ($events as $e): ?>
                                <option value="<?= $e['event_id'] ?>"><?= htmlspecialchars($e['event_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_reason" class="form-label">Fine Reason <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_reason" name="fine_reason" required
                                maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="edit_amount" class="form-label">Amount (<?= $config['currency'] ?>) <span
                                    class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="edit_amount"
                                name="amount" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="unpaid">Unpaid</option>
                                <option value="paid">Paid</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateFineBtn">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        <span class="btn-text">Update Fine</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize DataTable with export buttons
        var table = $('#finesTable').DataTable({
            order: [
                [5, 'desc']
            ],
            pageLength: 10,
            language: {
                search: "Search fines:"
            },
            dom: '<"row"<"col-md-6"B><"col-md-6"f>>rtip',
            buttons: [{
                    extend: 'copy',
                    className: 'btn btn-outline-secondary btn-sm'
                },
                {
                    extend: 'csv',
                    className: 'btn btn-outline-secondary btn-sm'
                },
                {
                    extend: 'excel',
                    className: 'btn btn-outline-secondary btn-sm'
                },
                {
                    extend: 'pdf',
                    className: 'btn btn-outline-secondary btn-sm'
                },
                {
                    extend: 'print',
                    className: 'btn btn-outline-secondary btn-sm'
                }
            ]
        });

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

        // Edit: populate modal
        $('.edit-btn').click(function() {
            const btn = $(this);
            $('#edit_fine_id').val(btn.data('id'));
            $('#edit_student').val(btn.data('student'));
            $('#edit_event').val(btn.data('event'));
            $('#edit_reason').val(btn.data('reason'));
            $('#edit_amount').val(btn.data('amount'));
            $('#edit_status').val(btn.data('status'));
            $('#editFineModal').modal('show');
        });

        // Update Fine
        $('#updateFineBtn').click(function() {
            const btn = $(this);
            const form = $('#editFineForm')[0];

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);
            formData.append('action', 'edit');

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
                        $('#editFineModal').modal('hide');
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

        // Delete Fine with confirmation
        $('.delete-btn').click(function() {
            if (!confirm('Are you sure you want to delete this fine? This action cannot be undone.'))
                return;

            const fineId = $(this).data('id');
            const btn = $(this);

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
                        $('#fine-row-' + fineId).remove();
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

        // Pay / Unpay functions
        $('.pay-btn').click(function() {
            const fineId = $(this).data('id');
            performToggle(fineId, 'paid');
        });

        $('.unpay-btn').click(function() {
            const fineId = $(this).data('id');
            performToggle(fineId, 'unpaid');
        });

        function performToggle(fineId, newStatus) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'toggle_status',
                    fine_id: fineId,
                    status: newStatus,
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
        }

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
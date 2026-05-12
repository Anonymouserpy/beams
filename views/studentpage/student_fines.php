<?php
session_start();
require "../../Connection/connection.php";

// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: ../../Login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

// --- AJAX Handlers ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    switch ($_GET['action']) {
        case 'get_fines':
            $status = isset($_GET['status']) ? $_GET['status'] : 'unpaid'; // 'unpaid' or 'paid'
            $stmt = $conn->prepare("
                SELECT f.fine_id, f.event_id, e.event_name, f.fine_reason, f.amount, f.status, f.recorded_at
                FROM student_fines f
                JOIN events e ON f.event_id = e.event_id
                WHERE f.student_id = ? AND f.status = ?
                ORDER BY f.recorded_at DESC
            ");
            $stmt->bind_param("ss", $student_id, $status);
            $stmt->execute();
            $result = $stmt->get_result();
            $fines = [];
            while ($row = $result->fetch_assoc()) {
                $fines[] = $row;
            }
            $stmt->close();
            echo json_encode(['success' => true, 'fines' => $fines]);
            exit;

        case 'get_stats':
            // Total fines count
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM student_fines WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $totalFines = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            // Unpaid count & amount
            $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount FROM student_fines WHERE student_id = ? AND status = 'unpaid'");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $unpaid = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Paid count & amount
            $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount FROM student_fines WHERE student_id = ? AND status = 'paid'");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $paid = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            echo json_encode([
                'success' => true,
                'totalFines' => $totalFines,
                'unpaidCount' => $unpaid['count'],
                'unpaidAmount' => $unpaid['amount'],
                'paidCount' => $paid['count'],
                'paidAmount' => $paid['amount']
            ]);
            exit;
    }
}

// Initial stats (for server‑side rendering, optional)
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM student_fines WHERE student_id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$totalFines = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount FROM student_fines WHERE student_id = ? AND status = 'unpaid'");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$unpaid = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount FROM student_fines WHERE student_id = ? AND status = 'paid'");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$paid = $stmt->get_result()->fetch_assoc();
$stmt->close();

// No close yet
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fines | BEAMS Student</title>
    <!-- Bootstrap 5 & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary: #33A1E0;
        --primary-light: #e6f2ff;
        --primary-dark: #1e6f9f;
        --success: #28a745;
        --danger: #dc3545;
        --warning: #ffc107;
        --info: #17a2b8;
        --bg-light: #f4f7fc;
        --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
        --card-hover-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.15);
        --transition: all 0.2s ease;
    }

    body {
        background-color: var(--bg-light);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        color: #1e293b;
    }

    .main-content {
        margin-left: 260px;
        padding: 30px;
        transition: margin-left 0.3s ease;
    }

    @media (max-width: 991.98px) {
        .main-content {
            margin-left: 0;
            padding: 20px;
        }
    }

    .page-header {
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .page-header h2 {
        font-weight: 700;
        color: #0f172a;
        font-size: 2rem;
    }

    .page-header h2 i {
        color: var(--primary);
        margin-right: 0.5rem;
    }

    /* Stats cards */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid #edf2f7;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .stat-icon.primary {
        background: var(--primary-light);
        color: var(--primary);
    }

    .stat-icon.success {
        background: #d1fae5;
        color: var(--success);
    }

    .stat-icon.danger {
        background: #fee2e2;
        color: var(--danger);
    }

    .stat-icon.warning {
        background: #fff3cd;
        color: var(--warning);
    }

    .stat-info h3 {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 0.2rem;
        line-height: 1.2;
    }

    .stat-info p {
        color: #64748b;
        font-size: 0.9rem;
        font-weight: 500;
        margin: 0;
    }

    /* Tabs */
    .fines-tabs {
        display: flex;
        gap: 1rem;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 2rem;
    }

    .tab-btn {
        background: none;
        border: none;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        color: #64748b;
        cursor: pointer;
        position: relative;
        transition: var(--transition);
        font-size: 1rem;
    }

    .tab-btn:hover {
        color: var(--primary);
    }

    .tab-btn.active {
        color: var(--primary);
    }

    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--primary);
        border-radius: 2px 2px 0 0;
    }

    /* Search */
    .search-wrapper {
        margin-bottom: 1.5rem;
        position: relative;
        max-width: 300px;
    }

    .search-wrapper i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }

    .search-wrapper input {
        width: 100%;
        padding: 0.75rem 1rem 0.75rem 2.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 30px;
        font-size: 0.9rem;
        transition: var(--transition);
        background: white;
    }

    .search-wrapper input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(51, 161, 224, 0.15);
    }

    /* Cards grid */
    .fines-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1.5rem;
        margin-top: 1rem;
    }

    /* Skeleton */
    .skeleton-card {
        background: white;
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: var(--card-shadow);
        border: 1px solid #edf2f7;
        animation: pulse 1.5s infinite;
    }

    .skeleton-line {
        height: 1.2rem;
        background: #e2e8f0;
        border-radius: 8px;
        margin-bottom: 0.8rem;
    }

    .skeleton-line.short {
        width: 60%;
    }

    .skeleton-line.medium {
        width: 80%;
    }

    .skeleton-line.long {
        width: 100%;
    }

    @keyframes pulse {
        0% {
            opacity: 0.6;
        }

        50% {
            opacity: 1;
        }

        100% {
            opacity: 0.6;
        }
    }

    /* Fine card */
    .fine-card {
        background: white;
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        border: 1px solid #edf2f7;
        transition: var(--transition);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .fine-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--card-hover-shadow);
        border-color: var(--primary-light);
    }

    .card-header {
        padding: 1.5rem 1.5rem 1rem;
        background: linear-gradient(145deg, #ffffff, #fafcfc);
        border-bottom: 1px solid #edf2f7;
    }

    .event-name {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }

    .event-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem 1rem;
        font-size: 0.85rem;
        color: #64748b;
    }

    .event-meta span {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .event-meta i {
        color: var(--primary);
        width: 16px;
    }

    .card-body {
        padding: 1rem 1.5rem;
        flex: 1;
    }

    .fine-reason {
        font-size: 1rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .fine-amount {
        font-size: 1.5rem;
        font-weight: 700;
    }

    .fine-amount.unpaid {
        color: var(--danger);
    }

    .fine-amount.paid {
        color: var(--success);
    }

    .card-footer {
        padding: 1rem 1.5rem 1.5rem;
        border-top: 1px solid #edf2f7;
        background: #fafcfc;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .badge {
        padding: 0.35rem 1rem;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-unpaid {
        background: #fee2e2;
        color: #b91c1c;
    }

    .badge-paid {
        background: #d1fae5;
        color: #0d9488;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 30px;
        box-shadow: var(--card-shadow);
    }

    .empty-icon {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 1.5rem;
    }

    .empty-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }

    .empty-text {
        color: #64748b;
        margin-bottom: 2rem;
    }

    /* WebSocket status */
    .ws-status {
        position: fixed;
        bottom: 20px;
        left: 300px;
        padding: 0.5rem 1rem;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        z-index: 1000;
        background: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        border: 1px solid transparent;
        transition: var(--transition);
    }

    @media (max-width: 991.98px) {
        .ws-status {
            left: 20px;
        }
    }

    .ws-status.connected {
        color: var(--success);
        border-color: var(--success);
    }

    .ws-status.connected i {
        color: var(--success);
    }

    .ws-status.disconnected {
        color: var(--danger);
        border-color: var(--danger);
    }

    .ws-status.connecting {
        color: var(--warning);
        border-color: var(--warning);
    }

    .ws-status i {
        font-size: 0.5rem;
        animation: pulse 2s infinite;
    }
    </style>
</head>

<body>

    <!-- Include enhanced sidebar -->
    <?php include "../sidebar/student_sidebar.php"; ?>

    <!-- WebSocket Status -->
    <div class="ws-status disconnected" id="wsStatus">
        <i class="fas fa-circle"></i>
        <span>Offline</span>
    </div>

    <div class="main-content">
        <div class="container-fluid px-0">
            <!-- Page header -->
            <div class="page-header">
                <h2><i class="fas fa-coins"></i>Fines</h2>
                <span class="text-muted small"><i class="far fa-calendar-alt me-1"></i><?= date('l, F j, Y') ?></span>
            </div>

            <!-- Stats cards -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon primary"><i class="fas fa-receipt"></i></div>
                    <div class="stat-info">
                        <h3 id="totalFines"><?= $totalFines ?></h3>
                        <p>Total Fines</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="stat-info">
                        <h3 id="unpaidCount"><?= $unpaid['count'] ?></h3>
                        <p>Unpaid</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-info">
                        <h3 id="unpaidAmount">₱<?= number_format($unpaid['amount'], 2) ?></h3>
                        <p>Unpaid Amount</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3 id="paidCount"><?= $paid['count'] ?></h3>
                        <p>Paid</p>
                    </div>
                </div>
            </div>

            <!-- Tabs & Search -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div class="fines-tabs">
                    <button class="tab-btn active" id="tabUnpaid">Unpaid</button>
                    <button class="tab-btn" id="tabPaid">Paid</button>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search fines...">
                </div>
            </div>

            <!-- Fines Container (filled by JS) -->
            <div id="finesContainer">
                <!-- Skeleton loaders -->
                <div class="fines-grid" id="skeletonGrid">
                    <?php for ($i=0; $i<6; $i++): ?>
                    <div class="skeleton-card">
                        <div class="skeleton-line short"></div>
                        <div class="skeleton-line medium"></div>
                        <div class="skeleton-line long"></div>
                        <div style="margin-top:1rem;">
                            <div class="skeleton-line short" style="width:40%;"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // --- State ---
    let currentStatus = 'unpaid';
    let finesData = [];
    let filteredFines = [];
    let searchTerm = '';

    // DOM elements
    const container = document.getElementById('finesContainer');
    const skeleton = document.getElementById('skeletonGrid');
    const tabUnpaid = document.getElementById('tabUnpaid');
    const tabPaid = document.getElementById('tabPaid');
    const searchInput = document.getElementById('searchInput');
    const totalFinesEl = document.getElementById('totalFines');
    const unpaidCountEl = document.getElementById('unpaidCount');
    const unpaidAmountEl = document.getElementById('unpaidAmount');
    const paidCountEl = document.getElementById('paidCount');

    // WebSocket
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
    const studentId = '<?php echo $student_id; ?>';

    // --- Initialize ---
    document.addEventListener('DOMContentLoaded', function() {
        initWebSocket();
        fetchFines();
        fetchStats();

        tabUnpaid.addEventListener('click', () => switchStatus('unpaid'));
        tabPaid.addEventListener('click', () => switchStatus('paid'));
        searchInput.addEventListener('input', (e) => {
            searchTerm = e.target.value.toLowerCase();
            filterAndRender();
        });
    });

    // --- Fetch fines ---
    async function fetchFines() {
        try {
            const response = await fetch('?action=get_fines&status=' + currentStatus);
            const data = await response.json();
            if (data.success) {
                finesData = data.fines;
                filterAndRender();
            }
        } catch (e) {
            console.error('Failed to fetch fines:', e);
            showError();
        }
    }

    async function fetchStats() {
        try {
            const response = await fetch('?action=get_stats');
            const data = await response.json();
            if (data.success) {
                totalFinesEl.textContent = data.totalFines;
                unpaidCountEl.textContent = data.unpaidCount;
                unpaidAmountEl.textContent = '₱' + parseFloat(data.unpaidAmount).toFixed(2);
                paidCountEl.textContent = data.paidCount;
            }
        } catch (e) {
            console.error('Failed to fetch stats:', e);
        }
    }

    function filterAndRender() {
        filteredFines = finesData.filter(f =>
            (f.event_name + ' ' + f.fine_reason).toLowerCase().includes(searchTerm)
        );
        render();
    }

    function render() {
        if (filteredFines.length === 0) {
            container.innerHTML = getEmptyState();
        } else {
            let html = '<div class="fines-grid">';
            filteredFines.forEach(f => {
                html += renderFineCard(f);
            });
            html += '</div>';
            container.innerHTML = html;
        }
    }

    function renderFineCard(f) {
        const amountClass = f.status === 'unpaid' ? 'unpaid' : 'paid';
        return `
            <div class="fine-card" data-fine-id="${f.fine_id}">
                <div class="card-header">
                    <div class="event-name">${escapeHtml(f.event_name)}</div>
                    <div class="event-meta">
                        <span><i class="fas fa-tag"></i> ${escapeHtml(f.fine_reason)}</span>
                        <span><i class="fas fa-calendar-day"></i> ${formatDate(f.recorded_at)}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="fine-amount ${amountClass}">₱${parseFloat(f.amount).toFixed(2)}</div>
                </div>
                <div class="card-footer">
                    <span class="badge ${f.status === 'unpaid' ? 'badge-unpaid' : 'badge-paid'}">
                        ${f.status === 'unpaid' ? 'Unpaid' : 'Paid'}
                    </span>
                </div>
            </div>`;
    }

    function getEmptyState() {
        return `
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-coins"></i></div>
                <h3 class="empty-title">No ${currentStatus} fines</h3>
                <p class="empty-text">${currentStatus === 'unpaid' ? 'You have no unpaid fines. Great!' : 'No paid fines yet.'}</p>
            </div>`;
    }

    function formatDate(dateStr) {
        return new Date(dateStr).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function switchStatus(status) {
        currentStatus = status;
        tabUnpaid.classList.toggle('active', status === 'unpaid');
        tabPaid.classList.toggle('active', status === 'paid');
        fetchFines();
    }

    function showError() {
        container.innerHTML = '<div class="alert alert-danger">Failed to load fines. Please refresh.</div>';
    }

    // --- WebSocket ---
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
                ws.send(JSON.stringify({
                    type: 'subscribe',
                    student_id: studentId
                }));
            };
            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                if (data.student_id && data.student_id !== studentId) return;
                if (data.type === 'fines_updated') {
                    fetchFines();
                    fetchStats();
                }
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
            console.error('WebSocket init error:', e);
            updateWSStatus('disconnected', 'Failed');
        }
    }

    // Fallback polling every 30 seconds if WebSocket down
    setInterval(() => {
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            fetchFines();
            fetchStats();
        }
    }, 30000);

    window.addEventListener('beforeunload', () => {
        if (reconnectTimer) clearTimeout(reconnectTimer);
        if (ws) ws.close();
    });
    </script>
</body>

</html>
<?php
// Connection closes automatically at script end
?>
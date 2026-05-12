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
        case 'get_upcoming':
            $query = "
                SELECT e.event_id, e.event_name, e.event_date, e.event_type, e.half_day_period,
                       s.am_login_start, s.am_login_end, s.am_logout_start, s.am_logout_end,
                       s.pm_login_start, s.pm_login_end, s.pm_logout_start, s.pm_logout_end
                FROM events e
                LEFT JOIN attendance_schedule s ON e.event_id = s.event_id
                WHERE e.event_date >= CURDATE()
                ORDER BY e.event_date ASC
            ";
            $result = $conn->query($query);
            $events = [];
            while ($row = $result->fetch_assoc()) {
                $row['attended'] = hasAttendance($conn, $student_id, $row['event_id']);
                $events[] = $row;
            }
            echo json_encode(['success' => true, 'events' => $events]);
            exit;

        case 'get_past':
            $query = "
                SELECT e.event_id, e.event_name, e.event_date, e.event_type, e.half_day_period,
                       s.am_login_start, s.am_login_end, s.am_logout_start, s.am_logout_end,
                       s.pm_login_start, s.pm_login_end, s.pm_logout_start, s.pm_logout_end
                FROM events e
                LEFT JOIN attendance_schedule s ON e.event_id = s.event_id
                WHERE e.event_date < CURDATE()
                ORDER BY e.event_date DESC
                LIMIT 20
            ";
            $result = $conn->query($query);
            $events = [];
            while ($row = $result->fetch_assoc()) {
                $row['attended'] = hasAttendance($conn, $student_id, $row['event_id']);
                $events[] = $row;
            }
            echo json_encode(['success' => true, 'events' => $events]);
            exit;
    }
}

// Helper function to check attendance
function hasAttendance($conn, $student_id, $event_id) {
    $stmt = $conn->prepare("SELECT attendance_id FROM attendance WHERE student_id = ? AND event_id = ?");
    $stmt->bind_param("si", $student_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Initial data fetch (used for server‑side rendering)
$upcomingQuery = "
    SELECT e.event_id, e.event_name, e.event_date, e.event_type, e.half_day_period,
           s.am_login_start, s.am_login_end, s.am_logout_start, s.am_logout_end,
           s.pm_login_start, s.pm_login_end, s.pm_logout_start, s.pm_logout_end
    FROM events e
    LEFT JOIN attendance_schedule s ON e.event_id = s.event_id
    WHERE e.event_date >= CURDATE()
    ORDER BY e.event_date ASC
";
$upcomingResult = $conn->query($upcomingQuery);

$pastQuery = "
    SELECT e.event_id, e.event_name, e.event_date, e.event_type, e.half_day_period,
           s.am_login_start, s.am_login_end, s.am_logout_start, s.am_logout_end,
           s.pm_login_start, s.pm_login_end, s.pm_logout_start, s.pm_logout_end
    FROM events e
    LEFT JOIN attendance_schedule s ON e.event_id = s.event_id
    WHERE e.event_date < CURDATE()
    ORDER BY e.event_date DESC
    LIMIT 20
";
$pastResult = $conn->query($pastQuery);
// Connection remains open for the loops below
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events | BEAMS Student</title>
    <!-- Bootstrap 5 & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary: #33A1E0;
        --primary-light: #e6f2ff;
        --primary-dark: #1e6f9f;
        --secondary: #6c757d;
        --success: #28a745;
        --danger: #dc3545;
        --warning: #ffc107;
        --info: #17a2b8;
        --light: #f8f9fa;
        --dark: #343a40;
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

    /* Page header */
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

    /* Tabs */
    .event-tabs {
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

    /* Search bar */
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

    /* Event cards grid */
    .events-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        margin-top: 1rem;
    }

    /* Skeleton loading */
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

    /* Event card */
    .event-card {
        background: white;
        border-radius: 24px;
        box-shadow: var(--card-shadow);
        border: 1px solid #edf2f7;
        transition: var(--transition);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        height: 100%;
        position: relative;
    }

    .event-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--card-hover-shadow);
        border-color: var(--primary-light);
    }

    .event-header {
        padding: 1.5rem 1.5rem 1rem;
        background: linear-gradient(145deg, #ffffff, #fafcfc);
        border-bottom: 1px solid #edf2f7;
    }

    .event-name {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.5rem;
        line-height: 1.4;
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

    .event-body {
        padding: 1rem 1.5rem;
        flex: 1;
    }

    .schedule-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        color: #334155;
    }

    .schedule-icon {
        width: 24px;
        color: var(--primary);
        font-size: 1rem;
    }

    .event-footer {
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
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }

    .badge-upcoming {
        background: var(--primary-light);
        color: var(--primary-dark);
    }

    .badge-attended {
        background: #d1fae5;
        color: #0d9488;
    }

    .badge-missed {
        background: #fee2e2;
        color: #b91c1c;
    }

    .details-link {
        color: var(--primary);
        font-weight: 600;
        font-size: 0.9rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        transition: var(--transition);
    }

    .details-link:hover {
        color: var(--primary-dark);
        gap: 0.6rem;
    }

    /* Empty state */
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
                <h2><i class="fas fa-calendar-alt"></i>Events</h2>
                <span class="text-muted small"><i class="far fa-calendar-alt me-1"></i><?= date('l, F j, Y') ?></span>
            </div>

            <!-- Tabs & Search -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div class="event-tabs">
                    <button class="tab-btn active" id="tabUpcoming">Upcoming</button>
                    <button class="tab-btn" id="tabPast">Past</button>
                </div>
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search events...">
                </div>
            </div>

            <!-- Events Container (will be filled by JS) -->
            <div id="eventsContainer">
                <!-- Skeleton loaders (shown initially) -->
                <div class="events-grid" id="skeletonGrid">
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
    let currentTab = 'upcoming';
    let allUpcoming = [];
    let allPast = [];
    let filteredUpcoming = [];
    let filteredPast = [];
    let searchTerm = '';

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

    // DOM elements
    const container = document.getElementById('eventsContainer');
    const skeleton = document.getElementById('skeletonGrid');
    const tabUpcoming = document.getElementById('tabUpcoming');
    const tabPast = document.getElementById('tabPast');
    const searchInput = document.getElementById('searchInput');

    // --- Initialize ---
    document.addEventListener('DOMContentLoaded', function() {
        initWebSocket();
        fetchAllEvents();

        tabUpcoming.addEventListener('click', () => switchTab('upcoming'));
        tabPast.addEventListener('click', () => switchTab('past'));
        searchInput.addEventListener('input', (e) => {
            searchTerm = e.target.value.toLowerCase();
            filterAndRender();
        });
    });

    // --- Fetch events ---
    async function fetchAllEvents() {
        try {
            const [upcomingRes, pastRes] = await Promise.all([
                fetch('?action=get_upcoming'),
                fetch('?action=get_past')
            ]);
            const upcomingData = await upcomingRes.json();
            const pastData = await pastRes.json();
            if (upcomingData.success) allUpcoming = upcomingData.events;
            if (pastData.success) allPast = pastData.events;
            filterAndRender();
        } catch (e) {
            console.error('Failed to fetch events:', e);
            showError();
        }
    }

    function filterAndRender() {
        filteredUpcoming = allUpcoming.filter(e => e.event_name.toLowerCase().includes(searchTerm));
        filteredPast = allPast.filter(e => e.event_name.toLowerCase().includes(searchTerm));
        renderCurrentTab();
    }

    function renderCurrentTab() {
        const events = currentTab === 'upcoming' ? filteredUpcoming : filteredPast;
        const containerHtml = events.length === 0 ? getEmptyState() : renderEventsGrid(events);
        container.innerHTML = containerHtml;
    }

    function renderEventsGrid(events) {
        if (currentTab === 'upcoming') {
            return `<div class="events-grid">${events.map(e => renderUpcomingCard(e)).join('')}</div>`;
        } else {
            return `<div class="events-grid">${events.map(e => renderPastCard(e)).join('')}</div>`;
        }
    }

    function renderUpcomingCard(e) {
        return `
            <div class="event-card" data-event-id="${e.event_id}">
                <div class="event-header">
                    <div class="event-name">${escapeHtml(e.event_name)}</div>
                    <div class="event-meta">
                        <span><i class="fas fa-calendar-day"></i> ${formatDate(e.event_date)}</span>
                        <span><i class="fas fa-tag"></i> ${e.event_type.replace(/_/g, ' ')}</span>
                        ${e.half_day_period ? `<span><i class="fas fa-clock"></i> ${e.half_day_period.toUpperCase()}</span>` : ''}
                    </div>
                </div>
                <div class="event-body">
                    ${e.am_login_start ? `<div class="schedule-item"><i class="fas fa-sun schedule-icon"></i><span>AM: ${formatTime(e.am_login_start)} – ${formatTime(e.am_logout_end)}</span></div>` : ''}
                    ${e.pm_login_start ? `<div class="schedule-item"><i class="fas fa-moon schedule-icon"></i><span>PM: ${formatTime(e.pm_login_start)} – ${formatTime(e.pm_logout_end)}</span></div>` : ''}
                </div>
                <div class="event-footer">
                    <span class="badge badge-upcoming">Upcoming</span>
                    <a href="#" class="details-link">Details <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>`;
    }

    function renderPastCard(e) {
        const attended = e.attended;
        return `
            <div class="event-card" data-event-id="${e.event_id}">
                <div class="event-header">
                    <div class="event-name">${escapeHtml(e.event_name)}</div>
                    <div class="event-meta">
                        <span><i class="fas fa-calendar-day"></i> ${formatDate(e.event_date)}</span>
                        <span><i class="fas fa-tag"></i> ${e.event_type.replace(/_/g, ' ')}</span>
                        ${e.half_day_period ? `<span><i class="fas fa-clock"></i> ${e.half_day_period.toUpperCase()}</span>` : ''}
                    </div>
                </div>
                <div class="event-body">
                    ${e.am_login_start ? `<div class="schedule-item"><i class="fas fa-sun schedule-icon"></i><span>AM: ${formatTime(e.am_login_start)} – ${formatTime(e.am_logout_end)}</span></div>` : ''}
                    ${e.pm_login_start ? `<div class="schedule-item"><i class="fas fa-moon schedule-icon"></i><span>PM: ${formatTime(e.pm_login_start)} – ${formatTime(e.pm_logout_end)}</span></div>` : ''}
                </div>
                <div class="event-footer">
                    ${attended ? 
                        '<span class="badge badge-attended"><i class="fas fa-check-circle me-1"></i>Attended</span>' : 
                        '<span class="badge badge-missed"><i class="fas fa-times-circle me-1"></i>Missed</span>'
                    }
                    <a href="#" class="details-link">Details <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>`;
    }

    function getEmptyState() {
        return `
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-calendar-times"></i></div>
                <h3 class="empty-title">No ${currentTab} events</h3>
                <p class="empty-text">${currentTab === 'upcoming' ? 'Check back later for new events.' : 'You haven\'t attended any past events yet.'}</p>
            </div>`;
    }

    function formatDate(dateStr) {
        return new Date(dateStr).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function formatTime(timeStr) {
        return timeStr ? timeStr.substr(0, 5) : '';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function switchTab(tab) {
        currentTab = tab;
        tabUpcoming.classList.toggle('active', tab === 'upcoming');
        tabPast.classList.toggle('active', tab === 'past');
        filterAndRender();
    }

    function showError() {
        container.innerHTML = '<div class="alert alert-danger">Failed to load events. Please refresh.</div>';
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
                if (data.type === 'events_updated' || data.type === 'attendance_updated') {
                    fetchAllEvents(); // refresh all
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
            fetchAllEvents();
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
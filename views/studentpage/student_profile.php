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
        case 'get_profile':
            $stmt = $conn->prepare("SELECT student_id, full_name, year_level, section, created_at FROM students WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $student = $result->fetch_assoc();
            $stmt->close();
            echo json_encode($student ? ['success' => true, 'student' => $student] : ['success' => false, 'message' => 'Student not found']);
            exit;

        case 'update_profile':
            $data = json_decode(file_get_contents('php://input'), true);
            $section = isset($data['section']) ? trim($data['section']) : null;
            $password = isset($data['password']) ? $data['password'] : null;

            $updates = [];
            $params = [];
            $types = "";

            if ($section !== null && $section !== '') {
                $updates[] = "section = ?";
                $params[] = $section;
                $types .= "s";
            }
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $updates[] = "password = ?";
                $params[] = $hashed;
                $types .= "s";
            }

            if (empty($updates)) {
                echo json_encode(['success' => false, 'message' => 'No data to update']);
                exit;
            }

            $params[] = $student_id;
            $types .= "s";
            $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE student_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                broadcastWebSocket(['type' => 'student_updated', 'student_id' => $student_id, 'timestamp' => time()]);
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Update failed: ' . $conn->error]);
            }
            $stmt->close();
            exit;
    }
}

function broadcastWebSocket($data) {
    $socket = @fsockopen('tcp://127.0.0.1', 8081, $errno, $errstr, 1);
    if ($socket) {
        fwrite($socket, json_encode($data) . "\n");
        fclose($socket);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | BEAMS Student</title>
    <!-- Bootstrap 5 & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary: #33A1E0;
        --primary-dark: #1e6f9f;
        --primary-light: rgba(51, 161, 224, 0.1);
        --success: #28a745;
        --danger: #dc3545;
        --bg-light: #f8fafc;
        --card-shadow: 0 20px 35px -8px rgba(0, 0, 0, 0.1), 0 5px 12px -4px rgba(0, 0, 0, 0.05);
        --transition: all 0.25s ease;
    }

    body {
        background-color: var(--bg-light);
        font-family: 'Inter', sans-serif;
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

    /* ===== Profile page specific styles (scoped) ===== */
    .profile-page .profile-card {
        background: white;
        border-radius: 32px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        transition: var(--transition);
    }

    .profile-page .profile-card:hover {
        box-shadow: 0 25px 45px -10px rgba(51, 161, 224, 0.2);
    }

    .profile-page .profile-cover {
        height: 150px;
        background: linear-gradient(135deg, var(--primary), #2c3e50);
        position: relative;
    }

    .profile-page .profile-avatar {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: 5px solid white;
        box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3.8rem;
        font-weight: 600;
        color: white;
        position: absolute;
        bottom: -65px;
        left: 40px;
        transition: var(--transition);
    }

    .profile-page .profile-avatar:hover {
        transform: scale(1.02);
        border-color: var(--primary-light);
    }

    .profile-page .profile-info {
        padding: 90px 35px 35px 35px;
    }

    .profile-page .profile-name {
        font-size: 2.2rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        margin-bottom: 0.3rem;
        color: #0f172a;
    }

    .profile-page .profile-id {
        color: var(--primary);
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
    }

    .profile-page .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }

    .profile-page .detail-card {
        background: #f8fafc;
        border-radius: 20px;
        padding: 1.2rem;
        transition: var(--transition);
        border: 1px solid #edf2f7;
    }

    .profile-page .detail-card:hover {
        background: white;
        box-shadow: 0 12px 24px -12px rgba(51, 161, 224, 0.15);
        border-color: var(--primary-light);
    }

    .profile-page .detail-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #64748b;
        font-weight: 600;
        margin-bottom: 0.3rem;
    }

    .profile-page .detail-value {
        font-size: 1.3rem;
        font-weight: 700;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .profile-page .detail-value i {
        color: var(--primary);
        font-size: 1.2rem;
    }

    .profile-page .quick-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 1.5rem;
    }

    .profile-page .action-btn {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 40px;
        padding: 0.7rem 1.5rem;
        font-weight: 600;
        color: #334155;
        text-decoration: none;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
    }

    .profile-page .action-btn i {
        color: var(--primary);
        font-size: 1rem;
    }

    .profile-page .action-btn:hover {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
        transform: translateY(-2px);
    }

    .profile-page .action-btn:hover i {
        color: white;
    }

    .profile-page .btn-edit {
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 40px;
        padding: 0.7rem 2rem;
        font-weight: 600;
        transition: var(--transition);
        box-shadow: 0 8px 18px -6px var(--primary);
    }

    .profile-page .btn-edit:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 12px 24px -8px var(--primary);
    }

    /* Skeleton loader (scoped) */
    .profile-page .skeleton-card {
        background: white;
        border-radius: 32px;
        padding: 2.5rem;
        box-shadow: var(--card-shadow);
    }

    .profile-page .skeleton-circle {
        width: 130px;
        height: 130px;
        border-radius: 50%;
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        margin-bottom: 1.5rem;
    }

    .profile-page .skeleton-line {
        height: 1.5rem;
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 30px;
        margin-bottom: 1rem;
    }

    .profile-page .skeleton-line.short {
        width: 40%;
    }

    .profile-page .skeleton-line.medium {
        width: 60%;
    }

    .profile-page .skeleton-line.long {
        width: 80%;
    }

    @keyframes shimmer {
        0% {
            background-position: 200% 0;
        }

        100% {
            background-position: -200% 0;
        }
    }

    /* Modal (not scoped, but fine) */
    .modal-content {
        border: none;
        border-radius: 28px;
        overflow: hidden;
    }

    .modal-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        border-bottom: 0;
        padding: 1.5rem;
    }

    .modal-title i {
        margin-right: 0.5rem;
    }

    .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.8;
    }

    .btn-close:hover {
        opacity: 1;
    }

    .modal-body {
        padding: 2rem;
    }

    .form-floating>label {
        padding-left: 1.2rem;
    }

    .form-control,
    .form-floating {
        border-radius: 40px;
        border: 1px solid #e2e8f0;
        padding: 0.8rem 1.2rem;
    }

    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(51, 161, 224, 0.15);
    }

    .btn-primary {
        background: var(--primary);
        border: none;
        border-radius: 40px;
        padding: 0.8rem 2rem;
        font-weight: 600;
        transition: var(--transition);
    }

    .btn-primary:hover:not(:disabled) {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 8px 18px -6px var(--primary);
    }

    /* WebSocket status */
    .ws-status {
        position: fixed;
        bottom: 20px;
        left: 300px;
        padding: 0.5rem 1.2rem;
        border-radius: 40px;
        font-size: 0.8rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        z-index: 1000;
        background: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
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

    .ws-status.disconnected {
        color: var(--danger);
        border-color: var(--danger);
    }

    .ws-status.connecting {
        color: #f59e0b;
        border-color: #f59e0b;
    }

    .ws-status i {
        font-size: 0.5rem;
    }

    /* Toast */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1100;
    }

    .toast {
        background: white;
        border-radius: 40px;
        padding: 1rem 1.8rem;
        box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.2);
        border-left: 4px solid var(--success);
        margin-bottom: 0.8rem;
        animation: slideIn 0.3s ease;
    }

    .toast.error {
        border-left-color: var(--danger);
    }

    .toast.warning {
        border-left-color: #f59e0b;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    </style>
</head>

<body>

    <!-- Include enhanced sidebar (unchanged) -->
    <?php include "../sidebar/student_sidebar.php"; ?>

    <!-- WebSocket Status -->
    <div class="ws-status disconnected" id="wsStatus">
        <i class="fas fa-circle"></i>
        <span>Offline</span>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <div class="main-content">
        <div class="container-fluid px-0">
            <!-- Page header -->
            <div class="page-header">
                <h2><i class="fas fa-user-circle"></i>My Profile</h2>
                <span class="text-muted small"><i class="far fa-calendar-alt me-1"></i><?= date('l, F j, Y') ?></span>
            </div>

            <!-- Profile Container (filled by JS) - now wrapped with profile-page class -->
            <div id="profileContainer" class="profile-page">
                <!-- Skeleton loader -->
                <div class="skeleton-card">
                    <div class="skeleton-circle"></div>
                    <div class="skeleton-line short"></div>
                    <div class="skeleton-line medium"></div>
                    <div class="skeleton-line long"></div>
                    <div class="skeleton-line short" style="margin-top:2rem;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-edit"></i>Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editProfileForm">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="editSection" placeholder="Section">
                            <label for="editSection"><i class="fas fa-users me-2"></i>Section</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="editPassword" placeholder="New Password">
                            <label for="editPassword"><i class="fas fa-lock me-2"></i>New Password</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="password" class="form-control" id="editConfirmPassword"
                                placeholder="Confirm Password">
                            <label for="editConfirmPassword"><i class="fas fa-check-circle me-2"></i>Confirm
                                Password</label>
                        </div>
                        <small class="text-muted">Leave password blank to keep current</small>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveProfileBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // --- State ---
    let studentData = null;

    const container = document.getElementById('profileContainer');
    const toastContainer = document.getElementById('toastContainer');
    const editModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
    const editSection = document.getElementById('editSection');
    const editPassword = document.getElementById('editPassword');
    const editConfirm = document.getElementById('editConfirmPassword');
    const saveBtn = document.getElementById('saveProfileBtn');

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
        fetchProfile();

        saveBtn.addEventListener('click', saveProfile);
    });

    // --- Fetch profile (unchanged) ---
    async function fetchProfile() {
        try {
            const response = await fetch('?action=get_profile');
            const data = await response.json();
            if (data.success) {
                studentData = data.student;
                renderProfile(studentData);
            } else {
                showError('Could not load profile.');
            }
        } catch (e) {
            console.error('Fetch profile error:', e);
            showError('Network error. Please refresh.');
        }
    }

    function renderProfile(s) {
        const initials = s.full_name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        const joinDate = new Date(s.created_at).toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });
        const html = `
                <div class="profile-card">
                    <div class="profile-cover"></div>
                    <div class="profile-avatar">${escapeHtml(initials)}</div>
                    <div class="profile-info">
                        <div class="d-flex justify-content-between align-items-start flex-wrap">
                            <div>
                                <div class="profile-name">${escapeHtml(s.full_name)}</div>
                                <div class="profile-id">${escapeHtml(s.student_id)}</div>
                            </div>
                            <button class="btn-edit" onclick="openEditModal()"><i class="fas fa-pen me-2"></i>Edit Profile</button>
                        </div>
                        <div class="detail-grid">
                            <div class="detail-card">
                                <div class="detail-label">Year Level</div>
                                <div class="detail-value"><i class="fas fa-graduation-cap"></i>${escapeHtml(s.year_level)}</div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Section</div>
                                <div class="detail-value"><i class="fas fa-users"></i>${escapeHtml(s.section || 'N/A')}</div>
                            </div>
                            <div class="detail-card">
                                <div class="detail-label">Member Since</div>
                                <div class="detail-value"><i class="fas fa-calendar-alt"></i>${joinDate}</div>
                            </div>
                        </div>
                        <div class="quick-actions">
                            <a href="student_dashboard.php" class="action-btn"><i class="fas fa-chart-pie"></i>Dashboard</a>
                            <a href="student_events.php" class="action-btn"><i class="fas fa-calendar-alt"></i>Events</a>
                            <a href="student_attendance.php" class="action-btn"><i class="fas fa-clock"></i>Attendance</a>
                            <a href="student_fines.php" class="action-btn"><i class="fas fa-coins"></i>Fines</a>
                        </div>
                    </div>
                </div>`;
        container.innerHTML = html;
    }

    function openEditModal() {
        if (!studentData) return;
        editSection.value = studentData.section || '';
        editPassword.value = '';
        editConfirm.value = '';
        editModal.show();
    }

    async function saveProfile() {
        const section = editSection.value.trim();
        const password = editPassword.value;
        const confirm = editConfirm.value;

        if (password && password.length < 6) {
            showToast('Error', 'Password must be at least 6 characters', 'error');
            return;
        }
        if (password !== confirm) {
            showToast('Error', 'Passwords do not match', 'error');
            return;
        }

        const payload = {};
        if (section !== studentData.section) payload.section = section;
        if (password) payload.password = password;

        if (Object.keys(payload).length === 0) {
            editModal.hide();
            return;
        }

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        try {
            const response = await fetch('?action=update_profile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (result.success) {
                showToast('Success', result.message, 'success');
                editModal.hide();
                fetchProfile();
            } else {
                showToast('Error', result.message, 'error');
            }
        } catch (e) {
            showToast('Error', 'Network error', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = 'Save Changes';
        }
    }

    function showToast(title, message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<div><strong>${title}</strong><br>${message}</div>`;
        toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    function showError(message) {
        container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // --- WebSocket with subscription and dual message handling ---
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
                // Handle student updates (both uppercase and lowercase)
                if (data.type === 'STUDENT_UPDATED' || data.type === 'student_updated') {
                    fetchProfile();
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
                console.error(err);
                updateWSStatus('disconnected', 'Error');
            };
        } catch (e) {
            console.error(e);
            updateWSStatus('disconnected', 'Failed');
        }
    }

    // Fallback polling every 30 seconds if WebSocket down
    setInterval(() => {
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            fetchProfile();
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
// Connection closes automatically
?>
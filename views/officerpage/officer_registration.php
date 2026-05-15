<?php
session_start();

// Adjust paths if necessary
require __DIR__ . '/../../Connection/connection.php';

// Ensure only logged-in officers can access this page
if (!isset($_SESSION['officer_id'])) {
    header('Location: ../../officer_Login.php');
    exit();
}

// ========== AUDIT LOG FUNCTION WITH DEBUGGING ==========
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
            // Also log to a debug file
            $debug_log = __DIR__ . '/audit_debug.log';
            file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Audit Error: " . mysqli_stmt_error($stmt) . "\n", FILE_APPEND);
            return false;
        }
        mysqli_stmt_close($stmt);
        return true;
    } else {
        error_log("Failed to prepare audit log statement: " . mysqli_error($conn));
        $debug_log = __DIR__ . '/audit_debug.log';
        file_put_contents($debug_log, date('Y-m-d H:i:s') . " - Prepare Error: " . mysqli_error($conn) . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Send a message to the WebSocket server via internal TCP socket.
 * @param array $data
 */
function notifyWebSocket($data)
{
    $socket = @fsockopen('127.0.0.1', 8081, $errno, $errstr, 0.5);
    if ($socket) {
        fwrite($socket, json_encode($data));
        fclose($socket);
    } else {
        error_log("WebSocket notification failed: $errstr ($errno)");
    }
}

// --- Log page access ---
logAudit($conn, $_SESSION['officer_id'], 'VIEW', 'officer_registration_page', null, null, 
    json_encode(['action' => 'page_access', 'timestamp' => date('Y-m-d H:i:s'), 'page' => 'Officer Registration']));

// Handle AJAX registration request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Get and sanitize inputs
    $full_name   = trim($_POST['full_name'] ?? '');
    $officer_id  = trim($_POST['officer_id'] ?? '');
    $position    = $_POST['position'] ?? '';
    $password    = $_POST['password'] ?? '';

    $errors = [];

    // Validate inputs
    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }
    if (empty($officer_id)) {
        $errors[] = 'Officer ID is required.';
    }
    if (!in_array($position, ['Admin', 'Officer'])) {
        $errors[] = 'Invalid position selected.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (empty($errors)) {
        // Check if officer ID already exists
        $stmt = $conn->prepare("SELECT officer_id FROM officers WHERE officer_id = ?");
        $stmt->bind_param("s", $officer_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Officer ID already exists.']);
            $stmt->close();
            exit();
        }
        $stmt->close();

        // Hash the password and insert new officer
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $insert = $conn->prepare("INSERT INTO officers (officer_id, full_name, position, password) VALUES (?, ?, ?, ?)");
        $insert->bind_param("ssss", $officer_id, $full_name, $position, $hashed_password);

        if ($insert->execute()) {
            // Get the newly created officer data for audit
            $new_officer = [
                'officer_id' => $officer_id,
                'full_name' => $full_name,
                'position' => $position,
                'created_by' => $_SESSION['officer_id'],
                'created_by_name' => $_SESSION['full_name'] ?? 'Unknown',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // AUDIT: Log officer creation (excluding password for security)
            logAudit($conn, $_SESSION['officer_id'], 'CREATE', 'officers', $officer_id, null, json_encode($new_officer));
            
            // Send WebSocket notification
            notifyWebSocket([
                'type'    => 'OFFICER_CREATED',
                'payload' => [
                    'officer_id' => $officer_id,
                    'full_name'  => $full_name,
                    'position'   => $position
                ]
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Officer registered successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $conn->error]);
        }
        $insert->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
    }
    exit(); // Stop execution – no sidebar or HTML should be included
}

// If not POST, include the sidebar and display the HTML form
include __DIR__ . '/../sidebar/officer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Officer</title>

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
    :root {
        --primary-color: #0d6efd;
        --success-color: #198754;
        --danger-color: #dc3545;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: var(--dark);
        min-height: 100vh;
    }


    .registration-card {
        border: none;
        border-radius: 1rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
    }

    .card-header {
        background-color: transparent;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        padding: 1.5rem 1.5rem 0.5rem 1.5rem;
        font-weight: 600;
        font-size: 1.5rem;
        color: #1e293b;
    }

    .card-body {
        padding: 1.5rem;
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


    .form-label {
        font-weight: 500;
        color: #334155;
        margin-bottom: 0.3rem;
        font-size: 0.9rem;
    }

    .form-control,
    .form-select {
        border-radius: 0.5rem;
        border: 1px solid #e2e8f0;
        padding: 0.6rem 1rem;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }

    .is-invalid {
        border-color: var(--danger-color) !important;
        background-image: none;
    }

    .is-invalid:focus {
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
    }

    .invalid-feedback {
        font-size: 0.8rem;
        margin-top: 0.2rem;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border: none;
        border-radius: 0.5rem;
        padding: 0.7rem 1rem;
        font-weight: 500;
        transition: background-color 0.15s;
    }

    .btn-primary:hover:not(:disabled) {
        background-color: #0b5ed7;
    }

    .btn-primary:disabled {
        background-color: #6c757d;
        cursor: not-allowed;
    }

    .alert {
        border-radius: 0.5rem;
        border: none;
        padding: 1rem;
        margin-bottom: 1.5rem;
        display: none;
    }

    .alert.show {
        display: block;
        animation: slideDown 0.2s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-success {
        background-color: #d1e7dd;
        color: #0f5132;
    }

    .alert-danger {
        background-color: #f8d7da;
        color: #842029;
    }

    .spinner-border {
        width: 1rem;
        height: 1rem;
        display: none;
    }

    .btn-loading .spinner-border {
        display: inline-block;
    }

    .btn-loading .btn-text {
        display: none;
    }

    .password-match-icon {
        position: absolute;
        right: 15px;
        top: 38px;
        color: #28a745;
    }

    .footer-links a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 500;
    }

    .footer-links a:hover {
        text-decoration: underline;
    }
    </style>
</head>

<body>
    <div class="main-contents">
        <div class="card registration-card">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-person-plus-fill me-2" style="font-size: 1.8rem; color: var(--primary-color);"></i>
                Register New Officer
            </div>
            <div class="card-body">
                <!-- Alert Messages -->
                <div class="alert" id="errorAlert" role="alert"></div>
                <div class="alert" id="successAlert" role="alert"></div>
                <form id="registerForm" method="POST">
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                            placeholder="Enter full name" required>
                        <div class="invalid-feedback">Full name is required.</div>
                    </div>
                    <div class="mb-3">
                        <label for="officer_id" class="form-label">Officer ID</label>
                        <input type="text" class="form-control" id="officer_id" name="officer_id"
                            placeholder="e.g., 11-1111-111" required>
                        <div class="invalid-feedback">Officer ID is required.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="position" class="form-label">Position</label>
                            <select class="form-select" id="position" name="position" required>
                                <option value="" selected disabled>Select position</option>
                                <option value="Admin">Admin</option>
                                <option value="Officer">Officer</option>
                            </select>
                            <div class="invalid-feedback">Please select a position.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Min. 6 characters" required minlength="6">
                            <div class="invalid-feedback">Password must be at least 6 characters.
                            </div>
                        </div>
                    </div>
                    <div class="mb-4 position-relative">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password"
                            placeholder="Re-enter password" required>
                        <div class="invalid-feedback">Passwords do not match.</div>
                        <i class="bi bi-check-circle-fill password-match-icon" id="passwordMatchIcon"
                            style="display: none;"></i>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        <span class="btn-text">Register Officer</span>
                    </button>
                </form>
                <div class="text-center mt-4 footer-links">

                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    (function() {
        // DOM elements
        const form = document.getElementById('registerForm');
        const submitBtn = document.getElementById('submitBtn');
        const errorAlert = document.getElementById('errorAlert');
        const successAlert = document.getElementById('successAlert');
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const matchIcon = document.getElementById('passwordMatchIcon');

        // Real-time password match check with icon
        function checkPasswordMatch() {
            if (confirmInput.value.length === 0) {
                confirmInput.classList.remove('is-invalid');
                matchIcon.style.display = 'none';
                return;
            }
            if (passwordInput.value !== confirmInput.value) {
                confirmInput.classList.add('is-invalid');
                matchIcon.style.display = 'none';
            } else {
                confirmInput.classList.remove('is-invalid');
                matchIcon.style.display = 'block';
            }
        }

        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmInput.addEventListener('input', checkPasswordMatch);

        // Clear validation styling on input
        document.querySelectorAll('.form-control, .form-select').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
            input.addEventListener('change', function() {
                this.classList.remove('is-invalid');
            });
        });

        // Form submission
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Hide previous alerts
            errorAlert.classList.remove('show');
            successAlert.classList.remove('show');

            // Collect values
            const fullName = document.getElementById('full_name').value.trim();
            const officerId = document.getElementById('officer_id').value.trim();
            const position = document.getElementById('position').value;
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            // Reset validation
            document.querySelectorAll('.form-control, .form-select').forEach(el => el.classList.remove(
                'is-invalid'));

            let isValid = true;

            if (!fullName) {
                document.getElementById('full_name').classList.add('is-invalid');
                isValid = false;
            }
            if (!officerId) {
                document.getElementById('officer_id').classList.add('is-invalid');
                isValid = false;
            }
            if (!position) {
                document.getElementById('position').classList.add('is-invalid');
                isValid = false;
            }
            if (password.length < 6) {
                document.getElementById('password').classList.add('is-invalid');
                isValid = false;
            }
            if (password !== confirm) {
                document.getElementById('confirm_password').classList.add('is-invalid');
                showError('Passwords do not match.');
                isValid = false;
            }

            if (!isValid) return;

            // Prepare data
            const formData = new FormData(form);

            // Show loading
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;

            // Send AJAX
            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    submitBtn.classList.remove('btn-loading');
                    submitBtn.disabled = false;

                    if (data.status === 'success') {
                        showSuccess(data.message);
                        form.reset();
                        matchIcon.style.display = 'none';
                        setTimeout(() => {
                            window.location.href = 'manage_officer.php';
                        }, 2000);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    submitBtn.classList.remove('btn-loading');
                    submitBtn.disabled = false;
                    console.error('Fetch error:', error);
                    showError('Network error. Please try again.');
                });
        });

        function showError(message) {
            errorAlert.textContent = message;
            errorAlert.classList.add('show');
            setTimeout(() => errorAlert.classList.remove('show'), 5000);
        }

        function showSuccess(message) {
            successAlert.textContent = message;
            successAlert.classList.add('show');
            setTimeout(() => successAlert.classList.remove('show'), 5000);
        }

        // ---------- WebSocket Client ----------
        let ws;
        // Determine protocol based on current page
        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${wsProtocol}//${window.location.hostname}:8080`;

        function connectWebSocket() {
            ws = new WebSocket(wsUrl);
            ws.onopen = function() {
                console.log('WebSocket connected');
            };
            ws.onmessage = function(event) {
                const data = JSON.parse(event.data);
                if (data.type === 'OFFICER_CREATED') {
                    const toast = document.createElement('div');
                    toast.className =
                        'alert alert-info alert-dismissible fade show position-fixed bottom-0 end-0 m-3';
                    toast.style.zIndex = '9999';
                    toast.style.minWidth = '300px';
                    toast.innerHTML = `
                        <strong><i class="bi bi-person-plus-fill me-2"></i>New Officer Registered!</strong><br>
                        <small>ID: ${escapeHtml(data.payload.officer_id)}<br>
                        Name: ${escapeHtml(data.payload.full_name)}<br>
                        Position: ${escapeHtml(data.payload.position)}</small>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 5000);
                }
            };
            ws.onclose = function() {
                console.log('WebSocket disconnected, reconnecting in 3s...');
                setTimeout(connectWebSocket, 3000);
            };
            ws.onerror = function(err) {
                console.error('WebSocket error:', err);
            };
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

        // Start WebSocket connection after page loads
        window.addEventListener('load', connectWebSocket);
    })();
    </script>
</body>

</html>
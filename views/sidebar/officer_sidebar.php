<?php
// This file handles ALL PHP logic - include this FIRST in your main files
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['officer_id'])) {
    header("Location: ../../Login.php");
    exit();
}
?>
<!-- SIDEBAR -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --sidebar-width: 240px;
    --primary-blue: #33A1E0;
    --dark-bg: #1a1f2e;
    --hover-bg: rgba(255, 255, 255, 0.08);
    --active-bg: rgba(51, 161, 224, 0.2);
    --text-muted: rgba(255, 255, 255, 0.6);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background-color: #f1f5f9;
    overflow-x: hidden;
}

/* Modern Sidebar */
.sidebar {
    background: linear-gradient(180deg, var(--dark-bg) 0%, #0f1419 100%);
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-width);
    height: 100vh;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 1000;
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
}

/* Logo Section */
.sidebar-brand {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 0.5rem;
}

.brand-link {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: white;
}

.brand-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--primary-blue), #2563eb);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    box-shadow: 0 4px 15px rgba(51, 161, 224, 0.4);
}

.brand-text {
    font-weight: 700;
    font-size: 1.25rem;
    letter-spacing: -0.5px;
}

.brand-subtext {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Navigation */
.nav-section {
    padding: 0 1rem;
    flex: 1;
}

.nav-section-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--text-muted);
    padding: 0 1rem;
    margin: 1.25rem 0 0.5rem;
    font-weight: 600;
}

.nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin-bottom: 0.25rem;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.75rem 1rem;
    color: var(--text-muted) !important;
    text-decoration: none;
    border-radius: 10px;
    transition: var(--transition);
    position: relative;
    font-weight: 500;
    font-size: 0.95rem;
}

.nav-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 0;
    background: var(--primary-blue);
    border-radius: 0 3px 3px 0;
    transition: var(--transition);
}

.nav-link:hover {
    background: var(--hover-bg);
    color: white !important;
    transform: translateX(4px);
}

.nav-link:hover::before {
    height: 20px;
}

.nav-link.active {
    background: var(--active-bg);
    color: white !important;
}

.nav-link.active::before {
    height: 60%;
}

.nav-link i {
    width: 24px;
    text-align: center;
    font-size: 1.1rem;
    transition: var(--transition);
}

.nav-link:hover i,
.nav-link.active i {
    color: var(--primary-blue);
    transform: scale(1.1);
}

/* Badge */
.nav-badge {
    margin-left: auto;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    border-radius: 20px;
    font-weight: 600;
}

/* Logout Section */
.sidebar-footer {
    padding: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: auto;
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.875rem 1rem;
    color: #fca5a5 !important;
    text-decoration: none;
    border-radius: 10px;
    transition: var(--transition);
    font-weight: 500;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #fecaca !important;
    border-color: rgba(239, 68, 68, 0.5);
    transform: translateX(4px);
}

.logout-btn i {
    transition: var(--transition);
}

.logout-btn:hover i {
    transform: translateX(4px);
}

/* User Mini Profile */
.user-mini {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.875rem;
    margin: 0.5rem 1rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary-blue), #2563eb);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    color: var(--text-muted);
    font-size: 0.75rem;
    margin: 0;
}

/* Main Content Adjustment */
.main-content {
    margin-left: var(--sidebar-width);
    padding: 2rem;
    min-height: 100vh;
}

/* Mobile Responsive */
@media (max-width: 991px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .main-content {
        margin-left: 0;
    }

    .sidebar-toggle {
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        background: var(--dark-bg);
        color: white;
        border: none;
        padding: 0.75rem;
        border-radius: 8px;
        cursor: pointer;
    }
}

/* Scrollbar Styling */
.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
</style>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <a href="#" class="brand-link">
            <div class="brand-icon">
                <i class="fas fa-shield-halved"></i>
            </div>
            <div>
                <div class="brand-text">BEAMS</div>
                <div class="brand-subtext">Officer Portal</div>
            </div>
        </a>
    </div>

    <!-- User Mini Profile -->
    <div class="user-mini">
        <div class="user-avatar">
            <?php echo isset($_SESSION['officer_name']) ? strtoupper(substr($_SESSION['officer_name'], 0, 2)) : 'OF'; ?>
        </div>
        <div class="user-info">
            <p class="user-name">
                <?php echo isset($_SESSION['officer_name']) ? htmlspecialchars($_SESSION['officer_name']) : 'Officer'; ?>
            </p>
            <p class="user-role">System Officer</p>
        </div>
    </div>

    <!-- Navigation -->
    <div class="nav-section">
        <div class="nav-section-title">Main Menu</div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="../officerpage/officer_dashboard.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'officer_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../officerpage/manage_student.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'Students.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../officerpage/manage_event.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_event.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Event</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../officerpage/create_event.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create_event.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Create Events</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="/Beams/Views/Officer/AttendanceRecords.php"
                    class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'AttendanceRecords.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Attendance Records</span>
                </a>
            </li>
        </ul>

        <div class="nav-section-title">Management</div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-file-export"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Footer / Logout -->
    <div class="sidebar-footer">
        <a href="../../Auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Mobile Toggle Button (visible only on small screens) -->
<button class="sidebar-toggle d-lg-none" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay for mobile -->
<div class="sidebar-overlay d-lg-none" id="sidebarOverlay" onclick="toggleSidebar()"
    style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease;">
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    sidebar.classList.toggle('show');

    if (sidebar.classList.contains('show')) {
        overlay.style.opacity = '1';
        overlay.style.visibility = 'visible';
    } else {
        overlay.style.opacity = '0';
        overlay.style.visibility = 'hidden';
    }
}

// Active state handling
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = '<?php echo basename($_SERVER['PHP_SELF']); ?>';
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        if (link.getAttribute('href').includes(currentPage)) {
            link.classList.add('active');
        }
    });
});
</script>
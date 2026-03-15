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
    --sidebar-width: 220px;
    --beams-primary-blue: #33A1E0;
    --beams-dark-bg: #1a1f2e;
    --beams-hover-bg: rgba(255, 255, 255, 0.08);
    --beams-active-bg: rgba(51, 161, 224, 0.2);
    --beams-text-muted: rgba(255, 255, 255, 0.6);
    --beams-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
.beams-sidebar {
    background: linear-gradient(180deg, var(--beams-dark-bg) 0%, #0f1419 100%);
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
.beams-sidebar-brand {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 0.5rem;
}

.beams-brand-link {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: white;
}

.beams-brand-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--beams-primary-blue), #2563eb);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    box-shadow: 0 4px 15px rgba(51, 161, 224, 0.4);
}

.beams-brand-text {
    font-weight: 700;
    font-size: 1.25rem;
    letter-spacing: -0.5px;
}

.beams-brand-subtext {
    font-size: 0.75rem;
    color: var(--beams-text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Navigation */
.beams-nav-section {
    padding: 0 1rem;
    flex: 1;
}

.beams-nav-section-title {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--beams-text-muted);
    padding: 0 1rem;
    margin: 1.25rem 0 0.5rem;
    font-weight: 600;
}

.beams-nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.beams-nav-item {
    margin-bottom: 0.25rem;
}

.beams-nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.75rem 1rem;
    color: var(--beams-text-muted) !important;
    text-decoration: none;
    border-radius: 10px;
    transition: var(--beams-transition);
    position: relative;
    font-weight: 500;
    font-size: 0.95rem;
}

.beams-nav-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 0;
    background: var(--beams-primary-blue);
    border-radius: 0 3px 3px 0;
    transition: var(--beams-transition);
}

.beams-nav-link:hover {
    background: var(--beams-hover-bg);
    color: white !important;
    transform: translateX(4px);
}

.beams-nav-link:hover::before {
    height: 20px;
}

.beams-nav-link.active {
    background: var(--beams-active-bg);
    color: white !important;
}

.beams-nav-link.active::before {
    height: 60%;
}

.beams-nav-link i {
    width: 24px;
    text-align: center;
    font-size: 1.1rem;
    transition: var(--beams-transition);
}

.beams-nav-link:hover i,
.beams-nav-link.active i {
    color: var(--beams-primary-blue);
    transform: scale(1.1);
}

/* Badge */
.beams-nav-badge {
    margin-left: auto;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
    border-radius: 20px;
    font-weight: 600;
}

/* Logout Section */
.beams-sidebar-footer {
    padding: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: auto;
}

.beams-logout-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.875rem 1rem;
    color: #fca5a5 !important;
    text-decoration: none;
    border-radius: 10px;
    transition: var(--beams-transition);
    font-weight: 500;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.beams-logout-btn:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #fecaca !important;
    border-color: rgba(239, 68, 68, 0.5);
    transform: translateX(4px);
}

.beams-logout-btn i {
    transition: var(--beams-transition);
}

.beams-logout-btn:hover i {
    transform: translateX(4px);
}

/* User Mini Profile */
.beams-user-mini {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.875rem;
    margin: 0.5rem 1rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.beams-user-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--beams-primary-blue), #2563eb);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.beams-user-info {
    flex: 1;
    min-width: 0;
}

.beams-user-name {
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.beams-user-role {
    color: var(--beams-text-muted);
    font-size: 0.75rem;
    margin: 0;
}

/* Main Content Adjustment */
.beams-main-content {
    margin-left: var(--beams-sidebar-width);
    padding: 2rem;
    min-height: 100vh;
}

/* Mobile Responsive */
@media (max-width: 991px) {
    .beams-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }

    .beams-sidebar.show {
        transform: translateX(0);
    }

    .beams-main-content {
        margin-left: 0;
    }

    .beams-sidebar-toggle {
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        background: var(--beams-dark-bg);
        color: white;
        border: none;
        padding: 0.75rem;
        border-radius: 8px;
        cursor: pointer;
    }
}

/* Scrollbar Styling */
.beams-sidebar::-webkit-scrollbar {
    width: 4px;
}

.beams-sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.beams-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.beams-sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
</style>

<!-- Sidebar -->
<div class="beams-sidebar" id="beamsSidebar">
    <!-- Brand -->
    <div class="beams-sidebar-brand">
        <a href="#" class="beams-brand-link">
            <div class="beams-brand-icon">
                <i class="fas fa-shield-halved"></i>
            </div>
            <div>
                <div class="beams-brand-text">BEAMS</div>
                <div class="beams-brand-subtext">Officer Portal</div>
            </div>
        </a>
    </div>

    <!-- User Mini Profile -->
    <div class="beams-user-mini">
        <div class="beams-user-avatar">
            <?php echo isset($_SESSION['officer_name']) ? strtoupper(substr($_SESSION['officer_name'], 0, 2)) : 'OF'; ?>
        </div>
        <div class="beams-user-info">
            <p class="beams-user-name">
                <?php echo isset($_SESSION['officer_name']) ? htmlspecialchars($_SESSION['officer_name']) : 'Officer'; ?>
            </p>
            <p class="beams-user-role">System Officer</p>
        </div>
    </div>

    <!-- Navigation -->
    <div class="beams-nav-section">
        <div class="beams-nav-section-title">Main Menu</div>
        <ul class="beams-nav-list">
            <li class="beams-nav-item">
                <a href="../officerpage/officer_dashboard.php"
                    class="beams-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'officer_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="beams-nav-item">
                <a href="../officerpage/manage_student.php"
                    class="beams-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_student.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Students</span>
                </a>
            </li>
            <li class="beams-nav-item">
                <a href="../officerpage/manage_event.php"
                    class="beams-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_event.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Event</span>
                </a>
            </li>
            <li class="beams-nav-item">
                <a href="../officerpage/create_event.php"
                    class="beams-nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'create_event.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Create Events</span>
                </a>
            </li>
        </ul>

        <ul class="beams-nav-list">
            <li class="beams-nav-item">
                <a href="../officerpage/officer_register.php" class="beams-nav-link">
                    <i class="fas fa-user-plus"></i>
                    <span>Register Officer</span>
                </a>
            </li>
            <li class="beams-nav-item">
                <a href="../officerpage/manage_fines.php" class="beams-nav-link">
                    <i class="fas fa-cash-stack"></i>
                    <span>Manage Fines</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Footer / Logout -->
    <div class="beams-sidebar-footer">
        <a href="../../Auth/logout.php" class="beams-logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Mobile Toggle Button (visible only on small screens) -->
<button class="beams-sidebar-toggle d-lg-none" onclick="toggleBeamsSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay for mobile -->
<div class="beams-sidebar-overlay d-lg-none" id="beamsSidebarOverlay" onclick="toggleBeamsSidebar()"
    style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease;">
</div>

<script>
function toggleBeamsSidebar() {
    const sidebar = document.getElementById('beamsSidebar');
    const overlay = document.getElementById('beamsSidebarOverlay');

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
    const navLinks = document.querySelectorAll('.beams-nav-link');

    navLinks.forEach(link => {
        if (link.getAttribute('href').includes(currentPage)) {
            link.classList.add('active');
        }
    });
});
</script>
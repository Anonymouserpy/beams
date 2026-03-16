<?php
// student_sidebar.php – Self-contained sidebar with its own database connection
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default values (fallback)
$studentName = 'Student';
$studentInitials = 'S';
$studentRole = 'Student';

// If student is logged in, fetch real data using a new connection
if (isset($_SESSION['student_id'])) {
    // Database credentials – adjust if yours differ
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'beams';

    // Create a new connection (independent of the main script)
    $sidebarConn = new mysqli($host, $username, $password, $database);

    // Check connection
    if (!$sidebarConn->connect_error) {
        $student_id = $_SESSION['student_id'];
        $stmt = $sidebarConn->prepare("SELECT full_name FROM students WHERE student_id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $studentName = $row['full_name'];
                $studentInitials = strtoupper(substr($studentName, 0, 1));
            }
            $stmt->close();
        }
        $sidebarConn->close(); // Close this independent connection
    } else {
        // Log error but continue with defaults (prevents white page)
        error_log("Student sidebar: DB connection failed - " . $sidebarConn->connect_error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Sidebar</title>
    <!-- Bootstrap, Font Awesome, Google Fonts (already in parent page, but kept for completeness) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    :root {
        --sidebar-bg: rgba(18, 25, 35, 0.98);
        --sidebar-glass: rgba(255, 255, 255, 0.03);
        --sidebar-hover: rgba(255, 255, 255, 0.06);
        --sidebar-active: #33A1E0;
        --sidebar-text: rgba(255, 255, 255, 0.85);
        --sidebar-text-muted: rgba(255, 255, 255, 0.55);
        --sidebar-width: 200px;
        --transition: all 0.2s ease;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    /* Desktop sidebar */
    .desktop-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--sidebar-bg);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: var(--sidebar-text);
        box-shadow: 2px 0 20px rgba(0, 0, 0, 0.3);
        overflow-y: auto;
        transition: transform 0.3s ease;
        z-index: 1030;
        border-right: 1px solid rgba(255, 255, 255, 0.05);
    }

    /* Hide on mobile */
    @media (max-width: 991.98px) {
        .desktop-sidebar {
            transform: translateX(-100%);
            visibility: hidden;
        }
    }

    .sidebar-inner {
        display: flex;
        flex-direction: column;
        height: 100%;
        padding: 2rem 1.2rem;
    }

    /* Profile section */
    .sidebar-profile {
        text-align: center;
        padding-bottom: 2rem;
        margin-bottom: 2rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .profile-avatar {
        width: 90px;
        height: 90px;
        margin: 0 auto 1.2rem;
        background: linear-gradient(135deg, #33A1E0, #1d4e6b);
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.8rem;
        font-weight: 600;
        color: white;
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.3);
        border: 2px solid rgba(255, 255, 255, 0.15);
        transition: var(--transition);
    }

    .profile-avatar:hover {
        transform: scale(1.02);
        border-color: var(--sidebar-active);
    }

    .profile-name {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: white;
        letter-spacing: -0.01em;
    }

    .profile-role {
        font-size: 0.9rem;
        color: var(--sidebar-text-muted);
        letter-spacing: 0.3px;
        text-transform: uppercase;
        font-weight: 500;
    }

    /* Navigation */
    .sidebar-nav {
        flex: 1;
    }

    .nav {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .nav-item {
        margin-bottom: 0.3rem;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.8rem 1rem;
        border-radius: 14px;
        color: var(--sidebar-text);
        text-decoration: none;
        transition: var(--transition);
        font-weight: 500;
        position: relative;
        overflow: hidden;
    }

    .nav-link::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 0;
        background: var(--sidebar-glass);
        transition: width 0.2s ease;
        z-index: -1;
    }

    .nav-link:hover::before {
        width: 100%;
    }

    .nav-link i {
        width: 28px;
        font-size: 1.3rem;
        margin-right: 14px;
        text-align: center;
        transition: var(--transition);
        color: var(--sidebar-text-muted);
    }

    .nav-link:hover i {
        color: var(--sidebar-active);
        transform: translateX(2px);
    }

    .nav-link.active {
        background: rgba(51, 161, 224, 0.12);
        border-left: 3px solid var(--sidebar-active);
        color: white;
    }

    .nav-link.active i {
        color: var(--sidebar-active);
    }

    .nav-link.active::after {
        content: '';
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--sidebar-active);
        box-shadow: 0 0 10px var(--sidebar-active);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            opacity: 1;
            transform: translateY(-50%) scale(1);
        }

        50% {
            opacity: 0.6;
            transform: translateY(-50%) scale(1.3);
        }

        100% {
            opacity: 1;
            transform: translateY(-50%) scale(1);
        }
    }

    /* Logout button */
    .nav-item.logout {
        margin-top: 2rem;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        padding-top: 1.5rem;
    }

    .nav-link.logout-link {
        color: #ff8a8a;
    }

    .nav-link.logout-link i {
        color: #ff8a8a;
    }

    .nav-link.logout-link:hover {
        background: rgba(255, 107, 107, 0.1);
        color: #ffa8a8;
    }

    .nav-link.logout-link:hover i {
        color: #ffa8a8;
    }

    /* Main content adjustment */
    .main-content {
        margin-left: var(--sidebar-width);
        padding: 25px;
        transition: margin-left 0.3s ease;
    }

    @media (max-width: 991.98px) {
        .main-content {
            margin-left: 0;
        }
    }

    /* Mobile header */
    .mobile-header {
        display: none;
        background: var(--sidebar-bg);
        backdrop-filter: blur(10px);
        color: white;
        padding: 0.9rem 1.5rem;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 1025;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .mobile-header .menu-toggle {
        background: none;
        border: none;
        color: white;
        font-size: 1.6rem;
        cursor: pointer;
        padding: 0.3rem 0.6rem;
        border-radius: 8px;
        transition: background 0.2s;
    }

    .mobile-header .menu-toggle:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .mobile-header .logo {
        font-weight: 600;
        font-size: 1.2rem;
        letter-spacing: 0.5px;
    }

    @media (max-width: 991.98px) {
        .mobile-header {
            display: flex;
        }
    }

    /* Offcanvas sidebar */
    .offcanvas-sidebar {
        background: var(--sidebar-bg) !important;
        backdrop-filter: blur(10px);
        color: var(--sidebar-text);
    }

    .offcanvas-sidebar .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
        opacity: 0.8;
    }

    .offcanvas-sidebar .btn-close:hover {
        opacity: 1;
    }

    /* Scrollbar styling */
    .desktop-sidebar::-webkit-scrollbar,
    .offcanvas-sidebar::-webkit-scrollbar {
        width: 5px;
    }

    .desktop-sidebar::-webkit-scrollbar-track,
    .offcanvas-sidebar::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.2);
    }

    .desktop-sidebar::-webkit-scrollbar-thumb,
    .offcanvas-sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.15);
        border-radius: 10px;
    }

    .desktop-sidebar::-webkit-scrollbar-thumb:hover,
    .offcanvas-sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.25);
    }
    </style>
</head>

<body>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="menu-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSidebar">
            <i class="fas fa-bars"></i>
        </button>
        <span class="logo"><i class="fas fa-user-graduate me-2"></i>Student Portal</span>
        <div style="width: 28px;"></div>
    </div>

    <!-- Desktop Sidebar -->
    <div class="desktop-sidebar">
        <div class="sidebar-inner">
            <div class="sidebar-profile">
                <div class="profile-avatar">
                    <?= htmlspecialchars($studentInitials) ?>
                </div>
                <div class="profile-name"><?= htmlspecialchars($studentName) ?></div>
                <div class="profile-role"><?= htmlspecialchars($studentRole) ?></div>
            </div>

            <div class="sidebar-nav">
                <ul class="nav">
                    <li class="nav-item">
                        <a href="../Studentpage/student_dashboard.php" class="nav-link">
                            <i class="fas fa-chart-pie"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../Studentpage/student_event.php" class="nav-link">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Events</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../Studentpage/student_attendance.php" class="nav-link">
                            <i class="fas fa-clock"></i>
                            <span>Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../Studentpage/student_fines.php" class="nav-link">
                            <i class="fas fa-coins"></i>
                            <span>Fines</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../Studentpage/student_profile.php" class="nav-link">
                            <i class="fas fa-user-cog"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li class="nav-item logout">
                        <a href="../../Auth/logout.php" class="nav-link logout-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Offcanvas Sidebar (Mobile) -->
    <div class="offcanvas offcanvas-start offcanvas-sidebar" tabindex="-1" id="offcanvasSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title text-white">
                <i class="fas fa-user-graduate me-2"></i>Student Portal
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <div class="sidebar-inner" style="padding: 1.2rem;">
                <div class="sidebar-profile">
                    <div class="profile-avatar">
                        <?= htmlspecialchars($studentInitials) ?>
                    </div>
                    <div class="profile-name"><?= htmlspecialchars($studentName) ?></div>
                    <div class="profile-role"><?= htmlspecialchars($studentRole) ?></div>
                </div>

                <div class="sidebar-nav">
                    <ul class="nav">
                        <li class="nav-item">
                            <a href="../Studentpage/student_dashboard.php" class="nav-link">
                                <i class="fas fa-chart-pie"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/Beams/Views/Students/StudentEvents.php" class="nav-link">
                                <i class="fas fa-calendar-alt"></i>
                                <span>Events</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/Beams/Views/Students/StudentAttendance.php" class="nav-link">
                                <i class="fas fa-clock"></i>
                                <span>Attendance</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/Beams/Views/Students/StudentFines.php" class="nav-link">
                                <i class="fas fa-coins"></i>
                                <span>Fines</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/Beams/Views/Students/StudentProfile.php" class="nav-link">
                                <i class="fas fa-user-cog"></i>
                                <span>My Profile</span>
                            </a>
                        </li>
                        <li class="nav-item logout">
                            <a href="../../Auth/logout.php" class="nav-link logout-link">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Active link highlighting -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const currentPath = window.location.pathname;
        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }
        });
    });
    </script>
</body>

</html>
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
                // Get proper initials (first two letters or first letters of first two names)
                $nameParts = explode(' ', $studentName);
                if (count($nameParts) >= 2) {
                    $studentInitials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                } else {
                    $studentInitials = strtoupper(substr($studentName, 0, 2));
                }
            }
            $stmt->close();
        }
        $sidebarConn->close(); // Close this independent connection
    } else {
        // Log error but continue with defaults (prevents white page)
        error_log("Student sidebar: DB connection failed - " . $sidebarConn->connect_error);
    }
}

// Determine current page for active highlighting
$currentFile = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Sidebar</title>
    <!-- Bootstrap, Font Awesome, Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --bg-dark: #0f172a;
        --bg-card: #1e293b;
        --primary-blue: #3b82f6;
        --primary-blue-dark: #2563eb;
        --primary-cyan: #06b6d4;
        --text-primary: #f1f5f9;
        --text-secondary: #94a3b8;
        --text-muted: #64748b;
        --border-color: #334155;
        --hover-bg: #334155;
        --active-bg: rgba(59, 130, 246, 0.2);
        --active-border: #3b82f6;
        --sidebar-width: 190px;
        --transition: all 0.25s ease-in-out;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        background: var(--bg-dark);
    }

    /* Desktop sidebar */
    .desktop-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--bg-card);
        color: var(--text-primary);
        overflow-y: auto;
        transition: transform 0.3s ease;
        z-index: 1030;
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
    }

    @media (max-width: 991.98px) {
        .desktop-sidebar {
            transform: translateX(-100%);
            visibility: hidden;
        }
    }

    /* Sidebar Header / Logo */
    .sidebar-header {
        padding: 1.75rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 1.5rem;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 0.7rem;
    }

    .logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary-blue), var(--primary-cyan));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        font-weight: 700;
        color: white;
    }

    .logo-text {
        font-size: 0.95rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        background: linear-gradient(135deg, white, var(--text-secondary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    .logo-sub {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-muted);
        letter-spacing: 0.5px;
    }

    /* Welcome Section */
    .welcome-section {
        padding: 0 1.25rem 1.5rem 1.25rem;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 1.5rem;
    }

    .welcome-greeting {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 0.5rem;
    }

    .welcome-name {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.35rem;
        line-height: 1.3;
    }

    .welcome-role {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--primary-blue);
        background: rgba(59, 130, 246, 0.1);
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
    }

    /* Navigation */
    .sidebar-nav {
        flex: 1;
        padding: 0 0.75rem;
    }

    .nav-section {
        margin-bottom: 1.5rem;
    }

    .nav-section-title {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        padding: 0 1rem;
        margin-bottom: 0.75rem;
    }

    .nav {
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
        gap: 0.82rem;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 0.95rem;
        font-weight: 700;
        transition: var(--transition);
        width: 100%;
        position: relative;
    }

    .nav-link i {
        width: 28px;
        font-size: 0.95rem;
        text-align: center;
        color: var(--text-muted);
        transition: var(--transition);
    }

    .nav-link span {
        flex: 1;
        font-size: 0.95rem;
        font-weight: 700;
    }

    .nav-link:hover {
        background: var(--hover-bg);
        color: var(--text-primary);
    }

    .nav-link:hover i {
        color: var(--primary-blue);
    }

    /* ACTIVE STATE - Matching Officer Sidebar Style */
    .nav-link.active {
        background: var(--active-bg, 0.2);
        color: white;
        border-left: 3px solid var(--active-border);
    }
    
    .nav-link.active i {
        color: var(--primary-blue);
    }

    /* Logout button */
    .sidebar-footer {
        padding: 1.25rem 1rem;
        border-top: 1px solid var(--border-color);
        margin-top: auto;
    }

    .logout-link {
        display: flex;
        align-items: center;
        gap: 0.82rem;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        color: #f87171;
        text-decoration: none;
        font-size: 0.95rem;
        font-weight: 700;
        transition: var(--transition);
        width: 100%;
    }

    .logout-link i {
        width: 28px;
        font-size: 0.95rem;
        color: #f87171;
    }

    .logout-link span {
        font-size: 0.95rem;
        font-weight: 700;
    }

    .logout-link:hover {
        background: rgba(248, 113, 113, 0.1);
        color: #fca5a5;
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
        background: var(--bg-card);
        padding: 1rem 1.25rem;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 1025;
        border-bottom: 1px solid var(--border-color);
    }

    .mobile-header .menu-toggle {
        background: none;
        border: none;
        color: var(--text-primary);
        font-size: 1.4rem;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 8px;
        transition: background 0.2s;
    }

    .mobile-header .menu-toggle:hover {
        background: var(--hover-bg);
    }

    .mobile-header .logo {
        font-weight: 800;
        font-size: 1.25rem;
        background: linear-gradient(135deg, white, var(--text-secondary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }

    @media (max-width: 991.98px) {
        .mobile-header {
            display: flex;
        }
    }

    /* Offcanvas sidebar */
    .offcanvas-sidebar {
        background: var(--bg-card) !important;
        width: 280px !important;
        border-right: 1px solid var(--border-color);
    }

    .offcanvas-sidebar .offcanvas-header {
        border-bottom: 1px solid var(--border-color);
        padding: 1.25rem;
    }

    .offcanvas-sidebar .offcanvas-title {
        color: var(--text-primary);
        font-weight: 800;
        font-size: 1.25rem;
    }

    .offcanvas-sidebar .btn-close {
        filter: brightness(0) invert(1);
        opacity: 0.7;
    }

    /* Scrollbar styling */
    .desktop-sidebar::-webkit-scrollbar,
    .offcanvas-sidebar::-webkit-scrollbar {
        width: 4px;
    }

    .desktop-sidebar::-webkit-scrollbar-track,
    .offcanvas-sidebar::-webkit-scrollbar-track {
        background: var(--border-color);
    }

    .desktop-sidebar::-webkit-scrollbar-thumb,
    .offcanvas-sidebar::-webkit-scrollbar-thumb {
        background: var(--primary-blue);
        border-radius: 4px;
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
        <div style="width: 32px;"></div>
    </div>

    <!-- Desktop Sidebar -->
    <div class="desktop-sidebar">
        <!-- Logo / Header -->
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">B</div>
                <div>
                    <div class="logo-text">BEAMS</div>
                    <div class="logo-sub">STUDENT PORTAL</div>
                </div>
            </div>
        </div>

        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-greeting">
                <i class="fas fa-hand-wave me-1"></i> Welcome back,
            </div>
            <div class="welcome-name"><?= htmlspecialchars($studentName) ?></div>
            <span class="welcome-role">
                <i class="fas fa-user-graduate me-1"></i> <?= htmlspecialchars($studentRole) ?>
            </span>
        </div>

        <!-- Navigation - Active state matching Officer Sidebar -->
        <div class="sidebar-nav">
            <!-- MAIN MENU Section -->
            <div class="nav-section">
                <div class="nav-section-title">MAIN MENU</div>
                <ul class="nav">
                    <li class="nav-item">
                        <a href="../studentpage/student_dashboard.php" class="nav-link <?php echo $currentFile == 'student_dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- ACADEMICS Section -->
            <div class="nav-section">
                <div class="nav-section-title">ACADEMICS</div>
                <ul class="nav">
                    <li class="nav-item">
                        <a href="../studentpage/student_attendance.php" class="nav-link <?php echo $currentFile == 'student_attendance.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-check"></i>
                            <span>Attendance</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../studentpage/student_fines.php" class="nav-link <?php echo $currentFile == 'student_fines.php' ? 'active' : ''; ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Fines</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- EVENTS Section -->
            <div class="nav-section">
                <div class="nav-section-title">EVENTS</div>
                <ul class="nav">
                    <li class="nav-item">
                        <a href="../studentpage/student_event.php" class="nav-link <?php echo $currentFile == 'student_event.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span>Events</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- SETTINGS Section -->
            <div class="nav-section">
                <div class="nav-section-title">SETTINGS</div>
                <ul class="nav">
                    <li class="nav-item">
                        <a href="../studentpage/student_profile.php" class="nav-link <?php echo $currentFile == 'student_profile.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-cog"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Footer with Logout -->
        <div class="sidebar-footer">
            <a href="../../Auth/logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Offcanvas Sidebar (Mobile) -->
    <div class="offcanvas offcanvas-start offcanvas-sidebar" tabindex="-1" id="offcanvasSidebar">
        <div class="offcanvas-header">
            <div class="logo">
                <div class="logo-icon">B</div>
                <div>
                    <div class="logo-text">BEAMS</div>
                    <div class="logo-sub">STUDENT PORTAL</div>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <div style="padding: 1.25rem;">
                <!-- Welcome Section -->
                <div class="welcome-section" style="padding: 0 0 1rem 0;">
                    <div class="welcome-greeting">
                        <i class="fas fa-hand-wave me-1"></i> Welcome back,
                    </div>
                    <div class="welcome-name"><?= htmlspecialchars($studentName) ?></div>
                    <span class="welcome-role">
                        <i class="fas fa-user-graduate me-1"></i> <?= htmlspecialchars($studentRole) ?>
                    </span>
                </div>

                <!-- Navigation for Mobile -->
                <div class="sidebar-nav" style="padding: 0;">
                    <div class="nav-section">
                        <div class="nav-section-title">MAIN MENU</div>
                        <ul class="nav">
                            <li class="nav-item">
                                <a href="../studentpage/student_dashboard.php" class="nav-link">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <span>Dashboard</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">ACADEMICS</div>
                        <ul class="nav">
                            <li class="nav-item">
                                <a href="../studentpage/student_attendance.php" class="nav-link">
                                    <i class="fas fa-calendar-check"></i>
                                    <span>Attendance</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="../studentpage/student_fines.php" class="nav-link">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Fines</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">EVENTS</div>
                        <ul class="nav">
                            <li class="nav-item">
                                <a href="../studentpage/student_event.php" class="nav-link">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Events</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="nav-section">
                        <div class="nav-section-title">SETTINGS</div>
                        <ul class="nav">
                            <li class="nav-item">
                                <a href="../studentpage/student_profile.php" class="nav-link">
                                    <i class="fas fa-user-cog"></i>
                                    <span>My Profile</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Logout -->
                <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <a href="../../Auth/logout.php" class="logout-link" style="padding-left: 0;">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
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
        const currentFile = currentPath.split('/').pop();
        
        // Highlight active link in both desktop and mobile sidebars
        document.querySelectorAll('.nav-link').forEach(link => {
            const href = link.getAttribute('href');
            if (href && (href === currentFile || href.includes(currentFile))) {
                link.classList.add('active');
            }
        });
    });
    </script>
</body>

</html>